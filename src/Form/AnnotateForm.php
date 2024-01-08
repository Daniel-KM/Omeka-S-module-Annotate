<?php declare(strict_types=1);

namespace Annotate\Form;

use Common\Stdlib\EasyMeta;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;

class AnnotateForm extends Form
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    public function init(): void
    {
        // TODO Move all static params into annotate controller?
        // TODO Improve the custom vocab module to keep "literal" as value type.
        // TODO Convert with fieldsets to allow check via getData().
        // TODO Hidden fields can be removed since data are automatically completed during hydration.

        $resourceTemplateId = $this->easyMeta->resourceTemplateId('Annotation');
        $resourceClassId = $this->easyMeta->resourceClassId('oa:Annotation');

        $this
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'o:resource_template[o:id]',
                'attributes' => ['value' => $resourceTemplateId],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'o:resource_class[o:id]',
                'attributes' => ['value' => $resourceClassId],
            ]);

        // Motivated by.
        /** @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab */
        $customVocab = $this->api->read('custom_vocabs', ['label' => 'Annotation oa:motivatedBy'])->getContent();
        $terms = $customVocab->terms();
        $terms = array_combine($terms, $terms);
        $this
            ->add([
                'type' => Element\Select::class,
                'name' => 'oa:motivatedBy[0][@value]',
                'options' => [
                    'label' => 'Motivated by', // @translate
                    'value_options' => $terms,
                    'empty_option' => 'Select the motivation of this annotation…', // @translate
                ],
                'attributes' => [
                    'rows' => 15,
                    'id' => 'oa:motivatedBy[0][@value]',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select the motivation of this annotation…', // @translate
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:motivatedBy[0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('oa:motivatedBy'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:motivatedBy[0][type]',
                'attributes' => [
                    'value' => 'customvocab:' . $customVocab->id(),
                ],
            ]);

        // Annotation body.
        $this->initAnnotationBody();

        // Annotation target.
        $this->initAnnotationTarget();

        $this
            ->add([
                'name' => 'o:is_public',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Is public', // @translate
                ],
                'attributes' => [
                    'value' => 1,
                ],
            ])

            // Submit.
            ->add([
                'type' => Element\Submit::class,
                'name' => 'submit',
                'attributes' => [
                    'value' => 'Annotate it!', // @translate
                    'class' => 'o-icon- fa-hand-point-up fa-hand-o-up',
                ],
            ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'oa:motivatedBy[0][@value]',
                'required' => false,
            ])
            ->add([
                'name' => 'oa:hasBody[0][oa:hasPurpose][0][@value]',
                'required' => false,
            ])
            ->add([
                'name' => 'oa:hasTarget[0][rdf:type][0][@value]',
                'required' => false,
            ]);
    }

    protected function initAnnotationBody(): void
    {
        // Has purpose (only for the body, so different of motivated by).
        /** @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab */
        $customVocab = $this->api->read('custom_vocabs', ['label' => 'Annotation Body oa:hasPurpose'])->getContent();
        $terms = $customVocab->terms();
        $terms = array_combine($terms, $terms);

        $this
            // Rdf value.
            ->add([
                'type' => Element\Textarea::class,
                'name' => 'oa:hasBody[0][rdf:value][0][@value]',
                'options' => [
                    'label' => 'Content of the annotation', // @translate
                    'info' => 'The value of the body is generally the textual content of the annotation.', // @translate
                ],
                'attributes' => [
                    'rows' => 15,
                    'id' => 'oa:hasBody[0][rdf:value][0][@value]',
                    'class' => 'media-text',
                    'placeholder' => 'Any plain text or html…', // @translate
                    // TODO The body is not required in some cases, for example highlight: improve the dynamic validator.
                    'required' => false,
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasBody[0][rdf:value][0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('rdf:value'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasBody[0][rdf:value][0][type]',
                'attributes' => [
                    'value' => 'literal',
                ],
            ])
            // Purpose.
            ->add([
                'type' => Element\Select::class,
                'name' => 'oa:hasBody[0][oa:hasPurpose][0][@value]',
                'options' => [
                    'label' => 'Purpose of this content', // @translate
                    'value_options' => $terms,
                    'empty_option' => 'Select the purpose of this body content, if needed…', // @translate
                ],
                'attributes' => [
                    'rows' => 15,
                    'id' => 'oa:hasBody[0][oa:hasPurpose][0][@value]',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select the purpose of this body content, if needed…', // @translate
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasBody[0][oa:hasPurpose][0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('oa:hasPurpose'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasBody[0][oa:hasPurpose][0][type]',
                'attributes' => [
                    'value' => 'customvocab:' . $customVocab->id(),
                ],
            ]);
    }

    protected function initAnnotationTarget(): void
    {
        // Note, oa:hasSelector references an entity that have a rdf:type and a
        // rdf:value, or it is a simple uri.
        /** @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab */
        $customVocab = $this->api->read('custom_vocabs', ['label' => 'Annotation Target rdf:type'])->getContent();
        $terms = $customVocab->terms();
        $terms = array_combine($terms, $terms);

        // The source of the annotation target is the current resource.
        $this
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][oa:hasSource][0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('oa:hasSource'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][oa:hasSource][0][type]',
                'attributes' => ['value' => 'resource'],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][oa:hasSource][0][value_resource_id]',
            ])

            ->add([
                'type' => Element\Select::class,
                'name' => 'oa:hasTarget[0][rdf:type][0][@value]',
                'options' => [
                    'label' => 'Type of the target selector', // @translate
                    'value_options' => $terms,
                    'empty_option' => 'Select the selector type to specify a subpart of the resource, if needed…', // @translate
                ],
                'attributes' => [
                    'rows' => 15,
                    'id' => 'oa:motivatedBy[0][@value]',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select the selector type to specify a subpart of the resource, if needed…', // @translate
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][rdf:type][0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('rdf:type'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][rdf:type][0][type]',
                'attributes' => [
                    'value' => 'customvocab:' . $customVocab->id(),
                ],
            ])

            ->add([
                'type' => Element\Textarea::class,
                'name' => 'oa:hasTarget[0][rdf:value][0][@value]',
                'options' => [
                    'label' => 'Target selector', // @translate
                    'info' => 'Allows to delimit a portion of the resource (part of a text, an image or an item…).', // @translate
                ],
                'attributes' => [
                    'rows' => 15,
                    'id' => 'oa:hasTarget[0][rdf:value][0][@value]',
                    'class' => 'media-text',
                    'placeholder' => 'Any xml, json, svg, media api url, media id, etc. according to the type of selector.', // @translate
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][rdf:value][0][property_id]',
                'attributes' => [
                    'value' => $this->easyMeta->propertyId('rdf:value'),
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'oa:hasTarget[0][rdf:value][0][type]',
                'attributes' => ['value' => 'literal'],
            ]);
    }

    public function setApi(ApiManager $api): self
    {
        $this->api = $api;
        return $this;
    }

    public function setEasyMeta(EasyMeta $easyMeta): self
    {
        $this->easyMeta = $easyMeta;
        return $this;
    }
}
