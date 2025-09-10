<?php
/**
 * File Storage Management Page
 * 
 * @package ProtectedDocs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get file storage status
$protection_status = \ProtectedDocs\FileStorage::get_protection_status();
$upload_dir = \ProtectedDocs\FileStorage::get_dedicated_upload_dir();

// Get existing files count
$documents = get_posts([
    'post_type' => 'document',
    'meta_key' => '_authdocs_file_id',
    'posts_per_page' => -1
]);

$total_files = count($documents);
$files_in_dedicated = 0;
$files_in_uploads = 0;

foreach ($documents as $document) {
    $file_id = get_post_meta($document->ID, '_authdocs_file_id', true);
    if ($file_id) {
        $file_path = get_attached_file($file_id);
        if ($file_path) {
            if (\ProtectedDocs\FileStorage::is_file_in_dedicated_folder($file_path)) {
                $files_in_dedicated++;
            } else {
                $files_in_uploads++;
            }
        }
    }
}
?>

<div class="wrap">
    <h1><?php _e('File Storage Management', 'protecteddocs'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php _e('Dedicated File Storage', 'protecteddocs'); ?></strong></p>
        <p><?php _e('AuthDocs now stores uploaded files in a dedicated, protected folder to prevent unauthorized access.', 'protecteddocs'); ?></p>
    </div>
    
    <!-- Storage Status -->
    <div class="card">
        <h2><?php _e('Storage Status', 'protecteddocs'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Dedicated Folder', 'protecteddocs'); ?></th>
                <td>
                    <?php if ($protection_status['folder_exists']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('Created', 'protecteddocs'); ?>
                        <p class="description"><?php echo esc_html($protection_status['folder_path']); ?></p>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                        <?php _e('Not Created', 'protecteddocs'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('.htaccess Protection', 'protecteddocs'); ?></th>
                <td>
                    <?php if ($protection_status['htaccess_exists']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('Active', 'protecteddocs'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                        <?php _e('Not Active', 'protecteddocs'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Index Protection', 'protecteddocs'); ?></th>
                <td>
                    <?php if ($protection_status['index_exists']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('Active', 'protecteddocs'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                        <?php _e('Not Active', 'protecteddocs'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Folder Permissions', 'protecteddocs'); ?></th>
                <td>
                    <?php if ($protection_status['folder_writable']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('Writable', 'protecteddocs'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                        <?php _e('Not Writable', 'protecteddocs'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- File Migration -->
    <div class="card">
        <h2><?php _e('File Migration', 'protecteddocs'); ?></h2>
        <p><?php _e('Move existing files from the WordPress uploads folder to the dedicated AuthDocs folder.', 'protecteddocs'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Total Files', 'protecteddocs'); ?></th>
                <td><strong><?php echo esc_html($total_files); ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('In Dedicated Folder', 'protecteddocs'); ?></th>
                <td>
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <strong><?php echo esc_html($files_in_dedicated); ?></strong>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('In Uploads Folder', 'protecteddocs'); ?></th>
                <td>
                    <?php if ($files_in_uploads > 0): ?>
                        <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                        <strong><?php echo esc_html($files_in_uploads); ?></strong>
                        <p class="description"><?php _e('These files need to be migrated for better security.', 'protecteddocs'); ?></p>
                    <?php else: ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <strong>0</strong>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php if ($files_in_uploads > 0): ?>
            <div class="migration-section">
                <h3><?php _e('Migrate Files', 'protecteddocs'); ?></h3>
                <p><?php _e('Click the button below to move all existing files to the dedicated folder.', 'protecteddocs'); ?></p>
                
                <button type="button" id="migrate-files-btn" class="button button-primary">
                    <span class="dashicons dashicons-migrate"></span>
                    <?php _e('Migrate Files', 'protecteddocs'); ?>
                </button>
                
                <div id="migration-progress" style="display: none;">
                    <p><?php _e('Migrating files...', 'protecteddocs'); ?></p>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                </div>
                
                <div id="migration-results" style="display: none;"></div>
            </div>
        <?php else: ?>
            <div class="notice notice-success">
                <p><?php _e('All files are already in the dedicated folder. No migration needed.', 'protecteddocs'); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Debug Section -->
        <div class="debug-section" style="margin-top: 30px; padding: 20px; background: #f0f0f1; border-radius: 4px;">
            <h3><?php _e('Debug File Detection', 'protecteddocs'); ?></h3>
            <p><?php _e('Use this button to debug file detection issues that might affect pagination.', 'protecteddocs'); ?></p>
            
            <button type="button" id="debug-file-detection-btn" class="button button-secondary">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Debug File Detection', 'protecteddocs'); ?>
            </button>
            
            <button type="button" id="repair-file-associations-btn" class="button button-primary" style="margin-left: 10px;">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Repair File Associations', 'protecteddocs'); ?>
            </button>
            
            <div id="debug-results" style="display: none; margin-top: 15px;"></div>
            <div id="repair-results" style="display: none; margin-top: 15px;"></div>
        </div>
    </div>
    
    <!-- Security Information -->
    <div class="card">
        <h2><?php _e('Security Features', 'protecteddocs'); ?></h2>
        <ul>
            <li><strong><?php _e('Dedicated Folder:', 'protecteddocs'); ?></strong> <?php _e('Files are stored in a separate folder outside the public uploads directory.', 'protecteddocs'); ?></li>
            <li><strong><?php _e('.htaccess Protection:', 'protecteddocs'); ?></strong> <?php _e('Direct access to files is blocked via server-level rules.', 'protecteddocs'); ?></li>
            <li><strong><?php _e('Index Protection:', 'protecteddocs'); ?></strong> <?php _e('Directory listing is prevented with index.php files.', 'protecteddocs'); ?></li>
            <li><strong><?php _e('Token-Based Access:', 'protecteddocs'); ?></strong> <?php _e('Files can only be accessed through secure tokens.', 'protecteddocs'); ?></li>
        </ul>
    </div>
    
    <!-- Folder Structure -->
    <div class="card">
        <h2><?php _e('Folder Structure', 'protecteddocs'); ?></h2>
        <div class="folder-structure">
            <pre><?php echo esc_html($upload_dir['basedir']); ?>
└── authdocs-files/
    ├── .htaccess (blocks direct access)
    ├── index.php (prevents directory listing)
    └── authdocs_[timestamp]_[filename] (your files)</pre>
        </div>
    </div>
</div>

<style>
.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background-color: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}

.folder-structure {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

.migration-section {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

#migration-results {
    margin-top: 15px;
}

#migration-results.success {
    color: #46b450;
}

#migration-results.error {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#migrate-files-btn').on('click', function() {
        var $btn = $(this);
        var $progress = $('#migration-progress');
        var $results = $('#migration-results');
        
        // Disable button and show progress
        $btn.prop('disabled', true);
        $progress.show();
        $results.hide();
        
        // Animate progress bar
        $('.progress-fill').css('width', '100%');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'protecteddocs_migrate_files',
                nonce: '<?php echo wp_create_nonce('protecteddocs_migrate_files'); ?>'
            },
            success: function(response) {
                $progress.hide();
                $results.removeClass('error').addClass('success').html(
                    '<p><strong>Success:</strong> ' + response.data.message + '</p>'
                ).show();
                
                // Reload page after 3 seconds to show updated status
                setTimeout(function() {
                    location.reload();
                }, 3000);
            },
            error: function(xhr) {
                $progress.hide();
                var message = 'Migration failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                $results.removeClass('success').addClass('error').html(
                    '<p><strong>Error:</strong> ' + message + '</p>'
                ).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $('.progress-fill').css('width', '0%');
            }
        });
    });
    
    // Debug file detection
    $('#debug-file-detection-btn').on('click', function() {
        var $btn = $(this);
        var $results = $('#debug-results');
        
        // Disable button
        $btn.prop('disabled', true);
        $results.hide();
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'protecteddocs_debug_file_detection',
                nonce: '<?php echo wp_create_nonce('protecteddocs_admin_nonce'); ?>'
            },
            success: function(response) {
                $results.removeClass('error').addClass('success').html(
                    '<p><strong>Success:</strong> ' + response.data.message + '</p>'
                ).show();
            },
            error: function(xhr) {
                var message = 'Debug failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                $results.removeClass('success').addClass('error').html(
                    '<p><strong>Error:</strong> ' + message + '</p>'
                ).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Repair file associations
    $('#repair-file-associations-btn').on('click', function() {
        var $btn = $(this);
        var $results = $('#repair-results');
        
        // Disable button
        $btn.prop('disabled', true);
        $results.hide();
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'protecteddocs_repair_file_associations',
                nonce: '<?php echo wp_create_nonce('protecteddocs_admin_nonce'); ?>'
            },
            success: function(response) {
                var message = response.data.message;
                if (response.data.results && response.data.results.details) {
                    message += '<br><br><strong>Details:</strong><br>';
                    message += response.data.results.details.join('<br>');
                }
                
                $results.removeClass('error').addClass('success').html(
                    '<p><strong>Success:</strong> ' + message + '</p>'
                ).show();
            },
            error: function(xhr) {
                var message = 'Repair failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                $results.removeClass('success').addClass('error').html(
                    '<p><strong>Error:</strong> ' + message + '</p>'
                ).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
