<?php declare(strict_types=1);

namespace Annotate\Controller;

use Annotate\Serializer\W3cAnnotation;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\MvcEvent;

/**
 * W3C Web Annotation Protocol endpoint.
 *
 * @link https://www.w3.org/TR/annotation-protocol/
 */
class W3cAnnotationController extends AbstractActionController
{
    /**
     * @var W3cAnnotation
     */
    protected $serializer;

    public function __construct(W3cAnnotation $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Route by HTTP method instead of the route "action" param.
     */
    public function onDispatch(MvcEvent $e)
    {
        $method = strtolower(
            $this->getRequest()->getMethod()
        );
        $actionMap = [
            'get' => 'getAction',
            'head' => 'getAction',
            'post' => 'postAction',
            'put' => 'putAction',
            'delete' => 'deleteAction',
            'options' => 'optionsAction',
        ];
        if (!isset($actionMap[$method])) {
            $response = $this->getResponse();
            $response->setStatusCode(405);
            $response->getHeaders()
                ->addHeaderLine(
                    'Allow',
                    'GET, HEAD, POST, PUT, DELETE, OPTIONS'
                );
            $e->setResult($response);
            return $response;
        }
        $result = $this->{$actionMap[$method]}();
        $e->setResult($result);
        return $result;
    }

    /**
     * GET /annotations/ → LDP container or page.
     * GET /annotations/:id → single annotation.
     * HEAD works like GET (no body).
     */
    public function getAction()
    {
        $id = $this->params('id');
        if ($id) {
            return $this->show((int) $id);
        }
        return $this->container();
    }

    /**
     * OPTIONS /annotations/[:id]
     */
    public function optionsAction()
    {
        $response = $this->getResponse();
        $id = $this->params('id');
        $allow = $id
            ? 'GET, HEAD, OPTIONS, PUT, DELETE'
            : 'GET, HEAD, OPTIONS, POST';
        $response->getHeaders()
            ->addHeaderLine('Allow', $allow);
        $this->addProtocolHeaders($response, (bool) $id);
        $response->setStatusCode(204);
        return $response;
    }

    /**
     * POST /annotations/ → create.
     */
    public function postAction()
    {
        $body = $this->getW3cRequestBody();
        if ($body === null) {
            return $this->errorResponse(400, 'Invalid JSON body.');
        }

        $data = $this->w3cToOmeka($body);

        $api = $this->api();
        try {
            $response = $api->create('annotations', $data);
        } catch (\Exception $e) {
            return $this->errorResponse(400, $e->getMessage());
        }

        $annotation = $response->getContent();
        $baseUrl = $this->containerUrl();
        $w3c = $this->serializer->serialize($annotation, $baseUrl);

        $httpResponse = $this->getResponse();
        $httpResponse->setStatusCode(201);
        $httpResponse->getHeaders()
            ->addHeaderLine(
                'Location',
                $baseUrl . '/' . $annotation->id()
            )
            ->addHeaderLine(
                'Content-Type',
                W3cAnnotation::CONTENT_TYPE
            );
        $this->addProtocolHeaders($httpResponse, true);
        $this->addEtag($httpResponse, $annotation);

        return $this->jsonResponse($w3c);
    }

    /**
     * PUT /annotations/:id → update.
     */
    public function putAction()
    {
        $id = $this->params('id');
        if (!$id) {
            return $this->errorResponse(400, 'Missing annotation id.');
        }

        // Check If-Match.
        $ifMatch = $this->getRequest()->getHeader('If-Match');
        if ($ifMatch) {
            $existing = $this->readAnnotation((int) $id);
            if (!$existing) {
                return $this->errorResponse(404, 'Annotation not found.');
            }
            $currentEtag = $this->computeEtag($existing);
            $provided = trim($ifMatch->getFieldValue(), '" ');
            if ($provided !== $currentEtag) {
                return $this->errorResponse(
                    412,
                    'ETag mismatch.'
                );
            }
        }

        $body = $this->getW3cRequestBody();
        if ($body === null) {
            return $this->errorResponse(400, 'Invalid JSON body.');
        }

        $data = $this->w3cToOmeka($body);

        $api = $this->api();
        try {
            $response = $api->update('annotations', (int) $id, $data);
        } catch (\Exception $e) {
            return $this->errorResponse(400, $e->getMessage());
        }

        $annotation = $response->getContent();
        $baseUrl = $this->containerUrl();
        $w3c = $this->serializer->serialize($annotation, $baseUrl);

        $httpResponse = $this->getResponse();
        $this->addProtocolHeaders($httpResponse, true);
        $this->addEtag($httpResponse, $annotation);
        $httpResponse->getHeaders()
            ->addHeaderLine(
                'Content-Type',
                W3cAnnotation::CONTENT_TYPE
            );

        return $this->jsonResponse($w3c);
    }

    /**
     * DELETE /annotations/:id
     */
    public function deleteAction()
    {
        $id = $this->params('id');
        if (!$id) {
            return $this->errorResponse(400, 'Missing annotation id.');
        }

        $api = $this->api();
        try {
            $api->delete('annotations', (int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse(404, $e->getMessage());
        }

        $response = $this->getResponse();
        $response->setStatusCode(204);
        return $response;
    }

    protected function show(int $id)
    {
        $annotation = $this->readAnnotation($id);
        if (!$annotation) {
            return $this->errorResponse(404, 'Annotation not found.');
        }

        $baseUrl = $this->containerUrl();
        $w3c = $this->serializer->serialize($annotation, $baseUrl);

        $httpResponse = $this->getResponse();
        $this->addProtocolHeaders($httpResponse, true);
        $this->addEtag($httpResponse, $annotation);
        $httpResponse->getHeaders()
            ->addHeaderLine(
                'Content-Type',
                W3cAnnotation::CONTENT_TYPE
            );

        return $this->jsonResponse($w3c);
    }

    protected function container()
    {
        $page = $this->params()->fromQuery('page');
        $baseUrl = $this->containerUrl();
        $api = $this->api();
        $settings = $this->settings();
        $perPage = (int) $settings->get(
            'pagination_per_page',
            \Omeka\Stdlib\Paginator::PER_PAGE
        );

        // Container root (no page parameter).
        if ($page === null) {
            $total = $api->search('annotations', [
                'limit' => 0,
            ])->getTotalResults();

            $w3c = $this->serializer->serializeContainer(
                [],
                $total,
                $baseUrl,
                -1,
                $perPage
            );

            $httpResponse = $this->getResponse();
            $this->addProtocolHeaders($httpResponse, false);
            $this->addContainerEtag($httpResponse, $total);
            $httpResponse->getHeaders()
                ->addHeaderLine(
                    'Content-Type',
                    W3cAnnotation::CONTENT_TYPE
                );

            return $this->jsonResponse($w3c);
        }

        // AnnotationPage.
        $page = max(0, (int) $page);
        $annotations = $api->search('annotations', [
            'page' => $page + 1,
            'per_page' => $perPage,
            'sort_by' => 'id',
            'sort_order' => 'asc',
        ]);
        $total = $annotations->getTotalResults();
        $items = $annotations->getContent();

        $w3c = $this->serializer->serializeContainer(
            $items,
            $total,
            $baseUrl,
            $page,
            $perPage
        );

        $httpResponse = $this->getResponse();
        $this->addProtocolHeaders($httpResponse, false);
        $this->addContainerEtag($httpResponse, $total, $page);
        $httpResponse->getHeaders()
            ->addHeaderLine(
                'Content-Type',
                W3cAnnotation::CONTENT_TYPE
            );

        return $this->jsonResponse($w3c);
    }

    /**
     * Read a single annotation via API.
     *
     * @return \Annotate\Api\Representation\AnnotationRepresentation|null
     */
    protected function readAnnotation(int $id)
    {
        try {
            return $this->api()
                ->read('annotations', $id)
                ->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add W3C Annotation Protocol headers.
     */
    protected function addProtocolHeaders($response, bool $isItem): void
    {
        $headers = $response->getHeaders();
        $links = '<http://www.w3.org/ns/ldp#'
            . ($isItem ? 'Resource' : 'BasicContainer')
            . '>; rel="type"';
        if (!$isItem) {
            $links .= ', <http://www.w3.org/TR/annotation-protocol/>; rel="http://www.w3.org/ns/ldp#constrainedBy"';
        }
        $headers->addHeaderLine('Link', $links);
        $headers->addHeaderLine('Vary', 'Accept');

        $allow = $isItem
            ? 'GET, HEAD, OPTIONS, PUT, DELETE'
            : 'GET, HEAD, OPTIONS, POST';
        $headers->addHeaderLine('Allow', $allow);

        if (!$isItem) {
            $headers->addHeaderLine(
                'Accept-Post',
                'application/ld+json, application/json'
            );
        }
    }

    protected function addEtag($response, $annotation): void
    {
        $etag = $this->computeEtag($annotation);
        $response->getHeaders()
            ->addHeaderLine('ETag', '"' . $etag . '"');
    }

    protected function addContainerEtag(
        $response,
        int $total,
        int $page = -1
    ): void {
        $etag = md5('container-' . $total . '-' . $page);
        $response->getHeaders()
            ->addHeaderLine('ETag', '"' . $etag . '"');
    }

    protected function computeEtag($annotation): string
    {
        $modified = $annotation->modified()
            ?? $annotation->created();
        return md5($annotation->id() . '-'
            . ($modified ? $modified->getTimestamp() : '0'));
    }

    /**
     * Parse the request body as JSON.
     */
    protected function getW3cRequestBody(): ?array
    {
        $json = $this->getRequest()->getContent();
        if (!$json) {
            return null;
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Convert W3C annotation JSON to Omeka API format.
     *
     * Handles basic W3C → Omeka mapping for common cases.
     */
    protected function w3cToOmeka(array $w3c): array
    {
        $data = [];

        // Motivation.
        if (isset($w3c['motivation'])) {
            $motivations = (array) $w3c['motivation'];
            foreach ($motivations as $m) {
                $data['oa:motivatedBy'][] = [
                    'type' => 'customvocab:Annotation Motivation',
                    'o:label' => 'Annotation Motivation',
                    '@value' => str_contains($m, ':')
                        ? $m : 'oa:' . $m,
                ];
            }
        }

        // Body.
        if (isset($w3c['body'])) {
            $bodies = isset($w3c['body']['type'])
                ? [$w3c['body']]
                : (array) $w3c['body'];
            foreach ($bodies as $body) {
                $part = [];
                if (is_string($body)) {
                    $part['rdf:value'][] = [
                        'type' => 'literal',
                        '@value' => $body,
                    ];
                } else {
                    if (isset($body['value'])) {
                        $part['rdf:value'][] = [
                            'type' => 'literal',
                            '@value' => $body['value'],
                        ];
                    }
                    if (isset($body['purpose'])) {
                        $part['oa:hasPurpose'][] = [
                            'type' => 'customvocab:Annotation Motivation',
                            'o:label' => 'Annotation Motivation',
                            '@value' => str_contains(
                                $body['purpose'], ':'
                            )
                                ? $body['purpose']
                                : 'oa:' . $body['purpose'],
                        ];
                    }
                    if (isset($body['format'])) {
                        $part['dcterms:format'][] = [
                            'type' => 'literal',
                            '@value' => $body['format'],
                        ];
                    }
                    if (isset($body['language'])) {
                        $part['dcterms:language'][] = [
                            'type' => 'literal',
                            '@value' => $body['language'],
                        ];
                    }
                }
                $data['oa:hasBody'][] = $part;
            }
        }

        // Target.
        if (isset($w3c['target'])) {
            $targets = isset($w3c['target']['type'])
                || isset($w3c['target']['source'])
                || isset($w3c['target']['id'])
                ? [$w3c['target']]
                : (array) $w3c['target'];
            foreach ($targets as $target) {
                $part = [];
                if (is_string($target)) {
                    // IRI reference → try to resolve to an
                    // Omeka resource.
                    $part['oa:hasSource'][] =
                        $this->resolveSource($target);
                } else {
                    $source = $target['source']
                        ?? $target['id'] ?? null;
                    if ($source) {
                        $part['oa:hasSource'][] =
                            $this->resolveSource($source);
                    }
                    if (isset($target['selector'])) {
                        $part = $this->convertSelector(
                            $target['selector'],
                            $part
                        );
                    }
                }
                $data['oa:hasTarget'][] = $part;
            }
        }

        return $data;
    }

    /**
     * Resolve a source IRI to an Omeka resource link or URI value.
     */
    protected function resolveSource(string $source): array
    {
        // Try to extract an Omeka API resource id from the URL.
        if (preg_match('#/api/(?:items|media|item_sets)/(\d+)#', $source, $m)) {
            return [
                'type' => 'resource',
                'value_resource_id' => (int) $m[1],
            ];
        }
        return [
            'type' => 'uri',
            '@id' => $source,
        ];
    }

    /**
     * Convert a W3C selector to Omeka oa:hasSelector values.
     */
    protected function convertSelector($selector, array $part): array
    {
        if (is_string($selector)) {
            $part['oa:hasSelector'][] = [
                'type' => 'uri',
                '@id' => $selector,
            ];
            return $part;
        }
        // Fragment or CSS selector.
        if (isset($selector['value'])) {
            $part['oa:hasSelector'][] = [
                'type' => 'literal',
                '@value' => $selector['value'],
            ];
        }
        if (isset($selector['conformsTo'])) {
            $part['dcterms:conformsTo'][] = [
                'type' => 'uri',
                '@id' => $selector['conformsTo'],
            ];
        }
        return $part;
    }

    protected function containerUrl(): string
    {
        $url = $this->url();
        return rtrim($url->fromRoute(
            'w3c-annotations',
            [],
            ['force_canonical' => true]
        ), '/') . '/';
    }

    /**
     * Return JSON response without Laminas JsonModel to force application/json.
     */
    protected function jsonResponse(array $data)
    {
        $response = $this->getResponse();
        $response->setContent(json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        ));
        if (!$response->getHeaders()->has('Content-Type')) {
            $response->getHeaders()
                ->addHeaderLine(
                    'Content-Type',
                    W3cAnnotation::CONTENT_TYPE
                );
        }
        return $response;
    }

    protected function errorResponse(int $status, string $message)
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $response->setContent(json_encode([
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
