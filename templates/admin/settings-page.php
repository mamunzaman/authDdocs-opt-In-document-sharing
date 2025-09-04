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

// Get current tab
$current_tab = $_GET['tab'] ?? 'email-templates';
$allowed_tabs = ['email-templates', 'frontend-settings', 'general'];
if (!in_array($current_tab, $allowed_tabs)) {
    $current_tab = 'email-templates';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?post_type=document&page=authdocs-settings&tab=email-templates" 
           class="nav-tab <?php echo $current_tab === 'email-templates' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Email Templates', 'authdocs'); ?>
        </a>
        <a href="?post_type=document&page=authdocs-settings&tab=frontend-settings" 
           class="nav-tab <?php echo $current_tab === 'frontend-settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Frontend Settings', 'authdocs'); ?>
        </a>
        <a href="?post_type=document&page=authdocs-settings&tab=general" 
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'authdocs'); ?>
        </a>
    </nav>
    
    <div class="authdocs-settings-container">
        <!-- Email Templates Tab -->
        <div class="authdocs-tab-content" id="email-templates-tab" style="<?php echo $current_tab === 'email-templates' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('authdocs_options');
                        
                        // Render Access Request Email Section
                        echo '<h2>' . __('Access Request Email Template', 'authdocs') . '</h2>';
                        echo '<p>' . __('Configure the email template that will be sent to website owners when a document access request is submitted.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_access_request_section');
                        
                        // Render Auto-Response Email Section
                        echo '<h2>' . __('Auto-Response Email Template', 'authdocs') . '</h2>';
                        echo '<p>' . __('Configure the automatic response email that will be sent to users when they request document access.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_auto_response_section');
                        
                        // Render Grant/Decline Email Section
                        echo '<h2>' . __('Grant/Decline Email Template', 'authdocs') . '</h2>';
                        echo '<p>' . __('Configure the email template that will be sent when document access is granted or declined.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_grant_decline_section');
                        
                        submit_button(__('Save Email Templates', 'authdocs'), 'primary', 'submit', false);
                        ?>
                    </form>
                </div>
                
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
        
        <!-- Frontend Settings Tab -->
        <div class="authdocs-tab-content" id="frontend-settings-tab" style="<?php echo $current_tab === 'frontend-settings' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('authdocs_options');
                        
                        // Render Frontend Color Palette Section
                        echo '<h2>' . __('Frontend Color Palette', 'authdocs') . '</h2>';
                        echo '<p>' . __('Choose a color palette for the frontend document display. Changes will apply to all shortcodes and document grids.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_frontend_colors_section');
                        
                        // Render Pagination Section
                        echo '<h2>' . __('Pagination Settings', 'authdocs') . '</h2>';
                        echo '<p>' . __('Configure how pagination is displayed on the frontend.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_pagination_section');
                        
                        submit_button(__('Save Frontend Settings', 'authdocs'), 'primary', 'submit', false);
                        ?>
                    </form>
                </div>
                
                <div class="authdocs-settings-sidebar">
                    <div class="authdocs-settings-card">
                        <h3><?php _e('Frontend Preview', 'authdocs'); ?></h3>
                        <p><?php _e('Preview how your frontend will look with the selected settings:', 'authdocs'); ?></p>
                        <div class="authdocs-preview-container">
                            <div class="authdocs-preview-grid">
                                <div class="authdocs-preview-item">
                                    <h4><?php _e('Sample Document', 'authdocs'); ?></h4>
                                    <p><?php _e('This is a sample document description.', 'authdocs'); ?></p>
                                    <button class="authdocs-preview-btn authdocs-preview-lock">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="authdocs-settings-card">
                        <h3><?php _e('Color Palette Info', 'authdocs'); ?></h3>
                        <ul>
                            <li><?php _e('Black & White: Clean, professional look', 'authdocs'); ?></li>
                            <li><?php _e('Blue & Gray: Modern, corporate style', 'authdocs'); ?></li>
                            <li><?php _e('Changes apply to all frontend instances', 'authdocs'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="authdocs-settings-card">
                        <h3><?php _e('Pagination Styles', 'authdocs'); ?></h3>
                        <ul>
                            <li><?php _e('Classic: Traditional page numbers', 'authdocs'); ?></li>
                            <li><?php _e('Load More: Progressive loading', 'authdocs'); ?></li>
                            <li><?php _e('Both styles use AJAX for smooth experience', 'authdocs'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- General Settings Tab -->
        <div class="authdocs-tab-content" id="general-tab" style="<?php echo $current_tab === 'general' ? 'display: block;' : 'display: none;'; ?>">
            <div class="authdocs-settings-layout">
                <div class="authdocs-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('authdocs_options');
                        
                        // Render General Section
                        echo '<h2>' . __('General Settings', 'authdocs') . '</h2>';
                        echo '<p>' . __('General plugin settings and information.', 'authdocs') . '</p>';
                        do_settings_fields('authdocs-settings', 'authdocs_general_section');
                        
                        submit_button(__('Save General Settings', 'authdocs'), 'primary', 'submit', false);
                        ?>
                    </form>
                </div>
                
                <div class="authdocs-settings-sidebar">
                    <div class="authdocs-settings-card">
                        <h3><?php _e('Plugin Information', 'authdocs'); ?></h3>
                        <p><strong><?php _e('Version:', 'authdocs'); ?></strong> <?php echo AUTHDOCS_VERSION; ?></p>
                        <p><strong><?php _e('WordPress Version:', 'authdocs'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                        <p><strong><?php _e('PHP Version:', 'authdocs'); ?></strong> <?php echo PHP_VERSION; ?></p>
                    </div>
                    
                    <div class="authdocs-settings-card">
                        <h3><?php _e('Support', 'authdocs'); ?></h3>
                        <p><?php _e('Need help? Check out our documentation or contact support.', 'authdocs'); ?></p>
                        <a href="#" class="button button-secondary"><?php _e('Documentation', 'authdocs'); ?></a>
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
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).attr('href').split('tab=')[1];
        if (!targetTab) return;
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.authdocs-tab-content').hide();
        
        // Show target tab content
        $('#' + targetTab + '-tab').show();
        
        // Update URL without page reload
        var newUrl = window.location.pathname + '?post_type=document&page=authdocs-settings&tab=' + targetTab;
        window.history.pushState({path: newUrl}, '', newUrl);
    });
    
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