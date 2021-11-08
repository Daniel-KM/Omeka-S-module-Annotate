<?php declare(strict_types=1);
namespace Annotate\Api\Representation;

use Annotate\Entity\AnnotationBody;

/**
 * The representation of an Annotation body.
 */
class AnnotationBodyRepresentation extends AbstractValueResourceEntityRepresentation
{
    /**
     * @var AnnotationBody
     */
    protected $resource;

    public function getResourceJsonLdType()
    {
        return 'oa:hasBody';
    }

    public function displayTitle($default = null, $lang = null)
    {
        // TODO Check if this is a textual value or not before setting the title.
        $title = $this->value('rdf:value', ['default' => null, 'lang' => $lang]);

        if ($title !== null) {
            return strip_tags((string) $title);
        }

        // TODO Add a specific title from the metadata of the body (motivation)?
        if ($default === null) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $default = sprintf($translator->translate('[Annotation body #%d]'), $this->id());
        }

        return $default;
    }
}
