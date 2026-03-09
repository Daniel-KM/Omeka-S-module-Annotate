<?php declare(strict_types=1);

namespace AnnotateTest\Api\Adapter;

use Annotate\Api\Representation\AnnotationRepresentation;
use Annotate\Entity\Annotation;
use AnnotateTest\AnnotateTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class AnnotationAdapterTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testGetResourceName(): void
    {
        $adapter = $this->getAnnotationAdapter();
        $this->assertEquals('annotations', $adapter->getResourceName());
    }

    public function testGetRepresentationClass(): void
    {
        $adapter = $this->getAnnotationAdapter();
        $this->assertEquals(
            AnnotationRepresentation::class,
            $adapter->getRepresentationClass()
        );
    }

    public function testGetEntityClass(): void
    {
        $adapter = $this->getAnnotationAdapter();
        $this->assertEquals(
            Annotation::class,
            $adapter->getEntityClass()
        );
    }

    public function testSearchReturnsEmptyWhenNoAnnotations(): void
    {
        $response = $this->api()->search('annotations', []);
        $this->assertEquals(0, $response->getTotalResults());
        $this->assertEmpty($response->getContent());
    }

    public function testCreateAnnotation(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $this->assertNotNull($annotation->id());
        $this->assertInstanceOf(
            AnnotationRepresentation::class,
            $annotation
        );
    }

    public function testReadAnnotation(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $response = $this->api()->read(
            'annotations',
            $annotation->id()
        );
        $read = $response->getContent();

        $this->assertEquals($annotation->id(), $read->id());
    }

    public function testDeleteAnnotation(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());
        $annotationId = $annotation->id();

        // Remove from tracked list (we are deleting it manually).
        $this->createdAnnotations = array_diff(
            $this->createdAnnotations,
            [$annotationId]
        );

        $this->deleteAnnotation($annotationId);

        $conn = $this->getEntityManager()->getConnection();
        $count = (int) $conn->executeQuery(
            'SELECT COUNT(*) FROM annotation WHERE id = ?',
            [$annotationId]
        )->fetchOne();
        $this->assertEquals(0, $count);
    }

    public function testSearchByResourceId(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();

        $this->createAnnotation($item1->id());
        $this->createAnnotation($item2->id());

        $response = $this->api()->search('annotations', [
            'resource_id' => $item1->id(),
        ]);

        $this->assertEquals(1, $response->getTotalResults());
    }

    public function testSearchByOwnerId(): void
    {
        $item = $this->createItem();
        $this->createAnnotation($item->id());

        $adminUser = $this->getCurrentUser();
        $response = $this->api()->search('annotations', [
            'owner_id' => $adminUser->getId(),
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            $response->getTotalResults()
        );
    }

    public function testSearchPagination(): void
    {
        $item = $this->createItem();
        $this->createAnnotation($item->id());
        $this->createAnnotation($item->id(), [
            'body' => 'Second annotation.',
        ]);

        $response = $this->api()->search('annotations', [
            'resource_id' => $item->id(),
            'page' => 1,
            'per_page' => 1,
        ]);

        $this->assertEquals(1, count($response->getContent()));
        $this->assertEquals(2, $response->getTotalResults());
    }

    public function testAnnotationValueSideTable(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $sql = 'SELECT av.id, av.field, av.ordinal '
            . 'FROM annotation_value av '
            . 'WHERE av.annotation_id = :id '
            . 'ORDER BY av.field, av.ordinal';
        $rows = $conn->executeQuery(
            $sql,
            ['id' => $annotation->id()]
        )->fetchAllAssociative();

        $this->assertNotEmpty($rows);

        $fields = array_column($rows, 'field');
        $this->assertContains('annotation', $fields);
        $this->assertContains('body', $fields);
        $this->assertContains('target', $fields);
    }

    public function testAnnotationValueCascadeDelete(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());
        $annotationId = $annotation->id();

        $conn = $this->getEntityManager()->getConnection();

        // Verify side-table entries exist.
        $count = (int) $conn->executeQuery(
            'SELECT COUNT(*) FROM annotation_value '
            . 'WHERE annotation_id = :id',
            ['id' => $annotationId]
        )->fetchOne();
        $this->assertGreaterThan(0, $count);

        // Delete annotation (values cascade via FK).
        $this->createdAnnotations = array_diff(
            $this->createdAnnotations,
            [$annotationId]
        );
        $this->deleteAnnotation($annotationId);

        // Verify side-table entries are gone.
        $count = (int) $conn->executeQuery(
            'SELECT COUNT(*) FROM annotation_value '
            . 'WHERE annotation_id = :id',
            ['id' => $annotationId]
        )->fetchOne();
        $this->assertEquals(0, $count);
    }

    public function testAnnotationValueIdIsFkToValue(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        // Verify annotation_value.id matches value.id.
        $sql = 'SELECT av.id FROM annotation_value av '
            . 'WHERE av.annotation_id = :id';
        $avIds = $conn->executeQuery(
            $sql,
            ['id' => $annotation->id()]
        )->fetchFirstColumn();

        foreach ($avIds as $avId) {
            $valueExists = (bool) $conn->executeQuery(
                'SELECT 1 FROM value WHERE id = :id',
                ['id' => $avId]
            )->fetchOne();
            $this->assertTrue(
                $valueExists,
                "annotation_value.id=$avId should reference value.id"
            );
        }
    }

    public function testAnnotationIsPublic(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'is_public' => false,
        ]);

        $this->assertFalse($annotation->isPublic());
    }

    public function testSearchSortByCreated(): void
    {
        $item = $this->createItem();
        $a1 = $this->createAnnotation($item->id(), [
            'body' => 'First',
        ]);
        $a2 = $this->createAnnotation($item->id(), [
            'body' => 'Second',
        ]);

        $response = $this->api()->search('annotations', [
            'sort_by' => 'created',
            'sort_order' => 'asc',
        ]);

        $results = $response->getContent();
        $this->assertGreaterThanOrEqual(2, count($results));
        $this->assertTrue(
            $results[0]->id() <= $results[1]->id()
        );
    }
}
