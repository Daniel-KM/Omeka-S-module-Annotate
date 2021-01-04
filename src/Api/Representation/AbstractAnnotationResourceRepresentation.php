<?php declare(strict_types=1);
namespace Annotate\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * The representation of an Annotation resource (body or target).
 *
 * Internally, a body or a target is an Omeka resource but it's not a rdf class.
 * So this intermediate class is used for Annotation resources body or target.
 *
 * @todo Convert into a full object representation (selector, etc.).
 *
 * @øee https://www.w3.org/TR/annotation-model/#web-annotation-principles
 */
abstract class AbstractAnnotationResourceRepresentation extends AbstractResourceEntityRepresentation
{
    public function getResourceJsonLd()
    {
        return [];
    }

    public function getJsonLdType()
    {
        return $this->getResourceJsonLdType();
    }

    /**
     * Get an array representation of this resource using JSON-LD notation.
     *
     * This resource is technically an Omeka resource, but not a rdf resource.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        $values = $this->flatJsonLd();

        // @see https://www.w3.org/ns/anno.jsonld.
        // TODO Manage all properties (currently only the current ones used in the module).
        // Note: this abstract class is a upper class only for body an target.
        $mapping = [
            // Annotation.
            'oa:styledBy' => 'styledBy',
            // Body.
            'oa:hasPurpose' => 'purpose',
            // Target.
            'oa:hasSource' => 'source',
            'oa:styleClass' => 'styleClass',
            // All.
            'rdf:type' => 'type',
            'dcterms:format' => 'format',
            'rdf:value' => 'value',
            // Manage a specific value for cartography.
            // TODO Use a trigger to manage the values.
            'cartography:uncertainty' => 'cartography:uncertainty',
        ];

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $vv */
        foreach ($values as $key => $vv) {
            if (isset($mapping[$key])) {
                $values[$mapping[$key]] = $this->valuesOnly($vv);
                unset($values[$key]);
            }
        }

        // TODO If no source, keep id of the annotation target? This is not the way the module works currently.

        return $values;
    }

    /**
     * Set the values as JSON-LD value objects.
     *
     * @return array
     */
    protected function flatJsonLd()
    {
        $values = [];
        foreach ($this->values() as $term => $property) {
            foreach ($property['values'] as $value) {
                $values[$term][] = $value;
            }
        }
        return $values;
    }

    /**
     * Get the resource json-ld type from an api url.
     *
     * @todo Remove extractJsonLdTypeFromApiUrl(), too hacky.
     *
     * @param string $url
     * @return string|null
     */
    protected function extractJsonLdTypeFromApiUrl($url)
    {
        static $baseApiUrl;

        if (empty($baseApiUrl)) {
            $urlHelper = $this->getServiceLocator()->get('ViewHelperManager')->get('url');
            $baseApiUrl = $urlHelper('api', [], ['force_canonical' => true]) . '/';
        }

        $pos = mb_strpos($url, $baseApiUrl);
        if ($pos !== 0) {
            return null;
        }
        $type = strtok(mb_substr($url, mb_strlen($baseApiUrl)), '/');

        $mapResourceTypes = [
            'items' => 'o:Item',
            'item_sets' => 'o:ItemSet',
            'media' => 'o:Media',
        ];
        return $mapResourceTypes[$type]
            ?? null;
    }

    /**
     * Compose the complete JSON-LD object.
     *
     * This resource is technically an Omeka resource, but not a rdf resource.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $childJsonLd = $this->getJsonLd();
        // $type = $this->getJsonLdType();
        // $url = $this->getViewHelper('Url');

        $jsonLd = array_merge(
            [
                // 'id' => $this->apiUrl(),
            ],
            $childJsonLd
        );
        // No filter: use main annotation instead.
        return $jsonLd;
    }

    /**
     * Get the annotation.
     *
     * @return AnnotationRepresentation[]
     */
    public function annotation()
    {
        return $this->getAdapter('annotations')
            ->getRepresentation($this->resource->getAnnotation());
    }

    /**
     * Renormalize values as json-ld rdf Annotation resource.
     *
     * @see https://www.w3.org/TR/annotation-model/
     * @todo Factorize with AnnotationRepresentation::valuesOnly().
     *
     * @param \Omeka\Api\Representation\ValueRepresentation[] $values
     * @return array|string
     */
    protected function valuesOnly(array $values)
    {
        $result = [];

        foreach ($values as $value) {
            switch ($value->type()) {
                case 'resource':
                    $result[] = $value->valueResource()->apiUrl();
                    break;
                case 'uri':
                    $result[] = $value->uri();
                    break;
                case 'literal':
                default:
                    $result[] = $value->value();
                    break;
            }
        }

        return count($result) > 1 ? $result : reset($result);
    }
}
