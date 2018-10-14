<?php
namespace Annotate;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$api = $services->get('Omeka\ApiManager');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';

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
}
