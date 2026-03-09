<?php declare(strict_types=1);

namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationValue;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;
class AnnotationAdapter extends AbstractResourceEntityAdapter
{
    use QueryDateTimeTrait;
    use QueryPropertiesTrait;

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

    protected $scalarFields = [
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

    /**
     * The search is done on annotation bodies and targets too.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     */
    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        // Added before parent buildQuery because a property is added.
        // TODO Currently, the annotator is not set, anyway.
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
                    'omeka_root.owner',
                    $userAlias
                );
                $qb->andWhere($expr->isNull($userAlias . '.id'));
            } elseif ($query['annotator'] !== '' && $query['annotator'] !== []) {
                if (is_array($query['annotator']) && count($query['annotator']) === 1) {
                    $query['annotator'] = reset($query['annotator']);
                }
                $query['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:creator',
                    'type' => is_array($query['annotator']) ? 'list' : 'eq',
                    'text' => $query['annotator'],
                ];
            }
        }

        if (isset($query['owner_id']) && $query['owner_id'] !== '' && $query['owner_id'] !== []) {
            if (is_array($query['owner_id']) && count($query['owner_id']) === 1) {
                $query['owner_id'] = reset($query['owner_id']);
            }
            $userAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.owner',
                $userAlias
            );
            if (is_array($query['owner_id'])) {
                $qb->andWhere($expr->in(
                    "$userAlias.id",
                    $this->createNamedParameter($qb, $query['owner_id']))
                );
            } else {
                $qb->andWhere($expr->eq(
                    "$userAlias.id",
                    $this->createNamedParameter($qb, $query['owner_id']))
                );
            }
        }

        // Added before parent buildQuery because a property is added.
        // FIXME: oa:hasSource is used to get the target, but in very rare cases, it can be attached to the body. Require to search a property on a subpart.
        if (isset($query['resource_id']) && $query['resource_id'] !== '' && $query['resource_id'] !== []) {
            if (is_array($query['resource_id']) && count($query['resource_id']) === 1) {
                $query['resource_id'] = reset($query['resource_id']);
            }
            $query['property'][] = [
                'joiner' => 'and',
                'property' => 'oa:hasSource',
                'type' => 'res',
                'text' => $query['resource_id'],
            ];
        }

        // Added before parent buildQuery because a property is added.
        if (isset($query['motivation']) && $query['motivation'] !== '' && $query['motivation'] !== []) {
            if (is_array($query['motivation']) && count($query['motivation']) === 1) {
                $query['motivation'] = reset($query['motivation']);
            }
            $query['property'][] = [
                'joiner' => 'and',
                'property' => 'oa:motivatedBy',
                'type' => is_array($query['motivation']) ? 'list' : 'eq',
                'text' => $query['motivation'],
            ];
        }

        parent::buildQuery($qb, $query);

        // TODO Check the query of annotations by site.
        // TODO Make the limit to a site working for item sets and media too.
        if (!empty($query['site_id'])) {
            try {
                // FIXME Upgrade for item sites.
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
                $subEntityAlias = $this->createAlias();
                $subQb = $this->getEntityManager()
                    ->createQueryBuilder()
                    ->select($subEntityAlias . '.id')
                    ->from($subEntityClass, $subEntityAlias);
                $subAdapter
                    ->buildQuery($subQb, $params);
                $subQb
                    ->groupBy($subEntityAlias . '.id');

                /**
                 * See old version of this module Reference (#0c572cd).
                 * @see \Annotate\Api\Adapter\AnnotationAdapter::buildQuery()
                 * @see \ApiInfo\Module::limitMediaQuery()
                 * @see \Reference\Mvc\Controller\Plugin\References::searchQuery()
                 */

                // The subquery cannot manage the parameters, since there are
                // two independant queries, but they use the same aliases. Since
                // number of ids may be great, it will be possible to create a
                // temporary table. Currently, a simple string replacement of
                // aliases is used.
                // TODO Fix Omeka core for aliases in sub queries.
                // $subDql = strtr($subQb->getDQL(), [':omeka_' => ':akemo_']);
                $subDql = strtr($subQb->getDQL(), ['omeka_' => 'akemo_']);
                /** @var \Doctrine\ORM\Query\Parameter $parameter */
                $subParams = $subQb->getParameters();
                foreach ($subParams as $parameter) {
                    $qb
                        ->setParameter(
                            strtr($parameter->getName(), ['omeka_' => 'akemo_']),
                            $parameter->getValue(),
                            $parameter->getType()
                        );
                }

                $propertyId = (int) $this->getPropertyByTerm('oa:hasSource')->getId();
                // The resource is attached via the property oa:hasSource in the
                // annotation's own values.
                $valuesAlias = $this->createAlias();
                $qb->innerJoin(
                    'omeka_root.values',
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

        $this->buildResourceClassQuery($qb, $query);
        $this->searchDateTime($qb, $query);
    }

    /**
     * Search a resource class.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildResourceClassQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['resource_class'])) {
            $expr = $qb->expr();

            if (is_numeric($query['resource_class'])) {
                $resourceClassIds = array_filter([(int) $query['resource_class']]);
            } else {
                /** @var \Common\Stdlib\EasyMeta $easyMeta */
                $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
                $resourceClassIds = $easyMeta->resourceClassIds($query['resource_class']);
            }

            $resourceClassAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.resourceClass',
                $resourceClassAlias
            );
            if (count($resourceClassIds) <= 1) {
                $qb->andWhere(
                    $expr->eq(
                        $resourceClassAlias . '.id',
                        $this->createNamedParameter($qb, reset($resourceClassIds) ?: 0)
                    )
                );
            } else {
                $qb->andWhere(
                    $expr->in(
                        $resourceClassAlias . '.id',
                        $this->createNamedParameter($qb, $resourceClassIds)
                    )
                );
            }
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        // Notes about format of data.

        // Since 3.3.3.6, the form or source must send well-formed annotations:
        // no move, only basic default completion (property id, type, etc.).

        // Before 3.3.3.6, the annotation request was normalized and some
        // properties where moved from annotation into bodies and targets:
        // - oa:hasSource[] into oa:hasTarget[][oa:hasSource][]
        // - dcterms:format[] into oa:hasTarget[][dcterms:format][]
        // - oa:hasPurpose[] into oa:hasBody[]oa:hasPurpose[]
        // - oa:styleClass for cartography
        // - rdf:value for multiple target or one body
        //
        // The process allowed to use standard Omeka resource methods and
        // default form, that is not multi-level. Some heuristic was needed for
        // the value "rdf:value", according to motivation/purpose, type and
        // format.
        //
        // It managed too multiple targets and body. In most of the cases, an
        // annotation has only one target and a target has only one source and
        // an annotation with multiple targets has not an intuitive meaning: it
        // means that the bodies apply independantly on each target.
        // @see https://www.w3.org/TR/annotation-model/#sets-of-bodies-and-targets

        // Since 3.4.12, a field in table annotation_value indicate attachment.

        $this->completeRequest($request, $entity, $errorStore);

        $data = $request->getContent();

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        // Build a mapping of [term][index] => [field, ordinal] so we can create
        // AnnotationValue rows after parent hydration.
        $partMapping = [];

        // Track annotation-level properties.
        foreach ($data as $term => $values) {
            if (!is_array($values) || strpos($term, 'o:') === 0) {
                continue;
            }
            foreach ($values as $i => $v) {
                $partMapping[$term][] = [
                    'field' => 'annotation',
                    'ordinal' => 0,
                ];
            }
        }

        // Flatten body properties into top-level.
        $bodies = $data['oa:hasBody'] ?? [];
        unset($data['oa:hasBody']);
        foreach ($bodies as $ordinal => $body) {
            foreach ($body as $term => $values) {
                if (!is_array($values)) {
                    continue;
                }
                if (!isset($data[$term])) {
                    $data[$term] = [];
                }
                foreach ($values as $v) {
                    $data[$term][] = $v;
                    $partMapping[$term][] = [
                        'field' => 'body',
                        'ordinal' => (int) $ordinal + 1,
                    ];
                }
            }
        }

        // Flatten target properties into top-level.
        $targets = $data['oa:hasTarget'] ?? [];
        unset($data['oa:hasTarget']);
        foreach ($targets as $ordinal => $target) {
            foreach ($target as $term => $values) {
                if (!is_array($values)) {
                    continue;
                }
                if (!isset($data[$term])) {
                    $data[$term] = [];
                }
                foreach ($values as $v) {
                    $data[$term][] = $v;
                    $partMapping[$term][] = [
                        'field' => 'target',
                        'ordinal' => (int) $ordinal + 1,
                    ];
                }
            }
        }

        $request->setContent($data);

        parent::hydrate($request, $entity, $errorStore);

        $this->syncAnnotationValues($entity, $partMapping, $easyMeta);
    }

    /**
     * Sync annotation_value rows after parent hydration.
     */
    protected function syncAnnotationValues(
        EntityInterface $entity,
        array $partMapping,
        $easyMeta
    ): void {
        $em = $this->getEntityManager();

        // Remove existing AnnotationValue rows on update.
        $existing = $em->getRepository(AnnotationValue::class)
            ->findBy(['annotation' => $entity]);
        foreach ($existing as $av) {
            $em->remove($av);
        }
        $em->flush();

        // Create new AnnotationValue entries by matching values to partMapping
        // by term and index order.
        $counters = [];
        foreach ($entity->getValues() as $value) {
            $propertyId = $value->getProperty()->getId();
            $term = $easyMeta->propertyTerm($propertyId);

            $idx = $counters[$term] ?? 0;
            $counters[$term] = $idx + 1;

            $info = $partMapping[$term][$idx]
                ?? ['field' => 'annotation', 'ordinal' => 0];

            $av = new AnnotationValue();
            $av->setAnnotation($entity);
            $av->setValue($value);
            $av->setField($info['field']);
            $av->setOrdinal($info['ordinal']);
            $em->persist($av);
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore): void
    {
        $data = $request->getContent();

        if (array_key_exists('oa:hasBody', $data)
            && !is_array($data['oa:hasBody'])
        ) {
            $errorStore->addError('oa:hasBody', 'Annotation body must be an array.'); // @translate
        }

        if (empty($data['oa:hasTarget'])) {
            $errorStore->addError('oa:hasTarget', 'There must be one annotation target at least.'); // @translate
        } elseif (!is_array($data['oa:hasTarget'])) {
            $errorStore->addError('oa:hasTarget', 'Annotation target must be an array.'); // @translate
        }
    }

    /**
     * @todo Support reverse subject values for annotation.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::getSubjectValuesSimple()
     */
    public function getSubjectValuesSimple(Resource $resource, $propertyId = null, $resourceType = null, $siteId = null)
    {
        return [];
    }

    /**
     * To simplify sub-modules or third-party clients, the annotations can be
     * created partial, without property ids, language and type, so only value,
     * uri or value_resource_id can be passed. The main structure with a
     * oa:motivatedBy, a oa:hasBody and a oa:hasTarget should be kept
     * nevertheless.
     *
     * The process does not check consistency.
     */
    protected function completeRequest(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $data = $request->getContent();

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        $resourceTemplateId = $easyMeta->resourceTemplateId('Annotation');
        $resourceClassId = $easyMeta->resourceClassId('oa:Annotation');
        $data['o:resource_template'] = $resourceTemplateId ? ['o:id' => $resourceTemplateId] : null;
        $data['o:resource_class'] = $resourceClassId ? ['o:id' => $resourceClassId] : null;

        $dataTypeCustomVocabMotivatedBy = $easyMeta->dataTypeName('Annotation oa:motivatedBy');
        $dataTypeCustomVocabHasPurpose = $easyMeta->dataTypeName('Annotation Body oa:hasPurpose');
        $hasNumericDataTypes = $easyMeta->dataTypeName('numeric:integer') ? true : false;

        $motivation = $data['oa:motivatedBy'][0]['@value'] ?? 'undefined';
        $data['oa:motivatedBy'] = [[
            '@value' => $motivation,
            'property_id' => $easyMeta->propertyId('oa:motivatedBy'),
            'type' => $dataTypeCustomVocabMotivatedBy ?: 'literal',
        ]];

        $entityManager = $this->getEntityManager();
        $completeProperties = function (array &$propertyValues) use ($easyMeta, $entityManager): array {
            foreach ($propertyValues as $term => &$values) {
                if (!$values) {
                    unset($propertyValues[$term]);
                    continue;
                }
                $propertyId = $easyMeta->propertyId($term);
                if (!$propertyId) {
                    unset($propertyValues[$term]);
                    continue;
                }
                foreach ($values as $key => &$value) {
                    $hasResource = !empty($value['value_resource_id']);
                    if ($hasResource) {
                        /** @var \Omeka\Entity\Resource $resource */
                        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $value['value_resource_id']);
                        if (!$resource) {
                            unset($values[$key]);
                            continue;
                        }
                        $resourceTypes = [
                            'items' => 'resource:item',
                            'item_sets' => 'resource:itemset',
                            'media' => 'resource:media',
                        ];
                    }
                    $hasUri = !empty($value['@id']);
                    $value['property_id'] = $propertyId;
                    $value['type'] = $value['type']
                        ?? ($hasResource
                            ? ($resourceTypes[$resource->getResourceName()] ?? 'resource')
                            : ($hasUri ? 'uri' : 'literal'));
                }
                unset($value);
            }
            unset($values);
            return $propertyValues;
        };

        if (!empty($data['oa:hasBody'])) {
            foreach ($data['oa:hasBody'] as &$hasBody) {
                // Manage exceptions for rdf:value (integer) and oa:hasPurpose
                // (customvocab).
                if (!empty($hasBody['rdf:value'])) {
                    foreach ($hasBody['rdf:value'] as &$value) {
                        if ($motivation === 'assessing'
                            && $hasNumericDataTypes
                            && is_numeric($value['@value'])
                            && ctype_digit((string) $value['@value'])
                            && !empty($hasBody['dcterms:format'][0]['@value'])
                            && stripos($hasBody['dcterms:format'][0]['@value'], 'integer') !== false
                        ) {
                            // Even number, the value should always be a string.
                            $value = [
                                '@value' => (string) $value['@value'],
                                'type' => $value['type'] ?? 'numeric:integer',
                            ];
                        }
                    }
                    unset($value);
                }
                if (!empty($hasBody['oa:hasPurpose'])) {
                    foreach ($hasBody['oa:hasPurpose'] as &$value) {
                        $value = [
                            '@value' => (string) $value['@value'],
                            'type' => $dataTypeCustomVocabHasPurpose ?: 'literal',
                        ];
                    }
                    unset($value);
                }
                $completeProperties($hasBody);
            }
            unset($hasBody);
        }

        foreach ($data['oa:hasTarget'] as &$hasTarget) {
            $completeProperties($hasTarget);
        }
        unset($hasTarget);

        $request->setContent($data);
    }

    /**
     * Detect if a string is html or not.
     */
    protected function isHtml($string): bool
    {
        $string = trim((string) $string);
        return $string !== strip_tags($string);
    }
}
