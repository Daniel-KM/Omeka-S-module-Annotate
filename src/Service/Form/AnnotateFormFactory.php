<?php declare(strict_types=1);

namespace Annotate\Service\Form;

use Annotate\Form\AnnotateForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AnnotateFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new AnnotateForm(null, $options ?? []);
        return $form
            ->setApi($services->get('Omeka\ApiManager'))
            ->setEasyMeta($services->get('EasyMeta'));
    }
}
