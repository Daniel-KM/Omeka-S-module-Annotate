<?php declare(strict_types=1);

namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

/**
 * The annotation body adapter is a simple hydrator, but have the nearly same feature than a full adapter..
 */
class AnnotationBodyHydrator extends AbstractResourceEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName(): void
    {
        // return 'annotation_bodies';
    }

    public function getRepresentationClass()
    {
        return \Annotate\Api\Representation\AnnotationBodyRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Annotate\Entity\AnnotationBody::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        // The annotation id may be set or not, it is not updated in any case.
        parent::hydrate($request, $entity, $errorStore);

        $data = $request->getContent();

        if (Request::CREATE === $request->getOperation()) {
            if (!empty($data['oa:Annotation'])) {
                if (is_object($data['oa:Annotation'])) {
                    $annotation = $this->getAdapter('annotations')
                        ->findEntity($data['oa:Annotation']->id());
                    $entity->setAnnotation($annotation);
                } elseif (isset($data['oa:Annotation']['o:id'])) {
                    $annotation = $this->getAdapter('annotations')
                        ->findEntity($data['oa:Annotation']['o:id']);
                    $entity->setAnnotation($annotation);
                }
            }
        }
    }

    protected function authorize(EntityInterface $entity, $privilege)
    {
        // Always return true, since it's an hydrator (even if it has all the
        // features of an adapter, except the declaration in the config).
        return true;
    }

    public function hydrateOwner(Request $request, EntityInterface $entity): void
    {
        $annotation = $entity->getAnnotation();
        if ($annotation instanceof Annotation) {
            $entity->setOwner($annotation->getOwner());
        }
    }

    public function validateEntity(
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        if (!($entity->getAnnotation() instanceof Annotation)) {
            $errorStore->addError('oa:Annotation', 'An annotation body must be attached to an Annotation.'); // @translate
        }
        parent::validateEntity($entity, $errorStore);
    }
}
