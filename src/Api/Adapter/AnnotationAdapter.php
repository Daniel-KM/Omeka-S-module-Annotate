<?php
namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationTarget;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\ResourceInterface;
use Omeka\Api\Response;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\EventManager\Event;

/**
 * The Annotation adapter use the body and the target hydrators.
 *
 * @todo Create another hydrator (recursive) for selector, refinedBy, etc. or use all as selector, with exception for body and target (that are nearly selector entity in fact).
 */
class AnnotationAdapter extends AbstractResourceEntityAdapter
{
    use QueryDateTimeTrait;

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

    public function getRepresentation(ResourceInterface $data = null)
    {
        if ($data && $data->getPart() !== \Annotate\Entity\Annotation::class) {
            $data = $data->getAnnotation();
        }
        return parent::getRepresentation($data);
    }

    /**
     * Copy of the parent class, except that the "from" is AnnotationPart and
     * the GroupBy is "Annotation".
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::search()
     */
    public function search(Request $request)
    {
        $query = $request->getContent();

        // Set default query parameters
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Begin building the search query.
        $entityClass = $this->getEntityClass();

        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $entityClass : 'omeka_root';

        $this->index = 0;
        // Join all related bodies and targets to get their properties too.
        // Idealy, the request should be done on resource with a join or where
        // condition on resource_type (in annotation, body and target), but the
        // resource_type is not available in the ORM query builder, unlike the
        // DBAL query builder, because it is the discriminator map.
        // Nevertheless, Doctrine allows to use a special function in that case.
        // @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/inheritance-mapping.html#query-the-type
        // This special function is not so simple, so use AnnotationPart: all
        // annotations, bodies and targets are subparts of AnnotationPart. The
        // method getRepresentation() checks the part to return always the
        // annotation one. It avoids a "select from select unions" too.
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select($alias)
            // ->from($entityClass, $alias);
            ->from(
                // The annotation part allows to get values of all sub-parts
                // in properties or via modules.
                \Annotate\Entity\AnnotationPart::class,
                // The alias is this class, like in the normal queries. It
                // allows to manage derivated queries easily.
                $alias
            );
        $this->buildQuery($qb, $query);
        // The group is done on the annotation, not the id, so only annotations
        // are returned.
        $qb->groupBy("$alias.annotation");

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        // Add the LIMIT clause.
        $this->limitQuery($qb, $query);

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countQb = clone $qb;
        $countQb->select('1')->resetDQLPart('orderBy');
        $countPaginator = new Paginator($countQb, false);

        // Finish building the search query. In addition to any sorting the
        // adapters add, always sort by entity ID.
        $this->sortQuery($qb, $query);
        $qb->addOrderBy("$alias.annotation", $query['sort_order']);

        $scalarField = $request->getOption('returnScalar');
        if ($scalarField) {
            $fieldNames = $this->getEntityManager()->getClassMetadata($entityClass)->getFieldNames();
            if (!in_array($scalarField, $fieldNames)) {
                throw new Exception\BadRequestException(sprintf(
                    $this->getTranslator()->translate('The "%s" field is not available in the %s entity class.'),
                    $scalarField,
                    $entityClass
                ));
            }
            $qb->select($alias . '.' . $scalarField);
            $content = array_column($qb->getQuery()->getScalarResult(), $scalarField);
            $response = new Response($content);
            $response->setTotalResults(count($content));
            return $response;
        }

        $paginator = new Paginator($qb, false);
        $entities = [];
        // Don't make the request if the LIMIT is set to zero. Useful if the
        // only information needed is total results.
        if ($qb->getMaxResults() || null === $qb->getMaxResults()) {
            foreach ($paginator as $entity) {
                if (is_array($entity)) {
                    // Remove non-entity columns added to the SELECT. You can use
                    // "AS HIDDEN {alias}" to avoid this condition.
                    $entity = $entity[0];
                }
                $entities[] = $entity;
            }
        }

        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());
        return $response;
    }

    /**
     * Set sort_by and sort_order conditions to the query builder.
     *
     * Note about random sorting: There is no random query in doctrine and no
     * standard query in the sql standard, because it is too hard to implement
     * efficiently. So this order should be used only for small bases.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['sort_by']) && is_string($query['sort_by'])) {
            if (array_key_exists($query['sort_by'], $this->sortFields)) {
                $isOldOmeka = \Omeka\Module::VERSION < 2;
                $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
                $sortBy = $this->sortFields[$query['sort_by']];
                $qb->addOrderBy($alias . '.' . $sortBy, $query['sort_order']);
            } elseif ($query['sort_by'] === 'random') {
                $qb->orderBy('RAND()');
            }
        }
    }

    /**
     * The search is done on annotation bodies and targets too.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery()
     */
    public function buildQuery(QueryBuilder $qb, array $query)
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
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
                    $alias . '.owner',
                    $userAlias
                );
                $qb->andWhere($expr->isNull($userAlias . '.id'));
            } else {
                $query['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:creator',
                    'type' => 'eq',
                    'text' => $query['annotator'],
                ];
            }
        }

        // Added before parent buildQuery because a property is added.
        // FIXME: oa:hasSource is used to get the target, but in very rare cases, it can be attached to the body. Require to search a property on a subpart.
        if (isset($query['resource_id'])) {
            $query['property'][] = [
                'joiner' => 'and',
                'property' => 'oa:hasSource',
                'type' => 'res',
                'text' => $query['resource_id'],
            ];
        }

        // Parent buildQuery() uses "id" when "id" is queried, but it should be
        // "annotation_id".
        // So either copy all the parent method, either unset it before and
        // check it after. Else, change the data model to set "id" for "root".
        $hasQueryId = isset($query['id']) && is_numeric($query['id']);
        if ($hasQueryId) {
            $id = $query['id'];
            $qb->andWhere($expr->eq(
                $alias . '.annotation',
                $this->createNamedParameter($qb, $query['id'])
            ));
            unset($query['id']);
        }

        parent::buildQuery($qb, $query);

        if ($hasQueryId) {
            $query['id'] = $id;
        }

        // TODO Check the query of annotations by site.
        // TODO Make the limit to a site working for item sets and media too.
        if (!empty($query['site_id'])) {
            try {
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
                $subEntityAlias = $isOldOmeka ? $subEntityClass : $this->createAlias();
                $subQb = $this->getEntityManager()
                    ->createQueryBuilder()
                    ->select($subEntityAlias . '.id')
                    ->from($subEntityClass, $subEntityAlias);
                $subAdapter
                    ->buildQuery($subQb, $params);
                $subQb
                    ->groupBy($subEntityAlias . '.id');

                // The subquery cannot manage the parameters, since there are
                // two independant queries, but they use the same aliases. Since
                // number of ids may be great, it will be possible to create a
                // temporary table. Currently, a simple string replacement of
                // aliases is used.
                // TODO Fix Omeka core for aliases in sub queries.
                $subDql = str_replace('omeka_', 'akemo_', $subQb->getDQL());
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

        $this->buildResourceClassQuery($qb, $query);
        $this->searchDateTime($qb, $query);
    }

    /**
     * Build query on value.
     *
     * Similar than parent method with more query types.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * Query format:
     *
     *   - property[{index}][joiner]: "and" OR "or" joiner with previous query
     *   - property[{index}][property]: property ID
     *   - property[{index}][text]: search text
     *   - property[{index}][type]: search type
     *     - eq: is exactly
     *     - neq: is not exactly
     *     - in: contains
     *     - nin: does not contain
     *     - ex: has any value
     *     - nex: has no value
     *     - list: is in list
     *     - nlist: is not in list
     *     - sw: starts with
     *     - nsw: does not start with
     *     - ew: ends with
     *     - new: does not end with
     *     - res: has resource
     *     - nres: has no resource
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query)
    {
        if (!isset($query['property']) || !is_array($query['property'])) {
            return;
        }

        if (\Omeka\Module::VERSION < 2) {
            return $this->buildPropertyQuery($qb, $query);
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        // @see \Doctrine\ORM\QueryBuilder::expr().
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
        };

        foreach ($query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $propertyId = $queryRow['property'];
            $queryType = $queryRow['type'];
            $joiner = isset($queryRow['joiner']) ? $queryRow['joiner'] : null;
            $value = isset($queryRow['text']) ? $queryRow['text'] : null;

            if (!mb_strlen($value) && $queryType !== 'nex' && $queryType !== 'ex') {
                continue;
            }

            $valuesAlias = $this->createAlias();
            $positive = true;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $this->createNamedParameter($qb, $value);
                    $subqueryAlias = $this->createAlias();
                    $subquery = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("$valuesAlias.value", $param),
                        $expr->eq("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nin':
                    $positive = false;
                    // no break.
                case 'in':
                    $param = $this->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $subqueryAlias = $this->createAlias();
                    $subquery = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nlist':
                    $positive = false;
                    // no break.
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', $list), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $this->createNamedParameter($qb, $list);
                    $subqueryAlias = $this->createAlias();
                    $subquery = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->in("$valuesAlias.value", $param),
                        $expr->in("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nsw':
                    $positive = false;
                    // no break.
                case 'sw':
                    $param = $this->createNamedParameter($qb, $escape($value) . '%');
                    $subqueryAlias = $this->createAlias();
                    $subquery = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'new':
                    $positive = false;
                    // no break.
                case 'ew':
                    $param = $this->createNamedParameter($qb, '%' . $escape($value));
                    $subqueryAlias = $this->createAlias();
                    $subquery = $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    $predicateExpr = $expr->eq(
                        "$valuesAlias.valueResource",
                        $this->createNamedParameter($qb, $value)
                    );
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                if (is_numeric($propertyId)) {
                    $propertyId = (int) $propertyId;
                } else {
                    $property = $this->getPropertyByTerm($propertyId);
                    if ($property) {
                        $propertyId = $property->getId();
                    } else {
                        $propertyId = 0;
                    }
                }
                $joinConditions[] = $expr->eq("$valuesAlias.property", (int) $propertyId);
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, 'WITH', $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner == 'or') {
                $where .= " OR $whereClause";
            } else {
                $where .= " AND $whereClause";
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Old version to build query on value.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function buildPropertyQueryOld(QueryBuilder $qb, array $query)
    {
        $valuesJoin = $this->getEntityClass() . '.values';
        $where = '';
        // @see \Doctrine\ORM\QueryBuilder::expr().
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
        };

        foreach ($query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $propertyId = $queryRow['property'];
            $queryType = $queryRow['type'];
            $joiner = isset($queryRow['joiner']) ? $queryRow['joiner'] : null;
            $value = isset($queryRow['text']) ? $queryRow['text'] : null;

            if (!mb_strlen($value) && $queryType !== 'nex' && $queryType !== 'ex') {
                continue;
            }

            $valuesAlias = $this->createAlias();
            $positive = true;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->orX(
                        $expr->eq("$valuesAlias.value", $param),
                        $expr->eq("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nin':
                    $positive = false;
                    // no break.
                case 'in':
                    $param = $this->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $predicateExpr = $expr->orX(
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nlist':
                    $positive = false;
                    // no break.
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', $list), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $this->createNamedParameter($qb, $list);
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.value", $param),
                        $expr->in("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nsw':
                    $positive = false;
                    // no break.
                case 'sw':
                    $param = $this->createNamedParameter($qb, $escape($value) . '%');
                    $predicateExpr = $expr->orX(
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'new':
                    $positive = false;
                    // no break.
                case 'ew':
                    $param = $this->createNamedParameter($qb, '%' . $escape($value));
                    $predicateExpr = $expr->orX(
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    $predicateExpr = $expr->eq(
                        "$valuesAlias.valueResource",
                        $this->createNamedParameter($qb, $value)
                    );
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                if (is_numeric($propertyId)) {
                    $propertyId = (int) $propertyId;
                } else {
                    $property = $this->getPropertyByTerm($propertyId);
                    if ($property) {
                        $propertyId = $property->getId();
                    } else {
                        $propertyId = 0;
                    }
                }
                $joinConditions[] = $expr->eq("$valuesAlias.property", (int) $propertyId);
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, 'WITH', $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner == 'or') {
                $where .= " OR $whereClause";
            } else {
                $where .= " AND $whereClause";
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Search a resource class.
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function buildResourceClassQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['resource_class'])) {
            $isOldOmeka = \Omeka\Module::VERSION < 2;
            $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
            $expr = $qb->expr();

            if (is_numeric($query['resource_class'])) {
                $resourceClass = (int) $query['resource_class'];
            } else {
                $resourceClass = $this->getResourceClassByTerm($query['resource_class']);
                $resourceClass = $resourceClass ? $resourceClass->getId() : 0;
            }
            $resourceClassAlias = $this->createAlias();
            $qb->innerJoin(
                $alias . '.resourceClass',
                $resourceClassAlias
            );
            $qb->andWhere(
                $expr->eq(
                    $resourceClassAlias . '.id',
                    $this->createNamedParameter($qb, $resourceClass)
                )
            );
        }
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
        // Body and target are no more adapter, but hydrator, so they are no
        // more managed by the api, but only the entity manager.
        $childHydrators = [
            'oa:hasBody' => AnnotationBodyHydrator::class,
            'oa:hasTarget' => AnnotationTargetHydrator::class,
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

        foreach ($childEntities as $jsonName => $resourceName) {
            if ($this->shouldHydrate($request, $jsonName)) {
                $childAdapter = new $childHydrators[$jsonName];
                $childAdapter->setServiceLocator($this->getServiceLocator());
                $class = $childAdapter->getEntityClass();

                $retainChildren = [];
                $childrenData = $request->getValue($jsonName, []);
                foreach ($childrenData as $childData) {
                    $subErrorStore = new ErrorStore;
                    $isCreate = true;
                    // Update an existing child.
                    if (is_object($childData)) {
                        $child = $childAdapter->findEntity($childData);
                        $isCreate = false;
                    } elseif (isset($childData['o:id'])) {
                        $child = $childAdapter->findEntity($childData['o:id']);
                        $isCreate = false;
                    }
                    // Create a new child.
                    else {
                        $child = new $class;
                    }
                    // The child data related to the resource should be the same
                    // than Annotation in order to do good search on them.
                    // Nevertheless, keep thumbnail, because there is no search
                    // on it, and resource type.
                    $child->setAnnotation($entity);
                    $child->setOwner($entity->getOwner());
                    $child->setResourceClass($entity->getResourceClass());
                    $child->setResourceTemplate($entity->getResourceTemplate());
                    $child->setIsPublic($entity->isPublic());
                    $child->setCreated($entity->getCreated());
                    $child->setModified($entity->getModified());

                    $subrequest = new Request($isCreate ? Request::CREATE : Request::UPDATE, $resourceName);
                    $subrequest->setContent($childData);
                    try {
                        $childAdapter->hydrateEntity($subrequest, $child, $subErrorStore);
                    } catch (Exception\ValidationException $e) {
                        $errorStore->mergeErrors($e->getErrorStore(), $jsonName);
                    }
                    if ($isCreate) {
                        $this->getCollection($entity, $resourceName)->add($child);
                    }
                    $retainChildren[] = $child;
                }

                // Remove child not included in request.
                $children = $this->getCollection($entity, $resourceName);
                foreach ($children as $child) {
                    if (!in_array($child, $retainChildren, true)) {
                        $children->removeElement($child);
                    }
                }
            }
        }
    }

    /**
     * Returns bodies or targets of the annotations.
     *
     * @param Annotation $annotation
     * @param string $collection
     * @return ArrayCollection
     */
    protected function getCollection(Annotation $annotation, $collection)
    {
        switch ($collection) {
            case 'oa:hasBody':
            case 'annotation_bodies':
                return $annotation->getBodies();
            case 'oa:hasTarget':
            case 'annotation_targets':
                return $annotation->getTargets();
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (array_key_exists('oa:hasBody', $data)
            && !is_array($data['oa:hasBody'])
        ) {
            $errorStore->addError('oa:hasBody', 'Annotation body must be an array.'); // @translate
        }

        if (array_key_exists('oa:hasTarget', $data)) {
            if (!is_array($data['oa:hasTarget'])) {
                $errorStore->addError('oa:hasTarget', 'Annotation target must be an array.'); // @translate
            } elseif (count($data['oa:hasTarget']) < 1) {
                $errorStore->addError('oa:hasTarget', 'There must be one annotation target at least.'); // @translate
            }
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

    /**
     * Get a resource class entity by JSON-LD term.
     *
     * @param string $term
     * @return \Omeka\Entity\ResourceClass
     */
    public function getResourceClassByTerm($term)
    {
        if (!$this->isTerm($term)) {
            return null;
        }
        list($prefix, $localName) = explode(':', $term);
        $dql = <<<DQL
SELECT r
FROM Omeka\Entity\ResourceClass r
JOIN r.vocabulary v
WHERE r.localName = :localName
AND v.prefix = :prefix
DQL;
        return $this->getEntityManager()
            ->createQuery($dql)
            ->setParameters([
                'localName' => $localName,
                'prefix' => $prefix,
            ])
            ->getOneOrNullResult();
    }
}
