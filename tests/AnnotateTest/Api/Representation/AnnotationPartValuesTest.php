<?php declare(strict_types=1);

namespace AnnotateTest\Api\Representation;

use Annotate\Api\Representation\AnnotationPartValues;
use Omeka\Api\Representation\ValueRepresentation;
use PHPUnit\Framework\TestCase;

/**
 * The options "type" and "lang" of value() must filter the values, as in
 * AbstractResourceEntityRepresentation::value().
 */
class AnnotationPartValuesTest extends TestCase
{
    protected function valueRep(string $type, ?string $lang = null): ValueRepresentation
    {
        $value = $this->createMock(ValueRepresentation::class);
        $value->method('type')->willReturn($type);
        $value->method('lang')->willReturn($lang);
        return $value;
    }

    protected function part(array $valuesByTerm): AnnotationPartValues
    {
        return new AnnotationPartValues('target', 0, $valuesByTerm, null);
    }

    public function testValueFiltersByType(): void
    {
        $resource = $this->valueRep('resource');
        $geography = $this->valueRep('geography');
        $part = $this->part(['rdf:value' => [$resource, $geography]]);

        $this->assertSame($geography, $part->value('rdf:value', ['type' => 'geography']));
        $this->assertSame($geography, $part->value('rdf:value', ['type' => ['geometry', 'GEOGRAPHY']]));
        $this->assertSame($resource, $part->value('rdf:value'));
    }

    public function testValueReturnsDefaultWhenNoTypeMatches(): void
    {
        $part = $this->part(['rdf:value' => [$this->valueRep('resource')]]);

        $this->assertSame([], $part->value('rdf:value', ['type' => 'geography', 'default' => []]));
        $this->assertNull($part->value('rdf:value', ['type' => 'geography']));
        $this->assertNull($part->value('dcterms:format'));
    }

    public function testValueAllFiltersByType(): void
    {
        $geometry = $this->valueRep('geometry');
        $geography = $this->valueRep('geography');
        $part = $this->part(['rdf:value' => [$this->valueRep('resource'), $geometry, $geography]]);

        $this->assertSame(
            [$geometry, $geography],
            $part->value('rdf:value', ['all' => true, 'type' => ['geometry', 'geography']])
        );
        $this->assertSame([], $part->value('rdf:value', ['all' => true, 'type' => 'uri']));
        $this->assertSame([], $part->value('dcterms:format', ['all' => true]));
    }

    public function testValueFiltersByLang(): void
    {
        $fr = $this->valueRep('literal', 'fr');
        $en = $this->valueRep('literal', 'en');
        $part = $this->part(['dcterms:title' => [$fr, $en]]);

        $this->assertSame($en, $part->value('dcterms:title', ['lang' => 'EN']));
        $this->assertSame([$fr], $part->value('dcterms:title', ['all' => true, 'lang' => ['fr']]));
        $this->assertNull($part->value('dcterms:title', ['lang' => 'de']));
    }
}
