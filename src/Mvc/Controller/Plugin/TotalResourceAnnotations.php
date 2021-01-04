<?php declare(strict_types=1);
namespace Annotate\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class TotalResourceAnnotations extends AbstractPlugin
{
    /**
     * Helper to return the total of annotations of a resource, without limit.
     *
     * @todo Manage properties of targets and bodies.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query
     * @return int
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        $query['resource_id'] = $resource->id();
        $query['limit'] = 0;
        unset($query['page']);
        unset($query['per_page']);
        unset($query['offset']);
        return $this->getController()->api()
            ->search('annotations', $query)
            ->getTotalResults();
    }
}
