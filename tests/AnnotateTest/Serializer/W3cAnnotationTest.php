<?php declare(strict_types=1);

namespace AnnotateTest\Serializer;

use Annotate\Serializer\W3cAnnotation;
use AnnotateTest\AnnotateTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class W3cAnnotationTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    /**
     * @var W3cAnnotation
     */
    protected $serializer;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->serializer = new W3cAnnotation();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    // =========================================================
    // serialize()
    // =========================================================

    public function testSerializeContext(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals(
            W3cAnnotation::CONTEXT, $result['@context']
        );
    }

    public function testSerializeType(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals('Annotation', $result['type']);
    }

    public function testSerializeId(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals(
            'http://localhost/annotations/' . $annotation->id(),
            $result['id']
        );
    }

    public function testSerializeNoOmekaKeys(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayNotHasKey('o:id', $result);
        $this->assertArrayNotHasKey('@id', $result);
        $this->assertArrayNotHasKey('o:owner', $result);
        $this->assertArrayNotHasKey('o:created', $result);
        $this->assertArrayNotHasKey('o:modified', $result);
        $this->assertArrayNotHasKey('o:is_public', $result);
        $this->assertArrayNotHasKey('rdf:type', $result);
    }

    // =========================================================
    // serialize() — motivation
    // =========================================================

    public function testSerializeMotivationStripsOaPrefix(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'motivation' => 'oa:commenting',
        ]);

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals('commenting', $result['motivation']);
    }

    public function testSerializeMotivationWithoutPrefix(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'motivation' => 'tagging',
        ]);

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals('tagging', $result['motivation']);
    }

    public function testSerializeMotivationRemovesWrappedKey(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'motivation' => 'oa:commenting',
        ]);

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        // The wrapped "motivatedBy" key must not exist.
        $this->assertArrayNotHasKey(
            'motivatedBy', $result
        );
    }

    // =========================================================
    // serialize() — body
    // =========================================================

    public function testSerializeBodyTextualBody(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'body' => 'Test text',
        ]);

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey('body', $result);
        $this->assertEquals(
            'TextualBody', $result['body']['type']
        );
        $this->assertEquals(
            'Test text', $result['body']['value']
        );
    }

    public function testSerializeBodyStripsTags(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id(), [
            'body' => '<b>Bold</b> text',
        ]);

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertEquals('Bold text', $result['body']['value']);
    }

    // =========================================================
    // serialize() — target
    // =========================================================

    public function testSerializeTargetHasSource(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey('target', $result);
        $target = $result['target'];
        $this->assertEquals(
            'SpecificResource', $target['type']
        );
    }

    // =========================================================
    // serialize() — creator
    // =========================================================

    public function testSerializeCreatorId(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey('creator', $result);
        $this->assertEquals(
            'Person', $result['creator']['type']
        );
        $this->assertArrayHasKey('id', $result['creator']);
        $this->assertStringContainsString(
            '/api/users/', $result['creator']['id']
        );
    }

    public function testSerializeCreatorNameByDefault(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        // Name included by default.
        $this->assertArrayHasKey(
            'name', $result['creator']
        );
        $this->assertNotEmpty($result['creator']['name']);
    }

    public function testSerializeCreatorNameDisabled(): void
    {
        $serializer = new W3cAnnotation([]);
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayNotHasKey(
            'name', $result['creator']
        );
    }

    public function testSerializeCreatorNoEmailByDefault(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayNotHasKey(
            'email', $result['creator']
        );
        $this->assertArrayNotHasKey(
            'email_sha1', $result['creator']
        );
    }

    public function testSerializeCreatorEmailEnabled(): void
    {
        $serializer = new W3cAnnotation(['name', 'email']);
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey(
            'email', $result['creator']
        );
        $this->assertStringStartsWith(
            'mailto:', $result['creator']['email']
        );
    }

    public function testSerializeCreatorEmailSha1Enabled(): void
    {
        $serializer = new W3cAnnotation(['email_sha1']);
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey(
            'email_sha1', $result['creator']
        );
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{40}$/',
            $result['creator']['email_sha1']
        );
        $this->assertArrayNotHasKey(
            'email', $result['creator']
        );
    }

    public function testSerializeCreatorAllFields(): void
    {
        $serializer = new W3cAnnotation(
            ['name', 'email', 'email_sha1']
        );
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey('name', $result['creator']);
        $this->assertArrayHasKey('email', $result['creator']);
        $this->assertArrayHasKey(
            'email_sha1', $result['creator']
        );
    }

    public function testSerializeCreatorMinimal(): void
    {
        $serializer = new W3cAnnotation([]);
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        // Only type + id.
        $this->assertCount(2, $result['creator']);
        $this->assertEquals(
            'Person', $result['creator']['type']
        );
        $this->assertArrayHasKey('id', $result['creator']);
    }

    // =========================================================
    // serialize() — created/modified
    // =========================================================

    public function testSerializeCreatedIso8601(): void
    {
        $item = $this->createItem();
        $annotation = $this->createAnnotation($item->id());

        $result = $this->serializer->serialize(
            $annotation, 'http://localhost/annotations'
        );

        $this->assertArrayHasKey('created', $result);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $result['created']
        );
    }

    // =========================================================
    // serializeContainer() — root
    // =========================================================

    public function testContainerRootContext(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/annotations', -1, 200
        );

        $this->assertIsArray($result['@context']);
        $this->assertContains(
            W3cAnnotation::CONTEXT, $result['@context']
        );
        $this->assertContains(
            'http://www.w3.org/ns/ldp.jsonld',
            $result['@context']
        );
    }

    public function testContainerRootTypes(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/annotations', -1, 200
        );

        $this->assertContains(
            'BasicContainer', $result['type']
        );
        $this->assertContains(
            'AnnotationCollection', $result['type']
        );
    }

    public function testContainerRootTotal(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 42, 'http://localhost/annotations', -1, 200
        );

        $this->assertEquals(42, $result['total']);
    }

    public function testContainerRootFirstLast(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 500, 'http://localhost/ann', -1, 200
        );

        $this->assertEquals(
            'http://localhost/ann?page=0', $result['first']
        );
        $this->assertEquals(
            'http://localhost/ann?page=2', $result['last']
        );
    }

    public function testContainerRootEmptyNoFirstLast(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 0, 'http://localhost/annotations', -1, 200
        );

        $this->assertEquals(0, $result['total']);
        $this->assertArrayNotHasKey('first', $result);
        $this->assertArrayNotHasKey('last', $result);
    }

    public function testContainerRootId(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 1, 'http://localhost/annotations', -1, 200
        );

        $this->assertEquals(
            'http://localhost/annotations', $result['id']
        );
    }

    // =========================================================
    // serializeContainer() — page
    // =========================================================

    public function testContainerPageType(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/ann', 0, 200
        );

        $this->assertEquals('AnnotationPage', $result['type']);
    }

    public function testContainerPageId(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/ann', 2, 200
        );

        $this->assertEquals(
            'http://localhost/ann?page=2', $result['id']
        );
    }

    public function testContainerPagePartOf(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/ann', 0, 200
        );

        $this->assertEquals(
            'http://localhost/ann', $result['partOf']
        );
    }

    public function testContainerPageStartIndex(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 100, 'http://localhost/ann', 2, 10
        );

        $this->assertEquals(20, $result['startIndex']);
    }

    public function testContainerPageNextLink(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 100, 'http://localhost/ann', 0, 10
        );

        $this->assertEquals(
            'http://localhost/ann?page=1', $result['next']
        );
    }

    public function testContainerLastPageNoNext(): void
    {
        // 10 items, 10 per page → single page (page 0).
        $result = $this->serializer->serializeContainer(
            [], 10, 'http://localhost/ann', 0, 10
        );

        $this->assertArrayNotHasKey('next', $result);
    }

    public function testContainerPageItems(): void
    {
        $item = $this->createItem();
        $a = $this->createAnnotation($item->id());

        $result = $this->serializer->serializeContainer(
            [$a], 1, 'http://localhost/ann', 0, 200
        );

        $this->assertCount(1, $result['items']);
        $this->assertEquals(
            'Annotation', $result['items'][0]['type']
        );
    }

    public function testContainerPageEmptyItems(): void
    {
        $result = $this->serializer->serializeContainer(
            [], 0, 'http://localhost/ann', 0, 200
        );

        $this->assertCount(0, $result['items']);
    }

    // =========================================================
    // w3cKey() — via Reflection
    // =========================================================

    /**
     * @dataProvider w3cKeyProvider
     */
    public function testW3cKey(
        string $input,
        string $expected
    ): void {
        $method = new \ReflectionMethod(
            W3cAnnotation::class, 'w3cKey'
        );
        $method->setAccessible(true);

        $this->assertEquals(
            $expected,
            $method->invoke($this->serializer, $input)
        );
    }

    public function w3cKeyProvider(): array
    {
        return [
            'motivation' => [
                'oa:motivatedBy', 'motivation',
            ],
            'body' => ['oa:hasBody', 'body'],
            'target' => ['oa:hasTarget', 'target'],
            'source' => ['oa:hasSource', 'source'],
            'selector' => ['oa:hasSelector', 'selector'],
            'purpose' => ['oa:hasPurpose', 'purpose'],
            'exact' => ['oa:exact', 'exact'],
            'prefix' => ['oa:prefix', 'prefix'],
            'suffix' => ['oa:suffix', 'suffix'],
            'value' => ['rdf:value', 'value'],
            'format' => ['dcterms:format', 'format'],
            'language' => ['dcterms:language', 'language'],
            'conformsTo' => [
                'dcterms:conformsTo', 'conformsTo',
            ],
            'label' => ['rdfs:label', 'label'],
            'email' => ['foaf:mbox', 'email'],
            'unknown passthrough' => [
                'foo:bar', 'foo:bar',
            ],
        ];
    }

    // =========================================================
    // cleanMotivation() — via Reflection
    // =========================================================

    /**
     * @dataProvider cleanMotivationProvider
     */
    public function testCleanMotivation(
        string $input,
        string $expected
    ): void {
        $method = new \ReflectionMethod(
            W3cAnnotation::class, 'cleanMotivation'
        );
        $method->setAccessible(true);

        $this->assertEquals(
            $expected,
            $method->invoke($this->serializer, $input)
        );
    }

    public function cleanMotivationProvider(): array
    {
        return [
            'with oa prefix' => [
                'oa:commenting', 'commenting',
            ],
            'without prefix' => [
                'commenting', 'commenting',
            ],
            'describing' => [
                'oa:describing', 'describing',
            ],
            'custom prefix kept' => [
                'ex:myMotivation', 'ex:myMotivation',
            ],
        ];
    }

    // =========================================================
    // Constants
    // =========================================================

    public function testContentTypeConstant(): void
    {
        $this->assertStringContainsString(
            'application/ld+json',
            W3cAnnotation::CONTENT_TYPE
        );
        $this->assertStringContainsString(
            'anno.jsonld',
            W3cAnnotation::CONTENT_TYPE
        );
    }

    public function testContextConstant(): void
    {
        $this->assertEquals(
            'http://www.w3.org/ns/anno.jsonld',
            W3cAnnotation::CONTEXT
        );
    }
}
