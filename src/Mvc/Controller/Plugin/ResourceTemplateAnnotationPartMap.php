<?php
namespace Annotate\Mvc\Controller\Plugin;

use Omeka\Mvc\Controller\Plugin\Settings;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class ResourceTemplateAnnotationPartMap extends AbstractPlugin
{
    /**
     * @var Settings
     */
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get the annotation mapping of a resource template ().
     *
     * @todo Add these values directly in the json of the resource template via an event.
     *
     * @param int $resourceTemplateId
     * @return array
     */
    public function __invoke($resourceTemplateId)
    {
        $settings = $this->settings;
        $mapping = $settings()->get('annotate_resource_template_data', []);
        return isset($mapping[$resourceTemplateId])
            ? $mapping[$resourceTemplateId]
            : [];
    }
}
