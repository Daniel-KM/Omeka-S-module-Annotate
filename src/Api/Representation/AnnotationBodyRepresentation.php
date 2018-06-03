<?php
namespace Annotate\Api\Representation;

use Annotate\Entity\AnnotationBody;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * The representation of an Annotation body.
 *
 * Note: Internally, a body is an Omeka resource, but it is not a rdf class.
 * An intermediate class below or beside may be used for target and body.
 */
class AnnotationBodyRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var AnnotationBody
     */
    protected $resource;

    public function getControllerName()
    {
        return 'annotation-body';
    }

    public function getResourceJsonLdType()
    {
        return 'o-module-annotate:Body';
    }

    public function getResourceJsonLd()
    {
        return [
            'oa:Annotation' => $this->annotation()->getReference(),
        ];
    }

    // TODO Should bodies and targets keep omeka properties? (see parent).
    // public function getJsonLd()

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

    public function displayTitle($default = null)
    {
        // TODO Check if this is a textual value or not before setting the title.
        $title = $this->value('rdf:value', [
            'default' => null,
        ]);

        if ($title !== null) {
            return (string) strip_tags($title);
        }

        // TODO Add a specific title from the metadata of the body (motivation)?
        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Annotation body #%d]'), $this->id());
        }

        return $default;
    }

    /**
     * @todo Remove the annotation body controller.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::siteUrl()
     */
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
                'controller' => 'AnnotationBodyController',
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }
}
