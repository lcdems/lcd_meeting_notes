jQuery(document).ready(function($) {
    // Move focus to the meeting date field and away from title
    $('#meeting_date').focus();

    function getMeetingType() {
        // Check for any checked checkbox in the meeting type checklist
        return $('#meeting_typechecklist input[type="checkbox"]:checked').length > 0;
    }

    function validateMeetingFields() {
        var date = $('#meeting_date').val();
        var time = $('#meeting_time').val();
        var hasType = getMeetingType();
        return date && time && hasType;
    }

    function formatDateTime(date, time) {
        const datetime = new Date(date + 'T' + time);
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true,
            timeZone: 'UTC'
        };
        return datetime.toLocaleString('en-US', options);
    }

    function updateTitle() {
        var checkedTypes = $('#meeting_typechecklist input[type="checkbox"]:checked');
        var typeLabels = [];
        checkedTypes.each(function() {
            typeLabels.push($(this).parent().text().trim());
        });
        var date = $('#meeting_date').val();
        var time = $('#meeting_time').val();
        
        // Update classic editor title
        var $title = $('#title');
        var $titlePrompt = $('#title-prompt-text');
        
        if (typeLabels.length > 0 && date && time) {
            var formattedDateTime = formatDateTime(date, time);
            var title = typeLabels.join(' & ') + ' Meeting - ' + formattedDateTime;
            
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

    // Handle changes to meeting type, date, and time
    $(document).on('change', '#meeting_typechecklist input[type="checkbox"], #meeting_date, #meeting_time', updateTitle);

    // Add validation for publish button
    $('#publish').on('click', function(e) {
        if (!validateMeetingFields()) {
            e.preventDefault();
            alert(meetingNotesL10n.validationMessage);
            return false;
        }
    });
}); 