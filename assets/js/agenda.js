jQuery(document).ready(function($) {
    // Handle PDF selection button click
    $('.select-pdf').on('click', function() {
        $('#agenda_pdf').click();
    });

    // Handle file selection
    $('#agenda_pdf').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file type
        if (file.type !== 'application/pdf') {
            alert('Please select a PDF file.');
            return;
        }

        // Create FormData for upload
        const formData = new FormData();
        formData.append('action', 'upload_agenda_pdf');
        formData.append('nonce', meetingNotesL10n.nonce);
        formData.append('post_id', $('#post_ID').val());
        formData.append('agenda_pdf', file);

        // Show progress bar
        $('.upload-new-agenda').hide();
        $('.upload-progress').show();
        $('.progress-bar-fill').css('width', '0%');
        $('.progress-text').text('Uploading...');

        // Upload file
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        $('.progress-bar-fill').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // Update hidden input with attachment ID
                    $('#agenda_pdf_id').val(response.data.attachment_id);

                    // Update UI to show current agenda
                    $('.current-agenda .filename').text(file.name);
                    $('.current-agenda a.button').attr('href', response.data.url);
                    $('.current-agenda').show();
                    $('.upload-progress').hide();
                } else {
                    alert(response.data.message || 'Upload failed. Please try again.');
                    $('.upload-new-agenda').show();
                    $('.upload-progress').hide();
                }
            },
            error: function() {
                alert('Upload failed. Please try again.');
                $('.upload-new-agenda').show();
                $('.upload-progress').hide();
            }
        });
    });

    // Handle remove button click
    $('.remove-agenda').on('click', function() {
        if (confirm('Are you sure you want to remove this agenda?')) {
            $('#agenda_pdf_id').val('');
            $('.current-agenda').hide();
            $('.upload-new-agenda').show();
        }
    });
}); 