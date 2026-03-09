<?php declare(strict_types=1);

namespace Annotate\Api\Representation;

/**
 * Lightweight value object for a body or target part of an annotation. Not a
 * Resource representation.
 */
class AnnotationPartValues
{
    /**
     * @var string "body" or "target"
     */
    protected $field;

    /**
     * @var int
     */
    protected $ordinal;

    /**
     * Keyed by term, each value is an array of ValueRepresentation.
     *
     * @var array
     */
    protected $valuesByTerm;

    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    public function __construct(
        string $field,
        int $ordinal,
        array $valuesByTerm,
        $services
    ) {
        $this->field = $field;
        $this->ordinal = $ordinal;
        $this->valuesByTerm = $valuesByTerm;
        $this->services = $services;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function ordinal(): int
    {
        return $this->ordinal;
    }

    /**
     * Get values for a term, compatible with
     * AbstractResourceEntityRepresentation::value().
     *
     * @return mixed
     */
    public function value(string $term, array $options = [])
    {
        $all = $options['all'] ?? false;
        $default = $options['default'] ?? null;
        $values = $this->valuesByTerm[$term] ?? [];
        if ($all) {
            return $values;
        }
        return $values ? reset($values) : $default;
    }

    /**
     * Get all values grouped by term.
     */
    public function values(): array
    {
        return $this->valuesByTerm;
    }

    /**
     * Get the sources (target resources linked via oa:hasSource).
     */
    public function sources(): array
    {
        $result = [];
        foreach ($this->value('oa:hasSource', ['all' => true]) as $v) {
            $vr = $v->valueResource();
            if ($vr) {
                $result[] = $vr;
            }
        }
        return $result;
    }

    public function displayTitle($default = null): string
    {
        if ($this->field === 'body') {
            $v = $this->value('rdf:value');
            if ($v !== null) {
                return strip_tags((string) $v);
            }
        } elseif ($this->field === 'target') {
            $v = $this->value('oa:hasSource');
            if ($v !== null) {
                return (string) $v;
            }
        }
        if ($default === null) {
            $translator = $this->services
                ->get('MvcTranslator');
            $label = $this->field === 'body'
                ? 'Annotation body' // @translate
                : 'Annotation target'; // @translate
            $default = sprintf(
                $translator->translate('[%s]'),
                $translator->translate($label)
            );
        }
        return $default;
    }

    /**
     * Bodies/targets no longer have their own resource class.
     *
     * @return null
     */
    public function resourceClass()
    {
        return null;
    }

    /**
     * Render all values as HTML, compatible with template usage of the old
     * displayValues().
     */
    public function displayValues(): string
    {
        $escape = $this->services->get('ViewHelperManager')
            ->get('escapeHtml');
        $html = '';
        foreach ($this->valuesByTerm as $term => $values) {
            $html .= '<div class="property">';
            $html .= '<h4>' . $escape($term) . '</h4>';
            foreach ($values as $v) {
                $html .= '<div class="value">'
                    . $v->asHtml() . '</div>';
            }
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * JSON-LD serialization of this part.
     */
    public function jsonSerialize(): array
    {
        $result = [];
        foreach ($this->valuesByTerm as $term => $values) {
            foreach ($values as $v) {
                $result[$term][] = $v;
            }
        }
        return $result;
    }
}
