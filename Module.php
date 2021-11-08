<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2017-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Annotate;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Annotate\Entity\Annotation;
use Annotate\Permissions\Acl;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Permissions\Acl\Acl as LaminasAcl;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\AbstractEntity;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    protected $dependency = 'CustomVocab';

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // TODO Add filters (don't display when resource is private, like media?).
        // TODO Set Acl public rights to false when the visibility filter will be ready.
        // $this->addEntityManagerFilters();
        $this->addAclRoleAndRules();
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $module = $services->get('Omeka\ModuleManager')->getModule('Generic');
        if ($module && version_compare($module->getIni('version') ?? '', '3.3.28', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Generic', '3.3.28'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');

        // TODO Replace the resource templates for annotations that are not items.

        $resourceTemplateSettings = [
            'Annotation' => [
                'oa:motivatedBy' => 'oa:Annotation',
                'rdf:value' => 'oa:hasBody',
                'oa:hasPurpose' => 'oa:hasBody',
                'dcterms:language' => 'oa:hasBody',
                'oa:hasSource' => 'oa:hasTarget',
                'rdf:type' => 'oa:hasTarget',
                'dcterms:format' => 'oa:hasTarget',
            ],
        ];

        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        foreach ($resourceTemplateSettings as $label => $data) {
            try {
                $resourceTemplate = $api->read('resource_templates', ['label' => $label])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $message = new \Omeka\Stdlib\Message(
                    'The settings to manage the annotation template are not saved. You shoud edit the resource template "Annotation" manually.' // @translate
                );
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
                $messenger->addWarning($message);
                continue;
            }
            // Add the special resource template settings.
            $resourceTemplateData[$resourceTemplate->id()] = $data;
        }
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        $this->setServiceLocator($serviceLocator);
        $services = $serviceLocator;

        if (!class_exists(\Generic\InstallResources::class)) {
            require_once file_exists(dirname(__DIR__) . '/Generic/InstallResources.php')
                ? dirname(__DIR__) . '/Generic/InstallResources.php'
                : __DIR__ . '/src/Generic/InstallResources.php';
        }

        $installResources = new \Generic\InstallResources($services);
        $installResources = $installResources();

        if (!empty($_POST['remove-vocabulary'])) {
            $prefix = 'rdf';
            $installResources->removeVocabulary($prefix);
            $prefix = 'oa';
            $installResources->removeVocabulary($prefix);
        }

        if (!empty($_POST['remove-custom-vocab'])) {
            $customVocab = 'Annotation oa:motivatedBy';
            $installResources->removeCustomVocab($customVocab);
            $customVocab = 'Annotation Body oa:hasPurpose';
            $installResources->removeCustomVocab($customVocab);
            $customVocab = 'Annotation Target dcterms:format';
            $installResources->removeCustomVocab($customVocab);
            $customVocab = 'Annotation Target rdf:type';
            $installResources->removeCustomVocab($customVocab);
        }

        if (!empty($_POST['remove-template'])) {
            $resourceTemplate = 'Annotation';
            $installResources->removeResourceTemplate($resourceTemplate);
        }

        parent::uninstall($serviceLocator);
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $vocabularyLabels = 'RDF Concepts" / "Web Annotation Ontology';
        $customVocabs = 'Annotation oa:motivatedBy" / "oa:hasPurpose" / "rdf:type" / "dcterms:format';
        $resourceTemplates = 'Annotation';

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING'); // @translate
        $html .= '</strong>' . ': ';
        $html .= '</p>';

        $html .= '<p>';
        $html .= $t->translate('All the annotations will be removed.'); // @translate
        $html .= '</p>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the values of the vocabularies "%s" will be removed too. The class of the resources that use a class of these vocabularies will be reset.'), // @translate
            $vocabularyLabels
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-vocabulary" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the vocabularies "%s"'), $vocabularyLabels); // @translate
        $html .= '</label>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the custom vocabs "%s" will be removed too.'), // @translate
            $customVocabs
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-custom-vocab" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the custom vocabs "%s"'), $customVocabs); // @translate
        $html .= '</label>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the resource templates "%s" will be removed too. The resource template of the resources that use it will be reset.'), // @translate
            $resourceTemplates
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-template" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the resource templates "%s"'), $resourceTemplates); // @translate
        $html .= '</label>';

        echo $html;
    }

    /**
     * Add ACL role and rules for this module.
     *
     * @todo Keep rights for Annotation only (body and  target are internal classes).
     */
    protected function addAclRoleAndRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // Since Omeka 1.4, modules are ordered, so Guest come after Annotate.
        // See \Guest\Module::onBootstrap().
        if (!$acl->hasRole('guest')) {
            $acl->addRole('guest');
        }

        $acl
            ->addRole(Acl::ROLE_ANNOTATOR)
            ->addRoleLabel(Acl::ROLE_ANNOTATOR, 'Annotator'); // @translate

        $settings = $services->get('Omeka\Settings');
        // TODO Set rights to false when the visibility filter will be ready.
        // TODO Check if public can annotate and flag, and read annotations and own ones.
        $publicViewAnnotate = $settings->get('annotate_public_allow_view', true);
        if ($publicViewAnnotate) {
            $publicAllowAnnotate = $settings->get('annotate_public_allow_annotate', false);
            if ($publicAllowAnnotate) {
                $this->addRulesForVisitorAnnotators($acl);
            } else {
                $this->addRulesForVisitors($acl);
            }
        }

        // Identified users can annotate. Reviewer and above can approve. Admins
        // can delete.
        $this->addRulesForAnnotator($acl);
        $this->addRulesForAnnotators($acl);
        $this->addRulesForApprobators($acl);
        $this->addRulesForAdmins($acl);
    }

    /**
     * Add ACL rules for visitors (read only).
     *
     * @todo Add rights to update annotation (flag only).
     */
    protected function addRulesForVisitors(LaminasAcl $acl): void
    {
        $acl
            ->allow(
                null,
                [Annotation::class],
                ['read']
            )
            ->allow(
                null,
                [Api\Adapter\AnnotationAdapter::class],
                ['search', 'read']
            )
            ->allow(
                null,
                [Controller\Site\AnnotationController::class],
                ['index', 'browse', 'show', 'search', 'flag']
            );
    }

    /**
     * Add ACL rules for annotator visitors.
     */
    protected function addRulesForVisitorAnnotators(LaminasAcl $acl): void
    {
        $acl
            ->allow(
                null,
                [Annotation::class],
                ['read', 'create']
            )
            ->allow(
                null,
                [Api\Adapter\AnnotationAdapter::class],
                ['search', 'read', 'create']
            )
            ->allow(
                null,
                [Controller\Site\AnnotationController::class],
                ['index', 'browse', 'show', 'search', 'add', 'flag']
            );
    }

    /**
     * Add ACL rules for annotator.
     */
    protected function addRulesForAnnotator(LaminasAcl $acl): void
    {
        // The annotator has less rights than Researcher for core resources, but
        // similar rights for annotations that Author has for core resources.
        // The rights related to annotation are set with all other annotators.
        $acl
            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                [
                    'Omeka\Controller\Admin\Index',
                    'Omeka\Controller\Admin\Item',
                    'Omeka\Controller\Admin\ItemSet',
                    'Omeka\Controller\Admin\Media',
                ],
                [
                    'index',
                    'browse',
                    'show',
                    'show-details',
                ]
            )

            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                [
                    'Omeka\Controller\Admin\Item',
                    'Omeka\Controller\Admin\ItemSet',
                    'Omeka\Controller\Admin\Media',
                ],
                [
                    'search',
                    'sidebar-select',
                ]
            )

            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                ['Omeka\Controller\Admin\User'],
                ['show', 'edit']
            )
            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                ['Omeka\Api\Adapter\UserAdapter'],
                ['read', 'update', 'search']
            )
            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                [\Omeka\Entity\User::class],
                ['read']
            )
            ->allow(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                [\Omeka\Entity\User::class],
                ['update', 'change-password', 'edit-keys'],
                new \Omeka\Permissions\Assertion\IsSelfAssertion
            )

            // TODO Remove this rule for Omeka >= 1.2.1.
            ->deny(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                [
                    'Omeka\Controller\SiteAdmin\Index',
                    'Omeka\Controller\SiteAdmin\Page',
                ]
            )
            ->deny(
                [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
                ['Omeka\Controller\Admin\User'],
                ['browse']
            );
    }

    /**
     * Add ACL rules for annotators (not visitor).
     */
    protected function addRulesForAnnotators(LaminasAcl $acl): void
    {
        $annotators = [
            \Annotate\Permissions\Acl::ROLE_ANNOTATOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $acl
            ->allow(
                $annotators,
                [Annotation::class],
                ['create']
            )
            ->allow(
                $annotators,
                [Annotation::class],
                ['update', 'delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
            ->allow(
                $annotators,
                [Api\Adapter\AnnotationAdapter::class],
                ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
            )
            ->allow(
                $annotators,
                [Controller\Site\AnnotationController::class]
            )
            ->allow(
                $annotators,
                [Controller\Admin\AnnotationController::class],
                ['index', 'search', 'browse', 'show', 'show-details', 'add', 'edit', 'delete', 'delete-confirm', 'flag']
            );
    }

    /**
     * Add ACL rules for approbators.
     */
    protected function addRulesForApprobators(LaminasAcl $acl): void
    {
        // Admin are approbators too, but rights are set below globally.
        $approbators = [
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
        ];
        // "view-all" is added via main acl factory for resources.
        $acl
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_REVIEWER],
                [Annotation::class],
                ['read', 'create', 'update']
            )
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_REVIEWER],
                [Annotation::class],
                ['delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
            ->allow(
                [\Omeka\Permissions\Acl::ROLE_EDITOR],
                [Annotation::class],
                ['read', 'create', 'update', 'delete']
            )
            ->allow(
                $approbators,
                [Api\Adapter\AnnotationAdapter::class],
                ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
            )
            ->allow(
                $approbators,
                [Controller\Site\AnnotationController::class]
            )
            ->allow(
                $approbators,
                Controller\Admin\AnnotationController::class,
                [
                    'index',
                    'search',
                    'browse',
                    'show',
                    'show-details',
                    'add',
                    'edit',
                    'delete',
                    'delete-confirm',
                    'flag',
                    'batch-approve',
                    'batch-unapprove',
                    'batch-flag',
                    'batch-unflag',
                    'batch-set-spam',
                    'batch-set-not-spam',
                    'toggle-approved',
                    'toggle-flagged',
                    'toggle-spam',
                    'batch-delete',
                    'batch-delete-all',
                    'batch-update',
                    'approve',
                    'unflag',
                    'set-spam',
                    'set-not-spam',
                    'show-details',
                ]
            );
    }

    /**
     * Add ACL rules for approbators.
     */
    protected function addRulesForAdmins(LaminasAcl $acl): void
    {
        $admins = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        ];
        $acl
            ->allow(
                $admins,
                [
                    Annotation::class,
                    Api\Adapter\AnnotationAdapter::class,
                    Controller\Site\AnnotationController::class,
                    Controller\Admin\AnnotationController::class,
                ]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Add the Open Annotation part to the representation.
        $representations = [
            'users' => UserRepresentation::class,
            'item_sets' => ItemSetRepresentation::class,
            'items' => ItemRepresentation::class,
            'media' => MediaRepresentation::class,
        ];
        foreach ($representations as $representation) {
            $sharedEventManager->attach(
                $representation,
                'rep.resource.json',
                [$this, 'filterJsonLd']
            );
        }

        // TODO Add the special data to the resource template.

        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addHeadersAdmin']
        );

        // Allows to search resource template by resource class.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ResourceTemplateAdapter::class,
            'api.search.query',
            [$this, 'searchQueryResourceTemplate']
        );

        // Events for the public front-end.
        $controllers = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            // Add the annotations to the resource show public pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'displayPublic']
            );
        }

        // Manage the search query with special fields that are not present in
        // default search form.
        $sharedEventManager->attach(
            \Annotate\Controller\Admin\AnnotationController::class,
            'view.advanced_search',
            [$this, 'displayAdvancedSearchAnnotation']
        );
        // Filter the search filters for the advanced search pages.
        $sharedEventManager->attach(
            \Annotate\Controller\Admin\AnnotationController::class,
            'view.search.filters',
            [$this, 'filterSearchFiltersAnnotation']
        );

        // Events for the admin board.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.section_nav',
                [$this, 'addTab']
            );
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'displayListAndForm']
            );

            // Add the details to the resource browse admin pages.
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'viewDetails']
            );

            // Add the tab form to the resource edit admin pages.
            // Note: it can't be added to the add form, because it has no sense
            // to annotate something that does not exist.
            $sharedEventManager->attach(
                $controller,
                'view.edit.section_nav',
                [$this, 'addTab']
            );
            $sharedEventManager->attach(
                $controller,
                'view.edit.form.after',
                [$this, 'displayList']
            );
        }

        // Add a tab to the resource template admin pages.
        // Can be added to the view of the form too.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ResourceTemplateAdapter::class,
            'api.create.post',
            [$this, 'handleResourceTemplateCreateOrUpdatePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ResourceTemplateAdapter::class,
            'api.update.post',
            [$this, 'handleResourceTemplateCreateOrUpdatePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ResourceTemplateAdapter::class,
            'api.delete.post',
            [$this, 'handleResourceTemplateDeletePost']
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );

        // Module Csv Import.
        $sharedEventManager->attach(
            \CSVImport\Form\MappingForm::class,
            'form.add_elements',
            [$this, 'addCsvImportFormElements']
        );
    }

    /**
     * Add the annotation data to the resource JSON-LD.
     */
    public function filterJsonLd(Event $event): void
    {
        if (!$this->userCanRead()) {
            return;
        }

        $resource = $event->getTarget();
        $entityColumnName = $this->columnNameOfRepresentation($resource);
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $annotations = $api
            ->search('annotations', [$entityColumnName => $resource->id()], ['responseContent' => 'reference'])
            ->getContent();
        if ($annotations) {
            $jsonLd = $event->getParam('jsonLd');
            // It must be a property, not a class. Cf. iiif too, that uses annotations = iiif_prezi:annotations
            // Note: Omeka uses singular for "o:item_set" (array for item), but
            // plural for "o:items" (a link for item sets), but singular "o:item"
            // for medias. "o:site" uses singular (array for items).
            // Anyway, all other terms are singular (dublin core, etc.).
            $jsonLd['o:annotation'] = $annotations;
            /*
            $jsonLd['o:annotations'] = [
                '@id' => $this->getServiceLocator()->get('ViewHelperManager')->get('url')
                    ->__invoke('api/default', ['resource' => 'annotations'], ['query' => ['resource_id' => $resource->id()], 'force_canonical' => true]),
            ];
            */
            $event->setParam('jsonLd', $jsonLd);
        }
    }

    /**
     * Helper to filter search queries for resource templates.
     */
    public function searchQueryResourceTemplate(Event $event): void
    {
        $query = $event->getParam('request')->getContent();
        if (empty($query['resource_class'])) {
            return;
        }

        list($prefix, $localName) = explode(':', $query['resource_class']);

        /** @var \Doctrine\ORM\QueryBuilder $qb */
        $qb = $event->getParam('queryBuilder');
        /** @var \Omeka\Api\Adapter\ResourceTemplateAdapter $adapter */
        $adapter = $event->getTarget();

        $expr = $qb->expr();
        $resourceClassAlias = $adapter->createAlias();
        $qb->innerJoin(
            'omeka_root.resourceClass',
            $resourceClassAlias
        );
        $vocabularyAlias = $adapter->createAlias();
        $qb->innerJoin(
            \Omeka\Entity\Vocabulary::class,
            $vocabularyAlias,
            \Doctrine\ORM\Query\Expr\Join::WITH,
            $expr->eq($resourceClassAlias . '.vocabulary', $vocabularyAlias . '.id')
        );
        $qb->andWhere(
            $expr->andX(
                $expr->eq(
                    $vocabularyAlias . '.prefix',
                    $adapter->createNamedParameter($qb, $prefix)
                ),
                $expr->eq(
                    $resourceClassAlias . '.localName',
                    $adapter->createNamedParameter($qb, $localName)
                )
            )
        );
    }

    /**
     * Display the advanced search form for annotations via partial.
     */
    public function displayAdvancedSearchAnnotation(Event $event): void
    {
        $query = $event->getParam('query', []);
        $query['datetime'] = $query['datetime'] ?? '';
        $partials = $event->getParam('partials', []);

        // Remove the resource class field, since it is always "oa:Annotation".
        $key = array_search('common/advanced-search/resource-class', $partials);
        if ($key !== false) {
            unset($partials[$key]);
        }

        // Replace the resource template field, since the templates are
        // restricted to the class "oa:Annotation".
        $key = array_search('common/advanced-search/resource-template', $partials);
        if ($key === false) {
            $partials[] = 'common/advanced-search/resource-template-annotation';
        } else {
            $partials[$key] = 'common/advanced-search/resource-template-annotation';
        }

        $partials[] = 'common/advanced-search/date-time-annotation';

        // TODO Add a search form on the metadata of the resources.

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters of annotations for display.
     */
    public function filterSearchFiltersAnnotation(Event $event): void
    {
        $query = $event->getParam('query', []);
        $view = $event->getTarget();
        $normalizeDateTimeQuery = $view->plugin('normalizeDateTimeQuery');
        if (empty($query['datetime'])) {
            $query['datetime'] = [];
        } else {
            if (!is_array($query['datetime'])) {
                $query['datetime'] = [$query['datetime']];
            }
            foreach ($query['datetime'] as $key => $datetime) {
                $datetime = $normalizeDateTimeQuery($datetime);
                if ($datetime) {
                    $query['datetime'][$key] = $datetime;
                } else {
                    unset($query['datetime'][$key]);
                }
            }
        }
        if (!empty($query['created'])) {
            $datetime = $normalizeDateTimeQuery($query['created'], 'created');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }
        if (!empty($query['modified'])) {
            $datetime = $normalizeDateTimeQuery($query['modified'], 'modified');
            if ($datetime) {
                $query['datetime'][] = $datetime;
            }
        }

        if (empty($query['datetime'])) {
            return;
        }

        $filters = $event->getParam('filters');
        $translate = $view->plugin('translate');
        $queryTypes = [
            '>' => $translate('after'),
            '>=' => $translate('after or on'),
            '=' => $translate('on'),
            '<>' => $translate('not on'),
            '<=' => $translate('before or on'),
            '<' => $translate('before'),
            'gte' => $translate('after or on'),
            'gt' => $translate('after'),
            'eq' => $translate('on'),
            'neq' => $translate('not on'),
            'lte' => $translate('before or on'),
            'lt' => $translate('before'),
            'ex' => $translate('has any date / time'),
            'nex' => $translate('has no date / time'),
        ];

        $next = false;
        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $datetimeValue = $queryRow['value'];

            $fieldLabel = $field === 'modified' ? $translate('Modified') : $translate('Created');
            $filterLabel = $fieldLabel . ' ' . $queryTypes[$type];
            if ($next) {
                if ($joiner === 'or') {
                    $filterLabel = $translate('OR') . ' ' . $filterLabel;
                } else {
                    $filterLabel = $translate('AND') . ' ' . $filterLabel;
                }
            } else {
                $next = true;
            }
            $filters[$filterLabel][] = $datetimeValue;
        }

        $event->setParam('filters', $filters);
    }

    public function handleResourceTemplateCreateOrUpdatePost(Event $event): void
    {
        // TODO Allow to require a value for body or target via the template.

        // The acl are already checked via the api.
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $controllerPlugins = $services->get('ControllerPluginManager');
        $annotationPartMapper = $controllerPlugins->get('annotationPartMapper');

        $result = [];
        $requestContent = $request->getContent();
        $requestResourceProperties = $requestContent['o:resource_template_property'] ?? [];
        foreach ($requestResourceProperties as $propertyId => $requestResourceProperty) {
            if (!isset($requestResourceProperty['data']['annotation_part'])) {
                continue;
            }
            try {
                /** @var \Omeka\Api\Representation\PropertyRepresentation $property */
                $property = $api->read('properties', $propertyId)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                continue;
            }
            $term = $property->term();
            $result[$term] = $annotationPartMapper($term, $requestResourceProperty['data']['annotation_part']);
        }

        $resourceTemplateId = $response->getContent()->getId();
        $settings = $services->get('Omeka\Settings');
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        $resourceTemplateData[$resourceTemplateId] = $result;
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    public function handleResourceTemplateDeletePost(Event $event): void
    {
        // The acl are already checked via the api.
        $id = $event->getParam('request')->getId();
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        unset($resourceTemplateData[$id]);
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    public function addCsvImportFormElements(Event $event): void
    {
        /** @var \CSVImport\Form\MappingForm $form */
        $form = $event->getTarget();
        $resourceType = $form->getOption('resource_type');
        if ($resourceType !== 'annotations') {
            return;
        }

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        if (!$acl->userIsAllowed(Annotation::class, 'create')) {
            return;
        }

        $form->addResourceElements();
        if ($acl->userIsAllowed(\Annotate\Entity\Annotation::class, 'change-owner')) {
            $form->addOwnerElement();
        }
        $form->addProcessElements();
        $form->addAdvancedElements();
    }

    /**
     * Add the headers for admin management.
     */
    public function addHeadersAdmin(Event $event): void
    {
        // Hacked, because the admin layout doesn't use a partial or a trigger
        // for the search engine.
        $view = $event->getTarget();
        // TODO How to attach all admin events only before 1.3?
        if (!$view->params()->fromRoute('__ADMIN__')) {
            return;
        }
        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/annotate-admin.css', 'Annotate'));
        $searchUrl = sprintf('var searchAnnotationsUrl = %s;', json_encode($view->url('admin/annotate/default', ['action' => 'browse'], true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $view->headScript()
            ->appendScript($searchUrl)
            ->appendFile($view->assetUrl('js/annotate-admin.js', 'Annotate'), 'text/javascript', ['defer' => 'defer']);
    }

    /**
     * Add a tab to section navigation.
     */
    public function addTab(Event $event): void
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['annotate'] = 'Annotations'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Display a partial for a resource.
     */
    public function displayListAndForm(Event $event): void
    {
        $resource = $event->getTarget()->resource;
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $allowed = $acl->userIsAllowed(\Omeka\Entity\Item::class, 'create');

        echo '<div id="annotate" class="section annotate">';
        $this->displayResourceAnnotations($event, $resource, false);
        if ($allowed) {
            $this->displayForm($event);
        }
        echo '</div>';
    }

    /**
     * Display the list for a resource.
     */
    public function displayList(Event $event): void
    {
        echo '<div id="annotate" class="section annotate">';
        $vars = $event->getTarget()->vars();
        // Manage add/edit form.
        if (isset($vars->resource)) {
            $resource = $vars->resource;
        } elseif (isset($vars->item)) {
            $resource = $vars->item;
        } elseif (isset($vars->itemSet)) {
            $resource = $vars->itemSet;
        } elseif (isset($vars->media)) {
            $resource = $vars->media;
        } else {
            $resource = null;
        }
        $vars->offsetSet('resource', $resource);
        $this->displayResourceAnnotations($event, $resource, false);
        echo '</div>';
    }

    /**
     * Display the details for a resource.
     */
    public function viewDetails(Event $event): void
    {
        $representation = $event->getParam('entity');
        // TODO Use a paginator to limit and display all annotations dynamically in the details view (using api).
        $this->displayResourceAnnotations($event, $representation, true, ['limit' => 10]);
    }

    /**
     * Display a form.
     */
    public function displayForm(Event $event): void
    {
        $view = $event->getTarget();
        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $event->getTarget()->resource;

        $services = $this->getServiceLocator();
        $viewHelpers = $services->get('ViewHelperManager');
        $api = $viewHelpers->get('api');
        $url = $viewHelpers->get('url');

        $options = [];
        $attributes = [];
        $attributes['action'] = $url(
            'admin/annotate/default',
            ['action' => 'annotate'],
            ['query' => ['redirect' => $resource->adminUrl() . '#annotate']]
        );

        // TODO Get the post when an error occurs (but this is never the case).
        // Currently, this is a redirect.
        // $request = $services->get('Request');
        // $isPost = $request->isPost();
        // if ($isPost) {
        //     $controllerPlugins = $services->get('ControllerPluginManager');
        //     $params = $controllerPlugins->get('params');
        //     $data = $params()->fromPost();
        // }
        $data = [];
        // TODO Make the property id of oa:hasTarget/oa:hasSource static or integrate it to avoid a double query.
        $property = $api->searchOne('properties', ['term' => 'oa:hasSource'])->getContent();
        if (!$property) {
            return;
        }
        $propertyId = $property->id();
        // TODO Make the form use fieldset.
        $data['oa:hasTarget[0][oa:hasSource][0][property_id]'] = $propertyId;
        $data['oa:hasTarget[0][oa:hasSource][0][type]'] = 'resource';
        $data['oa:hasTarget[0][oa:hasSource][0][value_resource_id]'] = $resource->id();

        echo $view->showAnnotateForm($resource, $options, $attributes, $data);
    }

    /**
     * Display a partial for a resource in public.
     */
    public function displayPublic(Event $event): void
    {
        $view = $event->getTarget();
        $resource = $view->resource;
        echo $view->annotations($resource);
    }

    /**
     * Helper to display a partial for a resource.
     *
     * @param bool $listAsDiv Return the list with div, not ul.
     */
    protected function displayResourceAnnotations(
        Event $event,
        AbstractResourceEntityRepresentation $resource,
        bool $listAsDiv = false,
        array $query = []
    ): void {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceAnnotationsPlugin = $controllerPlugins->get('resourceAnnotations');
        $annotations = $resourceAnnotationsPlugin($resource, $query);
        $totalResourceAnnotationsPlugin = $controllerPlugins->get('totalResourceAnnotations');
        $totalAnnotations = $totalResourceAnnotationsPlugin($resource, $query);
        $partial = $listAsDiv
            // Quick detail view.
            ? 'common/admin/annotation-resource'
            // Full view in tab.
            : 'common/admin/annotation-resource-list';
        echo $event->getTarget()->partial(
            $partial,
            [
                'resource' => $resource,
                'annotations' => $annotations,
                'totalAnnotations' => $totalAnnotations,
            ]
        );
    }

    /**
     * Check if a user can read annotations.
     *
     * @todo Is it really useful to check if user can read annotations?
     */
    protected function userCanRead(): bool
    {
        $userIsAllowed = $this->getServiceLocator()->get('ViewHelperManager')
            ->get('userIsAllowed');
        return $userIsAllowed(Annotation::class, 'read');
    }

    /**
     * Helper to get the column id of an entity.
     */
    protected function columnNameOfEntity(AbstractEntity $resource): ?string
    {
        $entityColumnNames = [
            \Omeka\Entity\ItemSet::class => 'resource_id',
            \Omeka\Entity\Item::class => 'resource_id',
            \Omeka\Entity\Media::class => 'resource_id',
            \Omeka\Entity\User::class => 'owner_id',
        ];
        return $entityColumnNames[$resource->getResourceId()] ?? null;
    }

    /**
     * Helper to get the column id of a representation.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     */
    protected function columnNameOfRepresentation(AbstractEntityRepresentation $representation): ?string
    {
        $entityColumnNames = [
            'item-set' => 'resource_id',
            'item' => 'resource_id',
            'media' => 'resource_id',
            'user' => 'owner_id',
        ];
        return $entityColumnNames[$representation->getControllerName()] ?? null;
    }
}
