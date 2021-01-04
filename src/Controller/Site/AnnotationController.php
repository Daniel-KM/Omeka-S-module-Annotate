<?php declare(strict_types=1);
namespace Annotate\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class AnnotationController extends AbstractActionController
{
    public function browseAction()
    {
        $site = $this->currentSite();

        $this->setBrowseDefaults('created');

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();

        $response = $this->api()->search('annotations', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $resources = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('site', $site);
        $view->setVariable('resources', $resources);
        $view->setVariable('annotations', $resources);
        return $view;
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $response = $this->api()->read('annotations', $this->params('id'));

        $view = new ViewModel;
        $resource = $response->getContent();
        $view->setVariable('site', $site);
        $view->setVariable('resource', $resource);
        $view->setVariable('annotation', $resource);
        return $view;
    }
}
