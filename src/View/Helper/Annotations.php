<?php declare(strict_types=1);
namespace Annotate\View\Helper;

use Annotate\Mvc\Controller\Plugin\ResourceAnnotations;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Annotations extends AbstractHelper
{
    /**
     * @var ResourceAnnotations
     */
    protected $resourceAnnotationsPlugin;

    public function __construct(ResourceAnnotations $resourceAnnotationsPlugin)
    {
        $this->resourceAnnotationsPlugin = $resourceAnnotationsPlugin;
    }

    /**
     * Return the partial to display annotations.
     *
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $resourceAnnotationsPlugin = $this->resourceAnnotationsPlugin;
        $annotations = $resourceAnnotationsPlugin($resource);
        echo $this->getView()->partial(
            'common/site/annotation-resource',
            [
                'resource' => $resource,
                'annotations' => $annotations,
            ]
        );
    }
}
