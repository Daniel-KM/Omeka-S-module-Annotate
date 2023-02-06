<?php declare(strict_types=1);

namespace Annotate\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;

/**
 * @see \AdvancedSearch\View\Helper\EasyMeta
 * @see \Annotate\View\Helper\EasyMeta
 *
 * @see \BulkImport\Mvc\Controller\Plugin\Bulk
 * @see \Reference\Mvc\Controller\Plugin\References
 */
class EasyMeta extends AbstractHelper
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected static $propertiesByTerms;

    /**
     * @var array
     */
    protected static $propertiesByTermsAndIds;

    /**
     * @var array
     */
    protected static $propertiesLabels;

    /**
     * @var array
     */
    protected static $resourceClassesByTerms;

    /**
     * @var array
     */
    protected static $resourceClassesByTermsAndIds;

    /**
     * @var array
     */
    protected static $resourceClassesLabels;

    /**
     * @var array
     */
    protected static $resourceTemplatesByLabels;

    /**
     * @var array
     */
    protected static $resourceTemplatesByLabelsAndIds;

    /**
     * @var array
     */
    protected static $resourceTemplatesLabels;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get one or more property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[]|int|null The property ids matching terms or ids, or all
     * properties by terms.
     */
    public function propertyIds($termsOrIds = null)
    {
        if (is_null(static::$propertiesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            static::$propertiesByTerms = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            static::$propertiesByTermsAndIds = array_replace(static::$propertiesByTerms, array_combine(static::$propertiesByTerms, static::$propertiesByTerms));
        }

        if (is_null($termsOrIds)) {
            return static::$propertiesByTerms;
        }

        return is_scalar($termsOrIds)
            ? static::$propertiesByTermsAndIds[$termsOrIds] ?? null
            : array_intersect_key(array_flip($termsOrIds), static::$propertiesByTermsAndIds);
    }

    /**
     * Get one or more property terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The property terms matching terms or ids, or
     * all properties by ids.
     */
    public function propertyTerms($termsOrIds = null)
    {
        if (is_null(static::$propertiesByTerms)) {
            $this->propertyIds();
        }

        if (is_null($termsOrIds)) {
            return array_flip(static::$propertiesByTerms);
        }

        return is_scalar($termsOrIds)
            ? (array_search($termsOrIds, static::$propertiesByTermsAndIds) ?: null)
            // TODO Keep original order.
            : array_flip(array_intersect_key(static::$propertiesByTermsAndIds, array_fill_keys($termsOrIds, null)));
    }

    /**
     * Get one or more property labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The property labels matching terms or ids, or
     * all properties by ids. Labels are not translated.
     */
    public function propertyLabels($termsOrIds = null)
    {
        if (is_null(static::$propertiesLabels)) {
            static::$propertiesLabels = array_flip($this->propertyIds());
        }

        if (is_null($termsOrIds)) {
            return static::$propertiesLabels;
        }

        $ids = $this->propertyIds($termsOrIds);
        if (empty($ids)) {
            return $ids;
        }

        if (is_scalar($ids)) {
            return static::$propertiesLabels[$ids] ?? null;
        }

        // TODO Keep original order.
        return array_intersect_key(static::$propertiesLabels, array_fill_keys($ids, null));
    }

    /**
     * Get one or more resource class  ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[]|int|null The resource class ids matching terms or ids, or
     * all resource classes by terms.
     */
    public function resourceClassIds($termsOrIds = null)
    {
        if (is_null(static::$resourceClassesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                    'resource_class.id AS id',
                    // Required with only_full_group_by.
                    'vocabulary.id'
                )
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('resource_class.id', 'asc')
            ;
            static::$resourceClassesByTerms = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            static::$resourceClassesByTermsAndIds = array_replace(static::$resourceClassesByTerms, array_combine(static::$resourceClassesByTerms, static::$resourceClassesByTerms));
        }

        if (is_null($termsOrIds)) {
            return static::$resourceClassesByTerms;
        }

        return is_scalar($termsOrIds)
            ? static::$resourceClassesByTermsAndIds[$termsOrIds] ?? null
            : array_intersect_key(array_flip($termsOrIds), static::$resourceClassesByTermsAndIds);
    }

    /**
     * Get one or more resource class terms by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The resource class terms matching terms or
     * ids, or all resource classes by ids.
     */
    public function resourceClassTerms($termsOrIds = null)
    {
        if (is_null(static::$resourceClassesByTerms)) {
            $this->resourceClassIds();
        }

        if (is_null($termsOrIds)) {
            return array_flip(static::$resourceClassesByTerms);
        }

        return is_scalar($termsOrIds)
            ? (array_search($termsOrIds, static::$resourceClassesByTermsAndIds) ?: null)
            // TODO Keep original order.
            : array_flip(array_intersect_key(static::$resourceClassesByTermsAndIds, array_fill_keys($termsOrIds, null)));
    }

    /**
     * Get one or more resource class labels by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return string[]|string|null The resource class labels matching terms or
     * ids, or all resource classes by ids. Labels are not translated.
     */
    public function resourceClassLabels($termsOrIds = null)
    {
        if (is_null(static::$resourceClassesLabels)) {
            static::$resourceClassesLabels = array_flip($this->resourceClassIds());
        }

        if (is_null($termsOrIds)) {
            return static::$resourceClassesLabels;
        }

        $ids = $this->resourceClassIds($termsOrIds);
        if (empty($ids)) {
            return $ids;
        }

        if (is_scalar($ids)) {
            return static::$resourceClassesLabels[$ids] ?? null;
        }

        // TODO Keep original order.
        return array_intersect_key(static::$resourceClassesLabels, array_fill_keys($ids, null));
    }

    /**
     * Get one or more resource template ids by labels or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or labels.
     * @return string[]|string|null The resource template ids matching labels or
     * ids, or all resource templates by labels.
     */
    public function resourceTemplateIds($labelsOrIds = null)
    {
        if (is_null(static::$resourceTemplatesByLabelsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    'resource_template.label AS label',
                    'resource_template.id AS id'
                )
                ->from('resource_template', 'resource_template')
                ->orderBy('resource_template.label', 'asc')
            ;
            static::$resourceTemplatesByLabels = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
            static::$resourceTemplatesByLabelsAndIds = array_replace(static::$resourceTemplatesByLabels, array_combine(static::$resourceTemplatesByLabels, static::$resourceTemplatesByLabels));
        }

        if (is_null($labelsOrIds)) {
            return static::$resourceTemplateByLabels;
        }

        return is_scalar($labelsOrIds)
            ? static::$resourceTemplatesByLabelsAndIds[$labelsOrIds] ?? null
            : array_intersect_key(array_flip($labelsOrIds), static::$resourceTemplatesByLabelsAndIds);
    }

    /**
     * Get one or more resource template labels by labels or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or labels.
     * @return string[]|string|null The resource template labels matching labels or
     * ids, or all resource templates labels.
     */
    public function resourceTemplateLabels($labelsOrIds = null)
    {
        if (!isset(static::$resourceTemplatesLabels)) {
            static::$resourceTemplatesLabels = array_flip($this->resourceTemplatesLabels());
        }

        if (is_null($labelsOrIds)) {
            return static::$resourceTemplatesLabels;
        }

        $ids = $this->resourceTemplateIds($labelsOrIds);
        if (empty($ids)) {
            return $ids;
        }

        if (is_scalar($ids)) {
            return static::$resourceTemplatesLabels[$ids] ?? null;
        }

        // TODO Keep original order.
        return array_intersect_key(static::$resourceTemplatesLabels, array_fill_keys($ids, null));
    }
}
