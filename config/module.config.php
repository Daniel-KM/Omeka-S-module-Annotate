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
            // Bodies and targets should not be available through api since they
            // are not meaningfull objects, but part of the main annotation one.
            // Nevertheless, they are resources with property values, and the
            // adapter must be available. See ValueHydrator or ResourceTemplateProperty.
            // These feature is working, but will be disabled once a way to
            // import them by csv import will be found (oa:hasBody::dcterms:creator?)
            // TODO Disable annotation_bodies and annotation_targets api.
            // Eventually create another class for selector, refinedBy, etc.
            /** @deprecated Api manager for bodies and targets will be removed soon. */
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
            'divideMergedValues' => Service\ControllerPlugin\DivideMergedValuesFactory::class,
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
