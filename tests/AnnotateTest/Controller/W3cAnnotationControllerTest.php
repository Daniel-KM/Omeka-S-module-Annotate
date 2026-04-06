<?php declare(strict_types=1);

namespace AnnotateTest\Controller;

use Annotate\Serializer\W3cAnnotation;
use AnnotateTest\AnnotateTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class W3cAnnotationControllerTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    // =========================================================
    // onDispatch() — HTTP method routing
    // =========================================================

    public function testUnsupportedMethodReturns405(): void
    {
        $this->dispatch('/annotations', 'PATCH');
        $this->assertResponseStatusCode(405);
        $allow = $this->getResponse()
            ->getHeaders()->get('Allow');
        $this->assertNotFalse($allow);
        $this->assertStringContainsString(
            'GET', $allow->getFieldValue()
        );
    }

    public function testHeadReturns200(): void
    {
        $this->dispatch('/annotations', 'HEAD');
        $this->assertResponseStatusCode(200);
    }

    // =========================================================
    // getAction() — container
    // =========================================================

    public function testGetContainerBody(): void
    {
        $this->dispatch('/annotations');
        $this->assertResponseStatusCode(200);
        $body = $this->jsonBody();
        $this->assertContains(
            'BasicContainer', $body['type']
        );
        $this->assertArrayHasKey('total', $body);
    }

    public function testGetContainerContentType(): void
    {
        $this->dispatch('/annotations');
        $ct = $this->getResponse()
            ->getHeaders()->get('Content-Type')
            ->getFieldValue();
        $this->assertStringContainsString(
            'application/ld+json', $ct
        );
    }

    public function testGetContainerLinkHeaders(): void
    {
        $this->dispatch('/annotations');
        $link = $this->getResponse()
            ->getHeaders()->get('Link')
            ->getFieldValue();
        $this->assertStringContainsString(
            'ldp#BasicContainer', $link
        );
        $this->assertStringContainsString(
            'constrainedBy', $link
        );
    }

    public function testGetContainerVaryHeader(): void
    {
        $this->dispatch('/annotations');
        $vary = $this->getResponse()
            ->getHeaders()->get('Vary');
        $this->assertNotFalse($vary);
        $this->assertStringContainsString(
            'Accept', $vary->getFieldValue()
        );
    }

    public function testGetContainerAllowHeader(): void
    {
        $this->dispatch('/annotations');
        $allow = $this->getResponse()
            ->getHeaders()->get('Allow')
            ->getFieldValue();
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('HEAD', $allow);
        $this->assertStringContainsString('POST', $allow);
        $this->assertStringNotContainsString('PUT', $allow);
        $this->assertStringNotContainsString(
            'DELETE', $allow
        );
    }

    public function testGetContainerAcceptPostHeader(): void
    {
        $this->dispatch('/annotations');
        $acceptPost = $this->getResponse()
            ->getHeaders()->get('Accept-Post');
        $this->assertNotFalse($acceptPost);
        $this->assertStringContainsString(
            'application/ld+json',
            $acceptPost->getFieldValue()
        );
    }

    public function testGetContainerEtagHeader(): void
    {
        $this->dispatch('/annotations');
        $etag = $this->getResponse()
            ->getHeaders()->get('ETag');
        $this->assertNotFalse($etag);
        $val = $etag->getFieldValue();
        $this->assertStringStartsWith('"', $val);
        $this->assertStringEndsWith('"', $val);
    }

    // =========================================================
    // getAction() — annotation page
    // =========================================================

    public function testGetAnnotationPage(): void
    {
        $this->dispatch('/annotations?page=0');
        $this->assertResponseStatusCode(200);
        $body = $this->jsonBody();
        $this->assertEquals('AnnotationPage', $body['type']);
        $this->assertArrayHasKey('items', $body);
        $this->assertIsArray($body['items']);
    }

    public function testGetAnnotationPageStartIndex(): void
    {
        $this->dispatch('/annotations?page=0');
        $body = $this->jsonBody();
        $this->assertEquals(0, $body['startIndex']);
    }

    public function testGetAnnotationPagePartOf(): void
    {
        $this->dispatch('/annotations?page=0');
        $body = $this->jsonBody();
        $this->assertArrayHasKey('partOf', $body);
        $this->assertStringContainsString(
            '/annotations', $body['partOf']
        );
    }

    public function testGetAnnotationPageNegativeClampedToZero(): void
    {
        $this->dispatch('/annotations?page=-5');
        $body = $this->jsonBody();
        $this->assertEquals(0, $body['startIndex']);
    }

    // =========================================================
    // getAction() — single annotation
    // =========================================================

    public function testGetSingleAnnotation(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $this->assertResponseStatusCode(200);
        $body = $this->jsonBody();
        $this->assertEquals('Annotation', $body['type']);
        $this->assertEquals(
            W3cAnnotation::CONTEXT, $body['@context']
        );
    }

    public function testGetSingleAnnotationLinkHeader(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $link = $this->getResponse()
            ->getHeaders()->get('Link')
            ->getFieldValue();
        $this->assertStringContainsString(
            'ldp#Resource', $link
        );
        // No constrainedBy on individual annotations.
        $this->assertStringNotContainsString(
            'constrainedBy', $link
        );
    }

    public function testGetSingleAnnotationEtag(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $etag = $this->getResponse()
            ->getHeaders()->get('ETag');
        $this->assertNotFalse($etag);
        $val = $etag->getFieldValue();
        // ETag must be quoted.
        $this->assertStringStartsWith('"', $val);
        $this->assertStringEndsWith('"', $val);
    }

    public function testGetSingleAnnotationAllowHeader(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $allow = $this->getResponse()
            ->getHeaders()->get('Allow')
            ->getFieldValue();
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('PUT', $allow);
        $this->assertStringContainsString('DELETE', $allow);
        $this->assertStringNotContainsString('POST', $allow);
    }

    public function testGetSingleAnnotationNoAcceptPost(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $acceptPost = $this->getResponse()
            ->getHeaders()->get('Accept-Post');
        $this->assertFalse($acceptPost);
    }

    public function testGetNonExistentAnnotation(): void
    {
        $this->dispatch('/annotations/999999999');
        $this->assertResponseStatusCode(404);
        $body = $this->jsonBody();
        $this->assertArrayHasKey('error', $body);
    }

    // =========================================================
    // optionsAction()
    // =========================================================

    public function testOptionsContainer(): void
    {
        $this->dispatch('/annotations', 'OPTIONS');
        $this->assertResponseStatusCode(204);
        $allow = $this->getResponse()
            ->getHeaders()->get('Allow')
            ->getFieldValue();
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('HEAD', $allow);
        $this->assertStringContainsString('POST', $allow);
        $this->assertStringNotContainsString('PUT', $allow);
        $this->assertStringNotContainsString(
            'DELETE', $allow
        );
    }

    public function testOptionsAnnotation(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch(
            '/annotations/' . $a->id(), 'OPTIONS'
        );
        $this->assertResponseStatusCode(204);
        $allow = $this->getResponse()
            ->getHeaders()->get('Allow')
            ->getFieldValue();
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('PUT', $allow);
        $this->assertStringContainsString('DELETE', $allow);
        $this->assertStringNotContainsString('POST', $allow);
    }

    // =========================================================
    // postAction() — create
    // =========================================================

    public function testPostCreateAnnotation(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => [
                'type' => 'TextualBody',
                'value' => 'Test POST',
                'purpose' => 'commenting',
            ],
            'target' => [
                'source' => $item->apiUrl(),
            ],
        ]);

        $this->assertResponseStatusCode(201);
        $body = $this->jsonBody();
        $this->assertEquals('Annotation', $body['type']);
        $this->assertEquals('commenting', $body['motivation']);
        $this->registerCreatedAnnotation($body);
    }

    public function testPostCreateLocationHeader(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $location = $this->getResponse()
            ->getHeaders()->get('Location');
        $this->assertNotFalse($location);
        $this->assertStringContainsString(
            '/annotations/', $location->getFieldValue()
        );
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostCreateEtagHeader(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $etag = $this->getResponse()
            ->getHeaders()->get('ETag');
        $this->assertNotFalse($etag);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostAllowHeader(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $allow = $this->getResponse()
            ->getHeaders()->get('Allow')
            ->getFieldValue();
        $this->assertStringContainsString('GET', $allow);
        $this->assertStringContainsString('PUT', $allow);
        $this->assertStringContainsString('DELETE', $allow);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostLinkHeader(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $link = $this->getResponse()
            ->getHeaders()->get('Link')
            ->getFieldValue();
        $this->assertStringContainsString(
            'ldp#Resource', $link
        );
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostEmptyBodyReturns400(): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent('')
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $this->dispatch('/annotations');
        $this->assertResponseStatusCode(400);
    }

    public function testPostInvalidJsonReturns400(): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent('{invalid json}')
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $this->dispatch('/annotations');
        $this->assertResponseStatusCode(400);
        $body = $this->jsonBody();
        $this->assertArrayHasKey('error', $body);
    }

    public function testPostBodyAsString(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => 'Plain text body',
            'target' => ['source' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(201);
        $body = $this->jsonBody();
        $this->assertEquals(
            'Plain text body', $body['body']['value']
        );
        $this->registerCreatedAnnotation($body);
    }

    public function testPostTargetAsString(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => $item->apiUrl(),
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostWithFormat(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => [
                'type' => 'TextualBody',
                'value' => 'x',
                'format' => 'text/html',
            ],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostWithLanguage(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => [
                'type' => 'TextualBody',
                'value' => 'Bonjour',
                'language' => 'fr',
            ],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostWithSelector(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'highlighting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => [
                'source' => $item->apiUrl(),
                'selector' => [
                    'value' => '#xywh=100,100,200,200',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                ],
            ],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostWithSelectorAsString(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'highlighting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => [
                'source' => $item->apiUrl(),
                'selector' => 'http://example.org/selector/1',
            ],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostWithMultipleMotivations(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => ['commenting', 'tagging'],
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostMotivationPrefixedAutomatically(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $body = $this->jsonBody();
        // The output should show clean "commenting".
        $this->assertEquals(
            'commenting', $body['motivation']
        );
        $this->registerCreatedAnnotation($body);
    }

    public function testPostTargetWithId(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['id' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    public function testPostTargetExternalUri(): void
    {
        $this->postJson('/annotations', [
            'motivation' => 'bookmarking',
            'target' => 'http://example.org/page/1',
        ]);

        $this->assertResponseStatusCode(201);
        $this->registerCreatedAnnotation($this->jsonBody());
    }

    // =========================================================
    // putAction() — update
    // =========================================================

    public function testPutUpdateAnnotation(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->resetForSecondDispatch();
        $this->putJson('/annotations/' . $a->id(), [
            'motivation' => 'describing',
            'body' => [
                'type' => 'TextualBody',
                'value' => 'Updated text',
            ],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $this->assertResponseStatusCode(200);
        $body = $this->jsonBody();
        $this->assertEquals('Annotation', $body['type']);
    }

    public function testPutWithIfMatchSuccess(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        // First, GET to obtain the ETag.
        $this->dispatch('/annotations/' . $a->id());
        $etag = $this->getResponse()
            ->getHeaders()->get('ETag')
            ->getFieldValue();

        // Now PUT with matching If-Match.
        $this->resetForSecondDispatch();
        $this->getRequest()
            ->setMethod('PUT')
            ->setContent(json_encode([
                'motivation' => 'describing',
                'body' => [
                    'type' => 'TextualBody',
                    'value' => 'Changed',
                ],
                'target' => ['source' => $item->apiUrl()],
            ]))
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/ld+json')
            ->addHeaderLine('If-Match', $etag);
        $this->dispatch('/annotations/' . $a->id());
        $this->assertResponseStatusCode(200);
    }

    public function testPutWithIfMatchMismatchReturns412(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->getRequest()
            ->setMethod('PUT')
            ->setContent(json_encode([
                'motivation' => 'describing',
                'target' => ['source' => $item->apiUrl()],
            ]))
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/ld+json')
            ->addHeaderLine('If-Match', '"wrong-etag"');
        $this->dispatch('/annotations/' . $a->id());
        $this->assertResponseStatusCode(412);
    }

    public function testPutWithIfMatchNonExistentReturns404(): void
    {
        $this->getRequest()
            ->setMethod('PUT')
            ->setContent(json_encode([
                'motivation' => 'describing',
            ]))
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/ld+json')
            ->addHeaderLine('If-Match', '"some-etag"');
        $this->dispatch('/annotations/999999999');
        $this->assertResponseStatusCode(404);
    }

    public function testPutInvalidJsonReturns400(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->getRequest()
            ->setMethod('PUT')
            ->setContent('{broken}')
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $this->dispatch('/annotations/' . $a->id());
        $this->assertResponseStatusCode(400);
    }

    public function testPutReturnsEtag(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->putJson('/annotations/' . $a->id(), [
            'motivation' => 'describing',
            'body' => [
                'type' => 'TextualBody',
                'value' => 'Updated',
            ],
            'target' => ['source' => $item->apiUrl()],
        ]);

        $etag = $this->getResponse()
            ->getHeaders()->get('ETag');
        $this->assertNotFalse($etag);
    }

    // =========================================================
    // deleteAction()
    // =========================================================

    public function testDeleteAnnotation(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());
        $id = $a->id();

        $key = array_search($id, $this->createdAnnotations);
        if ($key !== false) {
            unset($this->createdAnnotations[$key]);
        }

        $this->dispatch('/annotations/' . $id, 'DELETE');
        $this->assertResponseStatusCode(204);
    }

    public function testDeleteThenGetReturns404(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());
        $id = $a->id();

        $key = array_search($id, $this->createdAnnotations);
        if ($key !== false) {
            unset($this->createdAnnotations[$key]);
        }

        $this->dispatch('/annotations/' . $id, 'DELETE');
        $this->assertResponseStatusCode(204);

        $this->resetForSecondDispatch();
        $this->dispatch('/annotations/' . $id);
        $this->assertResponseStatusCode(404);
    }

    public function testDeleteNonExistentReturns404(): void
    {
        $this->dispatch('/annotations/999999999', 'DELETE');
        $this->assertResponseStatusCode(404);
    }

    // =========================================================
    // Auth / ACL
    // =========================================================

    public function testUnauthenticatedGetAllowed(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());
        $id = $a->id();
        $this->logout();

        $this->dispatch('/annotations/' . $id);
        $this->assertResponseStatusCode(200);
    }

    public function testUnauthenticatedContainerAllowed(): void
    {
        $this->logout();
        $this->dispatch('/annotations');
        $this->assertResponseStatusCode(200);
    }

    // =========================================================
    // W3C context only (no Omeka API context)
    // =========================================================

    public function testW3cContextOnlyOnGet(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $this->dispatch('/annotations/' . $a->id());
        $body = $this->jsonBody();
        $this->assertEquals(
            W3cAnnotation::CONTEXT, $body['@context']
        );
    }

    public function testW3cContextOnlyOnPost(): void
    {
        $item = $this->createItem();
        $this->postJson('/annotations', [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => 'x'],
            'target' => ['source' => $item->apiUrl()],
        ]);
        $body = $this->jsonBody();
        $this->assertEquals(
            W3cAnnotation::CONTEXT, $body['@context']
        );
        $this->registerCreatedAnnotation($body);
    }

    // =========================================================
    // Helpers
    // =========================================================

    protected function jsonBody(): array
    {
        return json_decode(
            $this->getResponse()->getContent(), true
        ) ?? [];
    }

    protected function postJson(string $url, array $data): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode($data))
            ->getHeaders()
            ->addHeaderLine(
                'Content-Type', 'application/ld+json'
            );
        $this->dispatch($url);
    }

    protected function putJson(string $url, array $data): void
    {
        $this->getRequest()
            ->setMethod('PUT')
            ->setContent(json_encode($data))
            ->getHeaders()
            ->addHeaderLine(
                'Content-Type', 'application/ld+json'
            );
        $this->dispatch($url);
    }

    protected function registerCreatedAnnotation(array $body): void
    {
        if (isset($body['id'])) {
            preg_match('/(\d+)$/', $body['id'], $m);
            if ($m) {
                $this->createdAnnotations[] = (int) $m[1];
            }
        }
    }

    protected function resetForSecondDispatch(): void
    {
        $this->reset();
        $this->loginAdmin();
    }
}
