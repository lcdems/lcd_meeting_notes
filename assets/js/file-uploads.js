jQuery(document).ready(function($) {
    // Generic file upload handler class
    class FileUploadHandler {
        constructor(containerSelector, options = {}) {
            console.log('Initializing FileUploadHandler for', containerSelector);
            this.container = $(containerSelector);
            this.options = {
                fileType: 'application/pdf',
                fileTypeError: 'Please select a PDF file.',
                actionName: 'upload_file',
                ...options
            };
            this.init();
        }

        init() {
            // Create current file section if it doesn't exist
            if (this.container.find('.current-file').length === 0) {
                this.container.find('.upload-new-file').after(`
                    <div class="current-file" style="display:none;">
                        <p>
                            <strong>${this.options.actionName === 'upload_agenda_pdf' ? 'Current Agenda:' : 'Current Notes:'}</strong>
                            <span class="filename"></span>
                        </p>
                        <div class="file-actions">
                            <a href="#" class="button" target="_blank">View PDF</a>
                            <button type="button" class="button remove-file">Remove</button>
                        </div>
                    </div>
                `);
            }
            
            // Bind events once after everything is set up
            this.bindEvents();
        }

        bindEvents() {
            // Unbind existing events first to prevent duplicates
            this.container.off('click', '.select-file');
            this.container.find('input[type="file"]').off('change');
            this.container.off('click', '.remove-file');

            // Handle select button click using delegation
            this.container.on('click', '.select-file', (e) => {
                console.log('Select file button clicked');
                this.container.find('input[type="file"]').click();
            });

            // Handle file selection
            this.container.find('input[type="file"]').on('change', (e) => {
                console.log('File selected:', e.target.files[0]?.name);
                this.handleFileSelection(e);
            });

            // Handle remove button click using delegation
            this.container.on('click', '.remove-file', (e) => {
                console.log('Remove file button clicked');
                if (confirm('Are you sure you want to remove this file?')) {
                    const hiddenInput = this.container.find('input[type="hidden"]');
                    hiddenInput.val('');
                    this.container.find('.current-file').hide();
                    this.container.find('.upload-new-file').show();
                }
            });
        }

        handleFileSelection(e) {
            const file = e.target.files[0];
            if (!file) {
                console.log('No file selected');
                return;
            }

            console.log('Processing file:', file.name, 'Type:', file.type);

            // Validate file type
            if (file.type !== this.options.fileType) {
                console.log('Invalid file type:', file.type);
                alert(this.options.fileTypeError);
                return;
            }

            // Create FormData for upload
            const formData = new FormData();
            formData.append('action', this.options.actionName);
            formData.append('nonce', meetingNotesL10n.nonce);
            formData.append('post_id', $('#post_ID').val());
            formData.append('file', file);

            console.log('Starting upload for action:', this.options.actionName);

            // Show progress bar and hide upload button
            const uploadSection = this.container.find('.upload-new-file');
            const progressSection = this.container.find('.upload-progress');
            
            console.log('Current visibility states before change:', {
                uploadSection: uploadSection.is(':visible'),
                progressSection: progressSection.is(':visible')
            });

            uploadSection.hide();
            progressSection.show();
            progressSection.find('.progress-bar-fill').css('width', '0%');
            progressSection.find('.progress-text').text('Uploading...');

            console.log('Visibility states after change:', {
                uploadSection: uploadSection.is(':visible'),
                progressSection: progressSection.is(':visible')
            });

            // Upload file
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: () => this.createUploadXHR(),
                success: (response) => {
                    console.log('Upload response:', response);
                    this.handleUploadSuccess(response, file);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('Upload failed:', textStatus, errorThrown);
                    this.handleUploadError();
                }
            });
        }

        createUploadXHR() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', (evt) => {
                if (evt.lengthComputable) {
                    const percentComplete = (evt.loaded / evt.total) * 100;
                    console.log('Upload progress:', percentComplete + '%');
                    this.container.find('.progress-bar-fill').css('width', percentComplete + '%');
                }
            }, false);
            return xhr;
        }

        handleUploadSuccess(response, file) {
            console.log('Handling upload success');
            const uploadSection = this.container.find('.upload-new-file');
            const progressSection = this.container.find('.upload-progress');
            const currentFileSection = this.container.find('.current-file');

            console.log('Elements found:', {
                uploadSection: uploadSection.length,
                progressSection: progressSection.length,
                currentFileSection: currentFileSection.length
            });

            if (response.success) {
                console.log('Upload successful, updating UI');
                // Update hidden input with attachment ID
                this.container.find('input[type="hidden"]').val(response.data.attachment_id);

                // Update UI to show current file
                currentFileSection.find('.filename').text(file.name);
                currentFileSection.find('a.button').attr('href', response.data.url);
                
                // Hide progress and show current file
                progressSection.hide();
                uploadSection.hide();
                currentFileSection.show();

                console.log('Final visibility states:', {
                    uploadSection: uploadSection.is(':visible'),
                    progressSection: progressSection.is(':visible'),
                    currentFileSection: currentFileSection.is(':visible')
                });

                // Reset file input
                this.container.find('input[type="file"]').val('');
            } else {
                console.log('Upload response indicated failure:', response);
                alert(response.data.message || 'Upload failed. Please try again.');
                progressSection.hide();
                uploadSection.show();
            }
        }

        handleUploadError() {
            console.log('Handling upload error');
            const uploadSection = this.container.find('.upload-new-file');
            const progressSection = this.container.find('.upload-progress');

            alert('Upload failed. Please try again.');
            progressSection.hide();
            uploadSection.show();

            // Reset file input
            this.container.find('input[type="file"]').val('');
        }
    }

    // Initialize file upload handlers
    if ($('.meeting-agenda-wrapper').length) {
        new FileUploadHandler('.meeting-agenda-wrapper', {
            actionName: 'upload_agenda_pdf'
        });
    }

    if ($('.meeting-notes-wrapper').length) {
        new FileUploadHandler('.meeting-notes-wrapper', {
            actionName: 'upload_meeting_notes'
        });
    }
}); 