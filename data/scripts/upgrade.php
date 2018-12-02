<?php
namespace Annotate;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
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
