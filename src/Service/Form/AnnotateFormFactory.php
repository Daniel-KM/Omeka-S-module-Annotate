<?php
namespace Annotate\Service\Form;

use Annotate\Form\AnnotateForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AnnotateFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new AnnotateForm(null, $options);
        $form->setApi($services->get('ViewHelperManager')->get('api'));
        return $form;
    }
}
