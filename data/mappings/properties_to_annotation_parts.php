<?php declare(strict_types=1);
/**
 * Standard or recommended non-ambivalent properties mapping with annotation
 * part.
 *
 * According to the workflow of the annotation module and derivated modules,
 * selectors are attached to the target by default. In the standard, most of the
 * targets and the bodies properties are usable for the two parts when it is a
 * resource (selector, state, scope and derivative properties).
 *
 * @link https://www.w3.org/TR/annotation-vocab.
 */

return [
    // Web Annotation ontology.
    'oa:annotationService' => 'oa:Annotation',
    'oa:bodyValue' => 'oa:Annotation',
    'oa:cachedSource' => 'oa:hasTarget',
    'oa:canonical' => 'oa:Annotation',
    'oa:end' => 'oa:hasTarget',
    'oa:exact' => 'oa:hasTarget',
    'oa:hasBody' => 'oa:Annotation',
    'oa:hasEndSelector' => 'oa:hasTarget',
    'oa:hasPurpose' => 'oa:hasBody',
    'oa:hasScope' => 'oa:hasTarget',
    'oa:hasSelector' => 'oa:hasTarget',
    'oa:hasSource' => 'oa:hasTarget',
    'oa:hasStartSelector' => 'oa:hasTarget',
    'oa:hasState' => 'oa:hasTarget',
    'oa:hasTarget' => 'oa:Annotation',
    'oa:motivatedBy' => 'oa:Annotation',
    'oa:prefix' => 'oa:hasTarget',
    'oa:processingLanguage' => 'oa:hasBody',
    'oa:refinedBy' => 'oa:hasTarget',
    'oa:renderedVia' => 'oa:hasTarget',
    'oa:sourceDate' => 'oa:hasTarget',
    'oa:sourceDateEnd' => 'oa:hasTarget',
    'oa:sourceDateStart' => 'oa:hasTarget',
    'oa:start' => 'oa:hasTarget',
    'oa:styleClass' => 'oa:hasTarget',
    'oa:styledBy' => 'oa:Annotation',
    'oa:suffix' => 'oa:hasTarget',
    'oa:textDirection' => 'oa:hasBody',
    'oa:via' => 'oa:Annotation',
    // Recommended ontologies.
    'as:first' => 'oa:hasTarget',
    'as:generator' => 'oa:Annotation',
    'as:items' => 'oa:hasTarget',
    'as:last' => 'oa:hasTarget',
    'as:next' => 'oa:hasTarget',
    'as:partOf' => 'oa:hasTarget',
    'as:prev' => 'oa:hasTarget',
    'as:startIndex' => 'oa:hasTarget',
    'as:totalItems' => 'oa:hasTarget',
    'dc:format' => ['oa:hasTarget', 'oa:hasBody'],
    'dc:language' => 'oa:hasBody',
    'dcterms:conformsTo' => 'oa:hasTarget',
    'dcterms:created' => 'oa:Annotation',
    'dcterms:creator' => 'oa:Annotation',
    'dcterms:format' => ['oa:hasTarget', 'oa:hasBody'],
    'dcterms:issued' => 'oa:Annotation',
    'dcterms:language' => 'oa:hasBody',
    'dcterms:modified' => 'oa:Annotation',
    'dcterms:rights' => ['oa:Annotation', 'oa:hasBody'],
    'foaf:homepage' => 'oa:Annotation',
    'foaf:mbox' => 'oa:Annotation',
    'foaf:mbox_sha1sum' => 'oa:Annotation',
    'foaf:name' => 'oa:Annotation',
    'foaf:nick' => 'oa:Annotation',
    'rdf:type' => ['oa:hasBody', 'oa:hasTarget', 'oa:Annotation'],
    'rdf:value' => ['oa:hasBody', 'oa:hasTarget'],
    'rdfs:label' => 'oa:hasTarget',
    'schema:accessibilityFeature' => 'oa:hasTarget',
    'schema:audience' => 'oa:Annotation',
];
