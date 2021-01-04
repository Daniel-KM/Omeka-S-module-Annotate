<?php declare(strict_types=1);

namespace Annotate\Form;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Helper\Url;
use Omeka\Form\Element\ResourceSelect;

class QuickSearchForm extends Form
{
    use EventManagerAwareTrait;

    /**
     * @var Url
     */
    protected $urlHelper;

    public function init(): void
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

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $this->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Search', // @translate
                'type' => 'submit',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $event = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($event);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper): void
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return \Laminas\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
