$(document).ready(function() {

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

});
