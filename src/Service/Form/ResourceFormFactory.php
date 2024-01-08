<?php declare(strict_types=1);

namespace Annotate\Service\Form;

use Annotate\Form\ResourceForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourceFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelperManager = $services->get('ViewHelperManager');
        $form = new ResourceForm(null, $options ?? []);
        $form->setUrlHelper($viewHelperManager->get('Url'));
        $form->setEventManager($services->get('EventManager'));
        $form->setEasyMeta($services->get('EasyMeta'));
        return $form;
    }
}
