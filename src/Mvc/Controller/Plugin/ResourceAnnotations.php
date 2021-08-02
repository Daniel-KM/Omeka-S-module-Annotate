<?php declare(strict_types=1);

namespace Annotate\Mvc\Controller\Plugin;

use Annotate\Api\Representation\AnnotationRepresentation;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class ResourceAnnotations extends AbstractPlugin
{
    /**
     * Helper to return the list of annotations of a resource.
     *
     * @return AnnotationRepresentation[]
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $query = []): array
    {
        $query['resource_id'] = $resource->id();
        return $this->getController()->api()
            ->search('annotations', $query)
            ->getContent();
    }
}
