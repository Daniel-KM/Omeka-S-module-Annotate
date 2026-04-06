<?php declare(strict_types=1);

namespace Annotate\Service\Controller;

use Annotate\Controller\W3cAnnotationController;
use Annotate\Serializer\W3cAnnotation;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class W3cAnnotationControllerFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $services,
        $requestedName,
        ?array $options = null
    ) {
        return new W3cAnnotationController(
            new W3cAnnotation()
        );
    }
}
