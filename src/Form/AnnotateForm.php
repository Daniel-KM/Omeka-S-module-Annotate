<?php declare(strict_types=1);
namespace Annotate\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\View\Helper\Api;

class AnnotateForm extends Form
{
    /**
     * @var Api
     */
    protected $api;

    public function init(): void
    {
        // TODO Move all static params into annotate controller?
        // TODO Improve the custom vocab module to keep "literal" as value type.
        // TODO Convert with fieldsets to allow check via getData().

        $api = $this->api;

        $resourceTemplate = $api->searchOne('resource_templates', ['label' => 'Annotation'])->getContent();
        $resourceTemplateId = $resourceTemplate ? $resourceTemplate->id() : null;
        $vocabulary = $api->read('vocabularies', ['prefix' => 'oa'])->getContent();
        $resourceClass = $api->read('resource_classes', ['vocabulary' => $vocabulary->id(), 'localName' => 'Annotation'])->getContent();
        $resourceClassId = $resourceClass ? $resourceClass->id() : null;
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o:resource_template[o:id]',
            'attributes' => ['value' => $resourceTemplateId],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'o:resource_class[o:id]',
            'attributes' => ['value' => $resourceClassId],
        ]);

        // Motivated by.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation oa:motivatedBy'])->getContent();
        $terms = $customVocab->terms();
        $terms = is_array($terms) ? $terms : array_filter(array_map('trim', explode(PHP_EOL, $terms)));
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
                'data-placeholder' => 'Select the motivation of this annotation…', // @translate
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
                'class' => 'far fa-hand-o-up',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'oa:motivatedBy[0][@value]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'oa:hasBody[0][oa:hasPurpose][0][@value]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'oa:hasTarget[0][rdf:type][0][@value]',
            'required' => false,
        ]);
    }

    protected function initAnnotationBody(): void
    {
        $api = $this->api;

        // Rdf value.
        $this->add([
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
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasBody[0][rdf:value][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:value')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasBody[0][rdf:value][0][type]',
            'attributes' => ['value' => 'literal'],
        ]);

        // Has purpose (only for the body, so different of motivated by).
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation oa:motivatedBy'])->getContent();
        $terms = $customVocab->terms();
        $terms = is_array($terms) ? $terms : array_filter(array_map('trim', explode(PHP_EOL, $terms)));
        $terms = array_combine($terms, $terms);
        $this->add([
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
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasBody[0][oa:hasPurpose][0][property_id]',
            'attributes' => ['value' => $this->propertyId('oa:hasPurpose')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasBody[0][oa:hasPurpose][0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);
    }

    protected function initAnnotationTarget(): void
    {
        $api = $this->api;

        // The source of the annotation target is the current resource.
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][oa:hasSource][0][property_id]',
            'attributes' => ['value' => $this->propertyId('oa:hasSource')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][oa:hasSource][0][type]',
            'attributes' => ['value' => 'resource'],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][oa:hasSource][0][value_resource_id]',
        ]);

        // Note, oa:hasSelector references an entity that have a rdf:type and a
        // rdf:value, or it is a simple uri.
        $customVocab = $api->read('custom_vocabs', ['label' => 'Annotation Target rdf:type'])->getContent();
        $terms = $customVocab->terms();
        $terms = is_array($terms) ? $terms : array_filter(array_map('trim', explode(PHP_EOL, $terms)));
        $terms = array_combine($terms, $terms);
        $this->add([
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
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][rdf:type][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:type')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][rdf:type][0][type]',
            'attributes' => ['value' => 'customvocab:' . $customVocab->id()],
        ]);

        $this->add([
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
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][rdf:value][0][property_id]',
            'attributes' => ['value' => $this->propertyId('rdf:value')],
        ]);
        $this->add([
            'type' => Element\Hidden::class,
            'name' => 'oa:hasTarget[0][rdf:value][0][type]',
            'attributes' => ['value' => 'literal'],
        ]);
    }

    protected function propertyId($term)
    {
        $property = $this->api
            ->searchOne('properties', ['term' => $term])->getContent();
        return $property ? $property->id() : null;
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api): void
    {
        $this->api = $api;
    }
}
