<?php declare(strict_types=1);

namespace AnnotateTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Save of the annotation parts of the resource templates.
 */
class ResourceTemplateTest extends AbstractHttpControllerTestCase
{
    use AnnotateTestTrait;

    protected $templateId;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        if ($this->templateId) {
            $this->api()->delete('resource_templates', $this->templateId);
        }
        $this->logout();
        parent::tearDown();
    }

    public function testCreateAndUpdateResourceTemplateWithAnnotationParts(): void
    {
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $titleId = $easyMeta->propertyId('dcterms:title');
        $formatId = $easyMeta->propertyId('dcterms:format');

        $template = $this->api()->create('resource_templates', [
            'o:label' => 'Annotate test template',
            'o:resource_template_property' => [
                $titleId => [
                    'o:property' => ['o:id' => $titleId],
                    'o:data' => [],
                    'data' => ['annotation_part' => 'oa:hasBody'],
                ],
                $formatId => [
                    'o:property' => ['o:id' => $formatId],
                    'o:data' => [],
                    'data' => ['annotation_part' => 'oa:hasBody'],
                ],
            ],
        ])->getContent();
        $this->templateId = $template->id();

        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $data = $settings->get('annotate_resource_template_data', []);
        $this->assertSame('oa:hasBody', $data[$this->templateId]['dcterms:title']);
        // The static mapping takes precedence over the template.
        $this->assertSame('oa:hasTarget', $data[$this->templateId]['dcterms:format']);

        $this->api()->update('resource_templates', $this->templateId, [
            'o:label' => 'Annotate test template',
            'o:resource_template_property' => [
                $titleId => [
                    'o:property' => ['o:id' => $titleId],
                    'o:data' => [],
                    'data' => ['annotation_part' => 'oa:hasTarget'],
                ],
            ],
        ]);
        $data = $settings->get('annotate_resource_template_data', []);
        $this->assertSame('oa:hasTarget', $data[$this->templateId]['dcterms:title']);

        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $this->assertSame(
            ['dcterms:title' => 'oa:hasTarget'],
            $plugins->get('resourceTemplateAnnotationPartMap')($this->templateId)
        );
    }
}
