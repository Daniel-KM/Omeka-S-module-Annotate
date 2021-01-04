<?php declare(strict_types=1);

namespace Annotate\Entity;

use Omeka\Entity\Resource;

/**
 * @Entity
 * @InheritanceType(
 *      "JOINED"
 * )
 * @DiscriminatorColumn(
 *     name="resource_type",
 *     type="string"
 * )
 * @Table(
 *     indexes={
 *         @Index(
 *             name="idx_part",
 *             columns={"part"}
 *         )
 *     }
 * )
 */
abstract class AnnotationPart extends Resource
{
    /**
     * @Id
     * @Column(type="integer")
     */
    protected $id;

    /**
     * Ideally, this should be nullable=false, but the Annotation requires the
     * current auto-generated id here, and it's added during post persist, when
     * created.
     * @see Annotation::annotation.
     *
     * @var Annotation
     *
     * @ManyToOne(
     *     targetEntity="Annotation",
     *     inversedBy="annotation"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="CASCADE"
     * )
     */
    protected $annotation;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     nullable=false,
     *     length=190
     * )
     */
    protected $part;

    public function setAnnotation(Annotation $annotation): \Annotate\Entity\AnnotationPart
    {
        $this->annotation = $annotation;
        return $this;
    }

    public function getAnnotation(): ?\Annotate\Entity\Annotation
    {
        return $this->annotation;
    }

    /**
     * Get the annotation part.
     *
     * Allows to bypass limit related to the discriminator column, that cannot
     * be requested easily by the ORM query builder.
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/inheritance-mapping.html#query-the-type
     *
     * @return string
     */
    public function getPart(): string
    {
        return $this->part;
    }
}
