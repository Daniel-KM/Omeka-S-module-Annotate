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

            ->add([
                'name' => 'annotate_w3c_creator_fields',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'element_group' => 'annotate',
                    'label' => 'W3C endpoint: creator fields', // @translate
                    'info' => 'Fields to include in the creator object of the /annotations/ endpoint. The user id (API URL) is always included.', // @translate
                    'value_options' => [
                        'name' => 'Name', // @translate
                        'email' => 'Email', // @translate
                        'email_sha1' => 'Email SHA1 (pseudonymized)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'annotate-w3c-creator-fields',
                ],
            ])
        ;
    }
}
