<?php declare(strict_types=1);

namespace Annotate\Service\ControllerPlugin;

use Annotate\Mvc\Controller\Plugin\DivideMergedValues;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DivideMergedValuesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new DivideMergedValues(
            $services->get('Omeka\ApiManager'),
            $services->get('EasyMeta')
        );
    }
}
