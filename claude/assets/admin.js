
// converts basic markdown to HTML so it renders properly in CHATBOX
function parseMarkdown(text) {
    return text
        .replace(/^(PURPOSE|FUNCTIONS|HOOKS|CONSTANTS|INCLUDED FILES)/gm, '<strong>$1</strong>')  // bold section titles
        .replace(/\n{2,}/g, '<br><br>')   // double newline → paragraph break
        .replace(/\n/g, '<br>');          // single newline → line break
}

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
                '<div style="margin-bottom:12px;"><strong style="color:#38a169;">Claude:</strong><br>' 
                + parseMarkdown(response.data.message)   // ← parse markdown before appending
                + '</div>'
            );
        } else {
            $('#claude_chat_box').append(
                '<p><strong style="color:#e53e3e;">Error:</strong> ' + response.data.message + '</p>'
            );
        }
    });
});





// TABS IN ADMIN DASHBOARD -----------------
// tab switching
$('.claude-tab-btn').on('click', function() {
    var tab = $(this).data('tab');                           // get which tab was clicked

    $('.claude-tab-btn').removeClass('active');              // deactivate all tab buttons
    $('.claude-tab-content').removeClass('active');          // hide all tab content

    $(this).addClass('active');                              // activate clicked button
    $('#claude-tab-' + tab).addClass('active');              // show matching content
});





// ADMIN PANEL VIEW
// collapse/expand individual group
$('.claude-group-header').on('click', function() {
    var body = $(this).next('.claude-group-body');
    body.toggleClass('hidden');
    $(this).toggleClass('collapsed');
});

// collapse/expand all groups
$('#claude_toggle_all').on('click', function() {
    var allBodies  = $('.claude-group-body');
    var allHeaders = $('.claude-group-header');
    var isAnyOpen  = allBodies.not('.hidden').length > 0;

    if (isAnyOpen) {
        allBodies.addClass('hidden');
        allHeaders.addClass('collapsed');
        $(this).text('Expand All');
    } else {
        allBodies.removeClass('hidden');
        allHeaders.removeClass('collapsed');
        $(this).text('Collapse All');
    }
});

// click action button → autofill chat input
$('.claude-action-btn').on('click', function() {
    var action = $(this).data('action');
    $('#claude_chat_input').val(action).focus(); // fill and focus input
});


}); // close ready()