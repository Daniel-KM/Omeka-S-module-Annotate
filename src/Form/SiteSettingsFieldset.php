<?php declare(strict_types=1);

namespace Annotate\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Annotate'; // @translate

    protected $elementGroups = [
        'annotate' => 'Annotate', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'annotate')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'annotate_placement',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'annotate',
                    'label' => 'Display annotations (old themes)', // @translate
                    'info' => 'If unchecked, use the resource page block "Annotations" instead.', // @translate
                    'value_options' => [
                        'after/items' => 'Item show', // @translate
                        'after/media' => 'Media show', // @translate
                        'after/item_sets' => 'Item set show', // @translate
                        'before/items' => 'Item show: top', // @translate
                        'before/media' => 'Media show: top', // @translate
                        'before/item_sets' => 'Item set show: top', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'annotate_placement',
                    'required' => false,
                ],
            ])
        ;
    }
}
