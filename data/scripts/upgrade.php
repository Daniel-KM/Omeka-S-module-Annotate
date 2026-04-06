<?php declare(strict_types=1);

namespace Annotate;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.83')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.83'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

// All old upgrade steps are wrapped in try/catch to allow
// upgrading from any old version directly to 3.4.12+.

if (version_compare($oldVersion, '3.0.1', '<')) {
    try {
        $connection->executeStatement(<<<'SQL'
            UPDATE custom_vocab
            SET terms = REPLACE(terms, 'text/wkt', 'application/wkt')
            SQL
        );
    } catch (\Throwable $e) {
        // Table or column may not exist.
    }

    try {
        $connection->executeStatement(<<<'SQL'
            UPDATE value
            SET value = REPLACE(value, 'text/wkt', 'application/wkt')
            WHERE value = 'text/wkt';
            SQL
            );
    } catch (\Throwable $e) {
        // Table or column may not exist.
    }
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    try {
        $connection->executeStatement(<<<'SQL'
            UPDATE `custom_vocab`
            SET `label` = 'Annotation oa:motivatedBy'
            WHERE `label` = 'Annotation oa:Motivation'
            SQL
        );
    } catch (\Throwable $e) {
    }
    try {
        $label = 'Annotation Target rdf:type';
        $customVocab = $api
            ->read('custom_vocabs', ['label' => $label])
            ->getContent();
        $terms = $customVocab->terms();
        $terms = is_array($terms)
            ? $terms
            : array_map('trim', explode(PHP_EOL, $terms));
        $terms = array_unique(array_merge($terms, [
            'o:Item',
            'o:ItemSet',
            'o:Media',
        ]));
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);
    } catch (\Throwable $e) {
        // CustomVocab module may not be installed yet.
    }
}

if (version_compare($oldVersion, '3.0.5', '<')) {
    try {
        $rdfValueId = (int) $api->searchOne('properties', ['term' => 'rdf:value'])->getContent()->id();
        $oaHasBodyId = (int) $api->searchOne('properties', ['term' => 'oa:hasBody'])->getContent()->id();
        $connection->executeStatement(<<<'SQL'
            UPDATE value
            JOIN annotation_body ON value.resource_id = annotation_body.id
            SET property_id = ?
            WHERE value.property_id = ?
                AND value.type = 'resource'
            SQL,
            [$oaHasBodyId, $rdfValueId]
        );
        $oaHasSelectorId = (int) $api->searchOne('properties', ['term' => 'oa:hasSelector'])->getContent()->id();
        $connection->executeStatement(<<<'SQL'
            UPDATE value
            JOIN annotation_target ON value.resource_id = annotation_target.id
            SET property_id = ?
            WHERE value.property_id = ?
                AND value.type = 'resource'
            SQL,
            [$oaHasSelectorId, $rdfValueId]
        );
    } catch (\Throwable $e) {
        // Tables annotation_body/annotation_target may not exist.
    }
}

if (version_compare($oldVersion, '3.0.6', '<')) {
    $sqls = [
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS annotation_part (
            id INT NOT NULL,
            annotation_id INT DEFAULT NULL,
            part VARCHAR(190) NOT NULL,
            INDEX IDX_4ABEA042E075FC54 (annotation_id),
            INDEX idx_part (part),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL,
        'ALTER TABLE annotation DROP FOREIGN KEY FK_2E443EF2BF396750',
        'ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36E075FC54',
        'ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36BF396750',
        'ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6E075FC54',
        'ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6BF396750',
        <<<'SQL'
        INSERT IGNORE INTO `annotation_part` (`id`, `annotation_id`, `part`)
        SELECT `id`, `id`, 'Annotate\\Entity\\Annotation' FROM `annotation`
        SQL,
        <<<'SQL'
        INSERT IGNORE INTO `annotation_part` (`id`, `annotation_id`, `part`)
        SELECT `id`, `annotation_id`, 'Annotate\\Entity\\AnnotationBody'
        FROM `annotation_body`
        SQL,
        <<<'SQL'
        INSERT IGNORE INTO `annotation_part` (`id`, `annotation_id`, `part`)
        SELECT `id`, `annotation_id`, 'Annotate\\Entity\\AnnotationTarget'
        FROM `annotation_target`
        SQL,
        'ALTER TABLE `annotation_body` DROP `annotation_id`',
        'ALTER TABLE `annotation_target` DROP `annotation_id`',
        <<<'SQL'
        UPDATE `resource`
        INNER JOIN `annotation_part` ap
            ON ap.id = resource.id
            AND ap.part <> 'Annotate\\Entity\\Annotation'
        LEFT JOIN `resource` parent
            ON parent.id = ap.annotation_id
        SET
            resource.resource_class_id = parent.resource_class_id,
            resource.resource_template_id = parent.resource_template_id,
            resource.is_public = parent.is_public,
            resource.created = parent.created,
            resource.modified = parent.modified
        SQL,
        <<<'SQL'
        ALTER TABLE annotation_part ADD CONSTRAINT FK_4ABEA042E075FC54
            FOREIGN KEY (annotation_id) REFERENCES annotation (id)
            ON DELETE CASCADE
        SQL,
        <<<'SQL'
        ALTER TABLE annotation_part ADD CONSTRAINT FK_4ABEA042BF396750
            FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE
        SQL,
        <<<'SQL'
        ALTER TABLE annotation ADD CONSTRAINT FK_2E443EF2BF396750
            FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE
        SQL,
        <<<'SQL'
        ALTER TABLE annotation_body ADD CONSTRAINT FK_D819DB36BF396750
            FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE
        SQL,
        <<<'SQL'
        ALTER TABLE annotation_target ADD CONSTRAINT FK_9F53A3D6BF396750
            FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE
        SQL,
    ];
    foreach ($sqls as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $e) {
        }
    }
}

if (version_compare($oldVersion, '3.3', '<')) {
    try {
        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `annotation_part`
            CHANGE `annotation_id` `annotation_id` INT DEFAULT NULL
            SQL
        );
    } catch (\Throwable $e) {
    }
}

if (version_compare($oldVersion, '3.3.3.6', '<')) {
    try {
        $connection->executeStatement(<<<'SQL'
            DELETE FROM `site_setting`
            WHERE `id` IN (
                'annotate_append_item_set_show',
                'annotate_append_item_show',
                'annotate_append_media_show'
            )
            SQL
        );
    } catch (\Throwable $e) {
    }
}

if (version_compare($oldVersion, '3.4.3.8', '<')) {
    try {
        require_once __DIR__ . '/upgrade_vocabulary.php';
    } catch (\Throwable $e) {
    }
}

if (version_compare($oldVersion, '3.4.12', '<')) {
    // Enable placement on all existing sites to keep previous
    // behavior.
    $defaultPlacement = [
        'after/items',
        'after/media',
        'after/item_sets',
    ];
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])
        ->getContent();
    /** @var \Omeka\Settings\SiteSettings $siteSettings */
    $siteSettings = $services->get('Omeka\Settings\Site');
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        if ($siteSettings->get('annotate_placement') === null) {
            $siteSettings->set(
                'annotate_placement',
                $defaultPlacement
            );
        }
    }
    $messenger->addSuccess(
        'A resource block has been added for new themes. A new site setting "Annotations placement" has been added for old themes.' // @translate
    );

    $messenger->addWarning(
        'The JSON-LD key "o:annotation" on resources has been replaced by "@reverse.oa:hasTarget" for W3C conformance. Update any client code that depends on the old key. A main setting allows to keep the old format.' // @translate
    );
    // Enable old format by default on upgrade for backward compatibility.
    // $settings->set('annotate_jsonld_old_format', true);

    // Migrate from multi-entity model (annotation_part + annotation_body + annotation_target)
    // to single-entity model (annotation + annotation_value side-table).

    // An old upgrade that was bad.
    try {
        $connection->executeStatement(<<<'SQL'
            UPDATE value
            SET value = REPLACE(value, 'text/wkt', 'application/wkt')
            WHERE value = 'text/wkt';
            SQL
        );
    } catch (\Throwable $e) {
        // Table or column may not exist.
    }

    $hasAnnotationPart = (bool) $connection->executeQuery(<<<'SQL'
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
            AND table_name = 'annotation_part'
        SQL
    )->fetchOne();

    if ($hasAnnotationPart) {
        $messenger->addWarning(
            'Migrating annotations to new single-entity model.' // @translate
        );

        // Step 0: Clean orphaned body/target with NULL annotation_id.
        $connection->executeStatement(<<<'SQL'
            DELETE ap FROM annotation_part ap
            LEFT JOIN annotation_part a2
                ON a2.id = ap.annotation_id AND a2.part = 'Annotate\\Entity\\Annotation'
            WHERE ap.part IN ('Annotate\\Entity\\AnnotationBody', 'Annotate\\Entity\\AnnotationTarget')
                AND a2.id IS NULL
            SQL
        );

        // Step 1: Create annotation_value table.
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `annotation_value` (
                `id` INT NOT NULL,
                `annotation_id` INT NOT NULL,
                `field` VARCHAR(190) NOT NULL,
                `ordinal` SMALLINT NOT NULL DEFAULT 1,
                INDEX IDX_annotation_value_parts (`annotation_id`, `field`, `ordinal`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL
        );
        try {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `annotation_value`
                    ADD CONSTRAINT FK_annotation_value_annotation
                    FOREIGN KEY (`annotation_id`) REFERENCES `annotation` (`id`)
                    ON DELETE CASCADE
                SQL
            );
        } catch (\Throwable $e) {
        }
        try {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `annotation_value`
                    ADD CONSTRAINT FK_annotation_value_value
                    FOREIGN KEY (`id`) REFERENCES `value` (`id`)
                    ON DELETE CASCADE
                SQL
            );
        } catch (\Throwable $e) {
        }

        // Step 2: Populate annotation_value from annotation_part.
        // Annotation-level values.
        $connection->executeStatement(<<<'SQL'
            INSERT IGNORE INTO `annotation_value` (`annotation_id`, `id`, `field`, `ordinal`)
            SELECT ap.id, v.id, 'annotation', 0
            FROM `value` v
            INNER JOIN `annotation_part` ap ON ap.id = v.resource_id
            WHERE ap.part = 'Annotate\\Entity\\Annotation'
            SQL
        );

        // Body values with ordinal (ROW_NUMBER per annotation).
        $connection->executeStatement(<<<'SQL'
            INSERT IGNORE INTO `annotation_value` (`annotation_id`, `id`, `field`, `ordinal`)
            SELECT ap.annotation_id, v.id, 'body', 1 + (
                SELECT COUNT(DISTINCT ap2.id)
                FROM annotation_part ap2
                WHERE ap2.annotation_id = ap.annotation_id
                    AND ap2.part = 'Annotate\\Entity\\AnnotationBody'
                    AND ap2.id < ap.id
            )
            FROM `value` v
            INNER JOIN `annotation_part` ap ON ap.id = v.resource_id
            WHERE ap.part = 'Annotate\\Entity\\AnnotationBody'
            SQL
        );

        // Target values with ordinal (ROW_NUMBER per annotation).
        $connection->executeStatement(<<<'SQL'
            INSERT IGNORE INTO `annotation_value` (`annotation_id`, `id`, `field`, `ordinal`)
            SELECT ap.annotation_id, v.id, 'target', 1 + (
                SELECT COUNT(DISTINCT ap2.id)
                FROM annotation_part ap2
                WHERE ap2.annotation_id = ap.annotation_id
                    AND ap2.part = 'Annotate\\Entity\\AnnotationTarget'
                    AND ap2.id < ap.id
            )
            FROM `value` v
            INNER JOIN `annotation_part` ap ON ap.id = v.resource_id
            WHERE ap.part = 'Annotate\\Entity\\AnnotationTarget'
            SQL
        );

        // Step 3: Reassign body/target values to annotation resource.
        $connection->executeStatement(<<<'SQL'
            UPDATE `value` v
            INNER JOIN `annotation_value` av ON av.id = v.id
            SET v.resource_id = av.annotation_id
            WHERE v.resource_id != av.annotation_id
            SQL
        );

        // Step 3b: Nullify value_resource_id pointing to body/target.
        $connection->executeStatement(<<<'SQL'
            UPDATE `value` v
            INNER JOIN `annotation_part` ap
                ON ap.id = v.value_resource_id
                AND ap.part IN ('Annotate\\Entity\\AnnotationBody', 'Annotate\\Entity\\AnnotationTarget')
            SET v.value_resource_id = NULL
            SQL
        );

        // Step 4: Delete body/target resources.
        $connection->executeStatement(<<<'SQL'
            DELETE v FROM `value` v
            INNER JOIN `resource` r ON r.id = v.resource_id
            WHERE r.resource_type IN ('Annotate\\Entity\\AnnotationBody', 'Annotate\\Entity\\AnnotationTarget')
            SQL
        );
        $connection->executeStatement(<<<'SQL'
            DELETE FROM `resource`
            WHERE resource_type IN ('Annotate\\Entity\\AnnotationBody', 'Annotate\\Entity\\AnnotationTarget')
            SQL
        );

        // Step 5: Drop old tables.
        $connection->executeStatement('SET foreign_key_checks = 0');
        $connection->executeStatement('DROP TABLE IF EXISTS `annotation_body`');
        $connection->executeStatement('DROP TABLE IF EXISTS `annotation_target`');
        $connection->executeStatement('DROP TABLE IF EXISTS `annotation_part`');
        $connection->executeStatement('SET foreign_key_checks = 1');

        $messenger->addSuccess(
            'Annotations migrated successfully to single-entity model.' // @translate
        );
    }
}
