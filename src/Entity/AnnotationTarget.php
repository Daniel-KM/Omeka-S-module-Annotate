<?php declare(strict_types=1);

namespace Annotate\Entity;

/**
 * @Entity
 */
class AnnotationTarget extends AnnotationPart
{
    /**
     * @var Annotation
     *
     * @ManyToOne(
     *     targetEntity="Annotation",
     *     inversedBy="annotationTarget"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $annotation;

    protected $part = \Annotate\Entity\AnnotationTarget::class;

    public function getResourceName()
    {
        // An adapter should be set currently, since it's a derivative of
        // resource, but there is no api manager, only an hydrator.
        // return 'annotation_targets';
        return 'annotations';
    }
}
