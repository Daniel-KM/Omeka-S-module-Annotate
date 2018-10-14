<?php
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
        // Set the values as JSON-LD value objects.
        $values = [];
        foreach ($this->values() as $term => $property) {
            foreach ($property['values'] as $value) {
                $values[$term][] = $value;
            }
        }

        $jsonLdType = $this->getJsonLdType();

        // @see https://www.w3.org/ns/anno.jsonld.
        // TODO Manage all properties (currently only the current ones used in the module).
        $mapping = [
            // Body.
            'oa:hasPurpose' => 'purpose',
            // Target.
            'oa:hasSource' => 'source',
            'oa:styleClass' => 'styleClass',
            // All.
            'rdf:type' => 'type',
            'dcterms:format' => 'format',
            'rdf:value' => 'value',
            // Annotation.
            'oa:styledBy' => 'styledBy',
            // Manage a specific value for catography.
            // TODO Use the trigger to manage the values.
            'cartography:uncertainty' => 'cartography:uncertainty',
        ];

        // Force the omeka type for the source.
        $omekaType = null;

        /** @var \Omeka\Api\Representation\ValueRepresentation[] $vv */
        foreach ($values as $key => $vv) {
            switch ($key) {
                case $jsonLdType === 'o-module-annotate:Target'
                    && in_array($key, ['rdf:type', 'dcterms:format', 'rdf:value', 'cartography:uncertainty']):
                    $values['selector'][$mapping[$key]] = $this->valuesOnly($vv);
                    unset($values[$key]);
                    break;
                case isset($mapping[$key]):
                    $vonly = $this->valuesOnly($vv);
                    if ($key === 'oa:hasSource' && !is_array($vonly)) {
                        $vvv = reset($vv);
                        if ($vvv->type() === 'resource') {
                            $omekaType = $vvv->valueResource()->getJsonLdType();
                        }
                    }
                    $values[$mapping[$key]] = $this->valuesOnly($vv);
                    unset($values[$key]);
                    break;
            }
        }

        // TODO If no source, keep id of the annotation target? This is not the way the module works currently.

        // Reorder target keys according to spec examples (useless, but pretty).
        if ($jsonLdType === 'o-module-annotate:Target') {
            if ($omekaType) {
                $values['type'] = $omekaType;
            }
            $values = array_filter(array_merge(['type' => null, 'source' => null], $values));
        }

        return array_merge(
            $values
        );
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
     *
     * @param array $values
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
