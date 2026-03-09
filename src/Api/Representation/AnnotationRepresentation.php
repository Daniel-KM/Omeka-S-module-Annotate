<?php declare(strict_types=1);

namespace Annotate\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * The representation of an Annotation resource.
 *
 * @øee https://www.w3.org/TR/annotation-model/#web-annotation-principles
 */
class AnnotationRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var \Annotate\Entity\Annotation
     */
    protected $resource;

    /**
     * @var array|null Cache for loadPartValues().
     */
    protected $partValuesCache;

    public function getControllerName()
    {
        return 'annotation';
    }

    public function getResourceJsonLdType()
    {
        return 'oa:Annotation';
    }

    /**
     * {@inheritDoc}
     *
     * The class "oa:Annotation" is predefined and cannot be changed or merged.
     *
     * @link https://www.w3.org/TR/annotation-vocab/#annotation
     */
    public function getJsonLdType()
    {
        return $this->getResourceJsonLdType();
    }

    public function getResourceJsonLd()
    {
        $result = [];
        $bodies = $this->bodies();
        if ($bodies) {
            $result['oa:hasBody'] = array_map(
                fn($b) => $b->jsonSerialize(),
                $bodies
            );
        }
        $targets = $this->targets();
        if ($targets) {
            $result['oa:hasTarget'] = array_map(
                fn($t) => $t->jsonSerialize(),
                $targets
            );
        }
        return $result;
    }

    /**
     * Get an array representation of this resource using JSON-LD notation.
     *
     * @todo Check the following assertion: This resource is technically an Omeka resource, but not a rdf resource.
     * This resource is technically an Omeka resource, but not a rdf resource.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getJsonLd()
     *
     * {@inheritDoc}
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::jsonSerialize()
     */
    public function jsonSerialize(): array
    {
        $jsonLd = parent::jsonSerialize();

        $jsonLd['@context'] = [
            'http://www.w3.org/ns/anno.jsonld',
            $this->getViewHelper('serverUrl')('')
                . $this->getViewHelper('basePath')()
                . '/api-context',
        ];

        // Remove body/target values from the flat representation to avoid
        // duplication with oa:hasBody / oa:hasTarget.
        $parts = $this->loadPartValues();
        $bodyTargetTerms = [];
        foreach (['body', 'target'] as $field) {
            foreach ($parts[$field] ?? [] as $valuesByTerm) {
                foreach (array_keys($valuesByTerm) as $term) {
                    $bodyTargetTerms[$term] = true;
                }
            }
        }
        // Keep annotation-level terms even if they also appear in body/target.
        foreach ($parts['annotation'] ?? [] as $valuesByTerm) {
            foreach (array_keys($valuesByTerm) as $term) {
                unset($bodyTargetTerms[$term]);
            }
        }
        foreach (array_keys($bodyTargetTerms) as $term) {
            unset($jsonLd[$term]);
        }

        // Move oa:hasBody and oa:hasTarget to the end so annotation-level
        // properties appear first.
        foreach (['oa:hasBody', 'oa:hasTarget'] as $key) {
            if (isset($jsonLd[$key])) {
                $tmp = $jsonLd[$key];
                unset($jsonLd[$key]);
                $jsonLd[$key] = $tmp;
            }
        }

        return $jsonLd;
    }

    /**
     * Get the annotation-level values (not body, not target).
     *
     * @return AnnotationPartValues|null
     */
    public function annotationPart(): ?AnnotationPartValues
    {
        $parts = $this->partsByField('annotation');
        return $parts ? reset($parts) : null;
    }

    /**
     * Get the bodies assigned to this annotation.
     *
     * @return AnnotationPartValues[]
     */
    public function bodies(): array
    {
        return $this->partsByField('body');
    }

    /**
     * Return the first body if one exists.
     *
     * @return AnnotationPartValues|null
     */
    public function primaryBody(): ?AnnotationPartValues
    {
        $bodies = $this->bodies();
        return $bodies ? reset($bodies) : null;
    }

    /**
     * Get the targets assigned to this annotation.
     *
     * @return AnnotationPartValues[]
     */
    public function targets(): array
    {
        return $this->partsByField('target');
    }

    /**
     * Return the first target if one exists.
     *
     * @return AnnotationPartValues|null
     */
    public function primaryTarget(): ?AnnotationPartValues
    {
        $targets = $this->targets();
        return $targets ? reset($targets) : null;
    }

    /**
     * Return the target resources (annotated resources).
     *
     * @return AbstractResourceEntityRepresentation[]
     */
    public function targetSources(): array
    {
        $result = [];
        foreach ($this->targets() as $target) {
            $result = array_merge(
                $result,
                array_values($target->sources())
            );
        }
        return array_values($result);
    }

    /**
     * Return the primary target resource (annotated resource).
     */
    public function primaryTargetSource(): ?AbstractResourceEntityRepresentation
    {
        $targets = $this->targetSources();
        return $targets ? reset($targets) : null;
    }

    /**
     * Return the target selectors, generally a single media.
     *
     * @return \Omeka\Api\Representation\ValueRepresentation[]
     */
    public function targetSelectors(): array
    {
        $result = [];
        foreach ($this->targets() as $target) {
            $result = array_merge(
                $result,
                $target->value('oa:hasSelector', ['all' => true])
            );
        }
        return array_values($result);
    }

    /**
     * Return the target selectors that are resources, generally a single media.
     *
     * @return \Omeka\Api\Representation\ValueRepresentation[]
     */
    public function targetSelectorResources(): array
    {
        $result = [];
        foreach ($this->targets() as $target) {
            foreach ($target->value('oa:hasSelector', ['all' => true]) as $value) {
                if ($value->valueResource()) {
                    $result[] = $value;
                }
            }
        }
        return array_values($result);
    }

    public function motivations(): array
    {
        $result = [];
        foreach ($this->value('oa:motivatedBy', ['all' => true]) as $value) {
            $result[] = $value->value();
        }
        return $result;
    }

    public function motivation(): ?string
    {
        $value = $this->value('oa:motivatedBy');
        return $value ? $value->value() : null;
    }

    /**
     * @todo Support reverse subject values for annotation.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::subjectValuesForReverse()
     */
    public function subjectValuesForReverse($propertyId = null, $resourceType = null, $siteId = null)
    {
        return [];
    }

    public function siteUrl($siteSlug = null, $canonical = false)
    {
        if (!$siteSlug) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $url = $this->getViewHelper('Url');
        return $url(
            'site/resource-id',
            [
                'site-slug' => $siteSlug,
                'controller' => 'annotation',
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function displayTitle($default = null, $lang = null)
    {
        $title = $this->title();
        if ($title !== null) {
            return (string) $title;
        }

        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Annotation #%d]'), $this->id());
        }

        return $default;
    }

    /**
     * Get the annotator of this annotation.
     *
     * @return \Omeka\Api\Representation\UserRepresentation|array
     */
    public function annotator($default = null)
    {
        $owner = $this->owner();
        if ($owner) {
            return $owner;
        }

        $public = [];
        $creator = $this->value('dcterms:creator');
        if ($creator) {
            $public['id'] = true;
            $public['name'] = (string) $creator;
        } else {
            $public['id'] = false;
            if ($default === null) {
                $translator = $this->getServiceLocator()->get('MvcTranslator');
                $public['name'] = $translator->translate('[Unknown]'); // @translate
            } else {
                $public['name'] = $default;
            }
        }

        $public['email'] = (string) $this->value('foaf:mbox');

        return $public;
    }

    /**
     * Get the link to all annotations of the annotator.
     */
    public function linkAnnotator($default = null)
    {
        $services = $this->getServiceLocator();

        $annotator = $this->annotator();
        $query = [];
        if (is_object($annotator)) {
            $text = $annotator->name();
            $query['owner_id'] = $annotator->id();
        } else {
            $text = $annotator['name'];
            $query['annotator'] = $annotator['id'] ? $text : '0';
        }

        $status = $services->get('Omeka\Status');
        $url = $this->getViewHelper('Url');
        // Make compatible with Omeka < 1.2.1.
        if (method_exists($status, 'isAdminRequest')) {
            if ($status->isSiteRequest()) {
                $url = $url('site/annotate/default', [], ['query' => $query], true);
            } elseif ($status->isAdminRequest()) {
                $url = $url('admin/annotate/default', [], ['query' => $query]);
            } else {
                return;
            }
        } else {
            $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
            if ($routeMatch->getParam('__SITE__')) {
                $url = $url('site/annotate/default', [], ['query' => $query], true);
            } elseif ($routeMatch->getParam('__ADMIN__')) {
                $url = $url('admin/annotate/default', [], ['query' => $query]);
            } else {
                return;
            }
        }

        $hyperlink = $this->getViewHelper('hyperlink');
        $escapeHtml = $this->getViewHelper('escapeHtml');
        return $hyperlink->raw($escapeHtml($text), $url);
    }

    /**
     * Merge values of annotation, bodies and targets.
     *
     * In the new model, all values are on the annotation resource, so this
     * simply returns values().
     *
     * @deprecated Use values() directly.
     */
    public function mergedValues(): array
    {
        return $this->values();
    }

    /**
     * Separate flat properties between annotation, bodies and targets using the
     * W3C vocabulary map.
     *
     * @deprecated Use structured API data with oa:hasBody and oa:hasTarget.
     */
    public function divideMergedValues(array $data): array
    {
        $bodyTerms = [
            'oa:hasPurpose', 'oa:processingLanguage',
            'oa:textDirection', 'dcterms:language', 'rdf:value',
        ];
        $targetTerms = [
            'dcterms:format', 'oa:cachedSource', 'oa:end',
            'oa:exact', 'oa:hasEndSelector', 'oa:hasScope',
            'oa:hasSelector', 'oa:hasSource',
            'oa:hasStartSelector', 'oa:hasState', 'oa:prefix',
            'oa:refinedBy', 'oa:renderedVia', 'oa:sourceDate',
            'oa:sourceDateEnd', 'oa:sourceDateStart',
            'oa:start', 'oa:styleClass', 'oa:suffix',
            'as:first', 'as:items', 'as:last', 'as:next',
            'as:partOf', 'as:prev', 'as:startIndex',
            'as:totalItems', 'dc:format',
            'dcterms:conformsTo', 'rdfs:label',
            'schema:accessibilityFeature',
        ];

        if (!isset($data['oa:hasBody'])) {
            $data['oa:hasBody'] = [[]];
        }
        if (!isset($data['oa:hasTarget'])) {
            $data['oa:hasTarget'] = [[]];
        }

        foreach ($data as $term => $values) {
            if (!is_array($values)
                || strpos($term, 'o:') === 0
            ) {
                continue;
            }
            if (in_array($term, $bodyTerms, true)) {
                $data['oa:hasBody'][0][$term] = $values;
                unset($data[$term]);
            } elseif (in_array($term, $targetTerms, true)) {
                $data['oa:hasTarget'][0][$term] = $values;
                unset($data[$term]);
            }
        }

        return $data;
    }

    /**
     * Load values grouped by field and ordinal from the annotation_value
     * side-table.
     *
     * @return array [field => [ordinal => [term => ValueRep[]]]]
     */
    protected function loadPartValues(): array
    {
        if ($this->partValuesCache !== null) {
            return $this->partValuesCache;
        }

        $services = $this->getServiceLocator();
        $em = $services->get('Omeka\EntityManager');
        $conn = $em->getConnection();

        $sql = <<<'SQL'
            SELECT av.id, av.field, av.ordinal
            FROM annotation_value av
            WHERE av.annotation_id = :id
            ORDER BY av.field, av.ordinal, av.id
            SQL;
        $rows = $conn->executeQuery($sql, ['id' => $this->id()])
            ->fetchAllAssociative();

        // Index: value_id => [field, ordinal].
        $valueMap = [];
        foreach ($rows as $row) {
            $valueMap[(int) $row['id']] = [
                'field' => $row['field'],
                'ordinal' => (int) $row['ordinal'],
            ];
        }

        // Group the resource values by field/ordinal/term.
        // ValueRepresentation has no id() method, so build
        // representations from entities directly.
        $grouped = [];
        foreach ($this->resource->getValues() as $valueEntity) {
            $vid = $valueEntity->getId();
            if (!isset($valueMap[$vid])) {
                continue;
            }
            $info = $valueMap[$vid];
            $property = $valueEntity->getProperty();
            $term = $property->getVocabulary()->getPrefix()
                . ':' . $property->getLocalName();
            $valueRep = new \Omeka\Api\Representation\ValueRepresentation(
                $valueEntity,
                $services
            );
            $grouped[$info['field']][$info['ordinal']][$term][]
                = $valueRep;
        }

        $this->partValuesCache = $grouped;
        return $grouped;
    }

    /**
     * Get AnnotationPartValues for a given field (body or target).
     *
     * @return AnnotationPartValues[]
     */
    protected function partsByField(string $field): array
    {
        $parts = $this->loadPartValues();
        $fieldData = $parts[$field] ?? [];
        $services = $this->getServiceLocator();
        $result = [];
        foreach ($fieldData as $ordinal => $valuesByTerm) {
            $result[] = new AnnotationPartValues(
                $field,
                $ordinal,
                $valuesByTerm,
                $services
            );
        }
        return $result;
    }
}
