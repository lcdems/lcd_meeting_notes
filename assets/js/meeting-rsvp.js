jQuery(document).ready(function($) {
    // Use event delegation for RSVP button clicks
    $(document).on('click', '.rsvp-button', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var meetingId = $button.data('meeting-id');
        var action = $button.hasClass('add-rsvp') ? 'add' : 'remove';
        var $rsvpSection = $button.closest('.meeting-rsvp-section');
        var $rsvpCount = $('.rsvp-count[data-meeting-id="' + meetingId + '"]');

        // Disable button during request
        $button.prop('disabled', true);

        $.ajax({
            url: lcdRsvp.ajaxurl,
            type: 'POST',
            data: {
                action: 'rsvp_meeting',
                meeting_id: meetingId,
                rsvp_action: action,
                nonce: lcdRsvp.nonce
            },
            success: function(response) {
                if (response.success) {
                    var count = response.data.count;
                    
                    // Update the text based on RSVP status
                    $rsvpCount.each(function() {
                        var $container = $(this);
                        var text = '';
                        
                        if (action === 'add') {
                            if (count === 1) {
                                text = 'You are the first to RSVP';
                            } else {
                                text = 'You, plus ' + (count - 1) + ' others have RSVP\'d';
                            }
                        } else {
                            text = count === 1 ? '1 person has RSVP\'d' : count + ' people have RSVP\'d';
                        }
                        
                        $container.html('<i class="dashicons dashicons-groups"></i> ' + text);
                    });

                    // Toggle button
                    if (action === 'add') {
                        var newButton = $('<button type="button" class="rsvp-button remove-rsvp" data-meeting-id="' + meetingId + '">' +
                            '<i class="dashicons dashicons-no-alt"></i> ' + 'Cancel RSVP</button>');
                        $button.replaceWith(newButton);
                        
                        // Show success message
                        showMessage($rsvpSection, lcdRsvp.messages.success, 'success');
                    } else {
                        var newButton = $('<button type="button" class="rsvp-button add-rsvp" data-meeting-id="' + meetingId + '">' +
                            '<i class="dashicons dashicons-yes"></i> ' + 'RSVP Now</button>');
                        $button.replaceWith(newButton);
                        
                        // Show removed message
                        showMessage($rsvpSection, lcdRsvp.messages.removed, 'info');
                    }
                } else {
                    // Show error message
                    showMessage($rsvpSection, response.data.message || lcdRsvp.messages.error, 'error');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                showMessage($rsvpSection, lcdRsvp.messages.error, 'error');
                $button.prop('disabled', false);
            }
        });
    });

    // Helper function to show messages
    function showMessage($container, message, type) {
        var $message = $('<div class="rsvp-message ' + type + '">' + message + '</div>');
        
        // Remove any existing messages
        $container.find('.rsvp-message').remove();
        
        // Add new message
        $container.append($message);
        
        // Remove message after 3 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
}); 