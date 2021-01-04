<?php declare(strict_types=1);
namespace Annotate\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    public function init(): void
    {
        $this->setLabel('Annotate'); // @translate

        $this->add([
            'name' => 'annotate_append_item_set_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to item set page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'annotate_append_item_set_show',
            ],
        ]);

        $this->add([
            'name' => 'annotate_append_item_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to item page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'annotate_append_item_show',
            ],
        ]);

        $this->add([
            'name' => 'annotate_append_media_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to media page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'id' => 'annotate_append_media_show',
            ],
        ]);
    }
}
