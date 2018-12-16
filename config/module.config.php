<?php
namespace Annotate;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\Annotation::class => Entity\Annotation::class,
            // oa:hasBody can be used by oa:Annotation only.
            Entity\AnnotationBody::class => Entity\AnnotationBody::class,
            // oa:hasTarget can be used by oa:Annotation only.
            Entity\AnnotationTarget::class => Entity\AnnotationTarget::class,
            // May be added for full coverage of data model (useless for current modules):
            // oa:hasSelector can be used by body (rare) or target (mainly for
            // cartographic annotation here). The selector is not a Resource,
            // but depends on oa:ResourceSelection.
            // oa:refinedBy can be used by oa:hasSelector and oa:hasState only.
            // The oa:refinedBy is another selector or state.
            // oa:hasSource (for body (rare) or target).
            // as:items
            // oa:hasState
            // oa:hasStartSelector
            // oa:hasEndSelector
            // oa:renderedVia
            // oa:styledBy
            // as:generator
            // dcterms:creator
            // schema:audience
            // @link https://www.w3.org/TR/annotation-vocab/#as-application
            // TODO Any property can be another resource (uri), so it may be genericized, but the structure of
            // Omeka is not designed in such a way (and all values must be in the table value). Use datatype to bypass? So oa:resource:item?
            // The current desing simplifies search queries too.
        ],
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'annotations' => Api\Adapter\AnnotationAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'normalizeDateTimeQuery' => View\Helper\NormalizeDateTimeQuery::class,
        ],
        'factories' => [
            'showAnnotateForm' => Service\ViewHelper\ShowAnnotateFormFactory::class,
            'annotations' => Service\ViewHelper\AnnotationsFactory::class,
            // For compatibility with Omeka < 1.2.1.
            'resourceTemplateSelect' => Service\ViewHelper\ResourceTemplateSelectFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\AnnotateForm::class => Service\Form\AnnotateFormFactory::class,
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
            Form\ResourceForm::class => Service\Form\ResourceFormFactory::class,
            // For compatibility with Omeka < 1.2.1.
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\AnnotationController::class => Controller\Admin\AnnotationController::class,
            Controller\Site\AnnotationController::class => Controller\Site\AnnotationController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'isAnnotable' => Mvc\Controller\Plugin\IsAnnotable::class,
            'resourceAnnotations' => Mvc\Controller\Plugin\ResourceAnnotations::class,
            'totalResourceAnnotations' => Mvc\Controller\Plugin\TotalResourceAnnotations::class,
        ],
        'factories' => [
            'annotationPartMapper' => Service\ControllerPlugin\AnnotationPartMapperFactory::class,
            'divideMergedValues' => Service\ControllerPlugin\DivideMergedValuesFactory::class,
            'resourceTemplateAnnotationPartMap' => Service\ControllerPlugin\ResourceTemplateAnnotationPartMapFactory::class,
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            'annotate' => [
                'label' => 'Annotations', // @translate
                'class' => 'annotations far fa-hand-o-up',
                'route' => 'admin/annotate/default',
                'resource' => Controller\Admin\AnnotationController::class,
                'privilege' => 'browse',
                'pages' => [
                    [
                        'route' => 'admin/annotate/id',
                        'controller' => Controller\Admin\AnnotationController::class,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/annotate/default',
                        'controller' => Controller\Admin\AnnotationController::class,
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'annotate' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/annotation',
                            'defaults' => [
                                '__NAMESPACE__' => 'Annotate\Controller\Site',
                                '__SITE__' => true,
                                'controller' => Controller\Site\AnnotationController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'annotate' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/annotation',
                            'defaults' => [
                                '__NAMESPACE__' => 'Annotate\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => Controller\Admin\AnnotationController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Search annotations', // @target
        'Annotations', // @target
        'Web Open Annotation', // @target
        'With the class <code>oa:Annotation</code>, it’s important to choose the part of the annotation to which the property is attached:', // @target
        'It can be the annotation itself (default), but the body or the target too.', // @target
        'For example, to add an indication on a uncertainty of  a highlighted segment, the property should be attached to the target, but the description of a link should be attached to the body.', // @target
        'Standard non-ambivalent properties are automatically managed.', // @target
        'Annotation', // @target
        'Annotation part', // @target
        'To comply with Annotation data model, select the part of the annotation this property will belong to.', // @target
        'This option cannot be imported/exported currently.', // @target
        'Annotation', // @target
        'Annotation body', // @target
        'Annotation target', // @target
    ],
    'annotate' => [
        'config' => [
            'annotate_public_allow_view' => true,
            'annotate_public_allow_annotate' => false,
            'annotate_resource_template_data' => [],
        ],
        'site_settings' => [
            'annotate_append_item_set_show' => false,
            'annotate_append_item_show' => true,
            'annotate_append_media_show' => false,
        ],
    ],
    'csvimport' => [
        'mappings' => [
            'annotations' => [
                'label' => 'Annotations', // @translate
                'mappings' => [
                    Mapping\AnnotationMapping::class,
                    \CSVImport\Mapping\PropertyMapping::class,
                ],
            ],
        ],
        'user_settings' => [
            'csvimport_automap_user_list' => [
                'motivation' => 'annotation {oa:motivatedBy}',
                'purpose' => 'annotation_target {oa:hasPurpose}',
            ],
        ],
    ],
];
