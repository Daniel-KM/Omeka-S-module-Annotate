<?php declare(strict_types=1);

namespace Annotate;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            /*
             * Unlike previous versions, use a single resource type to manage
             * the annotation, the bodies and the targets. The values are
             * attached to the different part via a field in a specific table (annotation_value).
             * It's quicker, simpler and more versatile, because there is no
             * need for a predefined mapping of properties to each part, even if
             * it is still used for common use cases. It allows to create any
             * number of sub-resources too, so all the data-model can be
             * implemented.
             *
             * Furthermore, it avoids the doctrine mechanism that joins all
             * inherited resources in all cases, even when it is useless (for
             * example in many cases only the id is needed, that is unique among
             * all sub-resources), that has a big performance issue on some orm
             * queries.
             * @see https://github.com/doctrine/orm/issues/5961
             * @see https://github.com/doctrine/orm/issues/5980
             * @see https://github.com/doctrine/orm/pull/8704
             */
            Entity\Annotation::class => Entity\Annotation::class,
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
            'annotations' => View\Helper\Annotations::class,
            'normalizeDateTimeQuery' => View\Helper\NormalizeDateTimeQuery::class,
        ],
        'factories' => [
            'showAnnotateForm' => Service\ViewHelper\ShowAnnotateFormFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            Form\AnnotateForm::class => Service\Form\AnnotateFormFactory::class,
            Form\QuickSearchForm::class => Service\Form\QuickSearchFormFactory::class,
            Form\ResourceForm::class => Service\Form\ResourceFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\AnnotationController::class => Controller\Admin\AnnotationController::class,
            Controller\Site\AnnotationController::class => Controller\Site\AnnotationController::class,
        ],
    ],
    'column_types' => [
        'invokables' => [
            'annotation_id' => ColumnType\Id::class,
            'annotated_resource' => ColumnType\AnnotatedResource::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'annotations' => [
                [
                    'type' => 'value',
                    'header' => 'Motivation',
                    'default' => '',
                    'property_term' => 'oa:motivatedBy',
                    'max_values' => 1,
                ],
                [
                    'type' => 'annotated_resource',
                    'header' => 'Targets',
                    'default' => '',
                    'max_values' => null,
                ],
                ['type' => 'owner'],
                ['type' => 'created'],
            ],
        ],
        'public' => [
            'annotations' => [
                [
                    'type' => 'value',
                    'header' => 'Motivation',
                    'default' => '',
                    'property_term' => 'oa:motivatedBy',
                    'max_values' => 1,
                ],
                [
                    'type' => 'annotated_resource',
                    'header' => 'Targets',
                    'default' => '',
                    'max_values' => null,
                ],
                ['type' => 'owner'],
                ['type' => 'created'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'annotations' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
        ],
        'public' => [
            'annotations' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'annotations' => [
                'title' => 'Title', // @translate
                'resource_class_label' => 'Resource class', // @translate
                'owner_name' => 'Owner', // @translate
                'created' => 'Created', // @translate
            ],
        ],
        'public' => [
            'annotations' => [
                'title' => 'Title', // @translate
                'resource_class_label' => 'Resource class', // @translate
                'created' => 'Created', // @translate
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'annotate' => [
                        'type' => \Laminas\Router\Http\Literal::class,
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
                                'type' => \Laminas\Router\Http\Segment::class,
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
                                'type' => \Laminas\Router\Http\Segment::class,
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
                        'type' => \Laminas\Router\Http\Literal::class,
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
                                'type' => \Laminas\Router\Http\Segment::class,
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
                                'type' => \Laminas\Router\Http\Segment::class,
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
    'navigation' => [
        'AdminModule' => [
            'annotate' => [
                'label' => 'Annotations', // @translate
                'class' => 'o-icon- fa-hand-point-up',
                'route' => 'admin/annotate/default',
                'controller' => Controller\Admin\AnnotationController::class,
                'action' => 'browse',
                'resource' => Controller\Admin\AnnotationController::class,
                'admin_section' => 'users',
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
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => \Laminas\I18n\Translator\Loader\Gettext::class,
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        'Search annotations', // @translate
        'Annotations', // @translate
        'Web Open Annotation', // @translate
        'With the class <code>oa:Annotation</code>, it’s important to choose the part of the annotation to which the property is attached:', // @translate
        'It can be the annotation itself (default), but the body or the target too.', // @translate
        'For example, to add an indication on a uncertainty of  a highlighted segment, the property should be attached to the target, but the description of a link should be attached to the body.', // @translate
        'Standard non-ambivalent properties are automatically managed.', // @translate
        'Annotation', // @translate
        'Annotation part', // @translate
        'To comply with Annotation data model, select the part of the annotation this property will belong to.', // @translate
        'This option cannot be imported/exported currently.', // @translate
        'Annotation', // @translate
        'Annotation body', // @translate
        'Annotation target', // @translate
    ],
    'annotate' => [
        'config' => [
            'annotate_public_allow_view' => true,
            'annotate_public_allow_annotate' => false,
            'annotate_resource_template_data' => [],
        ],
        'settings' => [
            'annotate_jsonld_old_format' => false,
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
