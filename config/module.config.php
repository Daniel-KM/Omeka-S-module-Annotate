<?php
namespace Annotate;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\Annotation::class => Entity\Annotation::class,
            Entity\AnnotationBody::class => Entity\AnnotationBody::class,
            Entity\AnnotationTarget::class => Entity\AnnotationTarget::class,
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
            // TODO Don't make bodies and targets available through api, since
            // they are not classes. See ValueHydrator.
            'annotation_bodies' => Api\Adapter\AnnotationBodyAdapter::class,
            'annotation_targets' => Api\Adapter\AnnotationTargetAdapter::class,
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
        'factories' => [
            'showAnnotateForm' => Service\ViewHelper\ShowAnnotateFormFactory::class,
            'annotations' => Service\ViewHelper\AnnotationsFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\AnnotateForm::class => Service\Form\AnnotateFormFactory::class,
            Form\ResourceForm::class => Service\Form\ResourceFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\AnnotationController::class => Controller\Admin\AnnotationController::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'isAnnotable' => Mvc\Controller\Plugin\IsAnnotable::class,
            'resourceAnnotations' => Mvc\Controller\Plugin\ResourceAnnotations::class,
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            [
                'label' => 'Annotations', // @translate
                // FIXME For compatibility with Omeka 1.1 and 1.0.
                'class' => 'annotations far fa-point-up fa-hand-o-up',
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
    'csv_import' => [
        'mappings' => [
            'annotations' => [
                Mapping\AnnotationMapping::class,
                \CSVImport\Mapping\PropertyMapping::class,
            ],
        ],
        'user_settings' => [
            'csv_import_automap_user_list' => [
                'motivation' => 'annotation {oa:motivatedBy}',
                'purpose' => 'annotation_target {oa:hasPurpose}',
            ],
        ],
    ],
    'annotate' => [
        'config' => [
        ],
        'site_settings' => [
            'annotate_append_item_set_show' => true,
            'annotate_append_item_show' => true,
            'annotate_append_media_show' => true,
        ],
    ],
];
