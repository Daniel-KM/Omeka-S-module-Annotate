<?php
namespace Annotate\Mvc\Controller\Plugin;

use Annotate\Entity\Annotation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ResourceAnnotations extends AbstractPlugin
{
    /**
     * Helper to return the list of annotations of a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return Annotation[]
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        return $this->getController()->api()
            ->search('annotations', ['resource_id' => $resource->id()])
            ->getContent();
    }
}
