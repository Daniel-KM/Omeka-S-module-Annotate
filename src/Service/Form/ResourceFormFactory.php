<?php
namespace Annotate\Service\Form;

use Annotate\Form\ResourceForm;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ResourceFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelperManager = $services->get('ViewHelperManager');
        $form = new ResourceForm;
        $form->setUrlHelper($viewHelperManager->get('Url'));
        $form->setEventManager($services->get('EventManager'));
        $form->setApi($viewHelperManager->get('api'));
        return $form;
    }
}
