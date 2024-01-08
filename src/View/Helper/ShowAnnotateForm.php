<?php declare(strict_types=1);

namespace Annotate\View\Helper;

use Annotate\Form\AnnotateForm;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\Item;

class ShowAnnotateForm extends AbstractHelper
{
    protected $formElementManager;

    public function __construct($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Return the partial to display the annotate form.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @param array $attributes
     * @param array $data
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = [], array $attributes = [], array $data = [])
    {
        $view = $this->getView();
        if (!$view->userIsAllowed(Item::class, 'create')) {
            return '';
        }

        /** @var \Annotate\Form\AnnotateForm $form */
        $form = $this->formElementManager->get(AnnotateForm::class);
        $form->setOptions($options);
        $form->init();
        $form->setData($data);
        $form->setAttributes($attributes);
        $view->vars()->offsetSet('annotateForm', $form);
        return $view->partial('common/annotate-form');
    }
}
