<?php declare(strict_types=1);
namespace Annotate\Service\ControllerPlugin;

use Annotate\Mvc\Controller\Plugin\AnnotationPartMapper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AnnotationPartMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $filepath = '/data/mappings/properties_to_annotation_parts.php';
        $map = require dirname(__DIR__, 3) . $filepath;
        return new AnnotationPartMapper(
            $map
        );
    }
}
