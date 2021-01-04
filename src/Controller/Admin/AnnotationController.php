<?php declare(strict_types=1);
namespace Annotate\Controller\Admin;

use Annotate\Entity\Annotation;
use Annotate\Form\AnnotateForm;
use Annotate\Form\QuickSearchForm;
use Annotate\Form\ResourceForm;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Stdlib\Message;

class AnnotationController extends AbstractActionController
{
    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('annotations', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'browse'], true));
        $formSearch->setAttribute('id', 'annotation-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/annotate/default', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute('admin/annotate/default', ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $resources = $response->getContent();
        $view->setVariable('resources', $resources);
        $view->setVariable('annotations', $resources);
        $view->setVariable('formSearch', $formSearch);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('annotations', $this->params('id'));

        $view = new ViewModel;
        $resource = $response->getContent();
        $view->setVariable('resource', $resource);
        $view->setVariable('annotation', $resource);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('annotations', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $resource);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    // TODO Make possible to add an annotation directly (not only ajax or specialized annotation)?

    /**
     * Annotate a resource.
     *
     * Equivalent to action "add", but without specific page (so via ajax).
     */
    public function annotateAction()
    {
        $redirect = $this->params()->fromQuery('redirect');

        $isAjax = $this->getRequest()->isXmlHttpRequest();
        if (!$redirect && !$isAjax) {
            $this->messenger()->addError('Only a resource can be annotated.'); // @translate
            $urlHelper = $this->viewHelpers()->get('url');
            return $this->redirect()->toUrl($urlHelper('admin'));
        }

        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            if ($isAjax) {
                return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
            }
            $this->messenger()->addError('Unauthorized access.'); // @translate
            return $this->redirect()->toUrl($redirect);
        }

        // TODO Move validation inside form and adapter.
        $form = $this->getForm(AnnotateForm::class);
        $data = $this->params()->fromPost();

        $resourceId = $data['oa:hasTarget'][0]['oa:hasSource'][0]['value_resource_id'];
        if (empty($resourceId)) {
            if ($isAjax) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            } else {
                $this->messenger()->addError('Resource not found.'); // @translate
                return $this->redirect()->toUrl($redirect);
            }
        }

        $api = $this->api();

        $resource = $api->read('resources', ['id' => $resourceId])->getContent();
        if (!$resource) {
            if ($isAjax) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            } else {
                $this->messenger()->addError('Resource not found'); // @translate
                return $this->redirect()->toUrl($redirect);
            }
        }

        $form->setData($data);
        if (!$form->isValid()) {
            if ($isAjax) {
                return $this->jsonError($form->getMessages());
            } else {
                $this->messenger()->addFormErrors($form);
                return $this->redirect()->toUrl($redirect);
            }
        }

        // TODO Check of data form is currently not available.
        // $data = $form->getData();

        // Check if there is a value or a selector.
        // TODO Improve the checks of the annotation and move them in the right place.
        $bodyValue = (isset($data['oa:hasBody'][0]['rdf:value'][0]['@value'])
                && strlen(trim($data['oa:hasBody'][0]['rdf:value'][0]['@value'])))
            ? trim($data['oa:hasBody'][0]['rdf:value'][0]['@value'])
            : null;
        $targetValue = (isset($data['oa:hasTarget'][0]['rdf:value'][0]['@value'])
                && strlen(trim($data['oa:hasTarget'][0]['rdf:value'][0]['@value'])))
            ? trim($data['oa:hasTarget'][0]['rdf:value'][0]['@value'])
            : null;
        if (is_null($bodyValue) && is_null($targetValue)) {
            $message = 'The annotation is empty.'; // @translate
            if ($isAjax) {
                return $this->jsonError($message);
            } else {
                $this->messenger()->addError($message);
                return $this->redirect()->toUrl($redirect);
            }
        }

        // Add the format of the body.
        if (is_null($bodyValue)) {
            // TODO Remove the full body when there is no body.
            // $data['o:resource_class']['o:id'] = null;
            // Has purpose is used to add information about body text only.
            unset($data['oa:hasPurpose']);
        } else {
            // "text/plain" is useless with TextualBody.
            $format = $this->isHtml($bodyValue) ? 'text/html' : null;
            if ($format) {
                // TODO Use DataTypeRDF
            }
        }

        // TODO Check the format of the selector and the value.
        if (!is_null($targetValue)) {
            $format = $this->determineMediaType($targetValue);
            if ($format) {
                $customVocab = $api->read('custom_vocabs', [
                    'label' => 'Annotation Target dcterms:format',
                ], [], ['responseContent' => 'reference'])->getContent();
                $property = $api->searchOne('properties', [
                    'term' => 'dcterms:format',
                ], [], ['responseContent' => 'reference'])->getContent();
                $data['oa:hasTarget'][0]['dcterms:format'][] = [
                    'property_id' => $property->id(),
                    'type' => 'customvocab:' . $customVocab->id(),
                    '@value' => $format,
                ];
            }

            $targetSelectorType = $data['oa:hasTarget'][0]['rdf:type'][0]['@value'];
            if (in_array($targetSelectorType, ['o:Item', 'o:ItemSet', 'o:Media'])) {
                $resourceType = $resource->getResourceJsonLdType();
                if ($targetSelectorType === $resourceType) {
                    $message = 'A resource can’t have the same resource as selector.'; // @translate
                    if ($isAjax) {
                        return $this->jsonError($message);
                    } else {
                        $this->messenger()->addError($message);
                        return $this->redirect()->toUrl($redirect);
                    }
                }
                if ($resourceType === 'o:Media') {
                    $message = 'A media can’t have a resource selector.'; // @translate
                    if ($isAjax) {
                        return $this->jsonError($message);
                    } else {
                        $this->messenger()->addError($message);
                        return $this->redirect()->toUrl($redirect);
                    }
                }
                if ($resourceType === 'o:Item' && $targetSelectorType === 'o:ItemSet') {
                    $message = 'An item can’t have an item set selector.'; // @translate
                    if ($isAjax) {
                        return $this->jsonError($message);
                    } else {
                        $this->messenger()->addError($message);
                        return $this->redirect()->toUrl($redirect);
                    }
                }

                $targetSelectorResourceType = $targetSelectorType === 'o:Item' ? 'items' : 'media';

                // Check if the target value is a resource url.
                if (!((int) $targetValue)) {
                    $url = $this->viewHelpers()->get('url');
                    $testTargetValue = null;
                    $apiUrl = $url('api/default', ['resource' => $targetSelectorResourceType], ['force_canonical' => true]) . '/';
                    if (mb_strpos($targetValue, $apiUrl) === 0) {
                        $testTargetValue = (int) mb_substr($targetValue, mb_strlen($apiUrl));
                    } else {
                        $apiUrl = $url('api/default', ['resource' => $targetSelectorResourceType]) . '/';
                        if (mb_strpos($targetValue, $apiUrl) === 0) {
                            $testTargetValue = (int) mb_substr($targetValue, mb_strlen($apiUrl));
                        }
                    }
                    if (empty($testTargetValue)) {
                        $message = new Message('The target selector "%s" cannot be identified: it should be a resource id or a resource api url.', // @translate
                            $targetValue);
                        if ($isAjax) {
                            return $this->jsonError($message);
                        } else {
                            $this->messenger()->addError($message);
                            return $this->redirect()->toUrl($redirect);
                        }
                    }
                    $targetValue = $testTargetValue;
                }

                $selectorResource = $api->searchOne($targetSelectorResourceType, ['id' => (int) $targetValue])->getContent();
                if (empty($selectorResource)) {
                    $message = new Message('There is no %s with id #%s.', $targetSelectorType, $targetValue); // @translate
                    if ($isAjax) {
                        return $this->jsonError($message);
                    } else {
                        $this->messenger()->addError($message);
                        return $this->redirect()->toUrl($redirect);
                    }
                }

                $targetValue = (int) $targetValue;
                $test = false;
                switch ($resource->getResourceJsonLdType()) {
                    case 'o:ItemSet':
                        // TODO Check if the item is in the item set.
                        // TODO Check if the media is in the item set.
                        $test = true;
                        break;
                    case 'o:Item':
                        // Check if the media is in the item.
                        foreach ($resource->media() as $media) {
                            if ($media->id() === $targetValue) {
                                $test = true;
                                break;
                            }
                        }
                        break;
                }
                if (!$test) {
                    $message = new Message('There is no %s with id #%s that belongs to resource #%s.', // @translate
                        $targetSelectorType, $targetValue, $resourceId);
                    if ($isAjax) {
                        return $this->jsonError($message);
                    } else {
                        $this->messenger()->addError($message);
                        return $this->redirect()->toUrl($redirect);
                    }
                }

                // Convert the text selector into a resource selector.
                $data['oa:hasTarget'][0]['rdf:value'][0] = [
                    'property_id' => $data['oa:hasTarget'][0]['rdf:value'][0]['property_id'],
                    'type' => 'resource',
                    'value_resource_id' => $targetValue,
                ];
            }
        }

        // The form contains errors if any.
        $response = $this->api($form)->create('annotations', $data);
        if (!$response) {
            if ($isAjax) {
                return new JsonModel([
                    'error' => $form->getMessages(),
                ]);
            } else {
                return $this->redirect()->toUrl($redirect);
            }
        }

        $annotation = $response->getContent();

        if ($isAjax) {
            return new JsonModel([
                'content' => [
                    'resource_id' => $resourceId,
                    'annotation' => $annotation->getJsonLd(),
                    'moderation' => !$this->userIsAllowed(Annotation::class, 'update'),
                ],
            ]);
        }

        $message = new Message(
            'Resource #%d successfully annotated.', // @translate
            $resourceId
        );
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toUrl($redirect);
    }

    public function editAction()
    {
        $form = $this->getForm(ResourceForm::class);

        $response = $this->api()->read('annotations', $this->params('id'));
        $resource = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('annotation', $resource);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->params()->fromPost();
        if (isset($data['values_json']) && $valuesJson = json_decode($data['values_json'], true)) {
            $data = array_merge_recursive($data, $valuesJson);
            unset($data['values_json']);
        }

        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        // TODO Make data available from the form.
        // $data = $form->getData();
        $data = $resource->divideMergedValues($data);
        $response = $this->api($form)->update('annotations', $resource->id(), $data);
        if (!$response) {
            return $view;
        }

        $this->messenger()->addSuccess('Annotation successfully updated'); // @translate
        return $this->redirect()->toUrl($response->getContent()->url());
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('annotations', $this->params('id'));
        $resource = $response->getContent();
        $values = $resource->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'annotation');
        $view->setVariable('partialPath', 'annotate/admin/annotation/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('annotation', $resource);
        $view->setVariable('values', json_encode($values));

        // With a redirect, the Omeka view helper deleteConfirm cannot be used.
        $redirect = $this->params()->fromQuery('redirect');
        if ($redirect) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setAttribute('action', $resource->url('delete') . '?' . http_build_query(['redirect' => $redirect]));
            $view->setVariable('form', $form);
            $view->setTemplate('annotate/admin/annotation/delete-confirm-redirect');
        }

        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('annotations', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Annotation successfully deleted.'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $redirect = $this->params()->fromQuery('redirect');
        return $redirect
            ? $this->redirect()->toUrl($redirect)
            : $this->redirect()->toRoute('admin/annotate');
    }

    public function batchDeleteConfirmAction()
    {
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true));
        $form->setButtonLabel('Confirm delete'); // @translate
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('form', $form);
        return $view;
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one annotation to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('annotations', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Annotations successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        $this->messenger()->addError('Delete of all annotations is not supported currently.'); // @translate
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Return the annotation settings for a specific resource template (ajax only).
     */
    public function resourceTemplateDataAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }
        $resourceTemplateId = $this->params()->fromQuery('resource_template_id');
        $annotationPartMap = $this->resourceTemplateAnnotationPartMap($resourceTemplateId);
        $result = [];
        $api = $this->api();
        foreach ($annotationPartMap as $term => $value) {
            $property = $api->searchOne('properties', ['term' => $term])->getContent();
            if ($property) {
                $result[$property->id()] = $value;
            }
        }
        return new JsonModel($result);
    }

    protected function jsonError($message, $statusCode = Response::STATUS_CODE_500)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    /** TODO Move all the checks in adapter or in form. */

    /**
     * Detect if a string is html or not.
     *
     * @see \Annotate\Api\Representation\AnnotationRepresentation::isHtml()
     *
     * @param string $string
     * @return bool
     */
    protected function isHtml($string)
    {
        return $string != strip_tags((string) $string);
    }

    /**
     * Determine the media type of a string.
     *
     * Only annotation target media-types are managed.
     *
     * @todo Simplify and improve the determination of the media-type (via stream).
     * @see \Annotate\Mvc\Controller\Plugin\DivideMergedValues::determineMediaType()
     *
     * @param string $string
     * @return string|null
     */
    protected function determineMediaType($string)
    {
        $string = trim((string) $string);
        if (strlen($string) == 0) {
            return;
        }
        // TODO Json is a format, not a mime-type: may be "application/geo+json.
        if ($string === 'null' || (json_decode($string) !== null)) {
            return 'application/json';
        }
        if (strpos($string, '<svg ') === 0) {
            return 'image/svg+xml';
        }
        if (strpos($string, '<!DOCTYPE html>') === 0) {
            return 'text/html';
        }
        if (strpos($string, '<?xml ') === 0) {
            $pos = strpos($string, '<', 1);
            $str = trim(substr($string, $pos));
            if (strpos($str, '<svg ') === 0) {
                return 'image/svg+xml';
            }
            if (strpos($str, '<html>') === 0 || strpos($str, '<html ') === 0) {
                return 'text/html';
            }

            // There may be a doctype.
            $pos = strpos($str, '<', 1);
            $str = trim(substr($str, $pos));
            if (strpos($str, '<svg ') === 0) {
                return 'image/svg+xml';
            }
            if (strpos($str, '<html>') === 0 || strpos($str, '<html ') === 0) {
                return 'text/html';
            }

            return 'application/xml';
        }
        // TODO Partial xml/html.
        if ($this->isHtml($string)) {
            return 'text/html';
        }
        // TODO Find a better way to check if a string is a wkt.
        $wktTags = [
            'GEOMETRY',
            'POINT',
            'LINESTRING',
            'POLYGON',
            'MULTIPOINT',
            'MULTILINESTRING',
            'MULTIPOLYGON',
            'GEOMETRYCOLLECTION',
            'CIRCULARSTRING',
            'COMPOUNDCURVE',
            'CURVEPOLYGON',
            'MULTICURVE',
            'MULTISURFACE',
            'CURVE',
            'SURFACE',
            'POLYHEDRALSURFACE',
            'TIN',
            'TRIANGLE',
            'CIRCLE',
            'CIRCLEMARKER',
            'GEODESICSTRING',
            'ELLIPTICALCURVE',
            'NURBSCURVE',
            'CLOTHOID',
            'SPIRALCURVE',
            'COMPOUNDSURFACE',
            'BREPSOLID',
            'AFFINEPLACEMENT',
        ];
        // Get first word to check wkt.
        $firstWord = strtoupper(strtok($string, " (\n\r"));
        if (strpos($string, '(') && in_array($firstWord, $wktTags)) {
            return 'application/wkt';
        }
    }
}
