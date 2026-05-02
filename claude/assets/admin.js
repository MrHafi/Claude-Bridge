jQuery(document).ready(function($) {

    // Save button click handler
    $('#claude_save_btn').on('click', function() {
        var data = {
            action:         'claude_save_settings',
            nonce:          claude_ajax.nonce,
            api_key:        $('#claude_api_key').val(),
            groq_api_key:   $('#claude_groq_api_key').val(),
            content_access: $('#claude_content_access').is(':checked') ? 1 : 0,
            file_access:    $('#claude_file_access').is(':checked') ? 1 : 0,
            db_access:      $('#claude_db_access').is(':checked') ? 1 : 0,
        };

        $.post(claude_ajax.ajax_url, data, function(response) {
            if (response.success) {
                $('#claude_notice').html(response.data.message).fadeIn();
            } else {
                $('#claude_notice').html('Something went wrong.').fadeIn();
            }
        });
    });

    // Test Groq connection — MUST be inside ready()
$('#claude_test_groq').on('click', function() {
    console.log('test clicked');
    $('#claude_test_result').show().html('Testing...');

    $.post(claude_ajax.ajax_url, {
        action: 'claude_test_groq',
        nonce:  claude_ajax.nonce,
    }, function(response) {
        if (response.success) {
            $('#claude_test_result').html('✅ ' + response.data.message);
        } else {
            $('#claude_test_result').html('❌ ' + response.data.message);
        }
    }); 
});






// ---------------------------- Send chat instruction --------------------------------------
$('#claude_send_btn').on('click', function() {
    var instruction = $('#claude_chat_input').val().trim();

    if (!instruction) {
        alert('Please type an instruction first!');
        return;
    }

    // Show loading
    $('#claude_chat_loading').show();

    // Add user message to chat box
    $('#claude_chat_box').append(
        '<p><strong style="color:#7c6aff;">You:</strong> ' + instruction + '</p>'
    );

    $.post(claude_ajax.ajax_url, {
        action: 'claude_handle_instruction',
        nonce:  claude_ajax.nonce,
        instruction: instruction,
    }, function(response) {
        $('#claude_chat_loading').hide();
        $('#claude_chat_input').val('');

        if (response.success) {
            $('#claude_chat_box').append(
                '<p><strong style="color:#38a169;">Claude:</strong> ' + response.data.message + '</p>'
            );
        } else {
            $('#claude_chat_box').append(
                '<p><strong style="color:#e53e3e;">Error:</strong> ' + response.data.message + '</p>'
            );
        }
    });
});






}); // close ready()