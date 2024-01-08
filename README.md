Annotate (module for Omeka S)
=============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Annotate] is a module for [Omeka S] that implements the backend to manage the
[Web Annotation Ontology] of the World Wide Web consortium [W3C]. It has no end
user friendly interface, that are provided by other modules or by a theme in
order to annotate, tag, comment, rate, highlight, draw, etc. any target resource
easily and in a normalized way.

The module adds a role "Annotator" too, who has less rights than a Researcher
and who can only annotate.

### Difference between web annotation and value annotation

This feature is not the same than the value annotations introduced in Omeka S
version 3.2.

A value annotation is a way for the librarian to annotate a value of the record,
for example to indicate the role of the Dublin Core Creator, who can be an
author, photograph, or a painter, or to indicate the quality of a value, for
example a date may be certain or uncertain.

A web annotation is a way for a user to annotate the resource itself, for
example to highlight some parts of a pdf media, or to comment some zones of an
image, or to rate the item as a whole. So the user may be the librarian or
curator or not. The value annotations belong to the records but the web
annotations are about the records.

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
Furthermore, unlike the value or the value annotation of a record, an web
annotation is static: it should not be updated or modified. For example, if a
user comments an extract of a text, he should not be able to modify this first
comment, but he should creates a second annotation motivated by an "edition" of
it in order to track history. Nevertheless, the module let people to modify
annotation records, because it's more common, but it can be controlled by a
third party module.

The main point is the fact that they are shareable through an open standard. On
the web, there are multiple sites to rate a restaurant, to add a review on a
book, etc. but they are generally not standard so each service keep its own
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
- The name of the four custom vocabs `Annotation oa:Motivation`, `Annotation Body dcterms:format`,
  `Annotation Target dcterms:format`, and `Annotation Target rdf:type` are used
  internally and must not be changed for now.
- The name of the resource template `Annotation` is used internally and should
  not be changed currently.
- For `rdf:type`, the four classes that should not be used in the Web Annotation
  model directly (`oa:ResourceSelection`, `oa:Selector`, `oa:State` and `oa:Style`)
  are not available by default (see the [Annotation vocabulary]). They can be
  added if really wanted, but it's better to extend the data model.

An important point to understand when filling the form is the distinction
between ["motivation" and "purpose"], because the list of the allowed values is
the same. The motivation is required and is a way to declare the meaning of the
annotation. It explains why the annotation is created. The purpose is optional
and is a way to declare the meaning of the body. It explains why the body is
what it is. For example, if a user adds a bookmark "readme" to a page of a text,
the motivation is "bookmarking" but the purpose will be "tagging". If the
bookmark is a small note, the purpose will be "describing". For a comment, the
motivation is "commenting", but the purpose may be "commenting", "replying",
"editing" or even "questionning", "describing", etc. For a rating, the
motivation is "assessment" and the purpose may be "classifying" when the rate is
a symbol or a descriptor like "good", "very good", or nothing when it is a
simple integer value (rendered, for example, as zero or one to five stars).

In practice, for common cases or for simplicity, the purpose is the same than
the motivation and can be skipped.


Development
-----------

- Internally, targets and bodies are managed like Omeka resources, but they
  aren’t rdf classes.
- In the json-ld of the resources, the list of annotation is available under
  the key `o:annotation`. The old key `oa:Annotation` is deprecated and
  has been removed since version 3.3.3.6.

### Api endpoint

#### Create

You can create annotations in a standard way on the api. To simplify process, it
is possible to skip some keys in the payload for some common motivations.

For a rating, that is an [assessment] with a value, generally numerical (that
may be a float, a positive integer (from 1), a non negative integer (from 0),
an enumeration, a range like here, etc., according to your needs), it can be
for item #51:

```sh
curl -X POST -H 'Accept: application/json' -i 'https://example.org/api/annotations?key_identity=xxx&key_credential=yyy&pretty_print=1' -F 'data={
    "oa:motivatedBy": [
        {"@value": "assessing"}
    ],
    "oa:hasBody": [
        {
            "rdf:value": [
                {"@value": 4}
            ],
            "dcterms:format": [
                {"@value": "type: xs:positiveInteger, start: 1, end: 5"}
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


For a [comment] replying to the comment #52:

```sh
curl -X POST -H 'Accept: application/json' -i 'https://example.org/api/annotations?key_identity=xxx&key_credential=yyy&pretty_print=1' -F 'data={
    "oa:motivatedBy": [
        {"@value": "commenting"}
    ],
    "oa:hasBody": [
        {
            "oa:hasPurpose": [
                {"@value": "replying"}
            ],
            "rdf:value": [
                {"@value": "My comment replying to "}
            ],
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

These shortcuts are available only when there is one and only one motivation and
at least one target. Values for other properties can be appended and they will
be completed with the property id and a generic type.

#### Search

Search can be done with standard api request with properties on `/api/annotations`.
Some query arguments are specific:

- `resource_id`: get all the annotations for a specific resource or multiple resources.
- `owner_id`: get all annotations for a specific user or multiple users.
- `motivation`: get all annotations for a specific motivation or multiple motivations.


TODO
----

- [ ] Move all code specific of Cartography into module Cartography.
- [ ] Remove dependency with CustomVocab?
- [ ] Keep "literal" as value type instead of a custom vocab?
- [x] Does the annotation need to be in the same json of the item? An item doesn't know annotations about itself, they are independant, so to be removed: just keep a link.
- [ ] Check the validity of multiple contexts omeka + annotation inside json-ld of annotations (see https://www.w3.org/TR/json-ld/#advanced-context-usage).
- [x] Targets and bodies should not have rest api access (they are created with the annotation). Upgrade them like value hydrator.
- [ ] Make compatible with module Group (user page).
- [x] Clean labels of oa vocabulary.
- [ ] Normalize sub-selector as value annotation of the target?


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

* The library [`webui-popover`] is published under the license [MIT].


Copyright
---------

* Copyright Daniel Berthereau, 2017-2023 (see [Daniel-KM] on GitLab)

* Library [webui-popover]: Sandy Walker

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. It was upgraded and improved for [Enssib].


[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[Omeka S]: https://omeka.org/s
[Web Annotation Ontology]: https://www.w3.org/annotation/
[W3C]: https://www.w3.org
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
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
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[webui-popover]: https://github.com/sandywalker/webui-popover
[EHESS]: https://www.ehess.fr
[Enssib]: https://www.enssib.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
