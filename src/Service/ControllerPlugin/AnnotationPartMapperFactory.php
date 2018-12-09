<?php
namespace Annotate\Service\ControllerPlugin;

use Annotate\Mvc\Controller\Plugin\AnnotationPartMapper;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AnnotationPartMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $filepath = '/data/mappings/properties_to_annotation_parts.php';
        $map = require dirname(dirname(dirname(__DIR__))) . $filepath;
        return new AnnotationPartMapper(
            $map
        );
    }
}
