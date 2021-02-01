<?php declare(strict_types=1);
/*
 * Copyright Daniel Berthereau, 2018-2021
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

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\RuntimeException;
use Omeka\Api\Representation\ResourceTemplateRepresentation;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

class InstallResources
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    public function __construct(ServiceLocatorInterface $services)
    {
        $this->services = $services;
        $this->api = $services->get('Omeka\ApiManager');
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
    public function checkAllResources(string $module): bool
    {
        $filepathData = OMEKA_PATH . '/modules/' . $module . '/data/';

        // Vocabularies.
        foreach ($this->listFilesInDir($filepathData . 'vocabularies', ['json']) as $filepath) {
            $data = file_get_contents($filepath);
            $data = json_decode($data, true);
            if ($data) {
                $data['file'] = $this->canonicalFileOrUrl($data['file'], $module, 'vocabularies', $filepath);
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
                if ($this->checkResourceTemplate($filepath)) {
                    throw new RuntimeException(
                        sprintf(
                            'A resource template named exists for %s: rename it or remove it before installing this module.', // @translate
                            pathinfo($filepath, PATHINFO_FILENAME)
                        )
                    );
                }
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
     * @return self
     */
    public function createAllResources(string $module): self
    {
        $filepathData = OMEKA_PATH . '/modules/' . $module . '/data/';

        // Vocabularies.
        foreach ($this->listFilesInDir($filepathData . 'vocabularies', ['json']) as $filepath) {
            $data = file_get_contents($filepath);
            $data = json_decode($data, true);
            if ($data) {
                $data['file'] = $this->canonicalFileOrUrl($data['file'], $module, 'vocabularies', $filepath);
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

        return $this;
    }

    /**
     * Check if a vocabulary exists and throws an exception if different.
     *
     * @param array $vocabulary
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool False if not found, true if exists.
     */
    public function checkVocabulary(array $vocabulary): bool
    {
        $filepath = (string) $vocabulary['file'];
        if (!$filepath || !file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            throw new RuntimeException(
                sprintf(
                    'The file "%s" cannot be read. Check your file system or the url.', // @translate
                    strpos($filepath, '/') === 0 ? basename($filepath) : $filepath
                )
            );
        }

        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $this->api->read('vocabularies', ['prefix' => $prefix])->getContent();
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

    protected function canonicalFileOrUrl($file, string $module, string $dataDirectory, string $mainFilepath): ?string
    {
        if (!$file) {
            return null;
        }

        if (strpos((string) $file, 'https://') !== false || strpos((string) $file, 'http://') !== false) {
            return $file;
        }

        $filepathData = OMEKA_PATH . '/modules/' . $module . '/data/';
        $filepath = $filepathData . ($dataDirectory ? $dataDirectory . '/' : '') . $file;
        if (file_exists($filepath)) {
            return $filepath;
        }

        // For compatibility with old modules.

        $filepath = dirname($mainFilepath) . '/' . $file;
        if (file_exists($filepath)) {
            return $filepath;
        }

        $filepath = OMEKA_PATH . '/modules/' . $module . '/' . $file;
        if (file_exists($filepath)) {
            return $filepath;
        }

        return null;
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
    public function checkResourceTemplate(string $filepath): bool
    {
        $data = json_decode(file_get_contents($filepath), true);
        if (!$data || empty($data['label'])) {
            return false;
        }

        try {
            $this->api->read('resource_templates', ['label' => $data['label']]);
        } catch (NotFoundException $e) {
            return false;
        }
        return true;
    }

    /**
     * Check if a custom vocab exists and throws an exception if different.
     *
     * @param string $filepath
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return bool False if not found, true if exists.
     */
    public function checkCustomVocab(string $filepath): bool
    {
        $data = json_decode(file_get_contents($filepath), true);
        if (!$data || empty($data['label'])) {
            return false;
        }

        $label = $data['o:label'];
        try {
            $customVocab = $this->api->read('custom_vocabs', ['label' => $label])->getContent();
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
    public function createVocabulary(array $vocabulary): bool
    {
        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $this->api->read('vocabularies', ['prefix' => $prefix])->getContent();
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
                (string) new Message(
                    'An error occured when adding the prefix "%s": another vocabulary exists with the same prefix. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                )
            );
        }

        /** @var \Omeka\Stdlib\RdfImporter $rdfImporter */
        $rdfImporter = $this->services->get('Omeka\RdfImporter');
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
                (string) new Message(
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
     * @throws \Omeka\Api\Exception\RuntimeException
     * @return \Omeka\Api\Representation\ResourceTemplateRepresentation
     */
    public function createResourceTemplate(string $filepath): ResourceTemplateRepresentation
    {
        $data = json_decode(file_get_contents($filepath), true);

        // Check if the resource template exists, so it is not replaced.
        $label = $data['o:label'];
        try {
            $resourceTemplate = $this->api->read('resource_templates', ['label' => $label])->getContent();
            $message = new Message(
                'The resource template named "%s" is already available and is skipped.', // @translate
                $label
            );
            $messenger = new Messenger();
            $messenger->addWarning($message);
            return $resourceTemplate;
        } catch (NotFoundException $e) {
        }

        // The check sets the internal ids of classes, properties and data types
        // and converts old data types into multiple data types and prepare
        // other data types (mainly custom vocabs).
        $data = $this->flagValid($data);

        // Process import.
        return $this->api->create('resource_templates', $data)->getContent();
    }

    /**
     * Create or update a custom vocab.
     *
     * @param string $filepath
     * @return ?\CustomVocab\Api\Representation\CustomVocabRepresentation|null
     */
    public function createOrUpdateCustomVocab(string $filepath): ?\CustomVocab\Api\Representation\CustomVocabRepresentation
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
     * @return \CustomVocab\Api\Representation\CustomVocabRepresentation|null
     */
    public function createCustomVocab(string $filepath): ?\CustomVocab\Api\Representation\CustomVocabRepresentation
    {
        $data = json_decode(file_get_contents($filepath), true);
        $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
        try {
            return $this->api->create('custom_vocabs', $data)->getContent();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Flag members and data types as valid.
     *
     * Copy of the method of the resource template controller (with services)
     * and remove of keys "data_types" inside "o:data".
     *
     * @see \AdvancedResourceTemplate\Controller\Admin\ResourceTemplateControllerDelegator::flagValid()
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
     * @todo Manage direct import of data types from Value Suggest and other modules.
     *
     * @param array $import
     * @return array|false
     */
    protected function flagValid(iterable $import)
    {
        $vocabs = [];

        // The controller plugin Api is used to allow to search one resource.
        $api = $this->services->get('ControllerPluginManager')->get('api');

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

        $getDataTypesByName = function ($dataTypesNameLabels) {
            $result = [];
            foreach ($dataTypesNameLabels ?? [] as $dataType) {
                $result[$dataType['name']] = $dataType;
            }
            return $result;
        };

        // Manage core data types and common modules ones.
        $getKnownDataType = function ($dataTypeNameLabel) use ($api): ?string {
            if (in_array($dataTypeNameLabel['name'], [
                'literal',
                'resource',
                'resource:item',
                'resource:itemset',
                'resource:media',
                'uri',
                // DataTypeGeometry
                'geometry:geography',
                'geometry:geometry',
                // DataTypeRdf.
                'boolean',
                'html',
                'xml',
                // DataTypePlace.
                'place',
                // NumericDataTypes
                'numeric:timestamp',
                'numeric:integer',
                'numeric:duration',
                'numeric:interval',
            ])
                || mb_substr((string) $dataTypeNameLabel['name'], 0, 13) === 'valuesuggest:'
                || mb_substr((string) $dataTypeNameLabel['name'], 0, 16) === 'valuesuggestall:'
            ) {
                return $dataTypeNameLabel['name'];
            }

            if (mb_substr((string) $dataTypeNameLabel['name'], 0, 12) === 'customvocab:') {
                try {
                    $customVocab = $api->read('custom_vocabs', ['label' => $dataTypeNameLabel['label']])->getContent();
                    return 'customvocab:' . $customVocab->id();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
            }
            return null;
        };

        if (isset($import['o:resource_class'])) {
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

        foreach (['o:title_property', 'o:description_property'] as $property) {
            if (isset($import[$property])) {
                if ($vocab = $getVocab($import[$property]['vocabulary_namespace_uri'])) {
                    $import[$property]['vocabulary_prefix'] = $vocab->prefix();
                    $prop = $api->searchOne('properties', [
                        'vocabulary_namespace_uri' => $import[$property]['vocabulary_namespace_uri'],
                        'local_name' => $import[$property]['local_name'],
                    ])->getContent();
                    if ($prop) {
                        $import[$property]['o:id'] = $prop->id();
                    }
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
                    // Check the deprecated "data_type_name" if needed and
                    // normalize it.
                    if (!array_key_exists('data_types', $import['o:resource_template_property'][$key])) {
                        if (!empty($import['o:resource_template_property'][$key]['data_type_name'])
                            && !empty($import['o:resource_template_property'][$key]['data_type_label'])
                        ) {
                            $import['o:resource_template_property'][$key]['data_types'] = [[
                                'name' => $import['o:resource_template_property'][$key]['data_type_name'],
                                'label' => $import['o:resource_template_property'][$key]['data_type_label'],
                            ]];
                        } else {
                            $import['o:resource_template_property'][$key]['data_types'] = [];
                        }
                    }
                    unset($import['o:resource_template_property'][$key]['data_type_name']);
                    unset($import['o:resource_template_property'][$key]['data_type_label']);
                    $import['o:resource_template_property'][$key]['data_types'] = $getDataTypesByName($import['o:resource_template_property'][$key]['data_types']);
                    // Prepare the list of standard data types.
                    $import['o:resource_template_property'][$key]['o:data_type'] = [];
                    foreach ($import['o:resource_template_property'][$key]['data_types'] as $name => $dataTypeNameLabel) {
                        $known = $getKnownDataType($dataTypeNameLabel);
                        if ($known) {
                            $import['o:resource_template_property'][$key]['o:data_type'][] = $known;
                            $import['o:resource_template_property'][$key]['data_types'][$name]['name'] = $known;
                        }
                    }
                    $import['o:resource_template_property'][$key]['o:data_type'] = array_unique($import['o:resource_template_property'][$key]['o:data_type']);
                    // Prepare the list of standard data types for duplicated
                    // properties (only one most of the time, that is the main).
                    $import['o:resource_template_property'][$key]['o:data'] = array_values($import['o:resource_template_property'][$key]['o:data'] ?? []);
                    $import['o:resource_template_property'][$key]['o:data'][0]['data_types'] = $import['o:resource_template_property'][$key]['data_types'] ?? [];
                    $import['o:resource_template_property'][$key]['o:data'][0]['o:data_type'] = $import['o:resource_template_property'][$key]['o:data_type'] ?? [];
                    $first = true;
                    foreach ($import['o:resource_template_property'][$key]['o:data'] as $k => $rtpData) {
                        if ($first) {
                            $first = false;
                            // Specific to the installer.
                            unset($import['o:resource_template_property'][$key]['o:data'][$k]['data_types']);
                            continue;
                        }
                        // Prepare the list of standard data types if any.
                        $import['o:resource_template_property'][$key]['o:data'][$k]['o:data_type'] = [];
                        if (empty($rtpData['data_types'])) {
                            continue;
                        }
                        $import['o:resource_template_property'][$key]['o:data'][$k]['data_types'] = $getDataTypesByName($import['o:resource_template_property'][$key]['o:data'][$k]['data_types']);
                        foreach ($import['o:resource_template_property'][$key]['o:data'][$k]['data_types'] as $name => $dataTypeNameLabel) {
                            $known = $getKnownDataType($dataTypeNameLabel);
                            if ($known) {
                                $import['o:resource_template_property'][$key]['o:data'][$k]['o:data_type'][] = $known;
                                $import['o:resource_template_property'][$key]['o:data'][$k]['data_types'][$name]['name'] = $known;
                            }
                        }
                        $import['o:resource_template_property'][$key]['o:data'][$k]['o:data_type'] = array_unique($import['o:resource_template_property'][$key]['o:data'][$k]['o:data_type']);
                        // Specific to the installer.
                        unset($import['o:resource_template_property'][$key]['o:data'][$k]['data_types']);
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
     * @return \CustomVocab\Api\Representation\CustomVocabRepresentation
     */
    public function updateCustomVocab(string $filepath): \CustomVocab\Api\Representation\CustomVocabRepresentation
    {
        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $customVocab = $this->api->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            throw new RuntimeException(
                (string) new Message(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                )
            );
        }

        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $terms = array_merge($terms, $data['o:terms']);
        $this->api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);

        return $customVocab;
    }

    /**
     * Remove a vocabulary by its prefix.
     *
     * @param string $prefix
     * @return self
     */
    public function removeVocabulary(string $prefix): self
    {
        // The vocabulary may have been removed manually before.
        try {
            $resource = $this->api->read('vocabularies', ['prefix' => $prefix])->getContent();
            $this->api->delete('vocabularies', $resource->id());
        } catch (NotFoundException $e) {
        }
        return $this;
    }

    /**
     * Remove a resource template by its label.
     *
     * @param string $label
     * @return self
     */
    public function removeResourceTemplate(string $label): self
    {
        // The resource template may be renamed or removed manually before.
        try {
            $resource = $this->api->read('resource_templates', ['label' => $label])->getContent();
            $this->api->delete('resource_templates', $resource->id());
        } catch (NotFoundException $e) {
        }
        return $this;
    }

    /**
     * Remove a custom vocab by its label.
     *
     * @param string $label
     * @return self
     */
    public function removeCustomVocab(string $label): self
    {
        // The custom vocab may be renamed or removed manually before.
        try {
            $resource = $this->api->read('custom_vocabs', ['label' => $label])->getContent();
            $this->api->delete('custom_vocabs', $resource->id());
        } catch (NotFoundException $e) {
        }
        return $this;
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
    protected function listFilesInDir($dirpath, iterable $extensions = []): array
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
