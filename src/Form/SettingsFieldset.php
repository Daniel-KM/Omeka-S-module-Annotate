<?php declare(strict_types=1);

namespace Annotate\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
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
                'name' => 'annotate_jsonld_old_format',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate',
                    'label' => 'Use old api format (o:annotation instead of @reverse)', // @translate
                    'info' => 'Check this to keep the deprecated "o:annotation" key on resources for backward compatibility with old clients.', // @translate
                ],
                'attributes' => [
                    'id' => 'annotate-jsonld-old-format',
                ],
            ])
        ;
    }
}
