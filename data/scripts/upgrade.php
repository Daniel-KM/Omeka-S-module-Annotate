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
            ));
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
