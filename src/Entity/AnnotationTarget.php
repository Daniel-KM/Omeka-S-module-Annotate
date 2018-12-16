<?php
namespace Annotate\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 */
class AnnotationTarget extends Resource
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
     *     inversedBy="annotationTarget"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $annotation;

    public function getResourceName()
    {
        return 'annotation_targets';
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
