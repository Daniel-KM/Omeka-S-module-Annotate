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
        $values = parent::getJsonLd();

        if (empty($values['type'])) {
            if (!empty($values['value']) && !is_array($values['value'])) {
                $type = $this->extractJsonLdTypeFromApiUrl($values['value']);
                if ($type) {
                    $values['selector']['type'] = $type;
                    $values['selector']['value'] = $values['value'];
                    unset($values['value']);
                }
            }
        } elseif ($values['type'] === 'oa:Selector') {
            $values['selector']['type'] = $values['type'];
            unset($values['type']);
            if (isset($values['format'])) {
                $values['selector']['format'] = $values['format'];
                unset($values['format']);
            }
            if (isset($values['value'])) {
                $values['selector']['value'] = $values['value'];
                unset($values['value']);
            }
            if (isset($values['cartography:uncertainty'])) {
                $values['selector']['cartography:uncertainty'] = $values['cartography:uncertainty'];
                unset($values['cartography:uncertainty']);

            }
            // TODO Clean the creation of selector with refinement.
            if (!empty($values['selector']['value'])) {
                if (is_array($values['selector']['value'])) {
                    foreach ($values['selector']['value'] as $key => $value) {
                        // TODO Use the media file original url instead of the Omeka api url ?
                        $type = $this->extractJsonLdTypeFromApiUrl($value);
                        if ($type) {
                            unset($values['selector']['value'][$key]);
                            $refinedBy = $values['selector'];
                            $refinedBy['value'] = count($refinedBy['value']) > 1
                                ? $refinedBy['value']
                                : reset($refinedBy['value']);
                            $selector = [];
                            $selector['type'] = $type;
                            $selector['value'] = $value;
                            $selector['refinedBy'] = $refinedBy;
                            $values['selector'] = $selector;
                            break;
                        }
                    }
                } else {
                    // TODO Use the media file original url instead of the Omeka api url ?
                    $value = $values['selector']['value'];
                    $type = $this->extractJsonLdTypeFromApiUrl($value);
                    if ($type) {
                        unset($values['selector']['value']);
                        $selector = [];
                        $selector['type'] = $type;
                        $selector['value'] = $value;
                        $values['selector'] = $selector;
                    }
                }
            }
        }

        if (isset($values['source']) && empty($values['type'])) {
            $type = $this->extractJsonLdTypeFromApiUrl($values['source']);
            if ($type) {
                $values['type'] = $type;
            }
        }

        // Reorder target keys according to spec examples (useless, but pretty).
        $values = array_filter(array_merge(['type' => null, 'source' => null, 'format' => null], $values));

        // TODO If no source, keep id of the annotation target? This is not the way the module works currently.

        return $values;
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
