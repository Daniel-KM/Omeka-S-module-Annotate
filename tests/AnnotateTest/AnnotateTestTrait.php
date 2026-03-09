<?php declare(strict_types=1);

namespace AnnotateTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\UserRepresentation;

trait AnnotateTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array
     */
    protected $createdResources = [];

    /**
     * @var array
     */
    protected $createdAnnotations = [];

    /**
     * @var array
     */
    protected $createdUsers = [];

    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function loginAs(
        string $email,
        string $password = 'test'
    ): void {
        $auth = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $auth->authenticate();
    }

    protected function logout(): void
    {
        $auth = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * @return \Omeka\Entity\User|null
     */
    protected function getCurrentUser()
    {
        $auth = $this->getServiceLocator()
            ->get('Omeka\AuthenticationService');
        return $auth->getIdentity();
    }

    protected function createUser(
        string $email,
        string $name,
        string $role = 'researcher'
    ): UserRepresentation {
        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => $name,
            'o:role' => $role,
            'o:is_active' => true,
        ]);
        $user = $response->getContent();
        $this->createdUsers[] = $user->id();

        $entityManager = $this->getEntityManager();
        $userEntity = $entityManager->find(
            \Omeka\Entity\User::class,
            $user->id()
        );
        $userEntity->setPassword('test');
        $entityManager->flush();

        return $user;
    }

    protected function createItem(
        array $data = []
    ): ItemRepresentation {
        $itemData = [];

        if (!isset($data['dcterms:title'])) {
            $data['dcterms:title'] = [
                ['type' => 'literal', '@value' => 'Test Item'],
            ];
        }

        $easyMeta = $this->getServiceLocator()
            ->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            if (strpos($term, ':') === false) {
                $itemData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                if (isset($value['@id'])) {
                    $valueData['@id'] = $value['@id'];
                }
                $itemData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdResources[] = [
            'type' => 'items',
            'id' => $item->id(),
        ];

        return $item;
    }

    /**
     * Create an annotation via entity manager.
     *
     * @return \Annotate\Api\Representation\AnnotationRepresentation
     */
    protected function createAnnotation(
        int $resourceId,
        array $data = []
    ) {
        $em = $this->getEntityManager();
        $easyMeta = $this->getServiceLocator()
            ->get('Common\EasyMeta');
        $currentUser = $this->getCurrentUser();

        // Create annotation resource.
        $annotation = new \Annotate\Entity\Annotation();
        $annotation->setOwner($currentUser);
        $annotation->setIsPublic($data['is_public'] ?? true);
        $annotation->setCreated(new \DateTime('now'));

        // Set resource class to oa:Annotation.
        $resourceClassId = $easyMeta->resourceClassId('oa:Annotation');
        if ($resourceClassId) {
            $resourceClass = $em->find(
                \Omeka\Entity\ResourceClass::class,
                $resourceClassId
            );
            $annotation->setResourceClass($resourceClass);
        }

        $em->persist($annotation);
        $em->flush();

        $annotationId = $annotation->getId();
        $this->createdAnnotations[] = $annotationId;

        // Add oa:hasSource value pointing to annotated resource.
        $hasSourceId = $easyMeta->propertyId('oa:hasSource');
        $resource = $em->find(
            \Omeka\Entity\Resource::class,
            $resourceId
        );

        $value = new \Omeka\Entity\Value();
        $value->setResource($annotation);
        $value->setProperty(
            $em->find(\Omeka\Entity\Property::class, $hasSourceId)
        );
        $value->setType('resource');
        $value->setValueResource($resource);
        $value->setIsPublic(true);
        $em->persist($value);

        // Add oa:motivatedBy value.
        $motivatedById = $easyMeta
            ->propertyId('oa:motivatedBy');
        $motivationValue = new \Omeka\Entity\Value();
        $motivationValue->setResource($annotation);
        $motivationValue->setProperty(
            $em->find(
                \Omeka\Entity\Property::class,
                $motivatedById
            )
        );
        $motivationValue->setType('literal');
        $motivationValue->setValue(
            $data['motivation'] ?? 'oa:commenting'
        );
        $motivationValue->setIsPublic(true);
        $em->persist($motivationValue);

        // Add body rdf:value if provided.
        $bodyText = $data['body'] ?? 'Test annotation body.';
        $rdfValueId = $easyMeta->propertyId('rdf:value');
        $bodyValue = new \Omeka\Entity\Value();
        $bodyValue->setResource($annotation);
        $bodyValue->setProperty(
            $em->find(\Omeka\Entity\Property::class, $rdfValueId)
        );
        $bodyValue->setType('literal');
        $bodyValue->setValue($bodyText);
        $bodyValue->setIsPublic(true);
        $em->persist($bodyValue);

        $em->flush();

        // Create annotation_value side-table entries.
        $av1 = new \Annotate\Entity\AnnotationValue();
        $av1->setAnnotation($annotation);
        $av1->setValue($motivationValue);
        $av1->setField('annotation');
        $av1->setOrdinal(0);
        $em->persist($av1);

        $av2 = new \Annotate\Entity\AnnotationValue();
        $av2->setAnnotation($annotation);
        $av2->setValue($bodyValue);
        $av2->setField('body');
        $av2->setOrdinal(1);
        $em->persist($av2);

        $av3 = new \Annotate\Entity\AnnotationValue();
        $av3->setAnnotation($annotation);
        $av3->setValue($value);
        $av3->setField('target');
        $av3->setOrdinal(1);
        $em->persist($av3);

        $em->flush();

        $adapter = $this->getAnnotationAdapter();
        return $adapter->getRepresentation($annotation);
    }

    /**
     * @return \Annotate\Api\Adapter\AnnotationAdapter
     */
    protected function getAnnotationAdapter()
    {
        return $this->getServiceLocator()
            ->get('Omeka\ApiAdapterManager')
            ->get('annotations');
    }

    /**
     * Delete an annotation with its values (FK-safe).
     */
    protected function deleteAnnotation(int $annotationId): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->executeStatement(
            'DELETE FROM annotation_value WHERE annotation_id = ?',
            [$annotationId]
        );
        $conn->executeStatement(
            'DELETE FROM value WHERE resource_id = ?',
            [$annotationId]
        );
        $conn->executeStatement(
            'DELETE FROM annotation WHERE id = ?',
            [$annotationId]
        );
        $conn->executeStatement(
            'DELETE FROM resource WHERE id = ?',
            [$annotationId]
        );
    }

    protected function cleanupResources(): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        foreach ($this->createdAnnotations as $annotationId) {
            try {
                $this->deleteAnnotation($annotationId);
            } catch (\Exception $e) {
            }
        }
        $this->createdAnnotations = [];

        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete(
                    $resource['type'],
                    $resource['id']
                );
            } catch (\Exception $e) {
            }
        }
        $this->createdResources = [];

        foreach ($this->createdUsers as $userId) {
            try {
                $this->api()->delete('users', $userId);
            } catch (\Exception $e) {
            }
        }
        $this->createdUsers = [];
    }
}
