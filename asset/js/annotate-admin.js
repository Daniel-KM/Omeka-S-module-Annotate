$(document).ready(function() {

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
    }

    // Set/unset the sub-form when the class oa:Annotation is selected.
    $(document).on('change', '#resourcetemplateform select[name="o:resource_class[o:id]"]', function(evt, params) {
        var termId = $('#resourcetemplateform select[name="o:resource_class[o:id]"]').val();
        var term = resourceClassTerm(termId);
        if (term === 'oa:Annotation') {
            $('#edit-sidebar .confirm-main').append(annotationPartForm());
        } else {
            $('#annotation-options').remove();
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
