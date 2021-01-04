<?php declare(strict_types=1);
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Annotate\Service\Form\Element;

use Annotate\Form\Element\ResourceTemplateSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ResourceTemplateSelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
