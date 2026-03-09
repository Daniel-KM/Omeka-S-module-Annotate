<?php declare(strict_types=1);

namespace Annotate\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class AnnotationController extends AbstractActionController
{
    public function browseAction()
    {
        $site = $this->currentSite();

        $this->browse()->setDefaults('annotations');

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('annotations', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $resources = $response->getContent();

        $view = new ViewModel([
            'site' => $site,
            'resources' => $resources,
            'annotations' => $resources,
        ]);
        return $view;
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $response = $this->api()->read('annotations', $this->params('id'));
        $resource = $response->getContent();

        return new ViewModel([
            'site' => $site,
            'resource' => $resource,
            'annotation' => $resource,
        ]);
    }
}
