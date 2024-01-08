<?php declare(strict_types=1);

namespace Annotate\Service\ViewHelper;

use Annotate\View\Helper\ShowAnnotateForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ShowAnnotateFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');
        return new ShowAnnotateForm($formElementManager);
    }
}
