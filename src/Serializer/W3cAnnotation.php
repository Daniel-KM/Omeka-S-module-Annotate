<?php declare(strict_types=1);

namespace Annotate\Serializer;

use Annotate\Api\Representation\AnnotationRepresentation;

/**
 * Serialize annotation to pure W3C Web Annotation JSON-LD, without Omeka value
 * wrapping.
 *
 * @link https://www.w3.org/TR/annotation-model/
 * @link https://www.w3.org/TR/annotation-protocol/
 */
class W3cAnnotation
{
    const CONTEXT = 'http://www.w3.org/ns/anno.jsonld';
    const CONTENT_TYPE = 'application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"';
    const CONTAINER_TYPE = 'http://www.w3.org/ns/ldp#BasicContainer';

    /**
     * Serialize one annotation to W3C JSON-LD.
     */
    public function serialize(
        AnnotationRepresentation $annotation,
        string $baseUrl
    ): array {
        $result = [
            '@context' => self::CONTEXT,
            'id' => $baseUrl . '/' . $annotation->id(),
            'type' => 'Annotation',
        ];

        // Annotation-level values.
        $part = $annotation->annotationPart();
        if ($part) {
            foreach ($part->values() as $term => $values) {
                $key = $this->w3cKey($term);
                $result[$key] = $this->serializeValues($values);
            }
        }

        // Motivation: unwrap to simple string(s).
        $motivations = $annotation->motivations();
        if ($motivations) {
            $result['motivation'] = count($motivations) === 1
                ? $this->cleanMotivation($motivations[0])
                : array_map([$this, 'cleanMotivation'], $motivations);
        }
        // Remove the wrapped version.
        unset($result['motivatedBy']);

        // dcterms:created / dcterms:creator from Omeka internals.
        $created = $annotation->created();
        if ($created) {
            $result['created'] = $created->format('c');
        }
        $modified = $annotation->modified();
        if ($modified) {
            $result['modified'] = $modified->format('c');
        }
        $owner = $annotation->owner();
        if ($owner) {
            $result['creator'] = [
                'type' => 'Person',
                'name' => $owner->name(),
            ];
            $email = $owner->email();
            if ($email) {
                $result['creator']['email'] = $email;
            }
        }

        // Bodies.
        $bodies = $annotation->bodies();
        if ($bodies) {
            $serialized = array_map(
                fn($b) => $this->serializePart($b, 'body'),
                $bodies
            );
            $result['body'] = count($serialized) === 1
                ? $serialized[0] : $serialized;
        }

        // Targets.
        $targets = $annotation->targets();
        if ($targets) {
            $serialized = array_map(
                fn($t) => $this->serializePart($t, 'target'),
                $targets
            );
            $result['target'] = count($serialized) === 1
                ? $serialized[0] : $serialized;
        }

        return $result;
    }

    /**
     * Serialize an LDP BasicContainer page.
     */
    public function serializeContainer(
        array $annotations,
        int $total,
        string $containerUrl,
        int $page,
        int $perPage
    ): array {
        $lastPage = max(0, (int) ceil($total / $perPage) - 1);

        if ($page === -1) {
            // Container root.
            $result = [
                '@context' => [
                    self::CONTEXT,
                    'http://www.w3.org/ns/ldp.jsonld',
                ],
                'id' => $containerUrl,
                'type' => ['BasicContainer', 'AnnotationCollection'],
                'total' => $total,
                'label' => 'Web Annotations',
            ];
            if ($total > 0) {
                $result['first'] = $containerUrl . '?page=0';
                $result['last'] = $containerUrl . '?page=' . $lastPage;
            }
            return $result;
        }

        // AnnotationPage.
        $result = [
            '@context' => self::CONTEXT,
            'id' => $containerUrl . '?page=' . $page,
            'type' => 'AnnotationPage',
            'partOf' => $containerUrl,
            'startIndex' => $page * $perPage,
        ];
        if ($page < $lastPage) {
            $result['next'] = $containerUrl . '?page=' . ($page + 1);
        }

        $items = [];
        foreach ($annotations as $annotation) {
            $items[] = $this->serialize($annotation, $containerUrl);
        }
        $result['items'] = $items;

        return $result;
    }

    /**
     * Serialize a body or target part.
     */
    protected function serializePart($part, string $field): array
    {
        $result = [];

        // @type.
        $values = $part->values();
        if ($field === 'body' && isset($values['rdf:value'])) {
            $result['type'] = 'TextualBody';
            $v = $part->value('rdf:value');
            if ($v !== null) {
                $result['value'] = strip_tags((string) $v);
            }
        } elseif ($field === 'target'
            && isset($values['oa:hasSource'])
        ) {
            $result['type'] = 'SpecificResource';
        }

        foreach ($values as $term => $termValues) {
            $key = $this->w3cKey($term);
            // rdf:value already handled above for TextualBody.
            if ($field === 'body' && $term === 'rdf:value') {
                continue;
            }
            // Clean oa: prefix from motivation/purpose.
            if ($term === 'oa:hasPurpose'
                || $term === 'oa:motivatedBy'
            ) {
                $serialized = $this
                    ->serializeMotivationValues($termValues);
                $result[$key] = $serialized;
                continue;
            }
            $serialized = $this->serializeValues($termValues);
            // For source and selector, unwrap single resource
            // references to IRIs.
            if ($term === 'oa:hasSource') {
                $serialized = $this
                    ->serializeSourceValues($termValues);
            }
            $result[$key] = $serialized;
        }

        // Simplify: if target is only a source IRI, return the IRI directly.
        if ($field === 'target'
            && count($result) === 2
            && isset($result['type'], $result['source'])
            && $result['type'] === 'SpecificResource'
            && is_string($result['source'])
        ) {
            return ['id' => $result['source'], 'type' => 'SpecificResource'];
        }

        return $result;
    }

    /**
     * Serialize an array of ValueRepresentation to plain values.
     *
     * @return mixed Single value or array of values.
     */
    protected function serializeValues(array $values)
    {
        $result = [];
        foreach ($values as $v) {
            $vr = $v->valueResource();
            if ($vr) {
                $result[] = $vr->apiUrl();
            } else {
                $result[] = $v->value() ?? $v->uri() ?? (string) $v;
            }
        }
        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Serialize motivation/purpose values, stripping the oa: prefix.
     *
     * @return mixed
     */
    protected function serializeMotivationValues(array $values)
    {
        $result = [];
        foreach ($values as $v) {
            $val = $v->value() ?? (string) $v;
            $result[] = $this->cleanMotivation($val);
        }
        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Serialize source values (resource references → API URLs).
     *
     * @return mixed
     */
    protected function serializeSourceValues(array $values)
    {
        $result = [];
        foreach ($values as $v) {
            $vr = $v->valueResource();
            if ($vr) {
                $result[] = $vr->apiUrl();
            } else {
                $result[] = $v->uri() ?? $v->value() ?? (string) $v;
            }
        }
        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Convert a prefixed term to the W3C short key.
     *
     * "oa:motivatedBy" → "motivation",
     * "oa:hasBody" → "body", etc.
     */
    protected function w3cKey(string $term): string
    {
        static $map = [
            'oa:motivatedBy' => 'motivation',
            'oa:hasBody' => 'body',
            'oa:hasTarget' => 'target',
            'oa:hasSource' => 'source',
            'oa:hasSelector' => 'selector',
            'oa:hasState' => 'state',
            'oa:hasPurpose' => 'purpose',
            'oa:hasScope' => 'scope',
            'oa:hasStartSelector' => 'startSelector',
            'oa:hasEndSelector' => 'endSelector',
            'oa:exact' => 'exact',
            'oa:prefix' => 'prefix',
            'oa:suffix' => 'suffix',
            'oa:start' => 'start',
            'oa:end' => 'end',
            'oa:cachedSource' => 'cached',
            'oa:refinedBy' => 'refinedBy',
            'oa:renderedVia' => 'renderedVia',
            'oa:sourceDate' => 'sourceDate',
            'oa:sourceDateStart' => 'sourceDateStart',
            'oa:sourceDateEnd' => 'sourceDateEnd',
            'oa:styleClass' => 'styleClass',
            'oa:processingLanguage' => 'processingLanguage',
            'oa:textDirection' => 'textDirection',
            'rdf:value' => 'value',
            'dcterms:format' => 'format',
            'dcterms:language' => 'language',
            'dcterms:conformsTo' => 'conformsTo',
            'dcterms:created' => 'created',
            'dcterms:creator' => 'creator',
            'dc:format' => 'format',
            'rdfs:label' => 'label',
            'schema:accessibilityFeature' => 'accessibility',
            'as:first' => 'first',
            'as:last' => 'last',
            'as:items' => 'items',
            'as:next' => 'next',
            'as:prev' => 'prev',
            'as:partOf' => 'partOf',
            'as:startIndex' => 'startIndex',
            'as:totalItems' => 'totalItems',
            'foaf:mbox' => 'email',
        ];
        return $map[$term] ?? $term;
    }

    /**
     * Remove "oa:" prefix from motivation values.
     */
    protected function cleanMotivation(string $motivation): string
    {
        return str_starts_with($motivation, 'oa:')
            ? substr($motivation, 3)
            : $motivation;
    }
}
