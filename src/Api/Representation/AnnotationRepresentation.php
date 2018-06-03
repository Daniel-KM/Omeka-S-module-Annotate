<?php
namespace Annotate\Api\Representation;

use Annotate\Entity\Annotation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class AnnotationRepresentation extends AbstractResourceEntityRepresentation
{
    /**
     * @var Annotation
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
        $bodies = [];
        foreach ($this->bodies() as $bodyRepresentation) {
            $bodies[] = $bodyRepresentation->getReference();
        }
        $targets = [];
        foreach ($this->targets() as $targetRepresentation) {
            $targets[] = $targetRepresentation->getReference();
        }
        return [
            'oa:hasBody' => $bodies,
            'oa:hasTarget' => $targets,
        ];
    }

    public function jsonSerialize()
    {
        $jsonLd = parent::jsonSerialize();
        $jsonLd['@context'] = [
            $jsonLd['@context'],
            ['anno' => 'http://www.w3.org/ns/anno.jsonld'],
        ];
        return $jsonLd;
    }

    /**
     * Get the bodies assigned to this annotation.
     *
     * @return AnnotationBodyRepresentation[]
     */
    public function bodies()
    {
        $bodies = [];
        $bodyAdapter = $this->getAdapter('annotation_bodies');
        foreach ($this->resource->getBodies() as $bodyEntity) {
            $bodies[] =
                $bodyAdapter->getRepresentation($bodyEntity);
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
        $targetAdapter = $this->getAdapter('annotation_targets');
        foreach ($this->resource->getTargets() as $targetEntity) {
            $targets[] =
                $targetAdapter->getRepresentation($targetEntity);
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
     * Return the first target if one exists.
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
                'controller' => 'AnnotationController',
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function displayTitle($default = null)
    {
        $title = $this->value('dcterms:title', [
            'default' => null,
        ]);

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
     * Merge values of the annotation, bodies and the targets.
     *
     *  Most of the time, there is only one body and one target, and each entity
     *  has its specific properties according to the specification. So the merge
     *  create a simpler list of values.
     *
     * @uses AbstractResourceEntityRepresentation::values()
     *
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
     * @param array $data
     * @return array
     */
    public function divideMergedValues(array $data)
    {
        $api = $this->getViewHelper('api');

        // Standard or recommended properties from https://www.w3.org/TR/annotation-vocab.
        $map = [
            'annotation' => [
                'oa:annotationService',
                // TODO Convert into a textual body rdf value (in adapter).
                'oa:bodyValue',
                'oa:canonical',
                'oa:hasBody',
                'oa:hasTarget',
                'oa:motivatedBy',
                'oa:styledBy',
                'oa:via',
                // Other ontologies.
                'as:generator',
                'dcterms:created',
                'dcterms:creator',
                'dcterms:issued',
                'dcterms:modified',
                // TODO May be a body property according to the official example.
                'dcterms:rights',
                'foaf:homepage',
                'foaf:mbox',
                'foaf:mbox_sha1sum',
                'foaf:name',
                'foaf:nick',
                // May be a body or target property (else oa:Annotation).
                'rdf:type',
                'schema:audience',
            ],
            'body' => [
                'oa:hasPurpose',
                'oa:processingLanguage',
                'oa:textDirection',
                // Other ontologies.
                'dcterms:language',
                // May be a target property with non textual text/html selectors
                // (json, css, xpath, svg, etc).
                'rdf:value',
            ],
            'target' => [
                'oa:hasSource',
                'oa:hasSelector',
                'dcterms:format',
                // Others oa.
                'oa:cachedSource',
                'oa:end',
                'oa:exact',
                'oa:hasEndSelector',
                'oa:hasScope',
                // In rare cases, can be a body property. Not managed currently.
                // TODO Manage hasSelector as body property.
                'oa:hasSelector',
                'oa:hasSource',
                'oa:hasStartSelector',
                'oa:hasState',
                'oa:prefix',
                'oa:refinedBy',
                'oa:renderedVia',
                'oa:sourceDate',
                'oa:sourceDateEnd',
                'oa:sourceDateStart',
                'oa:start',
                'oa:styleClass',
                'oa:suffix',
                // Other ontologies.
                'as:first',
                'as:items',
                'as:last',
                'as:next',
                'as:partOf',
                'as:prev',
                'as:startIndex',
                'as:totalItems',
                // May be a body property for textual body not plain text (html).
                'dc:format',
                'dcterms:conformsTo',
                'rdfs:label',
                'schema:accessibilityFeature',
            ],
        ];

        // Step 1.
        // Manage the exception for rdf:type.
        /** @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab */
        $customVocab = $api
            ->read('custom_vocabs', ['label' => 'Annotation Target rdf:type'])->getContent();

        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $rdfTypeData = empty($data['rdf:type']) ? [] : $data['rdf:type'];
        unset($data['rdf:type']);
        $rdfTypes = [
            'annotation' => [],
            'body' => [],
            'target' => [],
        ];
        foreach ($rdfTypeData as $key => $rdfTypeArray) {
            // TODO Manage oa:Choice and ordered collection with rdf type.
            $rdfType = $rdfTypeArray['@value'];
            if ($rdfType === 'oa:TextualBody') {
                $rdfTypes['body'][] = ['rdf:type' => [$rdfTypeArray]];
            } elseif (in_array($rdfType, $terms)) {
                $rdfTypes['target'][] = ['rdf:type' => [$rdfTypeArray]];
            } else {
                $rdfTypes['annotation'][] = $rdfTypeArray;
            }
        }
        if ($rdfTypes['annotation']) {
            $data['rdf:type'] = $rdfTypes['annotation'];
        } else {
            unset($data['rdf:type']);
        }
        $data['o-module-annotate:body'] = $rdfTypes['body'];
        $data['o-module-annotate:target'] = $rdfTypes['target'];

        // Step 2.
        // Manage standard and recommended annotation properties.
        foreach ($data as $term => $values) {
            if (in_array($term, $map['annotation'])) {
                continue;
            } elseif (in_array($term, $map['body'])) {
                $data['o-module-annotate:body'][0][$term] = $values;
                unset($data[$term]);
            } elseif (in_array($term, $map['target'])) {
                $data['o-module-annotate:target'][0][$term] = $values;
                unset($data[$term]);
            }
        }

        // Step 3.
        // Manage the exception for rdf:value, that can be in body or target.
        // Only some media type are managed, exclusively.
        // TODO Check when there are multiple bodies and targets.
        if (!empty($data['o-module-annotate:body'][0]['rdf:value'])) {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => 'Annotation Target dcterms:format'])->getContent();
            $targetFormats = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
            $property = $api->searchOne('properties', [
                'term' => 'dcterms:format',
            ], [], ['responseContent' => 'reference'])->getContent();
            foreach ($data['o-module-annotate:body'] as $key => $body) {
                foreach ($body['rdf:value'] as $keyB => $valueB) {
                    $mediaType = $this->determineMediaType($valueB['@value']);
                    // Note: the process cannot distinct text/html when xml.
                    if (in_array($mediaType, $targetFormats)) {
                        // TODO Set the media type to the right target (only one currently).
                        $data['o-module-annotate:target'][0]['rdf:value'][] = $valueB;
                        // Save the media type (forced).
                        $data['o-module-annotate:target'][0]['dcterms:format'][0] = [
                            '@value' => $mediaType,
                            'property_id' => $property->id(),
                            'type' => 'customvocab:' . $customVocab->id(),
                        ];
                        unset($data['o-module-annotate:body'][$key]['rdf:value'][$keyB]);
                    }
                    // Else, this is a plain text or an html snippet (TextualBody).
                }
            }
        }

        // TODO Fix the resource class of the body.

        return $data;
    }

    /**
     * Detect if a string is html or not.
     *
     * @see \Annotate\Controller\Admin\AnnotationController::isHtml()
     *
     * @param string $string
     * @return bool
     */
    protected function isHtml($string)
    {
        return $string != strip_tags($string);
    }

    /**
     * Determine the media type of a string.
     *
     * Only annotation target media-types are managed.
     *
     * @todo Simplify and improve the determination of the media-type (via stream).
     * @see \Annotate\Controller\Admin\AnnotationController::determineMediaType()
     *
     * @param string $string
     * @return string|null
     */
    protected function determineMediaType($string)
    {
        $string = trim($string);
        if (strlen($string) == 0) {
            return;
        }
        if ($string === 'null' || (json_decode($string) !== null)) {
            return 'application/json';
        }
        if (strpos($string, '<svg ') === 0) {
            return 'image/svg+xml';
        }
        if (strpos($string, '<!DOCTYPE html>') === 0) {
            return 'text/html';
        }
        if (strpos($string, '<?xml ') === 0) {
            $pos = strpos($string, '<', 1);
            $str = trim(substr($string, $pos));
            if (strpos($str, '<svg ') === 0) {
                return 'image/svg+xml';
            }
            if (strpos($str, '<html>') === 0 || strpos($str, '<html ') === 0) {
                return 'text/html';
            }

            // There may be a doctype.
            $pos = strpos($str, '<', 1);
            $str = trim(substr($str, $pos));
            if (strpos($str, '<svg ') === 0) {
                return 'image/svg+xml';
            }
            if (strpos($str, '<html>') === 0 || strpos($str, '<html ') === 0) {
                return 'text/html';
            }

            return 'application/xml';
        }
        // TODO Find a better way to check if a string is a wkt.
        $wktTags = [
            'GEOMETRY',
            'POINT',
            'LINESTRING',
            'POLYGON',
            'MULTIPOINT',
            'MULTILINESTRING',
            'MULTIPOLYGON',
            'GEOMETRYCOLLECTION',
            'CIRCULARSTRING',
            'COMPOUNDCURVE',
            'CURVEPOLYGON',
            'MULTICURVE',
            'MULTISURFACE',
            'CURVE',
            'SURFACE',
            'POLYHEDRALSURFACE',
            'TIN',
            'TRIANGLE',
            'CIRCLE',
            'GEODESICSTRING',
            'ELLIPTICALCURVE',
            'NURBSCURVE',
            'CLOTHOID',
            'SPIRALCURVE',
            'COMPOUNDSURFACE',
            'BREPSOLID',
            'AFFINEPLACEMENT',
        ];
        // Get first word to check wkt.
        $firstWord = strtoupper(strtok($string, " (\n\r"));
        if (strpos($string, '(') && in_array($firstWord, $wktTags)) {
            return 'text/wkt';
        }
    }
}
