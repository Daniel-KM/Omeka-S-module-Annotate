<?php

namespace Annotate\Form;

use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\View\Helper\Url;

class QuickSearchForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init()
    {
        $this->setAttribute('method', 'get');

        $urlHelper = $this->getUrlHelper();

        $this->add([
            'type' => Element\Text::class,
            'name' => 'created',
            'options' => [
                'label' => 'Date annotated', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a date with optional comparator…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'owner_id',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Annotator', // @translate
                'resource_value_options' => [
                    'resource' => 'users',
                    'query' => [],
                    'option_text_callback' => function ($user) {
                        return $user->name();
                    },
                ],
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'owner_id',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a user…', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'users']),
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Search', // @translate
                'type' => 'submit',
            ],
        ]);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return \Zend\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
