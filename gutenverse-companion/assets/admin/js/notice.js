(function($) {
    $('.gutenverse-upgrade-notice.page-content-upgrade .close-notification').on('click', function() {
        $.post( ajaxurl, {
            action: 'gutenverse_companion_notice_close'
        } );

        $('.gutenverse-upgrade-notice.page-content-upgrade').fadeOut();
    });
})(jQuery);