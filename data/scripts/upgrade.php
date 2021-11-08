<?php declare(strict_types=1);

namespace Annotate;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.1', '<')) {
    // The media-type is not standard, but application/wkt seems better.
    $sql = <<<'SQL'
UPDATE custom_vocab
SET terms = REPLACE(terms, 'text/wkt', 'application/wkt');
UPDATE value
SET terms = REPLACE(terms, 'text/wkt', 'application/wkt');
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    // Change the name of a custom vocab.
    $sql = <<<'SQL'
UPDATE `custom_vocab`
SET `label` = 'Annotation oa:motivatedBy'
WHERE `label` = 'Annotation oa:Motivation';
SQL;
    $connection->exec($sql);

    // Complete the annotation custom vocabularies with Omeka resource types.
    $label = 'Annotation Target rdf:type';
    try {
        $customVocab = $api
            ->read('custom_vocabs', ['label' => $label])->getContent();
    } catch (\Omeka\Api\Exception\NotFoundException $e) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            sprintf(
                'The custom vocab named "%s" is not available.', // @translate
                $label
            )
        );
    }
    $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
    $terms = array_unique(array_merge($terms, [
        'o:Item',
        'o:ItemSet',
        'o:Media',
    ]));
    $api->update('custom_vocabs', $customVocab->id(), [
        'o:label' => $label,
        'o:terms' => implode(PHP_EOL, $terms),
    ], [], ['isPartial' => true]);
}

if (version_compare($oldVersion, '3.0.5', '<')) {
    // Replace all resources rdf:value by oa:hasBody for annotation bodies.
    $rdfValueId = $api
        ->searchOne('properties', ['term' => 'rdf:value'])->getContent()
        ->id();
    $oaHasBodyId = $api
        ->searchOne('properties', ['term' => 'oa:hasBody'])->getContent()
        ->id();
    $sql = <<<SQL
UPDATE value
JOIN annotation_body ON value.resource_id = annotation_body.id
SET property_id = $oaHasBodyId
WHERE value.property_id = $rdfValueId
AND value.type = "resource"
SQL;
    $connection->exec($sql);

    // Unlike bodies, targets are saved in oa:hasSource (items in Cartography),
    // so there is no need to update them as "oa:hasTarget". Nevertheless,
    // replace all resources rdf:value by oa:hasSelector for annotation targets.
    $oaHasSelectorId = $api
        ->searchOne('properties', ['term' => 'oa:hasSelector'])->getContent()
        ->id();
    $sql = <<<SQL
UPDATE value
JOIN annotation_target ON value.resource_id = annotation_target.id
SET property_id = $oaHasSelectorId
WHERE value.property_id = $rdfValueId
AND value.type = "resource"
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.6', '<')) {
    $sql = <<<'SQL'
CREATE TABLE annotation_part (
    id INT NOT NULL,
    annotation_id INT DEFAULT NULL,
    part VARCHAR(190) NOT NULL,
    INDEX IDX_4ABEA042E075FC54 (annotation_id),
    INDEX idx_part (part),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE annotation DROP FOREIGN KEY FK_2E443EF2BF396750;
ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36E075FC54;
ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36BF396750;
ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6E075FC54;
ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6BF396750;

INSERT INTO `annotation_part` (`id`, `annotation_id`, `part`)
SELECT `id`, `id`, "Annotate\\Entity\\Annotation"
FROM `annotation`;

INSERT INTO `annotation_part` (`id`, `annotation_id`, `part`)
SELECT `id`, `annotation_id`, "Annotate\\Entity\\AnnotationBody"
FROM `annotation_body`;

INSERT INTO `annotation_part` (`id`, `annotation_id`, `part`)
SELECT `id`, `annotation_id`, "Annotate\\Entity\\AnnotationTarget"
FROM `annotation_target`;

ALTER TABLE `annotation_body` DROP `annotation_id`;
ALTER TABLE `annotation_target` DROP `annotation_id`;

UPDATE `resource`
INNER JOIN `annotation_part` annotation_part
    ON annotation_part.id = resource.id
        AND annotation_part.part <> "Annotate\\Entity\\Annotation"
LEFT JOIN `resource` parent ON parent.id = annotation_part.annotation_id
SET
    resource.resource_class_id = parent.resource_class_id,
    resource.resource_template_id = parent.resource_template_id,
    resource.is_public = parent.is_public,
    resource.created = parent.created,
    resource.modified = parent.modified
;

ALTER TABLE annotation_part ADD CONSTRAINT FK_4ABEA042E075FC54 FOREIGN KEY (annotation_id) REFERENCES annotation (id) ON DELETE CASCADE;
ALTER TABLE annotation_part ADD CONSTRAINT FK_4ABEA042BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE annotation ADD CONSTRAINT FK_2E443EF2BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE annotation_body ADD CONSTRAINT FK_D819DB36BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE annotation_target ADD CONSTRAINT FK_9F53A3D6BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
SQL;
    foreach (array_filter(explode(';', $sql)) as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.3', '<')) {
    $messenger = new Messenger();
    $message = new Message(
        'This release changed two features, so check your theme.'
    );
    $messenger->addWarning($message);
    $message = new Message(
        'In api, the key "oa:Annotation" is replaced by "o:annotation".'
    );
    $messenger->addWarning($message);

    $sql = <<<'SQL'
ALTER TABLE `annotation_part` CHANGE `annotation_id` `annotation_id` INT DEFAULT NULL;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.3.3.6', '<')) {
    $messenger = new Messenger();
    if ($this->isModuleActive('AdvancedSearch')) {
        // No need to update params when BlocksDisposition is present.
        $message = new Message(
            'This release moved the params for public display to module BlocksDisposition, so check your site settings.'
        );
    } else {
        $message = new Message(
            'This release moved the public display to module BlocksDisposition, so install it and check your site settings.'
        );
    }

    $sql = <<<SQL
DELETE FROM `site_setting`
WHERE `id` IN (
    "annotate_append_item_set_show",
    "annotate_append_item_show",
    "annotate_append_media_show"
)
SQL;
    $connection->exec($sql);

    $messenger->addWarning($message);
}
