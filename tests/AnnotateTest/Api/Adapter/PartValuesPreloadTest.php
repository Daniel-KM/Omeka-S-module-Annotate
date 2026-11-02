<?php declare(strict_types=1);

namespace AnnotateTest\Api\Adapter;

use AnnotateTest\AnnotateTestTrait;
use Doctrine\DBAL\Logging\DebugStack;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The part values (bodies and targets) of the annotations returned by a search
 * are preloaded, so their number of queries does not grow with the results.
 */
class PartValuesPreloadTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    /**
     * @return int[] Ids of the created annotations.
     */
    protected function createAnnotations(int $total): array
    {
        $item = $this->createItem();
        $ids = [];
        for ($i = 0; $i < $total; $i++) {
            $ids[] = $this->createAnnotation($item->id(), ['body' => 'Body ' . $i])->id();
        }
        $this->getEntityManager()->clear();
        $this->loginAdmin();
        return $ids;
    }

    /**
     * Search the annotations, then read their parts while counting queries.
     *
     * @return array The body texts by annotation id and the number of queries.
     */
    protected function readParts(array $ids): array
    {
        $annotations = $this->api()->search('annotations', ['id' => $ids])->getContent();

        $logger = new DebugStack();
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger($logger);
        $bodies = [];
        foreach ($annotations as $annotation) {
            $body = $annotation->primaryBody();
            $target = $annotation->primaryTarget();
            $bodies[$annotation->id()] = [
                (string) $body->value('rdf:value'),
                $target->value('oa:hasSource', ['type' => 'resource']) !== null,
            ];
        }
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger(null);

        ksort($bodies);
        return [$bodies, count($logger->queries)];
    }

    public function testQueriesDoNotGrowWithTheNumberOfAnnotations(): void
    {
        [, $queriesForTwo] = $this->readParts($this->createAnnotations(2));
        $this->getEntityManager()->clear();
        $this->loginAdmin();
        [, $queriesForSix] = $this->readParts($this->createAnnotations(6));

        $this->assertSame($queriesForTwo, $queriesForSix);
    }

    public function testPreloadedPartsMatchSingleRead(): void
    {
        $ids = $this->createAnnotations(3);
        [$bodies] = $this->readParts($ids);

        $this->getEntityManager()->clear();
        $this->loginAdmin();
        $expected = [];
        foreach ($ids as $id) {
            $annotation = $this->api()->read('annotations', $id)->getContent();
            $expected[$id] = [
                (string) $annotation->primaryBody()->value('rdf:value'),
                $annotation->primaryTarget()->value('oa:hasSource', ['type' => 'resource']) !== null,
            ];
        }
        ksort($expected);

        $this->assertSame($expected, $bodies);
        $this->assertSame(['Body 0', true], reset($bodies));
    }
}
