<?php

/*
 * Copyright Daniel Berthereau, 2017-2018
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

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationBody;
use Annotate\Entity\AnnotationTarget;
use Annotate\Permissions\Acl;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\ValidationException;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\AbstractEntity;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Acl\Acl as ZendAcl;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        // TODO Add filters (don't display when resource is private, like media?).
        // TODO Set Acl public rights to false when the visibility filter will be ready.
        // $this->addEntityManagerFilters();
        $this->addAclRoleAndRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $requiredModule = 'CustomVocab';
        $this->checkModule($requiredModule, $serviceLocator);

        $vocabularies = [
            [
                'vocabulary' => [
                    'o:namespace_uri' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                    'o:prefix' => 'rdf',
                    'o:label' => 'The RDF Concepts Vocabulary (RDF)', // @translate
                    'o:comment' => 'This is the RDF Schema for the RDF vocabulary terms in the RDF Namespace, defined in RDF 1.1 Concepts.', // @translate
                ],
                'strategy' => 'file',
                'file' => 'rdf_2014-02-25.n3',
                'format' => 'turtle',
            ],
            [
                'vocabulary' => [
                    'o:namespace_uri' => 'http://www.w3.org/ns/oa#',
                    'o:prefix' => 'oa',
                    'o:label' => 'Web Annotation Ontology', // @translate
                    'o:comment' => 'The Web Annotation Vocabulary specifies the set of RDF classes, predicates and named entities that are used by the Web Annotation Data Model (http://www.w3.org/TR/annotation-model/).', // @translate
                ],
                'strategy' => 'file',
                'file' => 'oa.ttl',
                'format' => 'turtle',
            ],
        ];
        foreach ($vocabularies as $key => $vocabulary) {
            if ($this->checkVocabulary($vocabulary, $serviceLocator)) {
                $message = new Message(
                    'The vocabulary "%s" was already installed and was kept.', // @translate
                    $vocabulary['vocabulary']['o:label']
                );
                $messenger = new Messenger();
                $messenger->addWarning($message);
                unset($vocabularies[$key]);
            }
        }

        $customVocabPaths = [
            __DIR__ . '/data/custom-vocabs/Annotation-oa-motivatedBy.json',
            __DIR__ . '/data/custom-vocabs/Annotation-Body-dcterms-format.json',
            __DIR__ . '/data/custom-vocabs/Annotation-Target-dcterms-format.json',
            __DIR__ . '/data/custom-vocabs/Annotation-Target-rdf-type.json',
        ];
        foreach ($customVocabPaths as $key => $customVocabPath) {
            if ($this->checkCustomVocab($customVocabPath, $serviceLocator)) {
                unset($customVocabPaths[$key]);
            }
        }

        // TODO Replace the resource templates for annotations that are not items.
        $resourceTemplatePaths = [
            __DIR__ . '/data/resource-templates/Annotation.json',
        ];
        foreach ($resourceTemplatePaths as $key => $resourceTemplatePath) {
            if ($this->checkResourceTemplate($resourceTemplatePath, $serviceLocator)) {
                unset($resourceTemplatePaths[$key]);
            }
        }

        // Checks are ok, so process the install.

        $sql = <<<'SQL'
CREATE TABLE annotation (
    id INT NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE annotation_body (
    id INT NOT NULL,
    annotation_id INT NOT NULL,
    INDEX IDX_D819DB36E075FC54 (annotation_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE annotation_target (
    id INT NOT NULL,
    annotation_id INT NOT NULL,
    INDEX IDX_9F53A3D6E075FC54 (annotation_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE annotation ADD CONSTRAINT FK_2E443EF2BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE annotation_body ADD CONSTRAINT FK_D819DB36E075FC54 FOREIGN KEY (annotation_id) REFERENCES annotation (id) ON DELETE CASCADE;
ALTER TABLE annotation_body ADD CONSTRAINT FK_D819DB36BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE annotation_target ADD CONSTRAINT FK_9F53A3D6E075FC54 FOREIGN KEY (annotation_id) REFERENCES annotation (id) ON DELETE CASCADE;
ALTER TABLE annotation_target ADD CONSTRAINT FK_9F53A3D6BF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }

        foreach ($vocabularies as $vocabulary) {
            $this->createVocabulary($vocabulary, $serviceLocator);
        }

        foreach ($customVocabPaths as $customVocabPath) {
            $this->importCustomVocab($customVocabPath, $serviceLocator);
        }

        foreach ($resourceTemplatePaths as $resourceTemplatePath) {
            $this->importResourceTemplate($resourceTemplatePath, $serviceLocator);
        }

        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $sql = <<<'SQL'
ALTER TABLE annotation DROP FOREIGN KEY FK_2E443EF2BF396750;
ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36E075FC54;
ALTER TABLE annotation_body DROP FOREIGN KEY FK_D819DB36BF396750;
ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6E075FC54;
ALTER TABLE annotation_target DROP FOREIGN KEY FK_9F53A3D6BF396750;
DELETE value FROM value LEFT JOIN resource ON resource.id = value.resource_id WHERE resource_type IN ("Annotate\\Entity\\Annotation", "Annotate\\Entity\\AnnotationBody", "Annotate\\Entity\\AnnotationTarget");
DELETE FROM resource WHERE resource_type = "Annotate\\Entity\\AnnotationTarget";
DELETE FROM resource WHERE resource_type = "Annotate\\Entity\\AnnotationBody";
DELETE FROM resource WHERE resource_type = "Annotate\\Entity\\Annotation";
DROP TABLE IF EXISTS annotation_target;
DROP TABLE IF EXISTS annotation_body;
DROP TABLE IF EXISTS annotation;
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }

        if (!empty($_POST['remove-vocabulary'])) {
            $prefix = 'rdf';
            $this->removeVocabulary($prefix, $serviceLocator);
            $prefix = 'oa';
            $this->removeVocabulary($prefix, $serviceLocator);
        }

        if (!empty($_POST['remove-custom-vocab'])) {
            $customVocab = 'Annotation oa:motivatedBy';
            $this->removeCustomVocab($customVocab, $serviceLocator);
            $customVocab = 'Annotation Body dcterms:format';
            $this->removeCustomVocab($customVocab, $serviceLocator);
            $customVocab = 'Annotation Target dcterms:format';
            $this->removeCustomVocab($customVocab, $serviceLocator);
            $customVocab = 'Annotation Target rdf:type';
            $this->removeCustomVocab($customVocab, $serviceLocator);
        }

        if (!empty($_POST['remove-template'])) {
            $resourceTemplate = 'Annotation';
            $this->removeResourceTemplate($resourceTemplate, $serviceLocator);
        }

        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion,
        ServiceLocatorInterface $serviceLocator)
    {
        require_once 'data/scripts/upgrade.php';
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    protected function manageSiteSettings(ServiceLocatorInterface $serviceLocator, $process)
    {
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $this->manageSettings($siteSettings, $process, 'site_settings');
        }
    }

    public function warnUninstall(Event $event)
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $vocabularyLabels = 'RDF Concepts" / "Web Annotation Ontology';
        $customVocabs = 'Annotation oa:motivatedBy" / "dcterms:format" / "rdf:type';
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
    protected function addAclRoleAndRules()
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $acl->addRole(Acl::ROLE_ANNOTATOR);
        $acl->addRoleLabel(Acl::ROLE_ANNOTATOR, 'Annotator'); // @translate

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
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForVisitors(ZendAcl $acl)
    {
        $acl->allow(
            null,
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['read']
        );
        $acl->allow(
            null,
            [Api\Adapter\AnnotationAdapter::class, Api\Adapter\AnnotationBodyAdapter::class, Api\Adapter\AnnotationTargetAdapter::class],
            ['search', 'read']
        );
        $acl->allow(
            null,
            [Controller\Site\AnnotationController::class],
            ['index', 'browse', 'show', 'search', 'flag']
        );
    }

    /**
     * Add ACL rules for annotator visitors.
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForVisitorAnnotators(ZendAcl $acl)
    {
        $acl->allow(
            null,
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['read', 'create']
        );
        $acl->allow(
            null,
            [Api\Adapter\AnnotationAdapter::class, Api\Adapter\AnnotationBodyAdapter::class, Api\Adapter\AnnotationTargetAdapter::class],
            ['search', 'read', 'create']
        );
        $acl->allow(
            null,
            [Controller\Site\AnnotationController::class],
            ['index', 'browse', 'show', 'search', 'add', 'flag']
        );
    }

    /**
     * Add ACL rules for annotator.
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForAnnotator(ZendAcl $acl)
    {
        // The annotator has less rights than Researcher for core resources, but
        // similar rights for annotations that Author has for core resources.
        // The rights related to annotation are set with all other annotators.
        $acl->allow(
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
        );

        $acl->allow(
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
        );

        $acl->allow(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            ['Omeka\Controller\Admin\User'],
            ['show', 'edit']
        );
        $acl->allow(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            ['Omeka\Api\Adapter\UserAdapter'],
            ['read', 'update', 'search']
        );
        $acl->allow(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            [\Omeka\Entity\User::class],
            ['read']
        );
        $acl->allow(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            [\Omeka\Entity\User::class],
            ['update', 'change-password', 'edit-keys'],
            new \Omeka\Permissions\Assertion\IsSelfAssertion
        );

        // TODO Remove this rule for Omeka >= 1.2.1.
        $acl->deny(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            [
                'Omeka\Controller\SiteAdmin\Index',
                'Omeka\Controller\SiteAdmin\Page',
            ]
        );
        $acl->deny(
            [\Annotate\Permissions\Acl::ROLE_ANNOTATOR],
            ['Omeka\Controller\Admin\User'],
            ['browse']
        );
    }

    /**
     * Add ACL rules for annotators (not visitor).
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForAnnotators(ZendAcl $acl)
    {
        $annotators = [
            \Annotate\Permissions\Acl::ROLE_ANNOTATOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $acl->allow(
            $annotators,
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['create']
        );
        $acl->allow(
            $annotators,
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['update', 'delete'],
            new \Omeka\Permissions\Assertion\OwnsEntityAssertion
        );
        $acl->allow(
            $annotators,
            [Api\Adapter\AnnotationAdapter::class, Api\Adapter\AnnotationBodyAdapter::class, Api\Adapter\AnnotationTargetAdapter::class],
            ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
        );
        $acl->allow(
            $annotators,
            [Controller\Site\AnnotationController::class]
        );
        $acl->allow(
            $annotators,
            [Controller\Admin\AnnotationController::class],
            ['index', 'browse', 'show', 'show-details', 'add', 'edit', 'delete', 'delete-confirm', 'flag']
        );
    }

    /**
     * Add ACL rules for approbators.
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForApprobators(ZendAcl $acl)
    {
        // Admin are approbators too, but rights are set below globally.
        $approbators = [
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
        ];
        // "view-all" is added via main acl factory for resources.
        $acl->allow(
            [\Omeka\Permissions\Acl::ROLE_REVIEWER],
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['read', 'create', 'update']
        );
        $acl->allow(
            [\Omeka\Permissions\Acl::ROLE_REVIEWER],
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['delete'],
            new \Omeka\Permissions\Assertion\OwnsEntityAssertion
        );
        $acl->allow(
            [\Omeka\Permissions\Acl::ROLE_EDITOR],
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class],
            ['read', 'create', 'update', 'delete']
        );
        $acl->allow(
            $approbators,
            [Api\Adapter\AnnotationAdapter::class, Api\Adapter\AnnotationBodyAdapter::class, Api\Adapter\AnnotationTargetAdapter::class],
            ['search', 'read', 'create', 'update', 'delete', 'batch_create', 'batch_update', 'batch_delete']
        );
        $acl->allow(
            $approbators,
            [Controller\Site\AnnotationController::class]
        );
        $acl->allow(
            $approbators,
            Controller\Admin\AnnotationController::class,
            [
                'index',
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
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForAdmins(ZendAcl $acl)
    {
        $admins = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        ];
        $acl->allow(
            $admins,
            [Annotation::class, AnnotationBody::class, AnnotationTarget::class]
        );
        $acl->allow(
            $admins,
            [Api\Adapter\AnnotationAdapter::class, Api\Adapter\AnnotationBodyAdapter::class, Api\Adapter\AnnotationTargetAdapter::class]
        );
        $acl->allow(
            $admins,
            [Controller\Site\AnnotationController::class, Controller\Admin\AnnotationController::class]
        );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add the Open Annotation part to the representation.
        $representations = [
            'user' => UserRepresentation::class,
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
            [$this, 'displayAdvancedSearch']
        );
        // Filter the search filters for the advanced search pages.
        $sharedEventManager->attach(
            \Annotate\Controller\Admin\AnnotationController::class,
            'view.search.filters',
            [$this, 'filterSearchFilters']
        );

        // Events for the admin board.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            // Add a tab to the resource show admin pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.before',
                [$this, 'addHeadersAdmin']
            );
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
                'view.browse.before',
                [$this, 'addHeadersAdmin']
            );
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
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.add.after',
            [$this, 'addHeadersAdmin']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ResourceTemplate',
            'view.edit.after',
            [$this, 'addHeadersAdmin']
        );
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

        // Site settings.
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
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
     *
     * @param Event $event
     */
    public function filterJsonLd(Event $event)
    {
        if (!$this->userCanRead()) {
            return;
        }

        $resource = $event->getTarget();
        $entityColumnName = $this->columnNameOfRepresentation($resource);
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $annotations = $api
        ->search('annotations', [$entityColumnName => $resource->id()], ['responseContent' => 'reference'])
        ->getContent();
        if ($annotations) {
            $jsonLd['oa:Annotation'] = $annotations;
            $event->setParam('jsonLd', $jsonLd);
        }
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event)
    {
        $query = $event->getParam('query', []);
        $query['datetime'] = isset($query['datetime']) ? $query['datetime'] : '';
        $partials = $event->getParam('partials', []);
        $partials[] = 'common/advanced-search/annotate-datetime';
        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters for display.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event)
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

    public function handleResourceTemplateCreateOrUpdatePost(Event $event)
    {
        // The acl are already checked via the api.
        $services = $this->getServiceLocator();
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $api = $services->get('Omeka\ApiManager');

        $result = [];
        $requestContent = $request->getContent();
        $requestResourceProperties = isset($requestContent['o:resource_template_property']) ? $requestContent['o:resource_template_property'] : [];
        foreach ($requestResourceProperties as $propertyId => $requestResourceProperty) {
            if (isset($requestResourceProperty['data']['annotation_part'])) {
                try {
                    /** @var \Omeka\Api\Representation\PropertyRepresentation $property */
                    $property = $api->read('properties', $propertyId)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
                $result[$property->term()] = $requestResourceProperty['data']['annotation_part'];
            }
        }

        $resourceTemplateId = $response->getContent()->getId();
        $settings = $services->get('Omeka\Settings');
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        $resourceTemplateData[$resourceTemplateId] = $result;
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    public function handleResourceTemplateDeletePost(Event $event)
    {
        // The acl are already checked via the api.
        $id = $event->getParam('request')->getId();
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        unset($resourceTemplateData[$id]);
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $form = $event->getTarget();

        $defaultSiteSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];

        $fieldset = new Fieldset('annotate');
        $fieldset->setLabel('Annotate'); // @translate

        $fieldset->add([
            'name' => 'annotate_append_item_set_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to item set page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'annotate_append_item_set_show',
                    $defaultSiteSettings['annotate_append_item_set_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'annotate_append_item_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to item page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'annotate_append_item_show',
                    $defaultSiteSettings['annotate_append_item_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'annotate_append_media_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append annotations automatically to media page', // @translate
                'info' => 'If unchecked, the annotations can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'annotate_append_media_show',
                    $defaultSiteSettings['annotate_append_media_show']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    public function addCsvImportFormElements(Event $event)
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
     *
     * @param Event $event
     */
    public function addHeadersAdmin(Event $event)
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/annotate-admin.css', __NAMESPACE__));
        $view->headScript()->appendFile($view->assetUrl('js/annotate-admin.js', __NAMESPACE__));
    }

    /**
     * Add a tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['annotate'] = 'Annotations'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Display a partial for a resource.
     *
     * @param Event $event
     */
    public function displayListAndForm(Event $event)
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
     *
     * @param Event $event
     */
    public function displayList(Event $event)
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
     *
     * @param Event $event
     */
    public function viewDetails(Event $event)
    {
        $representation = $event->getParam('entity');
        // TODO Use a paginator to limit and display all annotations dynamically in the details view (using api).
        $this->displayResourceAnnotations($event, $representation, true, ['limit' => 10]);
    }

    /**
     * Display a form.
     *
     * @param Event $event
     */
    public function displayForm(Event $event)
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
        $propertyId = $api->searchOne('properties', ['term' => 'oa:hasSource'])->getContent()->id();
        $data['oa:hasTarget[0][oa:hasSource][0][property_id]'] = $propertyId;
        $data['oa:hasTarget[0][oa:hasSource][0][type]'] = 'resource';
        $data['oa:hasTarget[0][oa:hasSource][0][value_resource_id]'] = $resource->id();

        echo $view->showAnnotateForm($resource, $options, $attributes, $data);
    }

    /**
     * Display a partial for a resource in public.
     *
     * @param Event $event
     */
    public function displayPublic(Event $event)
    {
        $serviceLocator = $this->getServiceLocator();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $appendMap = [
            'item_sets' => 'annotate_append_item_set_show',
            'items' => 'annotate_append_item_show',
            'media' => 'annotate_append_media_show',
        ];
        if (!$siteSettings->get($appendMap[$resourceName])) {
            return;
        }

        echo $view->annotations($resource);
    }

    /**
     * Helper to display a partial for a resource.
     *
     * @param Event $event
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $listAsDiv Return the list with div, not ul.
     */
    protected function displayResourceAnnotations(
        Event $event,
        AbstractResourceEntityRepresentation $resource,
        $listAsDiv = false,
        array $query = []
    ) {
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
     *
     * @return bool
     */
    protected function userCanRead()
    {
        $userIsAllowed = $this->getServiceLocator()->get('ViewHelperManager')
            ->get('userIsAllowed');
        return $userIsAllowed(Annotation::class, 'read');
    }

    protected function isAnnotationEnabledForResource(AbstractEntityRepresentation $resource)
    {
        // TODO Some type of annotation may be removed for some annotation types.
        return true;

        if ($resource->getControllerName() === 'user') {
            return true;
        }
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $commentResources = $settings->get('annotate_resources');
        $resourceName = $resource->resourceName();
        return in_array($resourceName, $commentResources);
    }

    /**
     * Helper to get the column id of an entity.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     *
     * @param AbstractEntity $resource
     * @return string
     */
    protected function columnNameOfEntity(AbstractEntity $resource)
    {
        $entityColumnNames = [
            \Omeka\Entity\ItemSet::class => 'resource_id',
            \Omeka\Entity\Item::class => 'resource_id',
            \Omeka\Entity\Media::class => 'resource_id',
            \Omeka\Entity\User::class => 'owner_id',
        ];
        $entityColumnName = $entityColumnNames[$resource->getResourceId()];
        return $entityColumnName;
    }

    /**
     * Helper to get the column id of a representation.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     *
     * @param AbstractEntityRepresentation $representation
     * @return string
     */
    protected function columnNameOfRepresentation(AbstractEntityRepresentation $representation)
    {
        $entityColumnNames = [
            'item-set' => 'resource_id',
            'item' => 'resource_id',
            'media' => 'resource_id',
            'user' => 'owner_id',
        ];
        $entityColumnName = $entityColumnNames[$representation->getControllerName()];
        return $entityColumnName;
    }

    /**
     * Check if a module is enabled.
     *
     * @param string $requiredModule
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool
     */
    protected function checkModule($requiredModule, ServiceLocatorInterface $serviceLocator)
    {
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($requiredModule);
        if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            throw new ModuleCannotInstallException(
                new Message('The module "%s" is required.', $requiredModule) // @translate
            );
        }
        return true;
    }

    /**
     * Check if a vocabulary exists and throws an exception if different.
     *
     * @param array $vocabulary
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool False if not found, true if exists.
     */
    protected function checkVocabulary(array $vocabulary, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $api
                ->read('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
            return false;
        }

        // Check if it is the same vocabulary.
        if ($vocabularyRepresentation->namespaceUri() === $vocabulary['vocabulary']['o:namespace_uri']) {
            return true;
        }

        // It is another vocabulary with the same prefix.
        throw new ModuleCannotInstallException(
            sprintf(
                'An error occured when adding the prefix "%s": another vocabulary exists. Resolve the conflict before installing this module.', // @translate
                $vocabulary['vocabulary']['o:prefix']
            )
        );
    }

    /**
     * Create a checked vocabulary.
     *
     * @param array $vocabulary
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     */
    protected function createVocabulary(array $vocabulary, ServiceLocatorInterface $serviceLocator)
    {
        $rdfImporter = $serviceLocator->get('Omeka\RdfImporter');

        try {
            $rdfImporter->import(
                $vocabulary['strategy'],
                $vocabulary['vocabulary'],
                [
                    'file' => __DIR__ . "/data/vocabularies/{$vocabulary['file']}",
                    'format' => $vocabulary['format'],
                ]
            );
        } catch (ValidationException $e) {
            throw new ModuleCannotInstallException(
                sprintf(
                    'An error occured when adding the prefix "%s" and the associated properties. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                )
            );
        }
    }

    /**
     * Remove a vocabulary by its prefix.
     *
     * @param string $prefix
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function removeVocabulary($prefix, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');
        // The vocabulary may have been removed manually before.
        try {
            $api->delete('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * Check if a custom vocab exists and throws an exception if different.
     *
     * @param string $filepath
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool False if not found, true if exists.
     */
    protected function checkCustomVocab($filepath, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            return false;
        }

        if (implode("\n", $data['o:terms']) !== $customVocab->terms()) {
            throw new ModuleCannotInstallException(
                sprintf(
                    'A custom vocab named "%s" exists and has not the needed terms: rename it or remove it before installing this module.', // @translate
                    $label,
                    $data['o:terms']
                )
            );
        }

        if ($data['o:lang'] != $customVocab->lang()) {
            throw new ModuleCannotInstallException(
                sprintf(
                    'A custom vocab named "%s" exists and has not the needed language ("%s"): check it or remove it before installing this module.', // @translate
                    $label,
                    $data['o:lang']
                )
            );
        }

        return true;
    }

    /**
     * Create a checked custom vocab.
     *
     * The vocab must be checked first.
     *
     * @param string $filepath
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     */
    protected function importCustomVocab($filepath, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

        $data = json_decode(file_get_contents($filepath), true);
        $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
        $api->create('custom_vocabs', $data);
    }

    /**
     * Remove a custom vocab by its label.
     *
     * @param string $label
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function removeCustomVocab($label, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');
        // The custom vocab may be renamed or removed manually before.
        try {
            $api->delete('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * Check if a resource template exists.
     *
     * Note: the vocabs of the resource template are not checked currently.
     *
     * @param string $filepath
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool False if not found, true if exists.
     */
    protected function checkResourceTemplate($filepath, ServiceLocatorInterface $serviceLocator)
    {
        $data = json_decode(file_get_contents($filepath), true);
        $label = $data['o:label'];

        $api = $serviceLocator->get('Omeka\ApiManager');
        try {
            $api->read('resource_templates', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            return false;
        }

        throw new ModuleCannotInstallException(
            sprintf(
                'A resource template named "%s" exists: rename it or remove it before installing this module.', // @translate
                $label
            )
        );

        // return true;
    }

    /**
     * Import a checked resource template.
     *
     * The resource template must be checked first.
     * Note: no check on custom vocab, class or vocabulary.
     *
     * @todo Use the resource template check and process of the controller.
     *
     * @param string $filepath
     * @param ServiceLocatorInterface $serviceLocator
     * @return bool True if the resource template has been created, false if
     * it exists already, so it is not created twice.
     */
    protected function importResourceTemplate($filepath, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $api
                ->read('resource_templates', ['label' => $label])->getContent();
            return false;
        } catch (NotFoundException $e) {
        }

        foreach ($data['o:resource_template_property'] as $key => $property) {
            $vocabulary = $api->read('vocabularies', ['namespaceUri' => $property['vocabulary_namespace_uri']])->getContent();
            $data['o:resource_template_property'][$key]['vocabulary_prefix'] = $vocabulary->prefix();

            $propertyRepresentation = $api->read('properties', ['vocabulary' => $vocabulary->id(), 'localName' => $property['local_name']])->getContent();
            $data['o:resource_template_property'][$key]['o:property']['o:id'] = $propertyRepresentation->id();

            $dataTypeName = $property['data_type_name'];
            if (strpos($dataTypeName, 'customvocab:') === 0) {
                $customVocab = $api->read('custom_vocabs', ['label' => $property['data_type_label']])->getContent();
                $data['o:resource_template_property'][$key]['o:data_type'] = 'customvocab:' . $customVocab->id();
            } else {
                $data['o:resource_template_property'][$key]['o:data_type'] = $dataTypeName;
            }
        }

        $resourceClass = empty($data['o:resource_class']) ? null : $data['o:resource_class'];
        if ($resourceClass) {
            $vocabulary = $api->read('vocabularies', ['namespaceUri' => $resourceClass['vocabulary_namespace_uri']])->getContent();
            $data['o:resource_class']['vocabulary_prefix'] = $vocabulary->prefix();

            $resourceClassReprsentation = $api->read('resource_classes', ['vocabulary' => $vocabulary->id(), 'localName' => $resourceClass['local_name']])->getContent();
            $data['o:resource_class']['o:id'] = $resourceClassReprsentation->id();
        }

        $api->create('resource_templates', $data);
        return true;
    }

    /**
     * Remove a resource template by its label.
     *
     * @param string $label
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function removeResourceTemplate($label, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');
        // The resource template may be renamed or removed manually before.
        try {
            $api->delete('resource_templates', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
        }
    }
}
