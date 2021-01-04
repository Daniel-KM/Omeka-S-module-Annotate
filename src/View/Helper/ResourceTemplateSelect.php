<?php declare(strict_types=1);
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Annotate\View\Helper;

use Annotate\Form\Element\ResourceTemplateSelect as Select;
use Laminas\Form\Factory;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for rendering a select menu containing all resource templates.
 */
class ResourceTemplateSelect extends AbstractHelper
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $formElementManager;

    /**
     * Construct the helper.
     *
     * @param ServiceLocatorInterface $formElementManager
     */
    public function __construct(ServiceLocatorInterface $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Render a select menu containing all resource classes.
     *
     * @param array $spec
     * @return string
     */
    public function __invoke(array $spec = [])
    {
        $spec['type'] = Select::class;
        if (!isset($spec['options']['empty_option'])) {
            $spec['options']['empty_option'] = 'Select template…'; // @translate
        }
        $factory = new Factory($this->formElementManager);
        $element = $factory->createElement($spec);
        return $this->getView()->formSelect($element);
    }
}
