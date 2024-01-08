<?php declare(strict_types=1);

namespace Annotate\Mapping;

use CSVImport\Mapping\AbstractResourceMapping;
use Omeka\Stdlib\Message;

class AnnotationMapping extends AbstractResourceMapping
{
    protected $label = 'Annotation data'; // @translate
    protected $resourceType = 'annotations';

    protected function processGlobalArgs(): void
    {
        parent::processGlobalArgs();

        /** @var array $data */
        $data = &$this->data;

        // Set the default resource type as "annotations".
        if (isset($this->args['column-resource_type'])) {
            $this->map['resourceType'] = $this->args['column-resource_type'];
            $data['resource_type'] = 'annotations';
        }
    }

    protected function processCell($index, array $values): void
    {
        parent::processCell($index, $values);
        $this->processCellAnnotation($index, $values);

        /** @var array $data */
        $data = &$this->data;

        if (isset($this->map['resourceType'][$index])) {
            $resourceType = (string) reset($values);
            // Add some heuristic to avoid common issues.
            $resourceType = str_replace([' ', '_'], '', strtolower($resourceType));
            $resourceTypes = [
                'annotations' => 'annotations',
                'annotation' => 'annotations',
            ];
            if (isset($resourceTypes[$resourceType])) {
                $data['resource_type'] = $resourceTypes[$resourceType];
            } else {
                $this->logger->err(new Message('"%s" is not a valid resource type.', reset($values))); // @translate
                $this->setHasErr(true);
            }
        }
    }

    protected function processCellAnnotation($index, array $values): void
    {
        $data = &$this->data;

        if (isset($this->map['annotation'][$index])) {
            $identifierProperty = $this->map['annotation'][$index];
            $resourceType = 'annotations';
            $findResourceFromIdentifier = $this->findResourceFromIdentifier;
            foreach ($values as $identifier) {
                $resourceId = $findResourceFromIdentifier($identifier, $identifierProperty, $resourceType);
                if ($resourceId) {
                    $data['o:annotation'][] = ['o:id' => $resourceId];
                } else {
                    $this->logger->err(new Message('"%s" (%s) is not a valid annotation.', // @translate
                        $identifier, $identifierProperty));
                    $this->setHasErr(true);
                }
            }
        }
    }
}
