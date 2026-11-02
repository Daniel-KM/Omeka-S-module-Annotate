<?php declare(strict_types=1);

namespace AnnotateTest\Api\Adapter;

use Annotate\Api\Adapter\AnnotationAdapter;
use AnnotateTest\AnnotateTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Cover the DigitalObject support added by getAnnotables().
 */
class DigitalObjectAnnotableTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists('DigitalObject\Module', false)) {
            $this->markTestSkipped('Module DigitalObject not installed.');
        }
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testGetAnnotablesIncludesDigitalObject(): void
    {
        /** @var AnnotationAdapter $adapter */
        $adapter = $this->getAnnotationAdapter();
        $annotables = $adapter->getAnnotables();

        self::assertContains(\Omeka\Entity\Item::class, $annotables);
        self::assertContains(\Omeka\Entity\Media::class, $annotables);
        self::assertContains(\Omeka\Entity\ItemSet::class, $annotables);
        self::assertContains(\DigitalObject\Entity\DigitalObject::class, $annotables);
    }

    protected function getAnnotationAdapter(): AnnotationAdapter
    {
        return $this->getApplication()
            ->getServiceManager()
            ->get('Omeka\ApiAdapterManager')
            ->get('annotations');
    }
}
