<?php
/**
 * Settings page template for AuthDocs plugin
 * 
 * @package AuthDocs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'authdocs'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="authdocs-settings-container">
        <form method="post" action="options.php">
            <?php
            settings_fields('authdocs_options');
            do_settings_sections('authdocs-settings');
            submit_button(__('Save Email Templates', 'authdocs'), 'primary', 'submit', false);
            ?>
        </form>
        
        <div class="authdocs-settings-sidebar">
            <div class="authdocs-settings-card">
                <h3><?php _e('Email Template Preview', 'authdocs'); ?></h3>
                <p><?php _e('Preview how your emails will look with sample data:', 'authdocs'); ?></p>
                <button type="button" id="preview-access-request" class="button button-secondary">
                    <?php _e('Preview Access Request', 'authdocs'); ?>
                </button>
                <button type="button" id="preview-auto-response" class="button button-secondary">
                    <?php _e('Preview Auto-Response', 'authdocs'); ?>
                </button>
                <button type="button" id="preview-grant-decline" class="button button-secondary">
                    <?php _e('Preview Grant/Decline', 'authdocs'); ?>
                </button>
            </div>
            
            <div class="authdocs-settings-card">
                <h3><?php _e('Template Tips', 'authdocs'); ?></h3>
                <ul>
                    <li><?php _e('Use HTML for rich formatting', 'authdocs'); ?></li>
                    <li><?php _e('Variables are automatically replaced', 'authdocs'); ?></li>
                    <li><?php _e('Test with different email clients', 'authdocs'); ?></li>
                    <li><?php _e('Keep subject lines under 60 characters', 'authdocs'); ?></li>
                </ul>
            </div>
            
            <div class="authdocs-settings-card">
                <h3><?php _e('Test Emails', 'authdocs'); ?></h3>
                <p><?php _e('Send test emails to verify your templates work correctly:', 'authdocs'); ?></p>
                <button type="button" id="test-access-request" class="button button-secondary">
                    <?php _e('Test Access Request', 'authdocs'); ?>
                </button>
                <button type="button" id="test-auto-response" class="button button-secondary">
                    <?php _e('Test Auto-Response', 'authdocs'); ?>
                </button>
                <button type="button" id="test-grant-decline" class="button button-secondary">
                    <?php _e('Test Grant/Decline', 'authdocs'); ?>
                </button>
                <div id="test-email-results" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="authdocs-email-preview-modal" class="authdocs-modal" style="display: none;">
    <div class="authdocs-modal-content">
        <div class="authdocs-modal-header">
            <h3 id="preview-modal-title"><?php _e('Email Preview', 'authdocs'); ?></h3>
            <button type="button" class="authdocs-modal-close">&times;</button>
        </div>
        <div class="authdocs-modal-body">
            <div class="authdocs-preview-tabs">
                <button type="button" class="authdocs-tab-button active" data-tab="html"><?php _e('HTML', 'authdocs'); ?></button>
                <button type="button" class="authdocs-tab-button" data-tab="preview"><?php _e('Preview', 'authdocs'); ?></button>
            </div>
            
            <div class="authdocs-tab-content active" id="html-tab">
                <div class="authdocs-preview-subject">
                    <strong><?php _e('Subject:', 'authdocs'); ?></strong>
                    <span id="preview-subject"></span>
                </div>
                <textarea id="preview-body-html" readonly rows="20" class="large-text code"></textarea>
            </div>
            
            <div class="authdocs-tab-content" id="preview-tab">
                <div class="authdocs-preview-subject">
                    <strong><?php _e('Subject:', 'authdocs'); ?></strong>
                    <span id="preview-subject-display"></span>
                </div>
                <div id="preview-body-display"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sample data for previews
    var sampleData = {
        name: 'John Doe',
        email: 'john.doe@example.com',
        file_name: 'Sample Document.pdf',
        site_name: '<?php echo esc_js(get_bloginfo('name')); ?>',
        status: 'Granted',
        status_color: '#28a745',
        link: 'https://example.com/authdocs/download?hash=abc123&file=document.pdf'
    };
    
    // Preview functions
    function previewEmail(templateType, title) {
        var subjectField, bodyField;
        
        switch(templateType) {
            case 'access_request':
                subjectField = '#access_request_subject';
                bodyField = '#access_request_body';
                break;
            case 'auto_response':
                subjectField = '#auto_response_subject';
                bodyField = '#auto_response_body';
                break;
            case 'grant_decline':
                subjectField = '#grant_decline_subject';
                bodyField = '#grant_decline_body';
                break;
        }
        
        var subject = $(subjectField).val();
        var body = $(bodyField).val();
        
        // Replace variables in preview
        var previewSubject = replaceVariables(subject, sampleData);
        var previewBody = replaceVariables(body, sampleData);
        
        // Update preview content
        $('#preview-modal-title').text(title);
        $('#preview-subject').text(previewSubject);
        $('#preview-subject-display').text(previewSubject);
        $('#preview-body-html').val(previewBody);
        $('#preview-body-display').html(previewBody);
        
        // Show modal
        $('#authdocs-email-preview-modal').show();
    }
    
    function replaceVariables(text, data) {
        return text.replace(/\{\{name\}\}/g, data.name)
                   .replace(/\{\{email\}\}/g, data.email)
                   .replace(/\{\{file_name\}\}/g, data.file_name)
                   .replace(/\{\{site_name\}\}/g, data.site_name)
                   .replace(/\{\{status\}\}/g, data.status)
                   .replace(/\{\{status_color\}\}/g, data.status_color)
                   .replace(/\{\{link\}\}/g, data.link);
    }
    
    // Preview button handlers
    $('#preview-access-request').on('click', function() {
        previewEmail('access_request', '<?php _e('Access Request Email Preview', 'authdocs'); ?>');
    });
    
    $('#preview-auto-response').on('click', function() {
        previewEmail('auto_response', '<?php _e('Auto-Response Email Preview', 'authdocs'); ?>');
    });
    
    $('#preview-grant-decline').on('click', function() {
        previewEmail('grant_decline', '<?php _e('Grant/Decline Email Preview', 'authdocs'); ?>');
    });
    
    // Close modal
    $('.authdocs-modal-close').on('click', function() {
        $('#authdocs-email-preview-modal').hide();
    });
    
    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).is('#authdocs-email-preview-modal')) {
            $('#authdocs-email-preview-modal').hide();
        }
    });
    
    // Tab functionality
    $('.authdocs-tab-button').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update active tab button
        $('.authdocs-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update active tab content
        $('.authdocs-tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });
    
    // Test email functions
    function testEmail(action, buttonText, successMessage) {
        var $btn = $('#' + action);
        var $result = $('#test-email-results');
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'authdocs_' + action,
                nonce: authdocs_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + successMessage + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Error sending test email</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text(buttonText);
            }
        });
    }
    
    // Test email button handlers
    $('#test-access-request').on('click', function() {
        testEmail('test_access_request', 'Test Access Request', 'Access request test email sent successfully!');
    });
    
    $('#test-auto-response').on('click', function() {
        testEmail('test_auto_response', 'Test Auto-Response', 'Auto-response test email sent successfully!');
    });
    
    $('#test-grant-decline').on('click', function() {
        testEmail('test_grant_decline', 'Test Grant/Decline', 'Grant/decline test email sent successfully!');
    });
});
</script>
