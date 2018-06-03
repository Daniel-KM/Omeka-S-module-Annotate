<?php
namespace Annotate\Mvc\Controller\Plugin;

use Annotate\Api\Representation\AnnotationRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ResourceAnnotations extends AbstractPlugin
{
    /**
     * Helper to return the list of annotations of a resource.
     *
     * @todo Manage properties of targets and bodies.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query
     * @return AnnotationRepresentation[]
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        $query['resource_id'] = $resource->id();
        return $this->getController()->api()
            ->search('annotations', $query)
            ->getContent();
    }
}
