<?php declare(strict_types=1);

namespace AnnotateTest\Entity;

use Annotate\Entity\Annotation;
use Annotate\Entity\AnnotationValue;
use Omeka\Entity\Value;
use PHPUnit\Framework\TestCase;

class AnnotationValueTest extends TestCase
{
    protected $annotationValue;

    public function setUp(): void
    {
        $this->annotationValue = new AnnotationValue();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->annotationValue->getId());
    }

    public function testSetField(): void
    {
        $this->annotationValue->setField('body');
        $this->assertEquals('body', $this->annotationValue->getField());

        $this->annotationValue->setField('target');
        $this->assertEquals('target', $this->annotationValue->getField());

        $this->annotationValue->setField('annotation');
        $this->assertEquals(
            'annotation',
            $this->annotationValue->getField()
        );
    }

    public function testSetOrdinal(): void
    {
        $this->assertEquals(1, $this->annotationValue->getOrdinal());

        $this->annotationValue->setOrdinal(3);
        $this->assertEquals(3, $this->annotationValue->getOrdinal());

        $this->annotationValue->setOrdinal(0);
        $this->assertEquals(0, $this->annotationValue->getOrdinal());
    }

    public function testSetAnnotation(): void
    {
        $annotation = $this->createMock(Annotation::class);
        $this->annotationValue->setAnnotation($annotation);
        $this->assertSame(
            $annotation,
            $this->annotationValue->getAnnotation()
        );
    }

    public function testSetValue(): void
    {
        $value = $this->createMock(Value::class);
        $this->annotationValue->setValue($value);
        $this->assertSame(
            $value,
            $this->annotationValue->getValue()
        );
    }

    public function testFluentInterface(): void
    {
        $annotation = $this->createMock(Annotation::class);
        $value = $this->createMock(Value::class);

        $result = $this->annotationValue
            ->setAnnotation($annotation)
            ->setValue($value)
            ->setField('body')
            ->setOrdinal(2);

        $this->assertSame($this->annotationValue, $result);
    }

    public function testGetIdReturnsValueId(): void
    {
        $value = $this->createMock(Value::class);
        $value->method('getId')->willReturn(42);

        $this->annotationValue->setValue($value);
        $this->assertEquals(42, $this->annotationValue->getId());
    }
}
