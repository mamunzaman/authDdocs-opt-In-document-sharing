<?php
/**
 * Email Templates settings page template for AuthDocs plugin
 * 
 * @package AuthDocs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'protecteddocs'));
}

// Create Settings instance for helper methods
$settings = new \ProtectedDocs\Settings();

// Check if settings were just saved
$settings_updated = false;
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    $settings_updated = true;
}
?>

<div class="wrap">
    <div class="authdocs-page-header">
        <h1 class="authdocs-page-title">
            <span class="authdocs-page-icon">
                <span class="dashicons dashicons-email-alt"></span>
            </span>
            <?php _e('Email Settings', 'protecteddocs'); ?>
        </h1>
        <p class="authdocs-page-description">
            <?php _e('Configure email templates for different stages of the document access workflow', 'protecteddocs'); ?>
        </p>
    </div>
    
    <?php if ($settings_updated): ?>
        <div class="authdocs-settings-notice notice notice-success">
            <div class="authdocs-notice-content">
                <div class="authdocs-notice-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="authdocs-notice-text">
                    <strong><?php _e('Settings Saved Successfully!', 'protecteddocs'); ?></strong>
                    <p><?php _e('Your email template settings have been saved and are now active.', 'protecteddocs'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="authdocs-settings-container">
        <div class="authdocs-settings-layout">
            <div class="authdocs-settings-main authdocs-email-templates-main">
                <div class="authdocs-email-templates-header">
                    <h2 class="authdocs-main-title">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e('Email Templates Configuration', 'protecteddocs'); ?>
                    </h2>
                    <p class="authdocs-main-description">
                        <?php _e('Customize your email templates for different stages of the document access workflow. Each template supports dynamic variables and HTML formatting.', 'protecteddocs'); ?>
                    </p>
                </div>
                
                <form method="post" action="options.php" class="authdocs-email-templates-form">
                    <?php 
                    settings_fields('authdocs_options');
                    
                    // Include hidden fields for frontend settings to preserve their values
                    echo '<input type="hidden" name="' . \ProtectedDocs\Settings::FRONTEND_COLOR_PALETTE_NAME . '" value="' . esc_attr(get_option(\ProtectedDocs\Settings::FRONTEND_COLOR_PALETTE_NAME, '')) . '" />';
                    echo '<input type="hidden" name="' . \ProtectedDocs\Settings::PAGINATION_STYLE_NAME . '" value="' . esc_attr(get_option(\ProtectedDocs\Settings::PAGINATION_STYLE_NAME, '')) . '" />';
                    echo '<input type="hidden" name="' . \ProtectedDocs\Settings::PAGINATION_TYPE_NAME . '" value="' . esc_attr(get_option(\ProtectedDocs\Settings::PAGINATION_TYPE_NAME, '')) . '" />';
                    ?>
                    
                    <!-- Top Save Button -->
                    <div class="authdocs-form-actions authdocs-form-actions-top">
                        <button type="submit" class="button button-primary authdocs-save-button">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Email Templates', 'protecteddocs'); ?>
                        </button>
                    </div>
                    
                    <div class="authdocs-email-sections">
                        <!-- Access Request Email Section -->
                        <div class="authdocs-email-section authdocs-section-access-request">
                            <div class="authdocs-section-header">
                                <div class="authdocs-section-header-left">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-admin-users"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Access Request Notification', 'protecteddocs'); ?></h3>
                                        <p><?php _e('Sent to website owners when someone requests document access', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="authdocs-section-preview-btn" onclick="previewEmail('access_request', '<?php _e('Access Request Email Preview', 'protecteddocs'); ?>')">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Preview', 'protecteddocs'); ?>
                                </button>
                            </div>
                            <div class="authdocs-section-content">
                                <?php do_settings_fields('authdocs-settings', 'authdocs_access_request_section'); ?>
                            </div>
                        </div>
                        
                        <!-- Middle Save Button -->
                        <div class="authdocs-form-actions authdocs-form-actions-middle">
                            <button type="submit" class="button button-primary authdocs-save-button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Email Templates', 'protecteddocs'); ?>
                            </button>
                        </div>
                        
                        <!-- Auto-Response Email Section -->
                        <div class="authdocs-email-section authdocs-section-auto-response">
                            <div class="authdocs-section-header">
                                <div class="authdocs-section-header-left">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Auto-Response Confirmation', 'protecteddocs'); ?></h3>
                                        <p><?php _e('Automatically sent to users when they submit an access request', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="authdocs-section-preview-btn" onclick="previewEmail('auto_response', '<?php _e('Auto-Response Email Preview', 'protecteddocs'); ?>')">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Preview', 'protecteddocs'); ?>
                                </button>
                            </div>
                            <div class="authdocs-section-content">
                                <?php do_settings_fields('authdocs-settings', 'authdocs_auto_response_section'); ?>
                            </div>
                        </div>
                        
                        <!-- Grant/Decline Email Section -->
                        <div class="authdocs-email-section authdocs-section-grant-decline">
                            <div class="authdocs-section-header">
                                <div class="authdocs-section-header-left">
                                    <div class="authdocs-section-icon">
                                        <span class="dashicons dashicons-awards"></span>
                                    </div>
                                    <div class="authdocs-section-title">
                                        <h3><?php _e('Access Decision Notification', 'protecteddocs'); ?></h3>
                                        <p><?php _e('Sent to users when their access request is approved or declined', 'protecteddocs'); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="authdocs-section-preview-btn" onclick="previewEmail('grant_decline', '<?php _e('Access Decision Email Preview', 'protecteddocs'); ?>')">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Preview', 'protecteddocs'); ?>
                                </button>
                            </div>
                            <div class="authdocs-section-content">
                                <?php do_settings_fields('authdocs-settings', 'authdocs_grant_decline_section'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="authdocs-form-actions authdocs-form-actions-bottom">
                        <button type="submit" class="button button-primary authdocs-save-button">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Email Templates', 'protecteddocs'); ?>
                        </button>
                        <div class="authdocs-save-status">
                            <span class="authdocs-save-indicator"></span>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="authdocs-settings-sidebar authdocs-email-sidebar">
                <div class="authdocs-sidebar-header">
                    <h3 class="authdocs-sidebar-title">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Tools & Resources', 'protecteddocs'); ?>
                    </h3>
                    <p class="authdocs-sidebar-description">
                        <?php _e('Preview, test, and optimize your email templates', 'protecteddocs'); ?>
                    </p>
                </div>
                
                <div class="authdocs-sidebar-content">
                    <!-- Email Preview Section -->
                    <div class="authdocs-sidebar-card authdocs-preview-card">
                        <div class="authdocs-card-header">
                            <div class="authdocs-card-icon">
                                <span class="dashicons dashicons-visibility"></span>
                            </div>
                            <div class="authdocs-card-title">
                                <h4><?php _e('Email Preview', 'protecteddocs'); ?></h4>
                                <p><?php _e('See how your emails will look', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <p class="authdocs-card-description">
                                <?php _e('Preview your email templates with sample data to ensure they look perfect:', 'protecteddocs'); ?>
                            </p>
                            <div class="authdocs-button-group">
                                <button type="button" id="preview-access-request" class="authdocs-sidebar-button authdocs-button-preview">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e('Access Request', 'protecteddocs'); ?>
                                </button>
                                <button type="button" id="preview-auto-response" class="authdocs-sidebar-button authdocs-button-preview">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Auto-Response', 'protecteddocs'); ?>
                                </button>
                                <button type="button" id="preview-grant-decline" class="authdocs-sidebar-button authdocs-button-preview">
                                    <span class="dashicons dashicons-awards"></span>
                                    <?php _e('Grant/Decline', 'protecteddocs'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Template Tips Section -->
                    <div class="authdocs-sidebar-card authdocs-tips-card">
                        <div class="authdocs-card-header">
                            <div class="authdocs-card-icon">
                                <span class="dashicons dashicons-lightbulb"></span>
                            </div>
                            <div class="authdocs-card-title">
                                <h4><?php _e('Template Tips', 'protecteddocs'); ?></h4>
                                <p><?php _e('Best practices for email design', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <div class="authdocs-tips-list">
                                <div class="authdocs-tip-item">
                                    <span class="dashicons dashicons-editor-code"></span>
                                    <span><?php _e('Use HTML for rich formatting', 'protecteddocs'); ?></span>
                                </div>
                                <div class="authdocs-tip-item">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <span><?php _e('Variables are automatically replaced', 'protecteddocs'); ?></span>
                                </div>
                                <div class="authdocs-tip-item">
                                    <span class="dashicons dashicons-email-alt"></span>
                                    <span><?php _e('Test with different email clients', 'protecteddocs'); ?></span>
                                </div>
                                <div class="authdocs-tip-item">
                                    <span class="dashicons dashicons-text"></span>
                                    <span><?php _e('Keep subject lines under 60 characters', 'protecteddocs'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Emails Section -->
                    <div class="authdocs-sidebar-card authdocs-test-card">
                        <div class="authdocs-card-header">
                            <div class="authdocs-card-icon">
                                <span class="dashicons dashicons-email"></span>
                            </div>
                            <div class="authdocs-card-title">
                                <h4><?php _e('Test Emails', 'protecteddocs'); ?></h4>
                                <p><?php _e('Send test emails to verify functionality', 'protecteddocs'); ?></p>
                            </div>
                        </div>
                        <div class="authdocs-card-content">
                            <p class="authdocs-card-description">
                                <?php _e('Send test emails to verify your templates work correctly:', 'protecteddocs'); ?>
                            </p>
                            <div class="authdocs-button-group">
                                <button type="button" id="test-access-request" class="authdocs-sidebar-button authdocs-button-test">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php _e('Test Access Request', 'protecteddocs'); ?>
                                </button>
                                <button type="button" id="test-auto-response" class="authdocs-sidebar-button authdocs-button-test">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Test Auto-Response', 'protecteddocs'); ?>
                                </button>
                                <button type="button" id="test-grant-decline" class="authdocs-sidebar-button authdocs-button-test">
                                    <span class="dashicons dashicons-awards"></span>
                                    <?php _e('Test Grant/Decline', 'protecteddocs'); ?>
                                </button>
                            </div>
                            <div id="test-email-results" class="authdocs-test-results"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div id="authdocs-email-preview-modal" class="authdocs-modal" style="display: none;">
    <div class="authdocs-modal-content">
        <div class="authdocs-modal-header">
            <h3 id="preview-modal-title"><?php _e('Email Preview', 'protecteddocs'); ?></h3>
            <button type="button" class="authdocs-modal-close">&times;</button>
        </div>
        <div class="authdocs-modal-body">
            <div class="authdocs-preview-content">
                <div class="authdocs-preview-subject">
                    <strong><?php _e('Subject:', 'protecteddocs'); ?></strong>
                    <span id="preview-subject-display"></span>
                </div>
                <div id="preview-body-display"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sample data for previews - Make globally accessible
    window.sampleData = {
        name: 'John Doe',
        email: 'john.doe@example.com',
        file_name: 'Sample Document.pdf',
        site_name: '<?php echo esc_js(get_bloginfo('name')); ?>',
        status: 'Granted',
        status_color: '#28a745',
        link: 'https://example.com/authdocs/download?hash=abc123&file=document.pdf'
    };
    
    // Preview functions - Make globally accessible
    window.previewEmail = function(templateType, title) {
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
        var previewSubject = window.replaceVariables(subject, window.sampleData);
        var previewBody = window.replaceVariables(body, window.sampleData);
        
        // Update preview content
        $('#preview-modal-title').text(title);
        $('#preview-subject-display').text(previewSubject);
        $('#preview-body-display').html(previewBody);
        
        // Show modal
        $('#authdocs-email-preview-modal').show();
    };
    
    window.replaceVariables = function(text, data) {
        return text.replace(/\{\{name\}\}/g, data.name)
                   .replace(/\{\{email\}\}/g, data.email)
                   .replace(/\{\{file_name\}\}/g, data.file_name)
                   .replace(/\{\{site_name\}\}/g, data.site_name)
                   .replace(/\{\{status\}\}/g, data.status)
                   .replace(/\{\{status_color\}\}/g, data.status_color)
                   .replace(/\{\{link\}\}/g, data.link);
    };
    
    // Preview button handlers
    $('#preview-access-request').on('click', function() {
        previewEmail('access_request', '<?php _e('Access Request Email Preview', 'protecteddocs'); ?>');
    });
    
    $('#preview-auto-response').on('click', function() {
        previewEmail('auto_response', '<?php _e('Auto-Response Email Preview', 'protecteddocs'); ?>');
    });
    
    $('#preview-grant-decline').on('click', function() {
        previewEmail('grant_decline', '<?php _e('Grant/Decline Email Preview', 'protecteddocs'); ?>');
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
                action: 'protecteddocs_' + action,
                nonce: protecteddocs_admin.nonce
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
