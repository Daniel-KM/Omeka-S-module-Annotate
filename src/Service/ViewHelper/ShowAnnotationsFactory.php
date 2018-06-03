<?php
namespace Annotate\Service\ViewHelper;

use Annotate\View\Helper\ShowAnnotations;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ShowAnnotationsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceAnnotationsPlugin = $controllerPlugins->get('resourceAnnotations');
        return new ShowAnnotations($resourceAnnotationsPlugin);
    }
}
