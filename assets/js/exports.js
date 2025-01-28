jQuery(document).ready(function($) {
    // Meeting Notes Export Functions
    $('#preview-pdf').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_export_meeting_pdf',
                post_id: $('#post_ID').val(),
                nonce: $('#meeting_export_nonce').val(),
                action_type: 'preview'
            },
            success: function(response) {
                if (response.success) {
                    window.open(response.data.url, '_blank');
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(exportsL10n.exportError);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    $('#download-pdf').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_export_meeting_pdf',
                post_id: $('#post_ID').val(),
                nonce: $('#meeting_export_nonce').val(),
                action_type: 'download'
            },
            success: function(response) {
                if (response.success) {
                    var a = document.createElement('a');
                    a.href = response.data.url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(exportsL10n.exportError);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    $('#send-email').on('click', function() {
        var emailTo = $('#email_to').val().trim();
        if (!emailTo) {
            alert(exportsL10n.emailRequired);
            return;
        }

        var $button = $(this);
        var $status = $('#email-status');
        
        $button.prop('disabled', true).text(exportsL10n.sending);
        $status.removeClass('success error').hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_generate_meeting_email',
                post_id: $('#post_ID').val(),
                nonce: $('#meeting_export_nonce').val(),
                to: emailTo,
                subject: $('#email_subject').val(),
                include_pdf: $('#include_pdf').is(':checked'),
                include_notes: $('#include_notes').is(':checked'),
                message: $('#email_message').val()
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message).show();
                    $('#email_message').val('');
                    $('#email_to').val('');
                } else {
                    $status.addClass('error').text(response.data.message).show();
                }
            },
            error: function() {
                $status.addClass('error').text(exportsL10n.emailError).show();
            },
            complete: function() {
                $button.prop('disabled', false).text(exportsL10n.sendEmail);
            }
        });
    });
}); 