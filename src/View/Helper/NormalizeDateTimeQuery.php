<?php declare(strict_types=1);
namespace Annotate\View\Helper;

use Doctrine\ORM\Query\Expr\Comparison;
use Laminas\View\Helper\AbstractHelper;

class NormalizeDateTimeQuery extends AbstractHelper
{
    /**
     * Normalize one query for date time.
     *
     * @param string|array $queryDateTime
     * @param string $field
     * @return array
     */
    public function __invoke($query, $field = 'created')
    {
        if (empty($query)) {
            return;
        }

        $operators = [
            '>=' => Comparison::GTE,
            '>' => Comparison::GT,
            '<' => Comparison::LT,
            '<=' => Comparison::LTE,
            '<>' => Comparison::NEQ,
            '=' => Comparison::EQ,
            'gte' => Comparison::GTE,
            'gt' => Comparison::GT,
            'lt' => Comparison::LT,
            'lte' => Comparison::LTE,
            'neq' => Comparison::NEQ,
            'eq' => Comparison::EQ,
            'ex' => 'ex',
            'nex' => 'nex',
        ];

        $defaults = [
            'joiner' => 'and',
            'field' => $field,
            'type' => Comparison::EQ,
            'value' => null,
        ];

        // Manage a single date time as created, with eventual operator.
        if (!is_array($query)) {
            $value = (string) $query;
            $matches = [];
            preg_match('/^[^\d]+/', $value, $matches);
            if (empty($matches[0])) {
                $operator = Comparison::EQ;
            } else {
                $operator = trim($matches[0]);
                $operator = $operators[$operator]
                    ?? Comparison::EQ;
                $value = mb_substr($value, mb_strlen($matches[0]));
            }
            $value = trim($value);
            $query = ['type' => $operator, 'value' => $value] + $defaults;
            return $query;
        }

        $query = $query + $defaults;

        // Clean query and manage default values.
        $query = array_map('mb_strtolower', array_map('trim', $query));
        if (!in_array($query['joiner'], ['and', 'or'])) {
            $query['joiner'] = 'and';
        }

        if (!in_array($query['field'], ['created', 'modified'])) {
            $query['field'] = $field;
        }

        if (!isset($operators[$query['type']])) {
            $query['type'] = Comparison::EQ;
        }
        $query['type'] = $operators[$query['type']];

        if (in_array($query['type'], ['ex', 'nex'])) {
            $query['value'] = null;
        } elseif (empty($query['value'])) {
            return;
        }

        // Date time cannot be longer than 19 characters.
        // But user can choose a year only, etc.

        return $query;
    }
}
