<?php declare(strict_types=1);
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Annotate\Service\ViewHelper;

use Annotate\View\Helper\ResourceTemplateSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ResourceTemplateSelect($services->get('FormElementManager'));
    }
}
