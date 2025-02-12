jQuery(document).ready(function($) {
    const form = $('#birthday-import-form');
    const progressDiv = $('#import-progress');
    const progressBar = progressDiv.find('.progress-bar');
    const progressText = progressDiv.find('.progress-text');
    const resultsDiv = $('#import-results');
    const resultsContent = resultsDiv.find('.results-content');

    // Help section toggle
    $('.help-toggle').on('click', function(e) {
        e.preventDefault();
        const helpContent = $('.help-content');
        const isVisible = helpContent.is(':visible');
        
        helpContent.slideToggle();
        $(this).text(isVisible ? 
            'Show usage instructions' : 
            'Hide usage instructions'
        );
    });

    form.on('submit', function(e) {
        e.preventDefault();
        
        const fileInput = $('#birthday_csv')[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'process_birthday_import');
        formData.append('birthday_csv', fileInput.files[0]);
        formData.append('nonce', birthdayImporter.nonce);

        // Reset and show progress
        progressBar.css('width', '0%');
        progressDiv.show();
        resultsDiv.hide();
        
        $.ajax({
            url: birthdayImporter.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        progressBar.css('width', percentComplete + '%');
                        progressText.text('Uploading: ' + Math.round(percentComplete) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    resultsContent.html(response.data.message);
                } else {
                    resultsContent.html('Error: ' + response.data.message);
                }
                progressDiv.hide();
                resultsDiv.show();
            },
            error: function() {
                resultsContent.html('An error occurred during the import.');
                progressDiv.hide();
                resultsDiv.show();
            }
        });
    });
}); 