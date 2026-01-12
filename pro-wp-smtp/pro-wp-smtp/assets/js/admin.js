(function($) {
    'use strict';
    
    $(document).ready(function() {
        $('#prowpsmtp_mailer').on('change', function() {
            var mailer = $(this).val();
            $('.prowpsmtp-mailer-settings').hide();
            $('#prowpsmtp-' + mailer + '-settings').show();
        });
        
        $('#prowpsmtp_smtp_auth').on('change', function() {
            if ($(this).is(':checked')) {
                $('.prowpsmtp-smtp-auth-row').show();
            } else {
                $('.prowpsmtp-smtp-auth-row').hide();
            }
        }).trigger('change');
    });
})(jQuery);
