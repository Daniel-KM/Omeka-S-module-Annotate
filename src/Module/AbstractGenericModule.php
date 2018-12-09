<?php
/*
 * Copyright Daniel Berthereau, 2018
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

namespace Annotate\Module;

use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Settings\SettingsInterface;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * This class allows to manage all methods that should run only once and that
 * are generic to all modules (install and settings).
 *
 * The logic is "config over code": so all settings have just to be set in the
 * main `config/module.config.php` file, inside a key with the lowercase module
 * name,  with sub-keys `config`, `settings`, `site_settings`, `user_settings`
 * and `block_settings`. All the forms have just to be standard Zend form.
 * Eventual install and uninstall sql can be set in `data/install/` and upgrade
 * code in `data/scripts`. A dependency on another module can be set as a
 * property of the main module  ($this->dependency and $this->dependencies).
 * To add it in a plugin, simply add at the  beginning of the file Module.php:
 * ```php
 * require_once dirname(__DIR__) . '/Next/src/Module/AbstractGenericModule.php';
 * ```
 * To avoid a dependency to this module, copy the file above file in your module
 * and replace the namespace.
 */
abstract class AbstractGenericModule extends AbstractModule
{
    /**
     * This is the root namespace of the module, instead the one of the current
     * abstract class.
     *
     * @var string
     */
    protected $namespace;

    public function __construct()
    {
        $this->namespace = (new \ReflectionClass($this))->getNamespaceName();
    }

    /**
     *
     * @return string
     */
    protected function modulePath()
    {
        return OMEKA_PATH . '/modules/' . $this->namespace;
    }

    public function getConfig()
    {
        return include $this->modulePath() . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->checkDependency();
        $this->checkDependencies();
        $this->execSqlFromFile($this->modulePath() . '/data/install/schema.sql');
        $this->manageConfig('install');
        $this->manageMainSettings('install');
        $this->manageSiteSettings('install');
        $this->manageUserSettings('install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->execSqlFromFile($this->modulePath() . '/data/install/uninstall.sql');
        $this->manageConfig('uninstall');
        $this->manageMainSettings('uninstall');
        $this->manageSiteSettings('uninstall');
        // Don't uninstall user settings, they don't belong to admin.
        // $this->manageUserSettings('uninstall');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $filepath = $this->modulePath() . '/data/scripts/upgrade.php';
        if (file_exists($filepath) && filesize($filepath) && is_readable($filepath)) {
            $this->setServiceLocator($serviceLocator);
            require_once $filepath;
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $formManager = $services->get('FormElementManager');
        $formClass = $this->namespace . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $data = $this->prepareDataToPopulate($settings, 'config');
        if (empty($data)) {
            return;
        }

        $form = $services->get('FormElementManager')->get($formClass);
        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower($this->namespace);
        if (empty($config[$space]['config'])) {
            return true;
        }

        $formManager = $services->get('FormElementManager');
        $formClass = $this->namespace . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return true;
        }

        $params = $controller->getRequest()->getPost();

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
        return true;
    }

    public function handleMainSettings(Event $event)
    {
        $this->handleAnySettings($event, 'settings');
    }

    public function handleSiteSettings(Event $event)
    {
        $this->handleAnySettings($event, 'site_settings');
    }

    public function handleUserSettings(Event $event)
    {
        $this->handleAnySettings($event, 'user_settings');
    }

    /**
     * Execute a sql from a file.
     *
     * @param string $filepath
     * @return mixed
     */
    protected function execSqlFromFile($filepath)
    {
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            return;
        }
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents($filepath);
        return $connection->exec($sql);
    }

    /**
     * Set or delete settings of the config of a module.
     *
     * @param string $process
     */
    protected function manageConfig($process)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'config', $process);
    }

    /**
     * Set or delete main settings.
     *
     * @param string $process
     */
    protected function manageMainSettings($process)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'settings', $process);
    }

    /**
     * Set or delete settings of all sites.
     *
     * @param string $process
     */
    protected function manageSiteSettings($process)
    {
        $settingsType = 'site_settings';
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower($this->namespace);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            $this->manageAnySettings($settings, $settingsType, $process);
        }
    }

    /**
     * Set or delete settings of all users.
     *
     * @param string $process
     */
    protected function manageUserSettings($process)
    {
        $settingsType = 'user_settings';
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower($this->namespace);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings\User');
        $api = $services->get('Omeka\ApiManager');
        $users = $api->search('users')->getContent();
        foreach ($users as $user) {
            $settings->setTargetId($user->id());
            $this->manageAnySettings($settings, $settingsType, $process);
        }
    }

    /**
     * Set or delete all settings of a specific type.
     *
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @param string $process
     */
    protected function manageAnySettings(SettingsInterface $settings, $settingsType, $process)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower($this->namespace);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $defaultSettings = $config[$space][$settingsType];
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

    /**
     * Prepare a settings fieldset.
     *
     * @param Event $event
     * @param string $settingsType
     */
    protected function handleAnySettings(Event $event, $settingsType)
    {
        $services = $this->getServiceLocator();

        $settingsTypes = [
            // 'config' => 'Omeka\Settings',
            'settings' => 'Omeka\Settings',
            'site_settings' => 'Omeka\Settings\Site',
            'user_settings' => 'Omeka\Settings\User',
        ];
        if (!isset($settingsTypes[$settingsType])) {
            return;
        }

        // TODO Check fieldsets in the config of the module.
        $settingFieldsets = [
            // 'config' => $this->namespace . '\Form\ConfigForm',
            'settings' => $this->namespace . '\Form\SettingsFieldset',
            'site_settings' => $this->namespace . '\Form\SiteSettingsFieldset',
            'user_settings' => $this->namespace . '\Form\UserSettingsFieldset',
        ];
        if (!isset($settingFieldsets[$settingsType])) {
            return;
        }

        $settings = $services->get($settingsTypes[$settingsType]);
        $data = $this->prepareDataToPopulate($settings, $settingsType);
        if (empty($data)) {
            return;
        }

        $space = strtolower($this->namespace);

        $fieldset = $services->get('FormElementManager')->get($settingFieldsets[$settingsType]);
        $fieldset->setName($space);
        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($data);
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower($this->namespace);
        if (empty($config[$space][$settingsType])) {
            return;
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, $value);
            $data[$name] = $val;
        }

        return $data;
    }

    /**
     * Check if the module has a dependency.
     *
     * This method is distinct of checkDependencies() for performance purpose.
     *
     * @throws ModuleCannotInstallException
     */
    protected function checkDependency()
    {
        if (empty($this->dependency) || $this->isModuleActive($this->dependency)) {
            return;
        }

        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $message = new Message(
            $translator->translate('This module requires the module "%s".'), // @translate
            $this->dependency
        );
        throw new ModuleCannotInstallException($message);
    }

    /**
     * Check if the module has dependencies.
     *
     * @throws ModuleCannotInstallException
     */
    protected function checkDependencies()
    {
        if (empty($this->dependencies)) {
            return;
        }

        $areAllActive = true;
        foreach ($this->dependencies as $dependency) {
            if (!$this->isModuleActive($dependency)) {
                $areAllActive = false;
                break;
            }
        }
        if ($areAllActive) {
            return;
        }

        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $message = new Message($translator->translate('This module requires the module "%s".'), // @translate
            $this->dependency
        );
        throw new ModuleCannotInstallException($message);
    }

    /**
     * Check if a module is active.
     *
     * @param string $moduleClass
     * @return bool
     */
    protected function isModuleActive($moduleClass)
    {
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        return $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    /**
     * Disable a module.
     *
     * @param string $moduleClass
     */
    protected function disableModule($moduleClass)
    {
        // Check if the module is enabled first to avoid an exception.
        if (!$this->isModuleActive($moduleClass)) {
            return;
        }

        // Check if the user is a global admin to avoid right issues.
        $services = $this->getServiceLocator();
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        if (!$user || $user->getRole() !== \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN) {
            return;
        }

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        $moduleManager->deactivate($module);

        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('The module "%s" was automatically deactivated because the dependencies are unavailable.'), // @translate
            $moduleClass
        );
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
        $messenger->addWarning($message);

        $logger = $services->get('Omeka\Logger');
        $logger->warn($message);
    }

    /**
     * Get each line of a string separately.
     *
     * @param string $string
     * @return array
     */
    protected function stringToList($string)
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))));
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Apple copy/paste from a textarea input.
     *
     * @param string $string
     * @return string
     */
    protected function fixEndOfLine($string)
    {
        return str_replace(["\r\n", "\n\r", "\r", "\n"], "\n", $string);
    }
}
