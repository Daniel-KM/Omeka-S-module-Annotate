<?php declare(strict_types=1);
namespace Annotate\Service\Media\Ingester;

use Annotate\Media\Ingester\AnnotationBody;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AnnotationBodyFactory implements FactoryInterface
{
    /**
     * Create the annotation body media ingester service.
     *
     * @return AnnotationBody
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AnnotationBody(
            $services->get('Omeka\File\Uploader'),
            $services->get('Omeka\File\Downloader'),
            $services->get('Omeka\File\Validator'),
            $services->get('FormElementManager'),
            $services->get('Omeka\AuthenticationService')->getIdentity()
        );
    }
}
