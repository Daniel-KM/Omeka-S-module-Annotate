<?php declare(strict_types=1);

namespace Annotate\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Value;

/**
 * @Entity
 * @Table(
 *     name="annotation_value",
 *     indexes={
 *         @Index(
 *             name="idx_annotation_value_parts",
 *             columns={"annotation_id", "field", "ordinal"}
 *         )
 *     }
 * )
 */
class AnnotationValue extends AbstractEntity
{
    /**
     * @Id
     * @OneToOne(targetEntity="Omeka\Entity\Value")
     * @JoinColumn(name="id", referencedColumnName="id", nullable=false,
     *     onDelete="CASCADE")
     */
    protected $value;

    /**
     * @ManyToOne(targetEntity="Annotation")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $annotation;

    /**
     * @Column(type="string", nullable=false, length=190)
     */
    protected $field;

    /**
     * @Column(type="smallint", nullable=false, options={"default":1})
     */
    protected $ordinal = 1;

    public function getId()
    {
        return $this->value instanceof Value
            ? $this->value->getId()
            : $this->value;
    }

    public function setAnnotation(Annotation $annotation): self
    {
        $this->annotation = $annotation;
        return $this;
    }

    public function getAnnotation(): Annotation
    {
        return $this->annotation;
    }

    public function setValue(Value $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): Value
    {
        return $this->value;
    }

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setOrdinal(int $ordinal): self
    {
        $this->ordinal = $ordinal;
        return $this;
    }

    public function getOrdinal(): int
    {
        return $this->ordinal;
    }
}
