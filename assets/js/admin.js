jQuery(document).ready(function($) {
    // Move focus to the meeting date field and away from title
    $('#meeting_date').focus();
    
    let pendingNewPerson = null;

    // Initialize Select2 for attendees
    $('#attendees_select').select2({
        tags: false, // Disable free text entry
        tokenSeparators: [','],
        ajax: {
            url: meetingNotesL10n.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    action: 'lcd_search_people',
                    nonce: meetingNotesL10n.nonce
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: meetingNotesL10n.searchPlaceholder,
        minimumInputLength: 2,
        templateResult: formatPeopleResult,
        templateSelection: formatPeopleSelection
    }).on('select2:select', function(e) {
        if (e.params.data.type === 'free_text') {
            // Show modal for new person
            pendingNewPerson = e.params.data.text;
            showNewPersonModal(e.params.data.text);
            
            // Remove the temporary selection
            const selections = $(this).val().filter(v => v !== e.params.data.id);
            $(this).val(selections).trigger('change');
        }
    }).on('change', function() {
        // Update hidden input with comma-separated values
        var values = $(this).select2('data').map(function(item) {
            return item.text;
        });
        $('#attendees').val(values.join(', '));
    });

    function showNewPersonModal(fullName) {
        // Try to split the name into first and last
        const nameParts = fullName.trim().split(/\s+/);
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';

        $('#new_person_first_name').val(firstName);
        $('#new_person_last_name').val(lastName);
        $('#new-person-modal').show();
    }

    // Handle modal close
    $('#cancel-new-person').on('click', function() {
        $('#new-person-modal').hide();
        pendingNewPerson = null;
    });

    // Handle save new person
    $('#save-new-person').on('click', function() {
        const firstName = $('#new_person_first_name').val().trim();
        const lastName = $('#new_person_last_name').val().trim();

        if (!firstName) {
            alert(meetingNotesL10n.firstNameRequired);
            return;
        }
        if (!lastName) {
            alert(meetingNotesL10n.lastNameRequired);
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true);

        // Create new person via AJAX
        $.ajax({
            url: meetingNotesL10n.ajaxurl,
            type: 'POST',
            data: {
                action: 'lcd_create_person',
                nonce: meetingNotesL10n.nonce,
                first_name: firstName,
                last_name: lastName
            },
            success: function(response) {
                if (response.success) {
                    // Add the new person to the select
                    const newOption = new Option(response.data.text, response.data.id, true, true);
                    $('#attendees_select').append(newOption).trigger('change');
                    
                    // Close modal
                    $('#new-person-modal').hide();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Failed to create person. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
                pendingNewPerson = null;
            }
        });
    });

    function formatPeopleResult(person) {
        if (!person.id) return person.text;
        
        var $result = $('<span></span>');
        if (person.type === 'person') {
            $result.text(person.text).addClass('select2-results__option--person');
        } else {
            $result.text(person.text).addClass('select2-results__option--free-text');
            $result.append(' (Add as new person)');
        }
        return $result;
    }

    function formatPeopleSelection(person) {
        if (!person.id) return person.text;
        return person.text;
    }

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
}); 