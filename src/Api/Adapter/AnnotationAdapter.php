<?php
namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationTarget;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

/**
 * @todo Make Annotation more independant from Omeka tools, and allow to import rdf annotation directly (avoid any normalization, etc.). Use easyrdf or create Selector class?
 */
class AnnotationAdapter extends AbstractResourceEntityAdapter
{
    protected $annotables = [
        \Omeka\Entity\Item::class,
        \Omeka\Entity\Media::class,
        \Omeka\Entity\ItemSet::class,
    ];

    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'annotations';
    }

    public function getRepresentationClass()
    {
        return \Annotate\Api\Representation\AnnotationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Annotate\Entity\Annotation::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $this->normalizeRequest($request, $entity, $errorStore);

        parent::hydrate($request, $entity, $errorStore);

        // $isUpdate = Request::UPDATE === $request->getOperation();
        // $isPartial = $isUpdate && $request->getOption('isPartial');
        // $append = $isPartial && 'append' === $request->getOption('collectionAction');
        // $remove = $isPartial && 'remove' === $request->getOption('collectionAction');

        $childEntities = [
            'o-module-annotate:body' => 'annotation_bodies',
            'o-module-annotate:target' => 'annotation_targets',
        ];
        foreach ($childEntities as $jsonName => $resourceName) {
            if ($this->shouldHydrate($request, $jsonName)) {
                $childrenData = $request->getValue($jsonName, []);
                $adapter = $this->getAdapter($resourceName);
                $class = $adapter->getEntityClass();
                $retainChildren = [];
                foreach ($childrenData as $childData) {
                    $subErrorStore = new ErrorStore;
                    // Keep an existing child.
                    if (is_object($childData)) {
                        $child = $this->getAdapter($resourceName)
                            ->findEntity($childData);
                        $retainChildren[] = $child;
                    } elseif (isset($childData['o:id'])) {
                        $child = $adapter->findEntity($childData['o:id']);
                        if (isset($childData['o:is_public'])) {
                            $child->setIsPublic($childData['o:is_public']);
                        }
                        $retainChildren[] = $child;
                    }
                    // Create a new child.
                    else {
                        $child = new $class;
                        $child->setAnnotation($entity);
                        $subrequest = new Request(Request::CREATE, $resourceName);
                        $subrequest->setContent($childData);
                        try {
                            $adapter->hydrateEntity($subrequest, $child, $subErrorStore);
                        } catch (Exception\ValidationException $e) {
                            $errorStore->mergeErrors($e->getErrorStore(), $jsonName);
                        }
                        switch ($resourceName) {
                            case 'annotation_bodies':
                                $entity->getBodies()->add($child);
                                break;
                            case 'annotation_targets':
                                $entity->getTargets()->add($child);
                                break;
                        }
                        $retainChildren[] = $child;
                    }
                }
                // Remove child not included in request.
                switch ($resourceName) {
                    case 'annotation_bodies':
                        $children = $entity->getBodies();
                        break;
                    case 'annotation_targets':
                        $children = $entity->getTargets();
                        break;
                }
                foreach ($children as $child) {
                    if (!in_array($child, $retainChildren, true)) {
                        $children->removeElement($child);
                    }
                }
            }
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (array_key_exists('o-module-annotate:body', $data)
            && !is_array($data['o-module-annotate:body'])
        ) {
            $errorStore->addError('o-module-annotate:body', 'Body must be an array'); // @translate
        }

        if (array_key_exists('o-module-annotate:target', $data)
            && !is_array($data['o-module-annotate:target'])
        ) {
            $errorStore->addError('o-module-annotate:target', 'Targets must be an array'); // @translate
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        parent::buildQuery($qb, $query);

        if (isset($query['id'])) {
            $qb->andWhere($qb->expr()->eq('Annotate\Entity\Annotation.id', $query['id']));
        }

        if (isset($query['resource_id'])) {
            $resources = $query['resource_id'];
            if (!is_array($resources)) {
                $resources = [$resources];
            }
            $resources = array_filter($resources, 'is_numeric');

            if ($resources) {
                // TODO Make the property id of oa:hasSource static or integrate it to avoid a double query.
                $propertyId = (int) $this->getPropertyByTerm('oa:hasSource')->getId();
                // The resource is attached via the property oa:hasSource of the
                // AnnotationTargets, that are attached to annotations.
                $targetAlias = $this->createAlias();
                $qb->innerJoin(
                    AnnotationTarget::class,
                    $targetAlias,
                    'WITH',
                    $qb->expr()->eq($targetAlias . '.annotation', Annotation::class)
                );
                $valuesAlias = $this->createAlias();
                $qb->innerJoin(
                    $targetAlias . '.values',
                    $valuesAlias,
                    'WITH',
                    $qb->expr()->andX(
                        $qb->expr()->eq($valuesAlias . '.property', $propertyId),
                        $qb->expr()->eq($valuesAlias . '.type', $this->createNamedParameter($qb, 'resource')),
                        $qb->expr()->in($valuesAlias . '.valueResource', $this->createNamedParameter($qb, $resources))
                    )
                );
            }
        }

        // TODO Build queries to find annotations by query on targets and bodies here?
    }

    /**
     * Normalize an annotation request (move properties in bodies and targets).
     *
     * This process is required as long as the standard Omeka resource methods
     * are used. Anyway, this is not a full implementation, but a quick tool for
     * common tasks (cartography, folksonomy, commenting, rating, quiz…).
     * Some heuristic is needed for the value "rdf:value", according to
     * motivation/purpose, type and format.
     *
     * @param Request $request
     * @param EntityInterface $entity
     * @param ErrorStore $errorStore
     */
    protected function normalizeRequest(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();

        // TODO Remove any language, since data model require to use a property.

        // Check if the data are already normalized.
        if (isset($data['o-module-annotate:body'])
            || isset($data['o-module-annotate:target'])
            // For direct annotation (directly from rdf or standard annotation).
            || isset($data['oa:hasTarget'])
            || isset($data['oa:hasBody'])
            || isset($data['target'])
            || isset($data['body'])
        ) {
            return;
        }

        // TODO Manage the normalization of an annotation update.
        if (Request::UPDATE === $request->getOperation()) {
            return;
        }

        $mainValue = null;
        $mainValueIsTarget = false;

        //  Targets (single).

        if (isset($data['oa:hasSource'])) {
            // The source should be a resource.
            $value = reset($data['oa:hasSource']);
            if ($value['type'] !== 'resource') {
                // TODO Check if the resource id exists. If not, keep value.
                // $resource = $api->searchOne('resources', ['id' => $value['@value']])->getContent();
                // if ($resource) {
                    $value['type'] = 'resource';
                    $value['value_resource_id'] = $value['@value'];
                    unset($value['@language']);
                    unset($value['@value']);
                // }
            }
            $data['o-module-annotate:target'][0]['oa:hasSource'][] = $value;
            unset($data['oa:hasSource']);
        }

        if (isset($data['dcterms:format'])) {
            $value = reset($data['dcterms:format']);
            $value = $value['@value'];
            switch ($value) {
                case 'application/wkt':
                    $data['o-module-annotate:target'][0]['rdf:type'][] = [
                        'property_id' => $this->propertyId('rdf:type'),
                        'type' => 'customvocab:' . $this->customVocabId('Annotation Target rdf:type'),
                        '@value' => 'oa:Selector',
                    ];
                    $data['o-module-annotate:target'][0]['dcterms:format'] = $data['dcterms:format'];
                    $data['o-module-annotate:target'][0]['dcterms:format'][0]['@language'] = null;
                    unset($data['dcterms:format']);
                    $mainValueIsTarget = true;
                    break;
            }
        }

        if (isset($data['oa:styleClass'])) {
            $data['o-module-annotate:target'][0]['oa:styleClass'] = $data['oa:styleClass'];
            unset($data['oa:styleClass']);
        }

        if ($mainValueIsTarget) {
            $mainValue = reset($data['rdf:value']);
            $mainValue = $mainValue['@value'];
            $data['o-module-annotate:target'][0]['rdf:value'] = $data['rdf:value'];
            $data['o-module-annotate:target'][0]['rdf:value'][0]['@language'] = null;
            unset($data['rdf:value']);
        }

        // Bodies (single).

        if (isset($data['oa:hasPurpose'])) {
            $data['o-module-annotate:body'][0]['oa:hasPurpose'] = $data['oa:hasPurpose'];
            unset($data['oa:hasPurpose']);
        }

        if (!$mainValueIsTarget && isset($data['rdf:value'])) {
            $mainValue = reset($data['rdf:value']);
            $mainValue = $mainValue['@value'];
            $data['o-module-annotate:body'][0]['rdf:value'] = $data['rdf:value'];
            unset($data['rdf:value']);

            $format = $this->isHtml($data['o-module-annotate:body'][0]['rdf:value']) ? 'text/html' : null;
            if ($format) {
                $data['o-module-annotate:body'][0]['dcterms:format'][] = [
                    'property_id' => $this->propertyId('dcterms:format'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation Body dcterms:format'),
                    '@value' => $format,
                ];
            }
        }

        $request->setContent($data);
    }

    protected function propertyId($term)
    {
        $api = $this->getServiceLocator()->get('ControllerPluginManager')->get('api');
        $result = $api->searchOne('properties', ['term' => $term])->getContent();
        return $result ? $result->id() : null;
    }

    protected function customVocabId($label)
    {
        $api = $this->getServiceLocator()->get('ControllerPluginManager')->get('api');
        $result = $api->read('custom_vocabs', ['label' => $label])->getContent();
        return $result ? $result->id() : null;
    }

    /**
     * Detect if a string is html or not.
     *
     * @see \Annotate\Api\Representation\AnnotationRepresentation::isHtml()
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
     * @see \Annotate\Api\Representation\AnnotationRepresentation::determineMediaType()
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
        // TODO Json is a format, not a mime-type: may be "application/geo+json.
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
            'CIRCLEMARKER',
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
            return 'application/wkt';
        }
    }
}
