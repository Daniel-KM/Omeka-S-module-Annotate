<?php declare(strict_types=1);

namespace Annotate\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Annotations extends AbstractHelper
{
    /**
     * Return the partial to display annotations.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource): string
    {
        $view = $this->getView();
        $query = ['resource_id' => $resource->id()];
        $response = $view->api()->search('annotations', $query);
        $annotations = $response->getContent();
        $totalAnnotations = $response->getTotalResults();
        return $view->partial(
            'common/site/annotation-resource',
            [
                'resource' => $resource,
                'annotations' => $annotations,
                'totalAnnotations' => $totalAnnotations,
            ]
        );
    }
}
