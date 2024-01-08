<?php declare(strict_types=1);

namespace Annotate\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\Settings;

class ResourceTemplateAnnotationPartMap extends AbstractPlugin
{
    /**
     * @var \Omeka\Settings\Settings
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
        $mapping = $this->settings->get('annotate_resource_template_data', []);
        return $mapping[$resourceTemplateId] ?? [];
    }
}
