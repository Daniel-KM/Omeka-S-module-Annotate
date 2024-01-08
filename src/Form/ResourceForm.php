<?php declare(strict_types=1);

namespace Annotate\Form;

use Common\Stdlib\EasyMeta;
use Laminas\Form\Element;
use Omeka\Form\Element as OmekaElement;

class ResourceForm extends \Omeka\Form\ResourceForm
{
    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    public function init(): void
    {
        parent::init();

        // A resource template with class "oa:Annotation" is required when manually edited.
        $this->add([
            'name' => 'o:resource_template[o:id]',
            'type' => OmekaElement\ResourceTemplateSelect::class,
            'options' => [
                'label' => 'Template', // @translate
                'empty_option' => null,
                'query' => [
                    'resource_class' => 'oa:Annotation',
                ],
            ],
            'attributes' => [
                'class' => 'chosen-select',
            ],
        ]);

        // The default resource template of an annotation is Annotation.
        $resourceTemplateId = $this->easyMeta->resourceTemplateId('Annotation');
        $this->get('o:resource_template[o:id]')
            ->setValue($resourceTemplateId);

        // The resource class of an annotation is always oa:Annotation.
        $resourceClassId = $this->easyMeta->resourceClassId('oa:Annotation');
        $this->add([
            'name' => 'o:resource_class[o:id]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Class', // @translate
                'value_options' => [
                    'oa' => [
                        'label' => $this->easyMeta->vocabularyLabel('oa'),
                        'options' => [
                            [
                                'label' => $this->easyMeta->resourceClassLabel('oa:Annotation'),
                                'value' => $resourceClassId,
                                'attributes' => [
                                    'data-term' => 'oa:Annotation',
                                    'data-resource-class-id' => $resourceClassId,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'attributes' => [
                'value' => $resourceClassId,
                'class' => 'chosen-select',
            ],
        ]);
    }

    public function setEasyMeta(EasyMeta $easyMeta): self
    {
        $this->easyMeta = $easyMeta;
        return $this;
    }
}
