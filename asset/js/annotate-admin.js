/*
 * Copyright Daniel Berthereau, 2017-2018
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

$(document).ready(function() {

    /**
     * Main simple search.
     */
    var searchAnnotations = `<input type="radio" name="resource-type" id="search-annotation" value="annotation" data-input-placeholder="${Omeka.jsTranslate('Search annotations')}" data-action="${searchAnnotationsUrl}">
    <label for="search-annotation">${Omeka.jsTranslate('Annotations')}</label>`;
    $('#search-form #advanced-options').append(searchAnnotations);

    /**
     * Advanced search
     *
     * Adapted from Omeka application/asset/js/advanced-search.js.
     */
    var values = $('#datetime-queries .value');
    var index = values.length;
    $('#datetime-queries').on('o:value-created', '.value', function(e) {
        var value = $(this);
        value.children(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + index + ']');
        });
        index++;
    });

    /**
     * Search sidebar.
     */
    $('#content').on('click', 'a.search', function(e) {
        e.preventDefault();
        var sidebar = $('#sidebar-search');
        Omeka.openSidebar(sidebar);

        // Auto-close if other sidebar opened
        $('body').one('o:sidebar-opened', '.sidebar', function () {
            if (!sidebar.is(this)) {
                Omeka.closeSidebar(sidebar);
            }
        });
    });

    /**
     * Better display of big annotation bodies.
     */
    if ( $.isFunction($.fn.webuiPopover) ) {
        $('a.popover').webuiPopover('destroy').webuiPopover({
            placement: 'auto-bottom',
            content: function (element) {
                var target = $('[data-target=' + element.id + ']');
                var content = target.closest('.webui-popover-parent').find('.webui-popover-current');
                $(content).removeClass('truncate').show();
                return content;
            },
            title: '',
            arrow: false,
            backdrop: true,
            onShow: function(element) { element.css({left: 0}); }
        });

        $('a.popover').webuiPopover();
    }

    /**
     * Append an annotation sub-form to the resource template form.
     *
     * @todo Allows Omeka to append a form element via triggers in Zend form or js.
     * @see Omeka resource-template-form.js
     */

    var propertyList = $('#resourcetemplateform #properties');

    /**
     * Because chosen is used, only the value is available, not the term.
     *
     * @param int id
     * @return string|null
     */
    var resourceClassTerm = function(termId) {
        return termId
            ? $('#resourcetemplateform select[name="o:resource_class[o:id]"] option[value=' + termId + ']').data('term')
            : null;
    }

    var annotationInfo = function() {
        return `
    <br />
    <div id="annotation-info">
        <h3>${Omeka.jsTranslate('Web Open Annotation')}</h3>
        <p>
            ${Omeka.jsTranslate('With the class <code>oa:Annotation</code>, it’s important to choose the part of the annotation to which the property is attached:')}
            ${Omeka.jsTranslate('It can be the annotation itself (default), but the body or the target too.')}
        </p>
        <p>${Omeka.jsTranslate('For example, to add an indication on a uncertainty of  a highlighted segment, the property should be attached to the target, but the description of a link should be attached to the body.')}</p>
        <p>${Omeka.jsTranslate('Standard non-ambivalent properties are automatically managed.')}</p>
    </div>`;
    }

    // Template of  the annotation sub-form (application/view/omeka/admin/resource-template/form.phtml).
    var annotationPartInput = function(propertyId, annotationPart) {
        annotationPart = annotationPart || 'oa:Annotation';
        return `<input class="annotation-part" type="hidden" name="o:resource_template_property[${propertyId}][data][annotation_part]" value="${annotationPart}">`;
    }
    var annotationPartForm = function(annotationPart) {
        var checked_2 = (annotationPart === 'oa:hasBody') ? 'checked="checked" ' : '';
        var checked_3 = (annotationPart === 'oa:hasTarget') ? 'checked="checked" ' : '';
        var checked_1 = (checked_2 === '' && checked_3 === '') ? 'checked="checked" ' : '';
        var html = `
    <div id="annotation-options" class="field">
        <h3>${Omeka.jsTranslate('Annotation')}</h3>
        <div id="annotation-part" class="option">
            <label for="annotation-part">${Omeka.jsTranslate('Annotation part')}</label>
            <span>${Omeka.jsTranslate('To comply with Annotation data model, select the part of the annotation this property will belong to.')}</span>
            <span><i>${Omeka.jsTranslate('This option cannot be imported/exported currently.')}</i></span><br />
            <input type="radio" name="annotation_part" ${checked_1}value="oa:Annotation" /> ${Omeka.jsTranslate('Annotation')}<br />
            <input type="radio" name="annotation_part" ${checked_2}value="oa:hasBody" /> ${Omeka.jsTranslate('Annotation body')}<br />
            <input type="radio" name="annotation_part" ${checked_3}value="oa:hasTarget" /> ${Omeka.jsTranslate('Annotation target')}
        </div>
    </div>`;
        return html;
    }

    // Initialization during load.
    if (resourceClassTerm($('#resourcetemplateform select[name="o:resource_class[o:id]"]').val()) === 'oa:Annotation') {
        // Set hidden params inside the form for each properties of  the resource template.
        var addNewPropertyRowUrl = propertyList.data('addNewPropertyRowUrl')
        var baseUrl = addNewPropertyRowUrl.split('?')[0];
        var resourceTemplateId = baseUrl.split('/')[baseUrl.split('/').length - 2];
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        var resourceTemplateDataUrl = baseUrl + '/annotation/resource-template-data';
        $.get(resourceTemplateDataUrl, {resource_template_id: resourceTemplateId})
            .done(function(data) {
                propertyList.find('li.property').each(function() {
                    var propertyId = $(this).data('property-id');
                    var annotationPart = data[propertyId] || '';
                    $(this).find('.data-type').after(annotationPartInput(propertyId, annotationPart));
                });
            });
        // Initialization of the sidebar.
        $('#edit-sidebar .confirm-main').append(annotationPartForm());
        $('#content').append(annotationInfo());
    }

    // Set/unset the sub-form when the class oa:Annotation is selected.
    $(document).on('change', '#resourcetemplateform select[name="o:resource_class[o:id]"]', function(evt, params) {
        var termId = $('#resourcetemplateform select[name="o:resource_class[o:id]"]').val();
        var term = resourceClassTerm(termId);
        if (term === 'oa:Annotation') {
            $('#edit-sidebar .confirm-main').append(annotationPartForm());
            $('#content').append(annotationInfo());
        } else {
            $('#annotation-options').remove();
            $('#annotation-info').remove();
        }
    });

    $('#property-selector .selector-child').click(function(e) {
        e.preventDefault();
        var propertyId = $(this).closest('li').data('property-id');
        if ($('#properties li[data-property-id="' + propertyId + '"]').length) {
            // Resource templates cannot be assigned duplicate properties.
            return;
        }
        propertyList.find('li:last-child').append(annotationPartInput(propertyId));
    });

    propertyList.on('click', '.property-edit', function(e) {
        e.preventDefault();
        var prop = $(this).closest('.property');
        var annotationPart = prop.find('.annotation-part');
        var annotationPartVal = annotationPart.val() || 'oa:Annotation';
        $('#annotation-part input[name=annotation_part][value="' + annotationPartVal + '"]').prop('checked', true)
            .trigger("click");

        // Save the value for the current property (the other values are managed by resource-template-form.js).
        $('#set-changes').on('click.setchanges', function(e) {
            annotationPart.val($('#annotation-part input[name="annotation_part"]:checked').val());
        });
    });

});
