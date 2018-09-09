<?php
namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class AnnotationTargetAdapter extends AbstractResourceEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'annotation_targets';
    }

    public function getRepresentationClass()
    {
        return \Annotate\Api\Representation\AnnotationTargetRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Annotate\Entity\AnnotationTarget::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        // Hydrate most of the metadata.
        parent::hydrate($request, $entity, $errorStore);

        $data = $request->getContent();

        if (Request::CREATE === $request->getOperation()) {
            if (!empty($data['o-module-annotate:annotation'])) {
                if (is_object($data['o-module-annotate:annotation'])) {
                    $annotation = $this->getAdapter('annotations')
                        ->findEntity($data['o-module-annotate:annotation']->id());
                    $entity->setAnnotation($annotation);
                } elseif (isset($data['o-module-annotate:annotation']['o:id'])) {
                    $annotation = $this->getAdapter('annotations')
                        ->findEntity($data['o-module-annotate:annotation']['o:id']);
                    $entity->setAnnotation($annotation);
                }
            }
        }
    }

    public function hydrateOwner(Request $request, EntityInterface $entity)
    {
        $annotation = $entity->getAnnotation();
        if ($annotation instanceof Annotation) {
            $entity->setOwner($annotation->getOwner());
        }
    }

    public function validateEntity(
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        if (!($entity->getAnnotation() instanceof Annotation)) {
            $errorStore->addError('o-module-annotate:annotation', 'An annotation target must be attached to an Annotation.'); // @translate
        }
        parent::validateEntity($entity, $errorStore);
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        parent::buildQuery($qb, $query);

        if (isset($query['id'])) {
            $qb->andWhere($qb->expr()->eq('Annotate\Entity\AnnotationTarget.id', $query['id']));
        }

        if (isset($query['annotation_id'])) {
            $qb->andWhere($qb->expr()->eq('Annotate\Entity\AnnotationTarget.annotation', $query['annotation_id']));
        }

        // TODO Build queries to find annotation target from annotation body here?
    }
}
