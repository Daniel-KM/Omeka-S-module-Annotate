Annotate (module for Omeka S)
=============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Annotate] is a module for [Omeka S] that implements the backend to manage the
[Web Annotation Ontology] of the World Wide Web consortium [W3C].

The three parts of the web annotation standard are implemented:
- [Web Annotation Vocabulary], that defines the ontology to use;
- [Web Annotation Data Model], that defines the representation of annotations,
- [Web Annotation Protocol], that defines the way to create and share annotations.

This module has no end user friendly interface and is designed to be a backend
for other modules or themes, that provides front-end in order to annotate, tag,
comment, rate, highlight, draw, etc. any target resource easily and in a
normalized way. The first associated module is [Cartography], that allows to
create markers and zones on images and to locate items on a map.

The module adds a role "Annotator" too, who has less rights than a Researcher
and who can only annotate.

### Difference between web annotation and value annotation

This feature is not the same than the value annotations introduced in Omeka S
version 3.2.

A value annotation is a way for the librarian to annotate a value of the record,
for example to indicate the role of the Dublin Core Creator, who can be an
author, photograph, or a painter, or to indicate the quality of a value, for
example a date may be certain or uncertain, or to indicate the source of a piece
of information.

A web annotation is a way for a user to annotate the resource itself, for
example to highlight some parts of a pdf media, or to comment some zones of an
image, or to rate the item as a whole. So the user may be the librarian or
curator, but he may be any user or visitor. The value annotations belong to the
record of a resource but the web annotations are full resources about other
resources.

Of course, in some cases, the values are the same, for example the record may
contain a Dublin Core Abstract that may have the same content than an assessment
done by a specific user. And value annotations can be added to the abstract to
indicate the user who wrote it, the date, etc., like for the web annotation. The
fact that Omeka is semantic allows to record anything about anything. But
precisely, in that case, the record strays from the logic of a record for a
described entity.

Besides, web annotations have some specific points not available as simple value
annotations. They are designed to be recursive: it is possible to annotate an
annotation, for example to reply to a comment. They are designed to be precise,
and it is possible to annotate a portion of a text, unlike a record that is
about a document as a whole, or to annotate a chapter of a book or a passage of
a video or a zone of an image. For this point, the specification manages
selectors and it makes a clear distinction between the target (the item or the
media, or part of them) and the body (the annotation in the general sense).
Furthermore, unlike the value or the value annotation of a record, a web
annotation is static: it should not be updated or modified. For example, if a
visitor comments an extract of a text, he should not be able to modify this
first comment, but he should create a second annotation motivated by "edition"
of it in order to track history. Nevertheless, the module let people to modify
annotation records, because it's more common, but it can be controlled by a
third party module.

The main point is the fact that they are shareable through an open standard. On
the web, there are multiple sites to rate a restaurant, to add a review on a
book, etc. but they are generally not standard so each service keeps its own
data. The web annotations are a way to genericize all annotations on anything in
a common way. And there are annotations servers than can be used to comment
resource managed by another server. For example, a common annotation server
used in universities is [Hypothes.is], that can be used to annotate Omeka
resources, like in some projects.


Installation
------------

See general end user documentation for [installing a module].

The module [Common] must be installed first.

The module uses external libraries, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [Annotate.zip] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Annotate`, go to the root of the module, and run:

```sh
composer install --no-dev
```


Quick start
-----------

### Annotating

Annotations can be created via the tab "Annotations" of each resource (item
sets, items and media). They can be browsed and managed via the main admin menu
(`admin/annotation`).

The process is simplified in order to manage the properties of the annotation,
its bodies and its targets in one form. Some cases with multiple bodies and
multiple targets for the same annotation are not managed, so it is recommended
to keep one body and one target by annotation currently (of course, there can be
multiple annotations by resource).

The module follows the rules of the [Annotation data model] and the [Annotation vocabulary].
Even if it is not forbidden, it is not recommended to add properties that are
not standard to annotations.

- The Annotation data model is not fully implemented, only the most frequent
  properties are managed. Only one motivation, one body and one target are
  generally managed (but it is possible to have multiple annotations of course).
- According to the Annotation data model, only textual bodies can have an
  optional purpose. So when the body is not a text, for example a link, the
  purpose is cleared. Nevertheless, another body can contain a description and a
  purpose.
- The name of the two custom vocabs `Annotation Motivation` and
  `Annotation Target dcterms:format` are used internally and must not be
  changed for now. Since v3.4.14, `oa:motivatedBy` and `oa:hasPurpose` share
  the same vocab (W3C uses a single `oa:Motivation` controlled set for both).
- The name of the resource template `Annotation` is used internally and should
  not be changed currently.

### Motivation and purpose

#### How to distinguish them?

An important point to understand when filling the form is the distinction
between ["motivation" and "purpose"], because the list of the allowed values is
the same: `bookmarking`, `commenting`, `tagging`, `assessing`, `describing`,
`classifying`, `editing`, `highlighting`, `identifying`, `linking`, `moderating`,
`questioning`, `replying`.

- The motivation is attached to the annotation itself and declares why the
  annotation is created, for example that this annotation is a comment.
- The purpose is attached to a body and declares what role this particular body
  plays, for example "this body is a tag" or "this body replies".

The two answer different questions and may differ. Examples:

| Action                                  | Motivation    | Purpose (on body)               |
|-----------------------------------------|---------------|---------------------------------|
| Bookmark a page with a label `readme`   | `bookmarking` | `tagging`                       |
| Bookmark a page with a short note       | `bookmarking` | `describing`                    |
| Comment a passage                       | `commenting`  | `commenting` (or omitted)       |
| Reply to an existing comment            | `commenting`  | `replying`                      |
| Suggest an edit on a value              | `editing`     | `editing` (or omitted)          |
| Assess with the symbolic value `good`   | `assessing`   | `classifying`                   |
| Assess with a numeric rating `4/5`      | `assessing`   | omitted (numeric needs no role) |

#### Are they required?

- The motivation is optional in the W3C model and optional in the api too. The
  module specifies `undefined` when no motivation is provided, so the annotation
  always has one stored value for now. The form requires it to force the caller
  to think about the intent.
- The purpose is always optional, both in the W3C model and in the module. Only
  meaningful on a textual body, because the W3C model restricts `oa:hasPurpose`
  to `TextualBody`); the form clears it on non-text bodies.
- Target: required (at least one).
- Body: optional.

#### Should they be the same?

In simple cases (a plain comment, a plain tag) the purpose duplicates the
motivation and can be skipped. Repeat it explicitly only when the body plays
a role distinct from the overall intent of the annotation, as in the
`bookmarking` / `tagging` example above.

#### Normalization

Currently, the module does not check if the annotation follows recommendation.


Development
-----------

Annotations are Omeka resources, so they can be managed, like any other
resources, at their own endpoint `/api/annotations`.

But annotations are standard w3c web annotations, so they can be managed at the
standard endpoint of the web annotation protocol, `/annotations/`.

### Json-ld representation

Until version 3.4.11, the list of annotation was available under the key `o:annotation`.
The old key `oa:Annotation` was deprecated and was removed since version 3.3.3.6.
The version 3.4.12 has normalized the the json-ld representation and the module
uses now `@reverse`, that is the most standard way to link external resources:
because web annotations are resources linked to another resources, they should
not be in the same representation. A setting allows to keep temporary the key
`o:annotation` to support old third party tools.

### Api endpoint

Annotations are exposed at the standard Omeka api endpoint `/api/annotations`.
The payload is the regular Omeka json-ld with three specific top-level keys:
`oa:motivatedBy`, `oa:hasBody` (optional, an array of body sub-resources) and
`oa:hasTarget` (required, an array of target sub-resources).

To simplify integration for sub-modules and third-party clients, the adapter
auto-completes missing `property_id` and `type` for any property inside a body
or a target. The caller only needs to provide:

- the term (e.g. `rdf:value`, `dcterms:format`, `oa:hasSource`, `oa:hasSelector`,
  `oa:hasPurpose`),
- the value (`@value`, `@id` or `value_resource_id`),
- optionally the `type`, that is only required to override the auto-detected one:
  `literal` for `@value`, `uri` for `@id`, `resource:item`, `resource:itemset`,
  or `resource:media` for a `value_resource_id`.

The motivation and the purpose are both normalised to the custom vocab
`Annotation Motivation`, and the rating value to
`numeric:integer` when `oa:motivatedBy` is `assessing` and `dcterms:format`
contains `integer`.

#### Create

Tagging item #51 with the tag `landscape`:

```sh
curl -X POST -i \
    -H 'Content-Type: application/json' \
    'https://example.org/api/annotations?key_identity=xxx&key_credential=yyy' \
    -d '{
        "oa:motivatedBy": [
            {"@value": "tagging"}
        ],
        "oa:hasBody": [
            {
                "rdf:value": [
                    {"@value": "landscape"}
                ]
            }
        ],
        "oa:hasTarget": [
            {
                "oa:hasSource": [
                    {"value_resource_id": 51}
                ]
            }
        ]
    }'
```

[Comment] replying to the annotation #52, targeting item #51:

```sh
curl -X POST -i \
    -H 'Content-Type: application/json' \
    'https://example.org/api/annotations?key_identity=xxx&key_credential=yyy' \
    -d '{
        "oa:motivatedBy": [
            {"@value": "replying"}
        ],
        "oa:hasBody": [
            {
                "rdf:value": [
                    {"@value": "My reply to annotation #52."}
                ],
                "dcterms:format": [
                    {"@value": "text/plain"}
                ]
            }
        ],
        "oa:hasTarget": [
            {
                "oa:hasSource": [
                    {"value_resource_id": 52}
                ]
            }
        ]
    }'
```

For an annotation on a part of a media (e.g. a zone of an image), the target
combines `oa:hasSource` (the item) and `oa:hasSelector` (the media or a selector
resource):

```json
"oa:hasTarget": [{
    "oa:hasSource": [{"value_resource_id": 51}],
    "oa:hasSelector": [{"value_resource_id": 1234}],
    "dcterms:format": [{"@value": "application/wkt"}],
    "rdf:value": [{"@value": "POLYGON((0 0,10 0,10 10,0 10,0 0))"}]
}]
```

#### Search

Standard Omeka api search applies on `/api/annotations` (filter by `property`,
sort, pagination, etc.). A few query arguments are specific to annotations:

- `resource_id`: annotations targeting a specific resource (item, item set or
  media). Accepts a single id or an array.
- `owner_id`: annotations created by a specific user. Accepts a single id or
  an array.
- `motivation`: annotations with a specific motivation (term value, e.g.
  `commenting`, `assessing`, `tagging`). Accepts a single value or an array.

```sh
curl 'https://example.org/api/annotations?resource_id=51&motivation[]=commenting&motivation[]=replying'
```

#### Read, update, delete

Standard Omeka rest semantics:

- `GET /api/annotations/:id` — fetch an annotation as Omeka json-ld.
- `PUT /api/annotations/:id` — full replace (same payload structure as `POST`).
- `PATCH /api/annotations/:id` — partial update.
- `DELETE /api/annotations/:id` — delete the annotation. Bodies and targets are
  cascade-deleted.

### W3C Web Annotation Protocol Endpoint

In addition to the standard Omeka api, the module exposes the W3C [Web Annotation Protocol]
at `/annotations/`. This endpoint serializes annotations strictly according to
the W3C Annotation Data Model (json-ld with the `http://www.w3.org/ns/anno.jsonld`
context) and is meant for external clients (IIIF viewers, clients like
Hypothes.is, interoperable annotation servers).

Routes:

- `/annotations/`: LDP `BasicContainer` of all annotations, paginated as `AnnotationPage`
  (query `page=N`, page size from the main Omeka setting `pagination_per_page`,
  default 25).
- `/annotations/:id` — single annotation as a W3C `Annotation`.

Methods supported on both routes:

| Method  | Purpose                                                     |
|---------|-------------------------------------------------------------|
| GET     | Fetch the container, a page or a single annotation          |
| HEAD    | Same as GET but without body (for ETag/Allow discovery)     |
| POST    | Create a new annotation (on the container only)             |
| PUT     | Replace an existing annotation                              |
| DELETE  | Delete an annotation                                        |
| OPTIONS | Discover allowed methods, `Accept-Post`, `Vary`, etc.       |

Headers returned:

- `Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"`
- `Link`: rel `type` pointing to LDP `BasicContainer` and W3C `Annotation`
- `ETag`, `Allow`, `Vary`, `Accept-Post`
- `Location` on `POST` (URI of the created annotation)

Authentication uses the standard Omeka api keys via query parameters `key_identity`
and `key_credential`. Anonymous read is allowed when the underlying annotations
are public.

Example — list annotations:

```sh
curl -H 'Accept: application/ld+json' \
    'https://example.org/annotations/?page=0'
```

Example — create an annotation:

```sh
curl -X POST -H 'Content-Type: application/ld+json' \
    'https://example.org/annotations/?key_identity=xxx&key_credential=yyy' \
    -d '{
        "@context": "http://www.w3.org/ns/anno.jsonld",
        "type": "Annotation",
        "motivation": "commenting",
        "body": {
            "type": "TextualBody",
            "value": "A comment",
            "format": "text/plain"
        },
        "target": "https://example.org/api/items/51"
    }'
```

#### Privacy of the creator

The `creator` exposed in the W3C representation is configurable via the main
setting `annotate_w3c_creator_fields` (multi-checkbox). Allowed fields:

- `name` (default): user display name
- `email`: raw email — disabled by default for GDPR
- `email_sha1`: sha1 hash of the email, suitable as a stable opaque identifier

When no field is enabled or the user is unknown, the `creator` is omitted.

#### Current limitations

- No check is done on data provided. If the content of an annotation is
  incorrect, it will remain as it is.
- Multi-body / multi-target annotations: stored but the simplified Omeka form
  only manages one body and one target.
- For module Cartography, `oa:styleClass` and `oa:styledBy` are filtered from
  the W3C output (they are used internally by Cartography for Leaflet styling,
  not as CSS).
- `rdf:type` is filtered from the W3C output (legacy field, removed in 3.4.13).
- Geographic selectors (used by Cartography) are not part of the W3C ontology
  and are currently exposed as a `dcterms:format` hint on the selector
  (`application/wkt`, `application/geo+json`, etc.). A future version will
  serialize image rectangles as `oa:FragmentSelector` (`#xywh=`) and geo shapes
  as GeoJSON bodies (IIIF recipe 0139).


TODO
----

- [ ] Move all code specific of Cartography into module Cartography.
- [ ] Remove dependency with CustomVocab?
- [ ] Keep "literal" as value type instead of a custom vocab?
- [x] Does the annotation need to be in the same json of the item? An item doesn't know annotations about itself, they are independant, so to be removed: just keep a link.
- [x] Check the validity of multiple contexts omeka + annotation inside json-ld of annotations (see https://www.w3.org/TR/json-ld/#advanced-context-usage).
- [x] Create standard endpoint /annotations/ (W3C Annotation Protocol).
- [ ] Create header request check for /api/annotations/.
- [x] Targets and bodies should not have rest api access (they are created with the annotation). Upgrade them like value hydrator.
- [ ] Make compatible with module Group (user page).
- [x] Clean labels of oa vocabulary.
- [ ] Normalize sub-selector as value annotation of the target?
- [ ] Public annotation as a list of resource blocks.
- [-] Replace role Annotator by Guest.
- [ ] Add rights to Guest.
- [ ] Normalize cartography: see part of IIIF.
- [ ] Normalize other motivations.
- [ ] Check if the annotation follows recommendation (motivation, format, etc.).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2017-2026 (see [Daniel-KM] on GitLab)

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. It was upgraded and improved for [Enssib].


[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[Omeka S]: https://omeka.org/s
[Web Annotation Ontology]: https://www.w3.org/annotation/
[W3C]: https://www.w3.org
[Web Annotation Vocabulary]: https://www.w3.org/TR/annotation-vocab/
[Web Annotation Data Model]: https://www.w3.org/TR/annotation-model/
[Web Annotation Protocol]: https://www.w3.org/TR/annotation-protocol/
[Cartography]: https://gitlab.com/Daniel-KM/Omeka-S-module-Cartography
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Annotate.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate/-/releases
[Annotation data model]: https://www.w3.org/TR/annotation-model/
[Annotation vocabulary]: https://www.w3.org/TR/annotation-vocab/
[Hypothes.is]: https://web.hypothes.is/
["motivation" and "purpose"]: https://www.w3.org/TR/annotation-model/#motivation-and-purpose
[comment]: https://www.w3.org/TR/annotation-vocab/#commenting
[assessment]: https://www.w3.org/TR/annotation-vocab/#assessing
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: https://opensource.org
[EHESS]: https://www.ehess.fr
[Enssib]: https://www.enssib.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
