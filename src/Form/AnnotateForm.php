<?php
namespace Annotate\Form;

use Omeka\View\Helper\Api;
use Zend\Form\Element;
use Zend\Form\Form;

class AnnotateForm extends Form
{
    /**
     * @var Api
     */
    protected $api;

    public function init()
    {
        // TODO Move all static params into annotate controller?
        // TODO Improve the custom vocab module to keep "literal" as value type.

        $api = $this->api;

        $resourceTemplateId = $api->read('resource_templates', ['label' => 'Annotation'])->getContent()->id();
        $vocabulary = $api->read('vocabularies', ['prefix' => 'oa'])->getContent();
        $resourceClassId = $api->read('resource_classes', ['vocabulary' => $vocabulary->id(), 'localName' => 'Annotation'])->getContent()->id();

        // Motivated by.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation oa:Motivation'])->getContent();
        $terms = explode(PHP_EOL, $customVocab->terms());
        $terms = array_combine($terms, $terms);
        $this->add([
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
                'data-placeholder' => 'Select the purpose of this annotation…', // @translate
            ],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:motivatedBy[0][property_id]',
            'attributes' => ['value' => $this->propertyId('oa:motivatedBy')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:motivatedBy[0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);

        // The resource template is always the annotation resource template
        // when added via the standard annotation forms.
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o:resource_template[o:id]',
            'attributes' => ['value' => $resourceTemplateId],
        ]);

        // The resource class is always the standard annotation class.
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o:resource_class[o:id]',
            'attributes' => ['value' => $resourceClassId],
        ]);

        // Annotation body.
        $this->initAnnotationBody();

        // Annotation target.
        $this->initAnnotationTarget();

        $this->add([
            'name' => 'o:is_public',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Is public', // @translate
            ],
            'attributes' => [
                'value' => 1,
            ],
        ]);

        // Submit.
        $this->add([
            'type' => Element\Submit::class,
            'name' => 'submit',
            'attributes' => [
                'value' => 'Annotate it!', // @translate
                // FIXME Add icon to input; for compatibility with Omeka 1.1 and 1.0.
                'class' => 'far fa-point-up fa fa-hand-o-up',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'oa:motivatedBy[0][@value]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'o-module-annotate:body[0][oa:hasPurpose][0][@value]',
            'required' => false,
        ]);
        /*
        $inputFilter->add([
            'name' => 'o-module-annotate:body[0][dcterms:format][0][@value]',
            'required' => false,
        ]);
        */
        $inputFilter->add([
            'name' => 'o-module-annotate:target[0][rdf:type][0][@value]',
            'required' => false,
        ]);
    }

    protected function initAnnotationBody()
    {
        $api = $this->api;

        // $resourceTemplateId = $api->read('resource_templates', ['label' => 'Annotation body'])->getContent()->id();
        $vocabulary = $api->read('vocabularies', ['prefix' => 'oa'])->getContent();
        $resourceClassId = $api->read('resource_classes', ['vocabulary' => $vocabulary->id(), 'localName' => 'TextualBody'])->getContent()->id();

        // Rdf value.
        $this->add([
            'type' => Element\Textarea::class,
            'name' => 'o-module-annotate:body[0][rdf:value][0][@value]',
            'options' => [
                'label' => 'Content of the annotation', // @translate
                'info' => 'The value of the body is generally the textual content of the annotation.', // @translate
            ],
            'attributes' => [
                'rows' => 15,
                'id' => 'o-module-annotate:body[0][rdf:value][0][@value]',
                'class' => 'media-text',
                'placeholder' => 'Any plain text or html…', // @translate'
                // TODO The body is not required in some cases, for example highlight: improve the dynamic validator.
                'required' => false,
            ],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][rdf:value][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:value')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][rdf:value][0][type]',
            'attributes' => ['value' => 'literal'],
        ]);

        // Has purpose.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation oa:Motivation'])->getContent();
        $terms = explode(PHP_EOL, $customVocab->terms());
        $terms = array_combine($terms, $terms);
        $this->add([
            'type' => Element\Select::class,
            'name' => 'o-module-annotate:body[0][oa:hasPurpose][0][@value]',
            'options' => [
                'label' => 'Has purpose', // @translate
                'value_options' => $terms,
                'empty_option' => 'Select the purpose of this annotation…', // @translate
            ],
            'attributes' => [
                'rows' => 15,
                'id' => 'o-module-annotate:body[0][oa:hasPurpose][0][@value]',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select the purpose of this annotation…', // @translate
            ],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][oa:hasPurpose][0][property_id]',
            'attributes' => ['value' => $this->propertyId('oa:hasPurpose')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][oa:hasPurpose][0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);

        /* // Determined automatically via the controller (plain text or html).
        // DC Format.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation Body dcterms:format'])->getContent();
        $terms = explode(PHP_EOL, $customVocab->terms());
        $terms = array_combine($terms, $terms);
        $this ->add([
            'type' => Element\Select::class,
            'name' => 'o-module-annotate:body[0][dcterms:format][0][@value]',
            'options' => [
                'label' => 'Format', // @translate
                'value_options' => $terms,
                'empty_option' => 'Select the format of the content of the annotation…', // @translate
            ],
            'attributes' => [
                'rows' => 15,
                'id' => 'o-module-annotate:body[0][dcterms:format][0][@value]',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select the format of the content of the annotation…', // @translate
            ],
        ]);
        $this ->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][dcterms:format][0][property_id]',
            'attributes' => ['value' => $this->propertyId('dcterms:format')],
        ]);
        $this ->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][dcterms:format][0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);
        */

        /*
        // The resource template is always the annotation body resource template.
        $this ->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][o:resource_template][o:id]',
            'attributes' => ['value' => $resourceTemplateId],
        ]);
        */

        // The resource class is the textual body by default. May be different
        // when a media or a map is annotated.
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:body[0][o:resource_class][o:id]',
            'attributes' => ['value' => $resourceClassId],
        ]);
    }

    protected function initAnnotationTarget()
    {
        $api = $this->api;

        /* // TODO Add a resource template and class for annotation target?
        $resourceTemplateId = $api->read('resource_templates', ['label' => 'Annotation target'])->getContent()->id();
        $vocabulary = $api->read('vocabularies', ['prefix' => 'oa'])->getContent();
        $resourceClassId = $api->read('resource_classes', ['vocabulary' => $vocabulary->id(), 'localName' => 'Selector'])->getContent()->id();
        */

        // The source of the annotation target is the current resource.
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][oa:hasSource][0][property_id]',
            'attributes' => ['value' => $this->propertyId('oa:hasSource')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][oa:hasSource][0][type]',
            'attributes' => ['value' => 'resource'],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][oa:hasSource][0][value_resource_id]',
        ]);

        // Note, oa:hasSelector references an entity that have a rdf:type and a
        // rdf:value, or it is a simple uri.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation Target rdf:type'])->getContent();
        $terms = explode(PHP_EOL, $customVocab->terms());
        $terms = array_combine($terms, $terms);
        $this->add([
            'type' => Element\Select::class,
            'name' => 'o-module-annotate:target[0][rdf:type][0][@value]',
            'options' => [
                'label' => 'Type of the target selector', // @translate
                'value_options' => $terms,
                'empty_option' => 'Select the type of the selector, if any…', // @translate
            ],
            'attributes' => [
                'rows' => 15,
                'id' => 'oa:motivatedBy[0][@value]',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select the purpose of this annotation…', // @translate
            ],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][rdf:type][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:type')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][rdf:type][0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);

        $this->add([
            'type' => Element\Textarea::class,
            'name' => 'o-module-annotate:target[0][rdf:value][0][@value]',
            'options' => [
                'label' => 'Selector of the target', // @translate
                'info' => 'Allows to delimit a portion of the target (part of a text or an image…).', // @translate
            ],
            'attributes' => [
                'rows' => 15,
                'id' => 'o-module-annotate:target[0][rdf:value][0][@value]',
                'class' => 'media-text',
                'placeholder' => 'Any xml, json, svg, etc. according to the type of selector.', // @translate'
            ],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][rdf:value][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:value')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][rdf:value][0][type]',
            'attributes' => ['value' => 'literal'],
        ]);

        /* // TODO Add a resource template and a resource class to annotation target.
        // The resource template is always the annotation body resource template.
        $this ->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][o:resource_template][o:id]',
            'attributes' => ['value' => $resourceTemplateId],
        ]);

        // The resource class is the textual body by default.
        $this ->add([
            'type' => Element\Hidden::class,
            'name' => 'o-module-annotate:target[0][o:resource_class][o:id]',
            'attributes' => ['value' => $resourceClassId],
        ]);
        */
    }

    protected function propertyId($term)
    {
        return $this->api
            ->searchOne('properties', ['term' => $term])
            ->getContent()->id();
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
    }
}
