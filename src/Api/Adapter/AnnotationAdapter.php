<?php
namespace Annotate\Api\Adapter;

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationTarget;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class AnnotationAdapter extends AbstractResourceEntityAdapter
{
    protected $annotables = [
        \Omeka\Entity\Item::class,
        \Omeka\Entity\Media::class,
        \Omeka\Entity\ItemSet::class,
    ];

    protected $sortFields = [
        'id' => 'id',
        'is_public' => 'isPublic',
        'created' => 'created',
        'modified' => 'modified',
    ];

    public function getResourceName()
    {
        return 'annotations';
    }

    public function getRepresentationClass()
    {
        return \Annotate\Api\Representation\AnnotationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Annotate\Entity\Annotation::class;
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        parent::hydrate($request, $entity, $errorStore);

        $isUpdate = Request::UPDATE === $request->getOperation();
        $isPartial = $isUpdate && $request->getOption('isPartial');
        $append = $isPartial && 'append' === $request->getOption('collectionAction');
        $remove = $isPartial && 'remove' === $request->getOption('collectionAction');

        $childEntities = [
            'o-module-annotate:body' => 'annotation_bodies',
            'o-module-annotate:target' => 'annotation_targets',
        ];
        foreach ($childEntities as $jsonName => $resourceName) {
            if ($this->shouldHydrate($request, $jsonName)) {
                $childrenData = $request->getValue($jsonName, []);
                $adapter = $this->getAdapter($resourceName);
                $class = $adapter->getEntityClass();
                $retainChildren = [];
                foreach ($childrenData as $childData) {
                    $subErrorStore = new ErrorStore;
                    // Keep an existing child.
                    if (is_object($childData)) {
                        $child = $this->getAdapter($resourceName)
                            ->findEntity($childData);
                        $retainChildren[] = $child;
                    } elseif (isset($childData['o:id'])) {
                        $child = $adapter->findEntity($childData['o:id']);
                        if (isset($childData['o:is_public'])) {
                            $child->setIsPublic($childData['o:is_public']);
                        }
                        $retainChildren[] = $child;
                    }
                    // Create a new child.
                    else {
                        $child = new $class;
                        $child->setAnnotation($entity);
                        $subrequest = new Request(Request::CREATE, $resourceName);
                        $subrequest->setContent($childData);
                        try {
                            $adapter->hydrateEntity($subrequest, $child, $subErrorStore);
                        } catch (Exception\ValidationException $e) {
                            $errorStore->mergeErrors($e->getErrorStore(), $jsonName);
                        }
                        switch ($resourceName) {
                            case 'annotation_bodies':
                                $entity->getBodies()->add($child);
                                break;
                            case 'annotation_targets':
                                $entity->getTargets()->add($child);
                                break;
                        }
                        $retainChildren[] = $child;
                    }
                }
                // Remove child not included in request.
                switch ($resourceName) {
                    case 'annotation_bodies':
                        $children = $entity->getBodies();
                        break;
                    case 'annotation_targets':
                        $children = $entity->getTargets();
                        break;
                }
                foreach ($children as $child) {
                    if (!in_array($child, $retainChildren, true)) {
                        $children->removeElement($child);
                    }
                }
            }
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (array_key_exists('o-module-annotate:body', $data)
            && !is_array($data['o-module-annotate:body'])
        ) {
            $errorStore->addError('o-module-annotate:body', 'Body must be an array'); // @translate
        }

        if (array_key_exists('o-module-annotate:target', $data)
            && !is_array($data['o-module-annotate:target'])
        ) {
            $errorStore->addError('o-module-annotate:target', 'Targets must be an array'); // @translate
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        parent::buildQuery($qb, $query);

        if (isset($query['id'])) {
            $qb->andWhere($qb->expr()->eq('Annotate\Entity\Annotation.id', $query['id']));
        }

        if (isset($query['resource_id'])) {
            $resources = $query['resource_id'];
            if (!is_array($resources)) {
                $resources = [$resources];
            }
            $resources = array_filter($resources, 'is_numeric');

            if ($resources) {
                // TODO Make the property id of oa:hasSource static or integrate it to avoid a double query.
                $propertyId = (int) $this->getPropertyByTerm('oa:hasSource')->getId();
                // The resource is attached via the property oa:hasSource of the
                // AnnotationTargets, that are attached to annotations.
                $targetAlias = $this->createAlias();
                $qb->innerJoin(
                    AnnotationTarget::class,
                    $targetAlias,
                    'WITH',
                    $qb->expr()->eq($targetAlias . '.annotation', Annotation::class)
                );
                $valuesAlias = $this->createAlias();
                $qb->innerJoin(
                    $targetAlias . '.values',
                    $valuesAlias,
                    'WITH',
                    $qb->expr()->andX(
                        $qb->expr()->eq($valuesAlias . '.property', $propertyId),
                        $qb->expr()->eq($valuesAlias . '.type', $this->createNamedParameter($qb, 'resource')),
                        $qb->expr()->in($valuesAlias . '.valueResource', $this->createNamedParameter($qb, $resources))
                    )
                );
            }
        }

        // TODO Build queries to find annotations by query on targets and bodies here?
    }
}
