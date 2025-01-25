jQuery(document).ready(function($) {
    // Move focus to the meeting date field and away from title
    $('#meeting_date').focus();
    
    function getMeetingType() {
        // Check for any checked checkbox in the meeting type checklist
        return $('#meeting_typechecklist input[type="checkbox"]:checked').length > 0;
    }

    function validateMeetingFields() {
        var date = $('#meeting_date').val();
        var hasType = getMeetingType();
        return date && hasType;
    }

    function updateTitle() {
        var checkedTypes = $('#meeting_typechecklist input[type="checkbox"]:checked');
        var typeLabels = [];
        checkedTypes.each(function() {
            typeLabels.push($(this).parent().text().trim());
        });
        var date = $('#meeting_date').val();
        
        // Update classic editor title
        var $title = $('#title');
        var $titlePrompt = $('#title-prompt-text');
        
        if (typeLabels.length > 0 && date) {
            // Split the date string and create date parts
            var dateParts = date.split('-');
            var dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
            
            var formattedDate = dateObj.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'UTC'
            });
            
            var title = typeLabels.join(' & ') + ' Meeting - ' + formattedDate;
            
            // Set title and hide prompt
            $title.val(title);
            $titlePrompt.hide();
            
            // Handle Gutenberg editor if present
            var $gutenbergTitle = $('.editor-post-title__input');
            if ($gutenbergTitle.length) {
                $gutenbergTitle
                    .val(title)
                    .removeClass('is-empty');
            }
        } else {
            // Reset title and show prompt if no types selected
            $title.val('');
            $titlePrompt.show();
            
            // Handle Gutenberg editor if present
            var $gutenbergTitle = $('.editor-post-title__input');
            if ($gutenbergTitle.length) {
                $gutenbergTitle
                    .val('')
                    .addClass('is-empty');
            }
        }
    }

    // Handle changes to meeting type and date
    $(document).on('change', '#meeting_typechecklist input[type="checkbox"], #meeting_date', updateTitle);

    // Add validation for publish button
    $('#publish').on('click', function(e) {
        if (!validateMeetingFields()) {
            e.preventDefault();
            alert(meetingNotesL10n.validationMessage);
            return false;
        }
    });

    // Handle PDF preview
    $('#preview-pdf').on('click', function() {
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
            }
        });
    });

    // Handle PDF download
    $('#download-pdf').on('click', function() {
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
                    // Create temporary link and trigger download
                    var a = document.createElement('a');
                    a.href = response.data.url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Handle email sending
    $('#send-email').on('click', function() {
        var emailTo = $('#email_to').val().trim();
        if (!emailTo) {
            alert(meetingNotesL10n.emailRequired || 'Please enter a recipient email address');
            return;
        }

        var $button = $(this);
        var $status = $('#email-status');
        
        $button.prop('disabled', true).text(meetingNotesL10n.sending || 'Sending...');
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
                    // Clear form
                    $('#email_message').val('');
                    $('#email_to').val('');
                } else {
                    $status.addClass('error').text(response.data.message).show();
                }
            },
            error: function() {
                $status.addClass('error').text(meetingNotesL10n.sendError || 'Failed to send email. Please try again.').show();
            },
            complete: function() {
                $button.prop('disabled', false).text(meetingNotesL10n.sendEmail || 'Send Email');
            }
        });
    });
}); 