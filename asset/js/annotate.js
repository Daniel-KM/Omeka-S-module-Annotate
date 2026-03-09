$(document).ready(function() {

    /**
     * Display full annotation value in a dialog.
     */
    $('#content').on('click', 'button.popover', function() {
        var message = $(this).closest('.annotation-popover-parent')
            .find('.annotation-popover-current').text();
        if (typeof CommonDialog !== 'undefined') {
            CommonDialog.dialogAlert({
                message: message,
                nl2br: true,
            });
        } else {
            alert(message);
        }
    });

});
