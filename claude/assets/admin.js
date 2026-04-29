jQuery(document).ready(function($) {

    // Save button click handler
    $('#claude_save_btn').on('click', function() {

        // Collect all field values
        var data = {
            action:         'claude_save_settings',
            nonce:          claude_ajax.nonce,
            api_key:        $('#claude_api_key').val(),
            content_access: $('#claude_content_access').is(':checked') ? 1 : 0,
            file_access:    $('#claude_file_access').is(':checked') ? 1 : 0,
            db_access:      $('#claude_db_access').is(':checked') ? 1 : 0,
        };

        // Send data to WordPress via AJAX
        $.post(claude_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $('#claude_notice').html(response.data.message).fadeIn();
            } else {
                $('#claude_notice').html('Something went wrong.').fadeIn();
            }
        });
    });

});