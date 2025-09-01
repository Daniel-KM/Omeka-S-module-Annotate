<?php declare(strict_types=1);

namespace Annotate;

use Common\Stdlib\PsrMessage;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var bool $skipMessage
 */
$services = $this->getServiceLocator();

if (!method_exists($this, 'getManageModuleAndResources')) {
    $plugins = $services->get('ControllerPluginManager');
    $translate = $plugins->get('translate');
    $message = new \Omeka\Stdlib\Message(
        $translate('This module requires module %1$s version %2$s or greater.'), // @translate
        'Common', '3.4.72'
    );
    throw new ModuleCannotInstallException((string) $message);
}

$manageModuleAndResources = $this->getManageModuleAndResources();

$module = __NAMESPACE__;
$filepath = dirname(__DIR__, 2) . '/data/vocabularies/oa.json';
$data = file_get_contents($filepath);
$data = json_decode($data, true);
$manageModuleAndResources->createOrUpdateVocabulary($data, $module);

if (!empty($skipMessage)) {
    $messenger = $services->get('ControllerPluginManager')->get('messenger');
    $message = new PsrMessage(
        'The vocabulary "{vocabulary}" was updated successfully.', // @translate
        ['vocabulary' => pathinfo($filepath, PATHINFO_FILENAME)]
    );
    $messenger->addSuccess($message);
}
