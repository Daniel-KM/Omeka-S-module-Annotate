<?php declare(strict_types=1);

namespace AnnotateTest\Controller;

use AnnotateTest\AnnotateTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Anonymous annotations via the w3c endpoint and the api, when allowed.
 */
class AnonymousCreationTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    protected $item;

    protected $annotationId;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->item = $this->createItem();
        $this->annotationId = $this->createAnnotation($this->item->id())->id();
        $this->logout();
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->getServiceLocator()->get('Omeka\Settings')
            ->set('annotate_public_allow_annotate', false);
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testW3cPostRequiresAuthenticationByDefault(): void
    {
        $this->dispatchJson('/annotations', $this->w3cData('Anonymous'));
        $this->assertResponseStatusCode(401);
    }

    public function testW3cPostIsAllowedWhenPublicAnnotationIsEnabled(): void
    {
        $this->allowPublicAnnotation();
        $this->dispatchJson('/annotations', $this->w3cData('A fine view of the harbour.'));
        $this->assertResponseStatusCode(201);
        $this->trackCreated(json_decode($this->getResponse()->getContent(), true)['id'] ?? null);
    }

    public function testApiCreateIsAllowedWhenPublicAnnotationIsEnabled(): void
    {
        $this->allowPublicAnnotation();
        $this->dispatchJson('/api/annotations', [
            'oa:motivatedBy' => [['@value' => 'commenting']],
            'oa:hasBody' => [['rdf:value' => [['@value' => 'A fine view of the harbour.']]]],
            'oa:hasTarget' => [['oa:hasSource' => [['value_resource_id' => $this->item->id()]]]],
        ]);
        $this->assertResponseStatusCode(200);
        $this->trackCreated(json_decode($this->getResponse()->getContent(), true)['o:id'] ?? null);
    }

    public function testW3cSpamIsRejectedWithSpamGuard(): void
    {
        $this->allowPublicAnnotation();
        if (!$this->getServiceLocator()->has('SpamGuard\SpamChecker')) {
            $this->markTestSkipped('Module SpamGuard not installed.');
        }
        $this->dispatchJson('/annotations', $this->w3cData(str_repeat('https://spam.example.com/ ', 5)));
        $this->assertResponseStatusCode(400);
    }

    public function testW3cDeleteRequiresAuthentication(): void
    {
        $this->allowPublicAnnotation();
        $this->dispatch('/annotations/' . $this->annotationId, 'DELETE');
        $this->assertResponseStatusCode(401);
    }

    protected function allowPublicAnnotation(): void
    {
        $this->loginAdmin();
        $this->getServiceLocator()->get('Omeka\Settings')
            ->set('annotate_public_allow_annotate', true);
        $this->logout();
        // Rebuild the application, so the acl allows anonymous annotators.
        $this->reset();
        $this->services = null;
    }

    protected function w3cData(string $text): array
    {
        return [
            'motivation' => 'commenting',
            'body' => ['type' => 'TextualBody', 'value' => $text, 'purpose' => 'commenting'],
            'target' => ['source' => $this->item->apiUrl()],
        ];
    }

    /**
     * @param string|int|null $id A w3c iri or an omeka id.
     */
    protected function trackCreated($id): void
    {
        if ($id) {
            $this->createdAnnotations[] = (int) (is_numeric($id) ? $id : basename((string) $id));
        }
    }

    protected function dispatchJson(string $url, array $data): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode($data))
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');
        $this->dispatch($url);
    }
}
