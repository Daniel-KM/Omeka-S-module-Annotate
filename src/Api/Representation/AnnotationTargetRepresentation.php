<?php
namespace Annotate\Api\Representation;

use Annotate\Entity\AnnotationTarget;

/**
 * The representation of an Annotation target.
 */
class AnnotationTargetRepresentation extends AbstractAnnotationResourceRepresentation
{
    /**
     * @var AnnotationTarget
     */
    protected $resource;

    public function getControllerName()
    {
        return 'annotation-target';
    }

    public function getResourceJsonLdType()
    {
        return 'o-module-annotate:Target';
    }

    public function getJsonLd()
    {
        $jsonLd = parent::getJsonLd();

        // TODO If no source, keep id of the annotation target? This is not the way the module works currently.

        if (isset($jsonLd['type']) && $jsonLd['type'] === 'oa:Selector') {
            $jsonLd['selector']['type'] = $jsonLd['type'];
            $jsonLd['selector']['format'] = $jsonLd['format'];
            $jsonLd['selector']['value'] = $jsonLd['value'];
            unset($jsonLd['type']);
            unset($jsonLd['format']);
            unset($jsonLd['value']);
        }

        return $jsonLd;
    }

    public function displayTitle($default = null)
    {
        // TODO Check if this is a textual value or not before setting the title.
        // $title = $this->value('rdf:value', [
        //     'default' => null,
        // ]);

        // if ($title !== null) {
        //     return (string) $title;
        // }

        // TODO Add a specific title from the metadata of the target (resource)?
        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Annotation target #%d]'), $this->id());
        }

        return $default;
    }

    /**
     * List tthe resources of the annotation target.
     *
     * @todo Add a filter "annotation_id" to query resources of a annotation.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation;[]
     */
    public function sources()
    {
        return array_map(function ($v) {
            return $v->valueResource();
        }, $this->value('oa:hasSource', ['type' => 'resource', 'all' => true, 'default' => []]));
    }

    /**
     * @todo Remove the annotation target controller.
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
                'controller' => 'AnnotationTargetController',
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }
}
