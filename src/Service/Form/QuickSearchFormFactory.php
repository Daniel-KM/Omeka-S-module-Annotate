<?php
namespace Annotate\Service\Form;

use Interop\Container\ContainerInterface;
use Annotate\Form\QuickSearchForm;
use Laminas\ServiceManager\Factory\FactoryInterface;

class QuickSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new QuickSearchForm(null, $options);
        $form->setEventManager($services->get('EventManager'));
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form->setUrlHelper($urlHelper);
        return $form;
    }
}
