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
                'name' => 'annotate_public_allow_view',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate',
                    'label' => 'Allow public to view annotations', // @translate
                ],
                'attributes' => [
                    'id' => 'annotate-public-allow-view',
                ],
            ])
            ->add([
                'name' => 'annotate_public_allow_annotate',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate',
                    'label' => 'Allow public to annotate', // @translate
                    'info' => 'Allow anonymous visitors to create annotations.', // @translate
                ],
                'attributes' => [
                    'id' => 'annotate-public-allow-annotate',
                ],
            ])
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
