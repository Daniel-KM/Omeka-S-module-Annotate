<?php declare(strict_types=1);

namespace Annotate\Api\Representation;

use Annotate\Entity\AnnotationTarget;

/**
 * The representation of an Annotation target.
 */
class AnnotationTargetRepresentation extends AbstractValueResourceEntityRepresentation
{
    /**
     * @var AnnotationTarget
     */
    protected $resource;

    public function getResourceJsonLdType()
    {
        return 'oa:hasTarget';
    }

    /**
     * Manage a specificity: some properties are used as oa:selector, that is
     * not a value resource entity currently. Since most of the properties are
     * different between target and selector, they can be easily separated. In
     * fact, only the rdf:type in the default vocabulary examples is common and
     * it is not really used in derivated modules. oa:refinedBy is used too by
     * oa:hasSelector and oa:hasState.
     * @link https://www.w3.org/TR/annotation-vocab/#as-application
     *
     * {@inheritDoc}
     * @see \Annotate\Api\Representation\AbstractAnnotationResourceRepresentation::getJsonLd()
     */
    public function getJsonLd()
    {
        // Get the default values, then recategories them.
        $values = parent::getJsonLd();

        // Standard or recommended properties from https://www.w3.org/TR/annotation-vocab.
        $map = [
            'oa:hasTarget' => [
                'rdf:type' => [
                    'oa:SpecificResource',
                    'dctype:DataSet',
                    'dctype:MovingImage',
                    'dctype:StillImage',
                    'dctype:Sound',
                    'dctype:Text',
                ],
                'oa:hasSource',
                'oa:styleClass',
                'oa:hasScope',
                'oa:hasSelector',
                'oa:hasState',
                'oa:renderedVia',
                'oa:styleClass',
                'schema:accessibilityFeature',
                // The format is related to the selector (the deeper value).
                // 'dcterms:format',
                'as:items',
            ],
            'oa:hasSelector' => [
                'rdf:type' => [
                    'oa:CssSelector',
                    'oa:DataPositionSelector',
                    'oa:FragmentSelector',
                    'oa:RangeSelector',
                    'oa:SvgSelector',
                    'oa:TextPositionSelector',
                    'oa:TextQuoteSelector',
                    'oa:XPathSelector',
                ],
                'rdf:value',
                'oa:start',
                'oa:end',
                'dcterms:conformsTo',
                'oa:hasStartSelector',
                'oa:hasEndSelector',
                'oa:prefix',
                'oa:exact',
                'oa:suffix',
                'oa:refinedBy',
                // The format is related to the selector (the deeper value).
                'dcterms:format',
            ],
            'oa:hasState' => [
                'rdf:type' => ['oa:HttpRequestState', 'oa:TimeState'],
                'oa:cachedSource',
                'oa:sourceDate',
                'oa:sourceDateEnd',
                'oa:sourceDateStart',
                'oa:refinedBy',
            ],
            'oa:renderedVia' => [
                'rdf:type' => ['as:Application'],
                'schema:softwareVersion',
            ],
        ];

        foreach ($values as $term => $valueRepresentations) {
            if ($term === 'rdf:type') {
                // Managed in a second time.
            } elseif (in_array($term, $map['oa:hasTarget'])) {
                continue;
            } elseif (in_array($term, $map['oa:hasSelector'])) {
                $values['oa:hasSelector'][$term] = $valueRepresentations;
                unset($values[$term]);
            } elseif (in_array($term, $map['oa:hasState'])) {
                $values['oa:hasState'][$term] = $valueRepresentations;
                unset($values[$term]);
            } elseif (in_array($term, $map['oa:renderedVia'])) {
                $values['oa:renderedVia'][$term] = $valueRepresentations;
                unset($values[$term]);
            }
        }

        $map = [
            'oa:hasTarget' => [
                'oa:SpecificResource' => 'http://www.w3.org/ns/oa#SpecificResource',
                'dctype:DataSet' => 'http://purl.org/dc/dcmitype/DataSet',
                'dctype:MovingImage' => 'http://purl.org/dc/dcmitype/MovingImage',
                'dctype:StillImage' => 'http://purl.org/dc/dcmitype/StillImage',
                'dctype:Sound' => 'http://purl.org/dc/dcmitype/Sound',
                'dctype:Text' => 'http://purl.org/dc/dcmitype/Text',
                // The class should not be used in the Web Annotation model directly.
                // @link https://www.w3.org/TR/annotation-vocab/#resourceselection
                'oa:ResourceSelection' => 'http://www.w3.org/ns/oa#ResourceSelection',
            ],
            'oa:hasSelector' => [
                'oa:CssSelector' => 'http://www.w3.org/ns/oa#CssSelector',
                'oa:DataPositionSelector' => 'http://www.w3.org/ns/oa#DataPositionSelector',
                'oa:FragmentSelector' => 'http://www.w3.org/ns/oa#FragmentSelector',
                'oa:RangeSelector' => 'http://www.w3.org/ns/oa#RangeSelector',
                'oa:SvgSelector' => 'http://www.w3.org/ns/oa#SvgSelector',
                'oa:TextPositionSelector' => 'http://www.w3.org/ns/oa#TextPositionSelector',
                'oa:TextQuoteSelector' => 'http://www.w3.org/ns/oa#TextQuoteSelector',
                'oa:XPathSelector' => 'http://www.w3.org/ns/oa#XPathSelector',
                // The class should only be used to derive subClasses.
                // @link https://www.w3.org/TR/annotation-vocab/#selector
                'oa:Selector' => 'http://www.w3.org/ns/oa#Selector',
            ],
            'oa:hasState' => [
                'oa:HttpRequestState' => 'http://www.w3.org/ns/oa#HttpRequestState',
                'oa:TimeState' => 'http://www.w3.org/ns/oa#TimeState',
                // The class should only be used in further ontologies to derive subclasses.
                // @link https://www.w3.org/TR/annotation-vocab/#state
                'oa:State' => 'http://www.w3.org/ns/oa#State',
            ],
            'oa:renderedVia' => [
                'as:Application' => 'http://www.w3.org/ns/activitystreams#Application',
            ],
        ];

        // Manage some specific properties.

        $term = 'rdf:type';
        if (array_key_exists($term, $values)) {
            foreach ($values[$term] as $key => $valueRepresentation) {
                $val = $valueRepresentation->uri() ?: (string) $valueRepresentation;
                if (in_array($val, $map['oa:hasTarget']) || isset($map['oa:hasTarget'][$val])) {
                    continue;
                } elseif (in_array($val, $map['oa:hasSelector']) || isset($map['oa:hasSelector'][$val])) {
                    $values['oa:hasSelector'][$term][] = $valueRepresentation;
                    unset($values[$term][$key]);
                } elseif (in_array($val, $map['oa:hasState']) || isset($map['oa:hasState'][$val])) {
                    $values['oa:hasState'][$term][] = $valueRepresentation;
                    unset($values[$term][$key]);
                } elseif (in_array($val, $map['oa:renderedVia']) || isset($map['oa:renderedVia'][$val])) {
                    $values['renderedVia'][$term][] = $valueRepresentation;
                    unset($values[$term][$key]);
                } elseif (substr($val, -8) === 'Selector') {
                    $values['oa:hasSelector'][$term][] = $valueRepresentation;
                    unset($values[$term][$key]);
                } elseif (substr($val, -5) === 'State') {
                    $values['oa:hasState'][$term][] = $valueRepresentation;
                    unset($values[$term][$key]);
                }
            }
            if (empty($values[$term])) {
                unset($values[$term]);
            }
        }

        // Manage the sub value resource entities: if it's a resource, it should
        // be the root (there should be only one in Cartography), and the other
        // properties of this ResourceSelection. So it's the same than the
        // exception managed in parent::getJsonLd() and values().
        $subs = ['oa:hasSelector', 'oa:hasState', 'oa:renderedVia'];
        $subs = array_intersect($subs, array_keys($values));
        $refinedBys = ['oa:hasSelector', 'oa:hasState'];
        foreach ($subs as $sub) {
            $resourceSelection = null;
            foreach ($values[$sub] as $key => $valueRepresentation) {
                // An array is not a resource, but a list of properties.
                if (is_array($valueRepresentation)) {
                    continue;
                }
                if ($valueRepresentation->type() === 'resource') {
                    $resourceSelection = $valueRepresentation;
                    break;
                }
            }
            if (!$resourceSelection) {
                continue;
            }

            // Manage the related sub value resource entities with oa:refinedBy.
            unset($values[$sub][$key]);
            if (in_array($sub, $refinedBys)) {
                $values[$sub] = array_merge(
                    $resourceSelection->jsonSerialize(),
                    ['oa:refinedBy' => $values[$sub]]
                );
            } else {
                $values[$sub] = array_merge(
                    $resourceSelection->jsonSerialize(),
                    $values[$sub]
                );
            }
        }
        return $values;
    }

    public function displayTitle($default = null, $lang = null)
    {
        $title = $this->value('oa:hasSource', [
            'default' => null,
        ]);

        if ($title !== null) {
            return (string) $title;
        }

        // TODO Add a specific title from the metadata of the target (resource)?
        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Annotation target #%d]'), $this->id());
        }

        return $default;
    }

    /**
     * List the resources of the annotation target.
     *
     * The selector is not listed (the media, if any, for an item).
     *
     * @todo Add a filter "annotation_id" to query resources of an annotation.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation;[]
     */
    public function sources()
    {
        // TODO Manage other resource types.
        return array_map(function ($v) {
            return $v->valueResource();
        }, $this->value('oa:hasSource', ['type' => ['resource', 'resource:item', 'resource:media', 'resource:itemset', 'resource:annotation'], 'all' => true]));
    }
}
