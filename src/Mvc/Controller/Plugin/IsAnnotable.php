<?php declare(strict_types=1);
namespace Annotate\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IsAnnotable extends AbstractPlugin
{
    protected $annotables = [
        \Omeka\Api\Representation\ItemRepresentation::class,
        \Omeka\Api\Representation\MediaRepresentation::class,
        \Omeka\Api\Representation\ItemSetRepresentation::class,
    ];

    /**
     * Check if a resource is annotable.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return bool
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        return in_array(get_class($resource), $this->annotables);
    }
}
