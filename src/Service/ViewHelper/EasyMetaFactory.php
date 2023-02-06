<?php declare(strict_types=1);

namespace Annotate\Service\ViewHelper;

use Annotate\View\Helper\EasyMeta;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EasyMetaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new EasyMeta(
            $services->get('Omeka\Connection')
        );
    }
}
