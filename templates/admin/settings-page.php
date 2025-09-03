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
            submit_button(__('Save Email Template', 'authdocs'), 'primary', 'submit', false);
            ?>
        </form>
        
        <div class="authdocs-settings-sidebar">
            <div class="authdocs-settings-card">
                <h3><?php _e('Email Template Preview', 'authdocs'); ?></h3>
                <p><?php _e('Preview how your email will look with sample data:', 'authdocs'); ?></p>
                <button type="button" id="preview-email" class="button button-secondary">
                    <?php _e('Preview Email', 'authdocs'); ?>
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
                <h3><?php _e('Test Email', 'authdocs'); ?></h3>
                <p><?php _e('Send a test email to verify your template works correctly:', 'authdocs'); ?></p>
                <button type="button" id="test-email" class="button button-secondary">
                    <?php _e('Send Test Email', 'authdocs'); ?>
                </button>
                <div id="test-email-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="authdocs-settings-card">
                <h3><?php _e('Test Autoresponder', 'authdocs'); ?></h3>
                <p><?php _e('Send a test autoresponder email to verify your template works correctly:', 'authdocs'); ?></p>
                <button type="button" id="test-autoresponder" class="button button-secondary">
                    <?php _e('Send Test Autoresponder', 'authdocs'); ?>
                </button>
                <div id="test-autoresponder-result" style="margin-top: 10px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="authdocs-email-preview-modal" class="authdocs-modal" style="display: none;">
    <div class="authdocs-modal-content">
        <div class="authdocs-modal-header">
            <h3><?php _e('Email Preview', 'authdocs'); ?></h3>
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
    // Preview email functionality
    $('#preview-email').on('click', function() {
        var subject = $('#email_subject').val();
        var body = $('#email_body').val();
        
        // Sample data for preview
        var sampleData = {
            name: 'John Doe',
            email: 'john.doe@example.com',
            link: 'https://example.com/authdocs/download?hash=abc123&file=document.pdf'
        };
        
        // Replace variables in preview
        var previewSubject = subject.replace(/\{\{name\}\}/g, sampleData.name)
                                   .replace(/\{\{email\}\}/g, sampleData.email)
                                   .replace(/\{\{link\}\}/g, sampleData.link);
        
        var previewBody = body.replace(/\{\{name\}\}/g, sampleData.name)
                             .replace(/\{\{email\}\}/g, sampleData.email)
                             .replace(/\{\{link\}\}/g, sampleData.link);
        
        // Update preview content
        $('#preview-subject').text(previewSubject);
        $('#preview-subject-display').text(previewSubject);
        $('#preview-body-html').val(previewBody);
        $('#preview-body-display').html(previewBody);
        
        // Show modal
        $('#authdocs-email-preview-modal').show();
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
    
    // Test email functionality
    $('#test-email').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-email-result');
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'authdocs_test_email',
                nonce: authdocs_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Error sending test email</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Send Test Email');
            }
        });
    });
    
    // Test autoresponder functionality
    $('#test-autoresponder').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-autoresponder-result');
        
        $btn.prop('disabled', true).text('Sending...');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'authdocs_test_autoresponder',
                nonce: authdocs_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Error sending test autoresponder email</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Send Test Autoresponder');
            }
        });
    });
});
</script>
