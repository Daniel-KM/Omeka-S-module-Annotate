<?php declare(strict_types=1);
namespace Annotate\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class AnnotationPartMapper extends AbstractPlugin
{
    /**
     * @var array
     */
    protected $map;

    protected $parts = [
        'oa:Annotation',
        'oa:hasBody',
        'oa:hasTarget',
    ];

    /**
     * @param array $map
     */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * Identify the annotation part of a property.
     *
     * The mapping can be modified directly in "data/mappings/properties_to_annotation_parts.php".
     * @link https://www.w3.org/TR/annotation-vocab
     *
     * @param string $term The property term.
     * @param string $default
     * @return string The part can be oa:Annotation, oa:hasBody, or oa:hasTarget.
     */
    public function __invoke($term, $default = 'oa:Annotation')
    {
        if (isset($this->map[$term])) {
            return is_array($this->map[$term])
                ? reset($this->map[$term])
                : $this->map[$term];
        }
        return in_array($default, $this->parts)
            ? $default
            : 'oa:Annotation';
    }
}
