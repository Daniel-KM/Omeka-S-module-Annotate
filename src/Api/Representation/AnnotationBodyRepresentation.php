<?php
namespace Annotate\Api\Representation;

use Annotate\Entity\AnnotationBody;

/**
 * The representation of an Annotation body.
 */
class AnnotationBodyRepresentation extends AbstractAnnotationResourceRepresentation
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

    public function getJsonLd()
    {
        // TODO Don't manage the type with a resource class, but with rdf:type.
        $result = [];
        $resourceClass = $this->resourceClass();
        if ($resourceClass) {
            $result['type'] = $resourceClass->localName();
        }

        $jsonLd = parent::getJsonLd();
        $values = array_merge(
            $result,
            $jsonLd
        );

        if (empty($values['type'])) {
            if (!empty($values['value']) && !is_array($values['value'])) {
                $type = $this->extractJsonLdTypeFromApiUrl($values['value']);
                if ($type) {
                    $values['type'] = $type;
                    $values = array_merge(['type' => null, 'value' => null], $values);
                }
            }
        }

        return $values;
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
