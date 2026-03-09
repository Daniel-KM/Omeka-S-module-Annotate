<?php declare(strict_types=1);

namespace AnnotateTest\Entity;

use Annotate\Entity\Annotation;
use PHPUnit\Framework\TestCase;

class AnnotationTest extends TestCase
{
    protected $annotation;

    public function setUp(): void
    {
        $this->annotation = new Annotation();
    }

    public function testGetResourceName(): void
    {
        $this->assertEquals(
            'annotations',
            $this->annotation->getResourceName()
        );
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->annotation->getId());
        $this->assertNull($this->annotation->getOwner());
        $this->assertNull($this->annotation->getResourceClass());
        $this->assertNull($this->annotation->getResourceTemplate());
        $this->assertTrue($this->annotation->isPublic());
    }

    public function testSetIsPublic(): void
    {
        $this->annotation->setIsPublic(false);
        $this->assertFalse($this->annotation->isPublic());

        $this->annotation->setIsPublic(true);
        $this->assertTrue($this->annotation->isPublic());
    }

    public function testSetCreated(): void
    {
        $created = new \DateTime('2024-01-15 10:30:00');
        $this->annotation->setCreated($created);
        $this->assertSame($created, $this->annotation->getCreated());
    }

    public function testSetModified(): void
    {
        $modified = new \DateTime('2024-01-16 14:00:00');
        $this->annotation->setModified($modified);
        $this->assertSame($modified, $this->annotation->getModified());
    }
}
