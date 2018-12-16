<?php
namespace Annotate\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 */
class AnnotationBody extends Resource
{
    /**
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @var Annotation
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

    public function getResourceName()
    {
        // An adapter should be set currently, since it's a derivative of
        // resource, but there is no api manager, only an hydrator.
        // return 'annotation_bodies';
        return 'annotations';
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Annotation $annotation
     */
    public function setAnnotation(Annotation $annotation)
    {
        $this->annotation = $annotation;
    }

    /**
     * @return \Annotate\Entity\Annotation
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }
}
