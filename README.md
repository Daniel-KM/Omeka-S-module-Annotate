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


Installation
------------

The module uses an external library, [webui-popover], so use the release zip to
install it, or use and init the source.

See general end user documentation for [installing a module].

* From the zip

Download the last release [Annotate.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

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
  generally managed.
- According to the Annotation data model, only textual bodies can have a
  purpose. So when the body is not a text, for example a link, the purpose is
  cleared. Nevertheless, another body can contain a description and a purpose.
- The name of the four custom vocabs `Annotation oa:Motivation`, `Annotation Body dcterms:format`,
  `Annotation Target dcterms:format`, and `Annotation Target rdf:type` are used
  internally and must not be changed for now.
- The name of the resource template `Annotation` is used internally and should
  not be changed currently.
- For `rdf:type`, the four classes that should not be used in the Web Annotation
  model directly (`oa:ResourceSelection`, `oa:Selector`, `oa:State` and `oa:Style`)
  are not available by default (see the [Annotation vocabulary]). They can be
  added if really wanted, but it's better to extend the data model.


Development
-----------

- Internally, targets and bodies are managed like Omeka resources, but they
  aren’t rdf classes.
- In the json-ld of the resources, the key `oa:Annotation` is deprecated and
  has been removed since version 3.3.3.6. Use the key `o:annotation`.

### Api endpoint

You can create annotations in a standard way on the api. To simplify process, it
is possible to skip some keys in the payload, for example:

```sh
curl -X POST -H 'Accept: application/json' -i 'https://example.org/api/annotations?key_identity=xxx&key_credential=yyy&pretty_print=1' -F 'data={"oa:motivatedBy":[{"@value":"commenting"}],"oa:hasBody":[{"rdf:value":[{"@value":"My comment"}]}],"oa:hasTarget":[{"oa:hasSource":[{"value_resource_id":1}]}]}'
```

This shortcut is available only when there is one and only one motivation and
only for "commenting" for now.


TODO
----

- [ ] Improve the core or the custom vocab module to keep "literal" as value type
  (new column in value table or new one to one table?) (cf https://github.com/omeka/omeka-s/pull/1262).
- [ ] Does the annotation need to be in the same json of the item? An item doesn't
  know annotations about itself, they are independant, so to be removed.
- [ ] Check the validity of multiple contexts omeka + annotation inside jsonld of
  annotations (see https://www.w3.org/TR/json-ld/#advanced-context-usage).
- [ ] Targets and bodies should not have rest api access (they are created with the
  annotation). Upgrade them like value hydrator.
- [ ] Make compatible with module Group (user page).
- [ ] Remove dependency with CustomVocab.


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

* Copyright Daniel Berthereau, 2017-2021 (see [Daniel-KM] on GitLab)

* Library [webui-popover]: Sandy Walker

This module was built first for the French École des hautes études en sciences
sociales [EHESS]. It was upgraded and improved for [Enssib].


[Annotate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate
[Omeka S]: https://omeka.org/s
[Web Annotation Ontology]: https://www.w3.org/annotation/
[W3C]: https://www.w3.org
[installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[Annotate.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Annotate/-/releases
[Annotation data model]: https://www.w3.org/TR/annotation-model/
[Annotation vocabulary]: https://www.w3.org/TR/annotation-vocab/
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
