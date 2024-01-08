<?php declare(strict_types=1);

namespace Annotate\Api\Adapter;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * The previous version to search annotations worked fine, except to search
 * values in different parts. See details in previous commit version (3.4.3.8-beta)
 * explaining various issues with doctrine and abstract super classes.
 *
 * The search should return annotation, but the values belong to multiple parts
 * (annotation, body or target). So just override buildPropertyQuery() with an
 * adapted version of the code in module AdvancedSearch.
 *
 * @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::buildPropertyQuery()
 */
trait QueryPropertiesTrait
{
    /**
     * Tables of all query types and their behaviors.
     *
     * May be used by other modules.
     *
     * @var array
     */
    protected $propertyQuery = [
        'reciprocal' => [
            'eq' => 'neq',
            'neq' => 'eq',
            'in' => 'nin',
            'nin' => 'in',
            'ex' => 'nex',
            'nex' => 'ex',
            'exs' => 'nexs',
            'nexs' => 'exs',
            'exm' => 'nexm',
            'nexm' => 'exm',
            'list' => 'nlist',
            'nlist' => 'list',
            'sw' => 'nsw',
            'nsw' => 'sw',
            'ew' => 'new',
            'new' => 'ew',
            'near' => 'nnear',
            'nnear' => 'near',
            'res' => 'nres',
            'nres' => 'res',
            'tp' => 'ntp',
            'ntp' => 'tp',
            'tpl' => 'ntpl',
            'ntpl' => 'tpl',
            'tpr' => 'ntpr',
            'ntpr' => 'tpr',
            'tpu' => 'ntpu',
            'ntpu' => 'tpu',
            'dtp' => 'ndtp',
            'ndtp' => 'dtp',
            'lex' => 'nlex',
            'nlex' => 'lex',
            'lres' => 'nlres',
            'nlres' => 'lres',
            'gt' => 'lte',
            'gte' => 'lt',
            'lte' => 'gt',
            'lt' => 'gte',
        ],
        'negative' => [
            'neq',
            'nin',
            'nex',
            'nexs',
            'nexm',
            'nlist',
            'nsw',
            'new',
            'nnear',
            'nres',
            'ntp',
            'ntpl',
            'ntpr',
            'ntpu',
            'ndtp',
            'nlex',
            'nlres',
        ],
        'value_array' => [
            'list',
            'nlist',
            'res',
            'nres',
            'lres',
            'nlres',
            'dtp',
            'ndtp',
        ],
        'value_integer' => [
            'res',
            'nres',
            'lres',
            'nlres',
        ],
        'value_none' => [
            'ex',
            'nex',
            'exs',
            'nexs',
            'exm',
            'nexm',
            'lex',
            'nlex',
            'tpl',
            'ntpl',
            'tpr',
            'ntpr',
            'tpu',
            'ntpu',
        ],
        'value_subject' => [
            'lex',
            'nlex',
            'lres',
            'nlres',
        ],
        'optimize' => [
            'eq' => 'list',
            'neq' => 'nlist',
        ],
    ];

    /**
     * @var \Annotate\Api\Adapter\AnnotationAdapter
     */
    protected $adapter;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * Build query on value.
     *
     * Pseudo-override buildPropertyQuery() via the api manager delegator.
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::buildPropertyQuery()
     * @see \Annotate\Api\Adapter\QueryPropertiesTrait::buildPropertyQuery()
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" OR "not" joiner with previous query
     * - property[{index}][property]: property ID or term or array of property IDs or terms
     * - property[{index}][text]: search text or array of texts or values
     * - property[{index}][type]: search type
     * - property[{index}][datatype]: filter on data type(s)
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - ex: has any value (core)
     *   - nex: has no value (core)
     *   - exs: has a single value
     *   - nexs: has not a single value
     *   - exm: has multiple values
     *   - nexm: has not multiple values
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - near: is similar to
     *   - nnear: is not similar to
     *   - res: has resource (core)
     *   - nres: has no resource (core)
     *   - tp: has main type (literal-like, resource-like, uri-like)
     *   - ntp: has not main type (literal-like, resource-like, uri-like)
     *   - tpl: has type literal-like
     *   - ntpl: has not type literal-like
     *   - tpr: has type resource-like
     *   - ntpr: has not type resource-like
     *   - tpu: has type uri-like
     *   - ntpu: has not type uri-like
     *   - dtp: has data type
     *   - ndtp: has not data type
     *   - lex: is a linked resource
     *   - nlex: is not a linked resource
     *   - lres: is linked with resource #id
     *   - nlres: is not linked with resource #id
     *
     * Reserved for future implementation (already in Solr):
     *   - ma: matches a simple regex
     *   - nma: does not match a simple regex
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query): void
    {
        if (empty($query['property']) || !is_array($query['property'])) {
            return;
        }

        // $valuesJoin = 'omeka_root.values';
        $where = '';
        $hasIncorrectValue = false;

        // To simplify maintenance with module AdvancedSearch, use class
        // properties for adapter and connection.
        $this->adapter = $this;

        /**
         * @see \Doctrine\ORM\QueryBuilder::expr().
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $expr = $qb->expr();
        $entityManager = $this->adapter->getEntityManager();

        $this->connection = $entityManager->getConnection();

        $escapeSqlLike = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        /** @var \Common\Stdlib\EasyMeta $easyMeta */
        $easyMeta = $this->adapter->getServiceLocator()->get('EasyMeta');

        $mainQb = $qb;

        // A sub query is required to search properties as a whole with and/or.
        // To fix issues with doctrine subqueries and parameters, execute
        // queries directly. Most of the time, it is to find annotations
        // motivated by a specific type (oa:motivatedBy) for an item (oa:hasSource)
        // for a user.
        // TODO Find a better way to aggregate sub-queries sub queries with parameters. Use sql? Add a  table with source (but problem will remain for other properties but most of them are in bodies)?
        $partialResults = null;
        $smqAlias = $this->adapter->createAlias();
        $smq = $entityManager->createQueryBuilder();
        $smqParameters = $smq->getParameters();
        // To fix issues with doctrine subqueries and parameters, execute
        // queries directly. Most of the time, it is to find annotation of an
        // item of a user.
        // TODO Find a better way to aggregate sub-queries sub queries with parameters or use sql.
        $partialResults = null;
        $qbs = [];

        foreach ($query['property'] as $queryRow) {
            if (!is_array($queryRow)
                || empty($queryRow['type'])
                || !isset($this->propertyQuery['reciprocal'][$queryRow['type']])
            ) {
                continue;
            }

            $queryType = $queryRow['type'];
            $value = $queryRow['text'] ?? '';

            // Quick check of value.
            // A empty string "" is not a value, but "0" is a value.
            if (in_array($queryType, $this->propertyQuery['value_none'], true)) {
                $value = null;
            }
            // Check array of values.
            elseif (in_array($queryType, $this->propertyQuery['value_array'], true)) {
                if ((is_array($value) && !count($value))
                    || (!is_array($value) && !strlen((string) $value))
                ) {
                    continue;
                }
                if (!is_array($value)) {
                    $value = [$value];
                }
                // To use array_values() avoids doctrine issue with string keys.
                $value = in_array($queryType, $this->propertyQuery['value_integer'])
                    ? array_values(array_unique(array_map('intval', $value)))
                    : array_values(array_unique(array_filter(array_map('trim', array_map('strval', $value)), 'strlen')));
                if (empty($value)) {
                    continue;
                }
            }
            // The value should be scalar in all other cases (int or string).
            elseif (is_array($value)) {
                continue;
            } else {
                $value = trim((string) $value);
                if (!strlen($value)) {
                    continue;
                }
                if (in_array($queryType, $this->propertyQuery['value_integer'])) {
                    if (!is_numeric($value)) {
                        continue;
                    }
                    $value = (int) $value;
                }
            }

            $joiner = $queryRow['joiner'] ?? '';
            $dataType = $queryRow['datatype'] ?? '';

            // Check joiner and invert the query type for joiner "not".
            if ($joiner === 'not') {
                $joiner = 'and';
                $queryType = $this->propertyQuery['reciprocal'][$queryType];
            } elseif ($joiner && $joiner !== 'or') {
                $joiner = 'and';
            }

            $valuesAlias = $this->adapter->createAlias();
            $positive = true;
            $incorrectValue = false;

            $qb = $entityManager->createQueryBuilder();

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSqlLike($value) . '%');
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $qb->setParameter(substr($param, 1), $value, Connection::PARAM_STR_ARRAY);
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->in("$subqueryAlias.title", $param));
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
                    $param = $this->adapter->createNamedParameter($qb, $escapeSqlLike($value) . '%');
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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
                    $param = $this->adapter->createNamedParameter($qb, '%' . $escapeSqlLike($value));
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
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

                case 'nnear':
                    $positive = false;
                    // no break.
                case 'near':
                    // The mysql soundex() is not standard, because it returns
                    // more than four characters, so the comparaison cannot be
                    // done with a static value from the php soundex() function.
                    $param = $this->adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $this->adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("SOUNDEX($subqueryAlias.title)", "SOUNDEX($param)"));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("SOUNDEX($valuesAlias.value)", "SOUNDEX($param)")/*,
                        // A soundex on a uri has no meaning.
                        $expr->eq("SOUNDEX($valuesAlias.uri)", "SOUNDEX($param)")
                        */
                    );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    if (count($value) <= 1) {
                        $param = $this->adapter->createNamedParameter($qb, (int) reset($value));
                        $predicateExpr = $expr->eq("$valuesAlias.valueResource", $param);
                    } else {
                        $param = $this->adapter->createNamedParameter($qb, $value);
                        $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                        $predicateExpr = $expr->in("$valuesAlias.valueResource", $param);
                    }
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                case 'nexs':
                    // No predicate expression, but simplify process.
                    $predicateExpr = $expr->eq(1, 1);
                    $qb->having($expr->neq("COUNT($valuesAlias.id)", 1));
                    break;
                case 'exs':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    $qb->having($expr->eq("COUNT($valuesAlias.id)", 1));
                    break;

                case 'nexm':
                    // No predicate expression, but simplify process.
                    $predicateExpr = $expr->eq(1, 1);
                    $qb->having($expr->lt("COUNT($valuesAlias.id)", 2));
                    break;
                case 'exm':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    $qb->having($expr->gt("COUNT($valuesAlias.id)", 1));
                    break;

                case 'ntp':
                    $positive = false;
                    // no break.
                case 'tp':
                    if ($value === 'literal') {
                        // Because a resource or a uri can have a label stored
                        // in "value", a literal-like value is a value without
                        // resource and without uri.
                        $predicateExpr = $expr->andX(
                            $expr->isNull("$valuesAlias.valueResource"),
                            $expr->isNull("$valuesAlias.uri")
                        );
                    } elseif ($value === 'resource') {
                        $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                    } elseif ($value === 'uri') {
                        $predicateExpr = $expr->isNotNull("$valuesAlias.uri");
                    } else {
                        $predicateExpr = $expr->eq(1, 0);
                    }
                    break;

                case 'ntpl':
                    $positive = false;
                    // no break.
                case 'tpl':
                    // Because a resource or a uri can have a label stored
                    // in "value", a literal-like value is a value without
                    // resource and without uri.
                    $predicateExpr = $expr->andX(
                        $expr->isNull("$valuesAlias.valueResource"),
                        $expr->isNull("$valuesAlias.uri")
                    );
                    break;

                case 'ntpr':
                    $positive = false;
                    // no break.
                case 'tpr':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.valueResource");
                    break;

                case 'ntpu':
                    $positive = false;
                    // no break.
                case 'tpu':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.uri");
                    break;

                case 'ndtp':
                    $positive = false;
                    // no break.
                case 'dtp':
                    if (count($value) <= 1) {
                        $dataTypeAlias = $this->adapter->createNamedParameter($qb, reset($value));
                        $predicateExpr = $expr->eq("$valuesAlias.type", $dataTypeAlias);
                    } else {
                        $dataTypeAlias = $this->adapter->createAlias();
                        $qb->setParameter($dataTypeAlias, $value, Connection::PARAM_STR_ARRAY);
                        $predicateExpr = $expr->in("$valuesAlias.type", ":$dataTypeAlias");
                    }
                    break;

                // The linked resources (subject values) use the same sub-query.
                case 'nlex':
                    // For consistency, "nlex" is the reverse of "lex" even when
                    // a resource is linked with a public and a private resource.
                    // A private linked resource is not linked for an anonymous.
                case 'nlres':
                    $positive = false;
                    // no break.
                case 'lex':
                case 'lres':
                    $subValuesAlias = $this->adapter->createAlias();
                    $subResourceAlias = $this->adapter->createAlias();
                    // Use a subquery so rights are automatically managed.
                    $subQb = $entityManager
                        ->createQueryBuilder()
                        ->select("IDENTITY($subValuesAlias.valueResource)")
                        ->from(\Omeka\Entity\Value::class, $subValuesAlias)
                        ->innerJoin("$subValuesAlias.resource", $subResourceAlias)
                        ->where($expr->isNotNull("$subValuesAlias.valueResource"));
                    // Warning: the property check should be done on subjects,
                    // so the predicate expression is finalized below.
                    if (is_array($value)) {
                        // In fact, "lres" is the list of linked resources.
                        if (count($value) <= 1) {
                            $param = $this->adapter->createNamedParameter($qb, (int) reset($value));
                            $subQb->andWhere($expr->eq("$subValuesAlias.resource", $param));
                        } else {
                            $param = $this->adapter->createNamedParameter($qb, $value);
                            $qb->setParameter(substr($param, 1), $value, Connection::PARAM_INT_ARRAY);
                            $subQb->andWhere($expr->in("$subValuesAlias.resource", $param));
                        }
                    }
                    break;

                default:
                    continue 2;
            }

            // Avoid to get results when the query is incorrect.
            // In that case, no param should be set in the current loop.
            if ($incorrectValue) {
                $where = $expr->eq('omeka_root.id', 0);
                $hasIncorrectValue = true;
                break;
            }

            $joinConditions = [];

            // Narrow to specific properties, if one or more are selected.
            $propertyIds = $queryRow['property'] ?? null;
            // Properties may be an array with an empty value (any property) in
            // advanced form, so remove empty strings from it, in which case the
            // check should be skipped.
            if (is_array($propertyIds) && in_array('', $propertyIds, true)) {
                $propertyIds = [];
            }
            // TODO What if a property is ""?
            $excludePropertyIds = $propertyIds || empty($queryRow['except'])
                ? false
                : array_values(array_unique($easyMeta->propertyIds($queryRow['except'])));
            if ($propertyIds) {
                $propertyIds = array_values(array_unique($easyMeta->propertyIds($propertyIds)));
                if ($propertyIds) {
                    // For queries on subject values, the properties should be
                    // checked against the sub-query.
                    if (in_array($queryType, $this->propertyQuery['value_subject'])) {
                        $subQb
                            ->andWhere(count($propertyIds) < 2
                                ? $expr->eq("$subValuesAlias.property", reset($propertyIds))
                                : $expr->in("$subValuesAlias.property", $propertyIds)
                            );
                    } else {
                        $joinConditions[] = count($propertyIds) < 2
                            ? $expr->eq("$valuesAlias.property", reset($propertyIds))
                            : $expr->in("$valuesAlias.property", $propertyIds);
                    }
                } else {
                    // Don't return results for this part for fake properties.
                    $joinConditions[] = $expr->eq("$valuesAlias.property", 0);
                }
            }
            // Use standard query if nothing to exclude, else limit search.
            elseif ($excludePropertyIds) {
                // The aim is to search anywhere except ocr content.
                // Use not positive + in() or notIn()? A full list is simpler.
                $otherIds = array_diff($easyMeta->propertyIdsUsed(), $excludePropertyIds);
                // Avoid issue when everything is excluded.
                $otherIds[] = 0;
                if (in_array($queryType, $this->propertyQuery['value_subject'])) {
                    $subQb
                        ->andWhere($expr->in("$subValuesAlias.property", $otherIds));
                } else {
                    $joinConditions[] = $expr->in("$valuesAlias.property", $otherIds);
                }
            }

            // Finalize predicate expression on subject values.
            if (in_array($queryType, $this->propertyQuery['value_subject'])) {
                $predicateExpr = $expr->in("$valuesAlias.resource", $subQb->getDQL());
            }

            if ($dataType) {
                if (!is_array($dataType) || count($dataType) <= 1) {
                    $dataTypeAlias = $this->adapter->createNamedParameter($qb, is_array($dataType) ? reset($dataType) : $dataType);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->eq("$valuesAlias.type", $dataTypeAlias)
                    );
                } else {
                    $dataTypeAlias = $this->adapter->createAlias();
                    $qb->setParameter($dataTypeAlias, array_values($dataType), Connection::PARAM_STR_ARRAY);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->in("$valuesAlias.type", ':' . $dataTypeAlias)
                    );
                }
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            $sqAlias = $this->adapter->createAlias();
            $qb
                ->select("DISTINCT IDENTITY($sqAlias.annotation)")
                ->from(\Annotate\Entity\AnnotationPart::class, $sqAlias);
            if ($joinConditions) {
                $qb->leftJoin("$sqAlias.values", $valuesAlias, Join::WITH, $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin("$sqAlias.values", $valuesAlias);
            }
            $qb
                ->where($whereClause);

            // TODO For now, "where" are concatenated manually.
            $qbPartialResult = $qb->getQuery()->getScalarResult();
            $qbPartialResult = $qbPartialResult ? array_column($qbPartialResult, '1', '1') : [];
            if ($partialResults === null) {
                $partialResults = $qbPartialResult;
            } elseif ($joiner === 'or') {
                $partialResults = array_replace($partialResults, $qbPartialResult);
            } else {
                $partialResults = array_intersect_key($partialResults, $qbPartialResult);
            }

            if ($where === '') {
                $where = '';
            } elseif ($joiner === 'or') {
                $where .= ' OR ';
            } else {
                $where .= ' AND ';
            }
            $where .= $expr->in($smqAlias, $qb->getDQL());
            foreach ($qb->getParameters() as $parameter) {
                $smqParameters->add($parameter);
            }
        }

        // Properties are one argument to append as a whole.
        if ($where) {
            $smq = $entityManager->createQueryBuilder();
            $smqAlias = $this->adapter->createAlias();
            $smqParameters = $smq->getParameters();
            $smq
                ->select("DISTINCT IDENTITY($smqAlias)")
                ->from(\Annotate\Entity\Annotation::class, $smqAlias);
            $first = true;
            foreach ($qbs as $qbd) {
                if ($first) {
                    $first = false;
                    $smq
                        ->where($expr->in("$smqAlias.id", $qbd['qb']->getDQL()));
                } elseif ($qbd['joiner'] === 'or') {
                    $smq
                        ->orWhere($expr->in("$smqAlias.id", $qbd['qb']->getDQL()));
                } else {
                    $smq
                        ->andWhere($expr->in("$smqAlias.id", $qbd['qb']->getDQL()));
                }
                foreach ($qbd['qb']->getParameters() as $qbParameters) {
                    $smqParameters->add($qbParameters);
                }
            }
            $mainQb
                ->andWhere($expr->in('omeka_root', ':annotation_ids'))
                ->setParameter('annotation_ids', array_keys($partialResults), $this->connection::PARAM_INT_ARRAY)
            ;
        }
    }
}
