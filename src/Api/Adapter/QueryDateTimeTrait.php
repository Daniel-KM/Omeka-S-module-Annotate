<?php
namespace Annotate\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Comparison;

/**
 * This trait must be used inside an adapter, because there are calls to the
 * adapter methods.
 * Nevertheless, the second method is used in Module too.
 */
trait QueryDateTimeTrait
{
    /**
     * Build query on date time (created/modified), partial date/time allowed.
     *
     * By default, sql replace missing time by 00:00:00, but this is not clear
     * for the user. And it doesn't allow partial date/time.
     *
     * @todo Manage search of negative date time.
     *
     * The query format is inspired by Doctrine and properties:
     * - datetime[{index}][joiner]: "and" OR "or" joiner with previous query
     * - datetime[{index}][field]: the field "created" or "modified"
     * - datetime[{index}][type]: search type
     *   - gt: greater than (after)
     *   - gte: greater than or equal
     *   - eq: is exactly
     *   - neq: is not exactly
     *   - lte: lower than or equal
     *   - lt: lower than (before)
     *   - ex: has any value
     *   - nex: has no value
     * - datetime[{index}][value]: search date time (sql format: "2017-11-07 17:21:17",
     *   partial date/time allowed ("2018-05", etc.).
     * Anyway, the query is normalized, so it can be a string too, and in fields
     * "datetime" (multiple), "created" and "modified' (single).
     *
     * @param QueryBuilder $qb
     * @param array $query
     */
    public function searchDateTime(QueryBuilder $qb, array $query)
    {
        $query = $this->normalizeQueryDateTime($query);
        if (empty($query['datetime'])) {
            return;
        }

        $resourceClass = $this->getEntityClass();

        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $resourceClass : 'omeka_root';

        $where = '';
        $expr = $qb->expr();

        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $value = $queryRow['value'];

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            // $qb->andWhere(new Comparison(
            //     $this->getEntityClass() . '.' . $column,
            //     $operator,
            //     $this->createNamedParameter($qb, $value)
            // ));
            // return;

            switch ($type) {
                case Comparison::GT:
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->gt($alias . '.' . $field, $param);
                    break;
                case Comparison::GTE:
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->gte($alias . '.' . $field, $param);
                    break;
                case Comparison::EQ:
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $this->createNamedParameter($qb, $valueFrom);
                        $paramTo = $this->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $expr->between($alias . '.' . $field, $paramFrom, $paramTo);
                    } else {
                        $param = $this->createNamedParameter($qb, $value);
                        $predicateExpr = $expr->eq($alias . '.' . $field, $param);
                    }
                    break;
                case Comparison::NEQ:
                    if (strlen($value) < 19) {
                        $valueFrom = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                        $valueTo = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                        $paramFrom = $this->createNamedParameter($qb, $valueFrom);
                        $paramTo = $this->createNamedParameter($qb, $valueTo);
                        $predicateExpr = $expr->not(
                            $expr->between($alias . '.' . $field, $paramFrom, $paramTo)
                            );
                    } else {
                        $param = $this->createNamedParameter($qb, $value);
                        $predicateExpr = $expr->neq($alias . '.' . $field, $param);
                    }
                    break;
                case Comparison::LTE:
                    if (strlen($value) < 19) {
                        $value = substr_replace('9999-12-31 23:59:59', $value, 0, strlen($value) - 19);
                    }
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->lte($alias . '.' . $field, $param);
                    break;
                case Comparison::LT:
                    if (strlen($value) < 19) {
                        $value = substr_replace('0000-01-01 00:00:00', $value, 0, strlen($value) - 19);
                    }
                    $param = $this->createNamedParameter($qb, $value);
                    $predicateExpr = $expr->lt($alias . '.' . $field, $param);
                    break;
                case 'ex':
                    $predicateExpr = $expr->isNotNull($alias . '.' . $field);
                    break;
                case 'nex':
                    $predicateExpr = $expr->isNull($alias . '.' . $field);
                    break;
                default:
                    continue 2;
            }

            // First expression has no joiner.
            if ($where === '') {
                $where = '(' . $predicateExpr . ')';
            } elseif ($joiner === 'or') {
                $where .= ' OR (' . $predicateExpr . ')';
            } else {
                $where .= ' AND (' . $predicateExpr . ')';
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Normalize the query for date time (datetime, created or modified).
     *
     * @param array $query
     * @return array
     */
    protected function normalizeQueryDateTime(array $query)
    {
        $normalizeDateTimeQuery = $this->getServiceLocator()->get('ViewHelperManager')->get('normalizeDateTimeQuery');
        if (empty($query['datetime'])) {
            $query['datetime'] = [];
        } else {
            if (!is_array($query['datetime'])) {
                $query['datetime'] = [$query['datetime']];
            }
            foreach ($query['datetime'] as $key => $datetime) {
                $datetime = $normalizeDateTimeQuery($datetime);
                if ($datetime) {
                    $query['datetime'][$key] = $datetime;
                } else {
                    unset($query['datetime'][$key]);
                }
            }
        }

        if (!empty($query['created'])) {
            $datetime = $normalizeDateTimeQuery($query['created'], 'created');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }

        if (!empty($query['modified'])) {
            $datetime = $normalizeDateTimeQuery($query['modified'], 'modified');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }

        return $query;
    }
}
