<?php
namespace Annotate\Service\ControllerPlugin;

use Annotate\Mvc\Controller\Plugin\ResourceTemplateAnnotationPartMap;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ResourceTemplateAnnotationPartMapFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ResourceTemplateAnnotationPartMap(
            $services->get('ControllerPluginManager')->get('settings')
        );
    }
}
