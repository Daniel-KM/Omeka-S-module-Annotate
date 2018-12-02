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
 * @todo Make annotation_bodies and annotation_targets unavailable by the api, but keep them as resource entities. Set them hydrator.
 */
class AnnotationAdapter extends AbstractResourceEntityAdapter
{
    protected $annotables = [
        \Omeka\Entity\Item::class,
        \Omeka\Entity\Media::class,
        \Omeka\Entity\ItemSet::class,
        // \Annotate\Entity\Annotation::class,
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

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['annotator'])) {
            if ($query['annotator'] === '0') {
                $query['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:creator',
                    'type' => 'nex',
                ];
                // Manage a null owner.
                $userAlias = $this->createAlias();
                $qb->innerJoin(
                    $this->getEntityClass() . '.owner',
                    $userAlias
                );
                $qb->andWhere($qb->expr()->isNull($userAlias . '.id'));
            } else {
                $query['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:creator',
                    'type' => 'eq',
                    'text' => $query['annotator'],
                ];
            }
        }

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
                $expr = $qb->expr();
                // TODO Make the property id of oa:hasSource static or integrate it to avoid a double query.
                $propertyId = (int) $this->getPropertyByTerm('oa:hasSource')->getId();
                // The resource is attached via the property oa:hasSource of the
                // AnnotationTargets, that are attached to annotations.
                $targetAlias = $this->createAlias();
                $qb->innerJoin(
                    AnnotationTarget::class,
                    $targetAlias,
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    $expr->eq($targetAlias . '.annotation', Annotation::class)
                );
                $valuesAlias = $this->createAlias();
                $qb->innerJoin(
                    $targetAlias . '.values',
                    $valuesAlias,
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    $expr->andX(
                        $expr->eq($valuesAlias . '.property', $propertyId),
                        $expr->eq($valuesAlias . '.type', $this->createNamedParameter($qb, 'resource')),
                        $expr->in(
                            $valuesAlias . '.valueResource',
                            $this->createNamedParameter($qb, $resources)
                        )
                    )
                );
            }
        }

        // TODO Make the limit to a site working for item sets and media too.
        if (!empty($query['site_id'])) {
            try {
                $expr = $qb->expr();
                // See \Omeka\Api\Adapter\ItemAdapter::buildQuery().
                $siteAdapter = $this->getAdapter('sites');
                $site = $siteAdapter->findEntity($query['site_id']);
                $params = $site->getItemPool();
                if (!is_array($params)) {
                    $params = [];
                }
                // Avoid potential infinite recursion.
                unset($params['site_id']);

                // The site pool is a list of items, so a sub-query of target.
                // The sub-query is the same than above for resource_id, but
                // it's a sub-query.
                // TODO Manage annotation of item sets.

                // TODO Add a sub-event on the sub query to limit annotations to the site? There is none for items (but it's the same).
                $subAdapter = $this->getAdapter('items');
                $subEntityClass = \Omeka\Entity\Item::class;
                $subQb = $this->getEntityManager()
                    ->createQueryBuilder()
                    ->select($subEntityClass . '.id')
                    ->from($subEntityClass, $subEntityClass);
                $subAdapter
                    ->buildQuery($subQb, $params);
                $subQb->groupBy($subEntityClass . '.id');

                // The subquery cannot manage the parameters, since there are
                // two independant queries, but they use the same aliases. Since
                // number of ids may be great, it will be possible to create a
                // temporary table. Currently, a simple string replacement of
                // aliases is used.
                // TODO Fix Omeka core for aliases in sub queries.
                $subDql = str_replace('omeka_', 'akemo_',  $subQb->getDQL());
                /** @var \Doctrine\ORM\Query\Parameter $parameter */
                $subParams = $subQb->getParameters();
                foreach ($subParams as $parameter) {
                    $qb->setParameter(
                        str_replace('omeka_', 'akemo_', $parameter->getName()),
                        $parameter->getValue(),
                        $parameter->getType()
                    );
                }

                $propertyId = (int) $this->getPropertyByTerm('oa:hasSource')->getId();
                // The resource is attached via the property oa:hasSource of the
                // AnnotationTargets, that are attached to annotations.
                $targetAlias = $this->createAlias();
                $qb->innerJoin(
                    AnnotationTarget::class,
                    $targetAlias,
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    $expr->eq($targetAlias . '.annotation', Annotation::class)
                );
                $valuesAlias = $this->createAlias();
                $qb->innerJoin(
                    $targetAlias . '.values',
                    $valuesAlias,
                    \Doctrine\ORM\Query\Expr\Join::WITH,
                    $expr->andX(
                        $expr->eq($valuesAlias . '.property', $propertyId),
                        $expr->eq($valuesAlias . '.type', $this->createNamedParameter($qb, 'resource')),
                        $expr->in(
                            $valuesAlias . '.valueResource',
                            $subDql
                        )
                    )
                );

            } catch (Exception\NotFoundException $e) {
            }
        }

        // TODO Build queries to find annotations by query on targets and bodies here?
        // TODO Query has_body / linked resource id.
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $this->normalizeRequest($request, $entity, $errorStore);

        // Skip the bodies and the targets that are hydrated separately below.
        // It avoids the value hydrator to try to hydrate values from them: they
        // are not properties.
        $childEntities = [
            'oa:hasBody' => 'annotation_bodies',
            'oa:hasTarget' => 'annotation_targets',
        ];
        $children = [];
        $data = $request->getContent();
        foreach ($childEntities as $jsonName => $resourceName) {
            $children[$jsonName] = $request->getValue($jsonName, []);
            unset($data[$jsonName]);
        }
        $request->setContent($data);
        parent::hydrate($request, $entity, $errorStore);
        // Reset the bodies and the targets that were skipped above.
        foreach ($childEntities as $jsonName => $resourceName) {
            $data[$jsonName] = $children[$jsonName];
        }
        $request->setContent($data);

        // $isUpdate = Request::UPDATE === $request->getOperation();
        // $isPartial = $isUpdate && $request->getOption('isPartial');
        // $append = $isPartial && 'append' === $request->getOption('collectionAction');
        // $remove = $isPartial && 'remove' === $request->getOption('collectionAction');

        // TODO Remove the checks of the existing id, since it's a simple hydrator now.
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

        if (array_key_exists('oa:hasBody', $data)
            && !is_array($data['oa:hasBody'])
        ) {
            $errorStore->addError('oa:hasBody', 'Body must be an array'); // @translate
        }

        if (array_key_exists('oa:hasTarget', $data)
            && !is_array($data['oa:hasTarget'])
        ) {
            $errorStore->addError('oa:hasTarget', 'Target must be an array'); // @translate
        }
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
        if (isset($data['oa:hasTarget'])
            || isset($data['oa:hasBody'])
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
            $data['oa:hasTarget'][0]['oa:hasSource'][] = $value;
            unset($data['oa:hasSource']);
        }

        if (isset($data['dcterms:format'])) {
            $value = reset($data['dcterms:format']);
            $value = $value['@value'];
            switch ($value) {
                case 'application/wkt':
                    $data['oa:hasTarget'][0]['rdf:type'][] = [
                        'property_id' => $this->propertyId('rdf:type'),
                        'type' => 'customvocab:' . $this->customVocabId('Annotation Target rdf:type'),
                        '@value' => 'oa:Selector',
                    ];
                    $data['oa:hasTarget'][0]['dcterms:format'] = $data['dcterms:format'];
                    $data['oa:hasTarget'][0]['dcterms:format'][0]['@language'] = null;
                    unset($data['dcterms:format']);
                    $mainValueIsTarget = true;
                    break;
            }
        }

        if (isset($data['oa:styleClass'])) {
            $data['oa:hasTarget'][0]['oa:styleClass'] = $data['oa:styleClass'];
            unset($data['oa:styleClass']);
        }

        if ($mainValueIsTarget) {
            $mainValue = reset($data['rdf:value']);
            $mainValue = $mainValue['@value'];
            $data['oa:hasTarget'][0]['rdf:value'] = $data['rdf:value'];
            $data['oa:hasTarget'][0]['rdf:value'][0]['@language'] = null;
            unset($data['rdf:value']);
        }

        // Bodies (single).

        if (isset($data['oa:hasPurpose'])) {
            $data['oa:hasBody'][0]['oa:hasPurpose'] = $data['oa:hasPurpose'];
            unset($data['oa:hasPurpose']);
        }

        if (!$mainValueIsTarget && isset($data['rdf:value'])) {
            $mainValue = reset($data['rdf:value']);
            $mainValue = $mainValue['@value'];
            $data['oa:hasBody'][0]['rdf:value'] = $data['rdf:value'];
            unset($data['rdf:value']);

            $format = $this->isHtml($data['oa:hasBody'][0]['rdf:value']) ? 'text/html' : null;
            if ($format) {
                $data['oa:hasBody'][0]['dcterms:format'][] = [
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
}
