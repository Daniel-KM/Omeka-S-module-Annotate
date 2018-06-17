<?php
namespace Annotate\Service\ViewHelper;

use Annotate\View\Helper\Annotations;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AnnotationsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceAnnotationsPlugin = $controllerPlugins->get('resourceAnnotations');
        return new Annotations($resourceAnnotationsPlugin);
    }
}
