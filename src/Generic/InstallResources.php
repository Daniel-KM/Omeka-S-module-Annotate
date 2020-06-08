<?php
/*
 * Copyright Daniel Berthereau, 2018-2020
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

namespace Generic;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\RuntimeException;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\ServiceManager\ServiceLocatorInterface;

class InstallResources
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    public function __construct(ServiceLocatorInterface $services)
    {
        $this->services = $services;
    }

    /**
     * Allows to manage all resources methods that should run once only and that
     * are generic to all modules. A little config over code.
     *
     * @return self
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Check all resources that are in the path data/ of a module.
     *
     * @param string $module
     * @throws \Omeka\Module\Exception\ModuleCannotInstallException
     * @return bool
     */
    public function checkAllResources($module)
    {
        $filepathData = OMEKA_PATH . '/modules/' . $module . '/data/';

        // Vocabularies.
        foreach ($this->listFilesInDir($filepathData . 'vocabularies', ['json']) as $filepath) {
            $data = file_get_contents($filepath);
            $data = json_decode($data, true);
            if ($data) {
                if ($data['file'] && strpos($data['file'], 'https://') === false && strpos($data['file'], 'http://') === false) {
                    $data['file'] = dirname($filepath) . '/' . $data['file'];
                }
                try {
                    $this->checkVocabulary($data);
                } catch (RuntimeException $e) {
                    throw new ModuleCannotInstallException($e->getMessage());
                }
            }
        }

        // Custom vocabs.
        foreach ($this->listFilesInDir($filepathData . 'custom-vocabs') as $filepath) {
            try {
                $this->checkCustomVocab($filepath);
            } catch (RuntimeException $e) {
                throw new ModuleCannotInstallException($e->getMessage());
            }
        }

        // Resource templates.
        foreach ($this->listFilesInDir($filepathData . 'resource-templates') as $filepath) {
            try {
                $this->checkResourceTemplate($filepath);
            } catch (RuntimeException $e) {
                throw new ModuleCannotInstallException($e->getMessage());
            }
        }

        return true;
    }

    /**
     * Install all resources that are in the path data/ of a module.
     *
     * @param string $module
     */
    public function createAllResources($module)
    {
        $filepathData = OMEKA_PATH . '/modules/' . $module . '/data/';

        // Vocabularies.
        foreach ($this->listFilesInDir($filepathData . 'vocabularies', ['json']) as $filepath) {
            $data = file_get_contents($filepath);
            $data = json_decode($data, true);
            if ($data) {
                if ($data['file'] && strpos($data['file'], 'https://') === false && strpos($data['file'], 'http://') === false) {
                    $data['file'] = dirname($filepath) . '/' . $data['file'];
                }
                if (!$this->checkVocabulary($data)) {
                    $this->createVocabulary($data);
                }
            }
        }

        // Custom vocabs.
        foreach ($this->listFilesInDir($filepathData . 'custom-vocabs') as $filepath) {
            if (!$this->checkCustomVocab($filepath)) {
                $this->createOrUpdateCustomVocab($filepath);
            }
        }

        // Resource templates.
        foreach ($this->listFilesInDir($filepathData . 'resource-templates') as $filepath) {
            if (!$this->checkResourceTemplate($filepath)) {
                $this->createResourceTemplate($filepath);
            }
        }
    }

    /**
     * Check if a vocabulary exists and throws an exception if different.
     *
     * @param array $vocabulary
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool False if not found, true if exists.
     */
    public function checkVocabulary(array $vocabulary)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $filepath = $vocabulary['file'];
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new RuntimeException(
                sprintf(
                    'The file "%s" cannot be read. Check your file system.', // @translate
                    '/data/vocabularies/' . basename($vocabulary['file'])
                )
            );
        }

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
        // See createVocabulary() about the trim.
        if (rtrim($vocabularyRepresentation->namespaceUri(), '#/') === rtrim($vocabulary['vocabulary']['o:namespace_uri'], '#/')) {
            return true;
        }

        // It is another vocabulary with the same prefix.
        throw new RuntimeException(
            sprintf(
                'An error occured when adding the prefix "%s": another vocabulary exists. Resolve the conflict before installing this module.', // @translate
                $vocabulary['vocabulary']['o:prefix']
            )
        );
    }

    /**
     * Check if a resource template exists.
     *
     * Note: the vocabs of the resource template are not checked currently.
     *
     * @param string $filepath
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool False if not found, true if exists.
     */
    public function checkResourceTemplate($filepath)
    {
        $services = $this->getServiceLocator();
        $data = json_decode(file_get_contents($filepath), true);
        $label = $data['o:label'];

        $api = $services->get('Omeka\ApiManager');
        try {
            $api->read('resource_templates', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            return false;
        }

        throw new RuntimeException(
            sprintf(
                'A resource template named "%s" exists: rename it or remove it before installing this module.', // @translate
                $label
            )
        );
    }

    /**
     * Check if a custom vocab exists and throws an exception if different.
     *
     * @param string $filepath
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool False if not found, true if exists.
     */
    public function checkCustomVocab($filepath)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            return false;
        } catch (\Omeka\Api\Exception\BadRequestException $e) {
            throw new RuntimeException(
                'The current version of this module requires the module Custom Vocab.' // @translate
            );
        }

        if ($data['o:lang'] != $customVocab->lang()) {
            throw new RuntimeException(
                sprintf(
                    'A custom vocab named "%s" exists and has not the needed language ("%s"): check it or remove it before installing this module.', // @translate
                    $label,
                    $data['o:lang']
                )
            );
        }

        $newTerms = $data['o:terms'];
        $existingTerms = explode("\n", $customVocab->terms());
        sort($newTerms);
        sort($existingTerms);
        if ($newTerms !== $existingTerms) {
            // To be completed.
            return false;
        }

        return true;
    }

    /**
     * Create a vocabulary, with a check of its existence before.
     *
     * @param array $vocabulary
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool True if the vocabulary has been created, false if it exists
     * already, so it is not created twice.
     */
    public function createVocabulary(array $vocabulary)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $api
                ->read('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
            $vocabularyRepresentation = null;
        }

        if ($vocabularyRepresentation) {
            // Check if it is the same vocabulary.
            // Note: in some cases, the uri of the ontology and the uri of the
            // namespace are mixed. So, the last character ("#" or "/") is
            // skipped for easier management.
            if (rtrim($vocabularyRepresentation->namespaceUri(), '#/') === rtrim($vocabulary['vocabulary']['o:namespace_uri'], '#/')) {
                $message = new Message('The vocabulary "%s" was already installed and was kept.', // @translate
                    $vocabulary['vocabulary']['o:label']);
                $messenger = new Messenger();
                $messenger->addWarning($message);
                return false;
            }

            // It is another vocabulary with the same prefix.
            throw new RuntimeException(
                new Message(
                    'An error occured when adding the prefix "%s": another vocabulary exists with the same prefix. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                )
            );
        }

        /** @var \Omeka\Stdlib\RdfImporter $rdfImporter */
        $rdfImporter = $services->get('Omeka\RdfImporter');
        try {
            $rdfImporter->import(
                $vocabulary['strategy'],
                $vocabulary['vocabulary'],
                [
                    'file' => $vocabulary['file'],
                    'format' => $vocabulary['format'],
                ]
            );
        } catch (\Omeka\Api\Exception\ValidationException $e) {
            throw new RuntimeException(
                new Message(
                    'An error occured when adding the prefix "%s" and the associated properties: %s', // @translate
                    $vocabulary['vocabulary']['o:prefix'],
                    $e->getMessage()
                )
            );
        }

        return true;
    }

    /**
     * Create a resource template, with a check of its existence before.
     *
     * @todo Some checks of the resource termplate controller are skipped currently.
     *
     * @param string $filepath
     * @return \Omeka\Api\Representation\ResourceTemplateRepresentation
     * @throws \Omeka\Api\Exception\RuntimeException
     */
    public function createResourceTemplate($filepath)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('ControllerPluginManager')->get('api');
        $data = json_decode(file_get_contents($filepath), true);

        // Check if the resource template exists, so it is not replaced.
        $label = $data['o:label'];
        try {
            $resourceTemplate = $api->read('resource_templates', ['label' => $label])->getContent();
            $message = new Message(
                'The resource template named "%s" is already available and is skipped.', // @translate
                $label
            );
            $messenger = new Messenger();
            $messenger->addWarning($message);
            return $resourceTemplate;
        } catch (NotFoundException $e) {
        }

        // Set the iinternal ids of classes, properties and data types.
        // TODO Check if the output is valid (else an error will be thrown during import).
        $data = $this->flagValid($data);

        // Manage the custom vocabs that may be set inside the template.
        foreach ($data['o:resource_template_property'] as &$templateProperty) {
            if (strpos($templateProperty['data_type_name'], 'customvocab:') !== 0) {
                continue;
            }
            $label = $templateProperty['data_type_label'] ?: $templateProperty['label'];
            try {
                $customVocab = $api
                    ->read('custom_vocabs', ['label' => $label])->getContent();
            } catch (NotFoundException $e) {
                throw new RuntimeException(
                    new Message(
                        'The custom vocab named "%s" is not available.', // @translate
                        $label
                    )
                );
            }
            $templateProperty['data_type_name'] = 'customvocab:' . $customVocab->id();
            $templateProperty['o:data_type'] = 'customvocab:' . $customVocab->id();
        }
        unset($templateProperty);

        // Check if there are title and description.
        foreach (['o:title_property', 'o:description_property'] as $property) {
            if (!empty($data[$property]['vocabulary_namespace_uri'])
                && !empty($data[$property]['local_name'])
            ) {
                $prop = $api->searchOne('properties', $data[$property])->getContent();
                if ($prop) {
                    $data[$property]['o:id'] = $prop->id();
                }
            }
        }

        // Process import.
        /** \Omeka\Api\Representation\ResourceTemplateRepresentation $resourceTemplate */
        $resourceTemplate = $api->create('resource_templates', $data)->getContent();

        return $resourceTemplate;
    }

    /**
     * Create or update a custom vocab.
     *
     * @param string $filepath
     */
    public function createOrUpdateCustomVocab($filepath)
    {
        try {
            return $this->updateCustomVocab($filepath);
        } catch (RuntimeException $e) {
            return $this->createCustomVocab($filepath);
        }
    }

    /**
     * Create a custom vocab.
     *
     * @param string $filepath
     */
    public function createCustomVocab($filepath)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $data = json_decode(file_get_contents($filepath), true);
        $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
        try {
            $api->create('custom_vocabs', $data);
        } catch (\Exception $e) {
        }
    }

    /**
     * Flag members and data types as valid.
     *
     * Copy of the method of the resource template controller (with services).
     *
     * @see \Omeka\Controller\Admin\ResourceTemplateController::flagValid()
     *
     * All members start as invalid until we determine whether the corresponding
     * vocabulary and member exists in this installation. All data types start
     * as "Default" (i.e. none declared) until we determine whether they match
     * the native types (literal, uri, resource).
     *
     * We flag a valid vocabulary by adding [vocabulary_prefix] to the member; a
     * valid class by adding [o:id]; and a valid property by adding
     * [o:property][o:id]. We flag a valid data type by adding [o:data_type] to
     * the property. By design, the API will only hydrate members and data types
     * that are flagged as valid.
     *
     * @param array $import
     * @return array
     */
    protected function flagValid(array $import)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('ControllerPluginManager')->get('api');

        $vocabs = [];
        $dataTypes = [
            'literal',
            'uri',
            'resource',
            'resource:item',
            'resource:itemset',
            'resource:media',
        ];

        $getVocab = function ($namespaceUri) use (&$vocabs, $api) {
            if (isset($vocabs[$namespaceUri])) {
                return $vocabs[$namespaceUri];
            }
            $vocab = $api->searchOne('vocabularies', [
                'namespace_uri' => $namespaceUri,
            ])->getContent();
            if ($vocab) {
                $vocabs[$namespaceUri] = $vocab;
                return $vocab;
            }
            return false;
        };

        if (!empty($import['o:resource_class'])) {
            if ($vocab = $getVocab($import['o:resource_class']['vocabulary_namespace_uri'])) {
                $import['o:resource_class']['vocabulary_prefix'] = $vocab->prefix();
                $class = $api->searchOne('resource_classes', [
                    'vocabulary_namespace_uri' => $import['o:resource_class']['vocabulary_namespace_uri'],
                    'local_name' => $import['o:resource_class']['local_name'],
                ])->getContent();
                if ($class) {
                    $import['o:resource_class']['o:id'] = $class->id();
                }
            }
        }

        foreach ($import['o:resource_template_property'] as $key => $property) {
            if ($vocab = $getVocab($property['vocabulary_namespace_uri'])) {
                $import['o:resource_template_property'][$key]['vocabulary_prefix'] = $vocab->prefix();
                $prop = $api->searchOne('properties', [
                    'vocabulary_namespace_uri' => $property['vocabulary_namespace_uri'],
                    'local_name' => $property['local_name'],
                ])->getContent();
                if ($prop) {
                    $import['o:resource_template_property'][$key]['o:property'] = ['o:id' => $prop->id()];
                    if (in_array($import['o:resource_template_property'][$key]['data_type_name'], $dataTypes)) {
                        $import['o:resource_template_property'][$key]['o:data_type'] = $import['o:resource_template_property'][$key]['data_type_name'];
                    }
                }
            }
        }

        return $import;
    }

    /**
     * Update a vocabulary, with a check of its existence before.
     *
     * @param string $filepath
     * @throws \Omeka\Api\Exception\RuntimeException
     */
    public function updateCustomVocab($filepath)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            throw new RuntimeException(
                new Message(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                )
            );
        }

        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $terms = array_merge($terms, $data['o:terms']);
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);
    }

    /**
     * Remove a vocabulary by its prefix.
     *
     * @param string $prefix
     */
    public function removeVocabulary($prefix)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        // The vocabulary may have been removed manually before.
        try {
            $resource = $api->read('vocabularies', ['prefix' => $prefix])->getContent();
            $api->delete('vocabularies', $resource->id())->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * Remove a resource template by its label.
     *
     * @param string $label
     */
    public function removeResourceTemplate($label)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        // The resource template may be renamed or removed manually before.
        try {
            $resource = $api->read('resource_templates', ['label' => $label])->getContent();
            $api->delete('resource_templates', $resource->id())->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * Remove a custom vocab by its label.
     *
     * @param string $label
     */
    public function removeCustomVocab($label)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        // The custom vocab may be renamed or removed manually before.
        try {
            $resource = $api->read('custom_vocabs', ['label' => $label])->getContent();
            $api->delete('custom_vocabs', $resource->id())->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->services;
    }

    /**
     * List filtered files in a directory, not recursively, and without subdirs.
     *
     * Unreadable and empty files are skipped.
     *
     * @param string $dirpath
     * @param array $extensions
     * @return array
     */
    protected function listFilesInDir($dirpath, array $extensions = [])
    {
        if (empty($dirpath) || !file_exists($dirpath) || !is_dir($dirpath) || !is_readable($dirpath)) {
            return [];
        }
        $list = array_filter(array_map(function ($file) use ($dirpath) {
            return $dirpath . DIRECTORY_SEPARATOR . $file;
        }, scandir($dirpath)), function ($file) {
            return is_file($file) && is_readable($file) && filesize($file);
        });
        if ($extensions) {
            $list = array_filter($list, function ($file) use ($extensions) {
                return in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions);
            });
        }
        return array_values($list);
    }
}
