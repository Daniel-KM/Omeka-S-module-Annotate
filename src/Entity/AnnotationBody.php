<?php declare(strict_types=1);

namespace Annotate\Entity;

/**
 * @Entity
 */
class AnnotationBody extends AnnotationPart
{
    /**
     * @var Annotation
     *
     * @ManyToOne(
     *     targetEntity="Annotation",
     *     inversedBy="annotationBody"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $annotation;

    protected $part = \Annotate\Entity\AnnotationBody::class;

    public function getResourceName()
    {
        // An adapter should be set currently, since it's a derivative of
        // resource, but there is no api manager, only an hydrator.
        // return 'annotation_bodies';
        return 'annotations';
    }
}
