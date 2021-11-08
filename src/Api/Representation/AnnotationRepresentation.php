<?php declare(strict_types=1);
namespace Annotate\Api\Representation;

use Annotate\Api\Adapter\AnnotationBodyHydrator;
use Annotate\Api\Adapter\AnnotationTargetHydrator;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class AnnotationRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var \Annotate\Entity\Annotation
     */
    protected $resource;

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
     * Unlike integrated resources, the class "oa:Annotation" is predefined and
     * cannot be changed or merged.
     *
     * @link https://www.w3.org/TR/annotation-vocab/#annotation
     * @see \Omeka\Api\Representation\AbstractResourceEntityRepresentation::getJsonLdType()
     */
    public function getJsonLdType()
    {
        return $this->getResourceJsonLdType();
    }

    public function getResourceJsonLd()
    {
        $result = [];
        $bodies = $this->bodies();
        // Complies with https://www.w3.org/TR/annotation-model/#cardinality-of-bodies-and-targets
        if ($bodies) {
            $result['oa:hasBody'] = $bodies;
        }
        $result['oa:hasTarget'] = $this->targets();
        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * Two rdf contexts are used: Open Annotation (main) and Omeka (secondary).
     * @todo Extend oa model: oa:styledBy should be a SVG stylesheet, but oa:SvgStylesheed does not exist (only CssStylesheet). But GeoJson allows to manage css.
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::jsonSerialize()
     */
    public function jsonSerialize()
    {
        $jsonLd = parent::jsonSerialize();

        $jsonLd['@context'] = [
            'oa' => 'http://www.w3.org/ns/anno.jsonld',
            'o' => 'http://localhost/OmekaS/api-context',
        ];

        return $jsonLd;
    }

    /**
     * Get the bodies assigned to this annotation.
     *
     * @todo Remove bodies without properties.
     *
     * @return AnnotationBodyRepresentation[]
     */
    public function bodies()
    {
        $bodies = [];
        $bodyAdapter = new AnnotationBodyHydrator();
        $bodyAdapter->setServiceLocator($this->getServiceLocator());
        foreach ($this->resource->getBodies() as $bodyEntity) {
            $bodies[] = $bodyAdapter->getRepresentation($bodyEntity);
        }
        return $bodies;
    }

    /**
     * Return the first body if one exists.
     *
     * @return AnnotationBodyRepresentation
     */
    public function primaryBody()
    {
        $bodies = $this->bodies();
        return $bodies ? reset($bodies) : null;
    }

    /**
     * Get the targets assigned to this annotation.
     *
     * @return AnnotationTargetRepresentation[]
     */
    public function targets()
    {
        $targets = [];
        $targetAdapter = new AnnotationTargetHydrator();
        $targetAdapter->setServiceLocator($this->getServiceLocator());
        foreach ($this->resource->getTargets() as $targetEntity) {
            $targets[] = $targetAdapter->getRepresentation($targetEntity);
        }
        return $targets;
    }

    /**
     * Return the first target if one exists.
     *
     * @return AnnotationTargetRepresentation
     */
    public function primaryTarget()
    {
        $targets = $this->targets();
        return $targets ? reset($targets) : null;
    }

    /**
     * Return the target resources if any.
     *
     * @return AbstractResourceEntityRepresentation[]
     */
    public function targetSources()
    {
        $result = [];
        $targets = $this->targets();
        foreach ($targets as $target) {
            $result = array_merge($result, array_values($target->sources()));
        }
        return array_values($result);
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
     * @param string $default
     * @return \Omeka\Api\Representation\UserRepresentation|array
     */
    public function annotator($default = null)
    {
        $owner = $this->owner();
        if ($owner) {
            return $owner;
        }

        // TODO The annotator may be a public or a deleted owner.
        $public = [];
        $creator = $this->value('dcterms:creator');
        if ($creator) {
            $public['id'] = true;
            $public['name'] = (string) $creator;
        } else {
            $public['id'] = false;
            if (is_null($default)) {
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
     * Get the link to all annotations of the annotator of this annotation.
     *
     * @param string $default
     * @return string
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
            // TODO Manage anonymous user deleted user.
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
     * Merge values of the annotation, bodies and the targets.
     *
     *  Most of the time, there is only one body and one target, and each entity
     *  has its specific properties according to the specification. So the merge
     *  create a simpler list of values.
     *
     * @uses AbstractResourceEntityRepresentation::values()
     *
     * @deprecated Will be replaced by a new resource form, on the resource template base.
s     *
     * @return array
     */
    public function mergedValues()
    {
        $values = $this->values();
        // Note: array_merge_recursive may failed for memory overkill.
        foreach ($this->bodies() as $body) {
            // $values = array_merge_recursive($values, $body->values());
            foreach ($body->values() as $term => $termValues) {
                if (isset($values[$term]['property'])) {
                    $values[$term]['values'] = empty($values[$term]['values'])
                        ? $termValues['values']
                        : array_merge($values[$term]['values'], $termValues['values']);
                } else {
                    $values[$term] = $termValues;
                }
            }
        }
        foreach ($this->targets() as $target) {
            // $values = array_merge_recursive($values, $target->values());
            foreach ($target->values() as $term => $termValues) {
                if (isset($values[$term]['property'])) {
                    $values[$term]['values'] = empty($values[$term]['values'])
                        ? $termValues['values']
                        : array_merge($values[$term]['values'], $termValues['values']);
                } else {
                    $values[$term] = $termValues;
                }
            }
        }
        return $values;
    }

    /**
     * Separate properties between annotation, bodies and targets.
     *
     * Note: only standard annotation data are managed. Specific properties are
     * kept in the annotation.
     *
     * @todo Use a standard rdf process, with no entities for bodies and targets.
     *
     * @deprecated Will be replaced by a new resource form, on the resource template base.
     *
     * @param array $data
     * @return array
     */
    public function divideMergedValues(array $data)
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        /** @var \Annotate\Mvc\Controller\Plugin\DivideMergedValues $divideMergedValues */
        $divideMergedValues = $plugins->get('divideMergedValues');
        $resourceTemplate = $this->resourceTemplate();
        if ($resourceTemplate) {
            /** @var \Annotate\Mvc\Controller\Plugin\ResourceTemplateAnnotationPartMap $resourceTemplateAnnotationPartMap */
            $resourceTemplateAnnotationPartMap = $plugins->get('resourceTemplateAnnotationPartMap');
            $annotationPartMap = $resourceTemplateAnnotationPartMap($resourceTemplate->id());
        } else {
            $annotationPartMap = [];
        }
        return $divideMergedValues($data, $annotationPartMap);
    }
}
