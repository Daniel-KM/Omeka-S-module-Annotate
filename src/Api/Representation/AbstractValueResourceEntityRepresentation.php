<?php declare(strict_types=1);

namespace Annotate\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ValueRepresentation;

/**
 * The representation of a value resource entity.
 *
 * Internally, a body or a target is an Omeka resource but it's not a rdf class.
 * So this intermediate class is used for Annotation resources body or target.
 *
 * @todo Convert into a full object representation (selector, etc.).
 *
 * @todo Use a resource template according to the type of value resource.
 *
 * @øee https://www.w3.org/TR/annotation-model/#web-annotation-principles
 */
abstract class AbstractValueResourceEntityRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * Value that is the same than the current resource json ld type.
     *
     * For example, the value "oa:hasBody" of an AnnotationBody, or the value
     * "oa:hasTarget" of an AnnotationTarget. By design, it should be zero or
     * unique (else another sub-resource is created).
     * Note that this value can be hidden if private. In that case, even its
     * reference is hidden.
     *
     * @var ValueRepresentation
     */
    protected $annotationSubValue;

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
     * This resource is technically an Omeka resource, but not a rdf resource,
     * so it keeps only parts of the full resource metadata.
     *
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        $values = [];
        $skip = $this->getResourceJsonLdType();

        // Set the values as JSON-LD value objects.
        foreach ($this->values() as $term => $property) {
            // Skip the value that is the resource entity itself.
            if ($term === $skip) {
                if (empty($this->annotationSubValue)) {
                    $this->annotationSubValue = reset($property['values']);
                }
                continue;
            }
            foreach ($property['values'] as $value) {
                $values[$term][] = $value;
            }
        }

        // TODO Don't manage the type with a resource class, but with rdf:type or o:type.
        $resourceClass = $this->resourceClass();
        if ($resourceClass) {
            $resourceClass = $resourceClass->getReference();
            $values['o:resource_class'] = $resourceClass;
        }

        $resourceTemplate = $this->resourceTemplate();
        if ($resourceTemplate) {
            $resourceTemplate = $resourceTemplate->getReference();
            $values['o:resource_template'] = $resourceTemplate;
        }

        // Manage the special value resource entity (resource used for value).
        // There can be only one sub-value by value (in Omeka): for example, a
        // a body is only one body.
        $result = [];
        if ($this->annotationSubValue) {
            $result = $this->annotationSubValue->jsonSerialize();
        }

        return array_merge(
            $result,
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
    public function jsonSerialize(): array
    {
        $jsonLd = $this->getJsonLd();
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

    // Disable function related to the resource entity that should not be used.

    public function linkPretty(
        $thumbnailType = 'square',
        $titleDefault = null,
        $action = null,
        ?array $attributes = null,
        $lang = null
    ) {
        return null;
    }

    public function userIsAllowed($privilege)
    {
        return true;
    }

    public function apiUrl()
    {
        return null;
    }

    public function url($action = null, $canonical = false)
    {
        return null;
    }

    public function adminUrl($action = null, $canonical = false)
    {
        return null;
    }

    public function link($text, $action = null, $attributes = [])
    {
        return null;
    }

    public function linkRaw($html, $action = null, $attributes = [])
    {
        return null;
    }

    public function getFileUrl($prefix, $name, $extension = null)
    {
        return null;
    }

    public function embeddedJsonLd(): void
    {
    }
}
