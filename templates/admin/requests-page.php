<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Document Requests', 'authdocs'); ?></h1>
    
    <?php if (!empty($requests)): ?>
        <div class="authdocs-table-controls">
            <div class="authdocs-filter-container">
                <input type="text" id="authdocs-requests-filter" class="authdocs-filter-input" 
                       placeholder="<?php _e('Filter requests...', 'authdocs'); ?>" 
                       title="<?php _e('Type at least 3 characters to filter', 'authdocs'); ?>">
                <span class="dashicons dashicons-search authdocs-filter-icon"></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (empty($requests)): ?>
        <div class="notice notice-info">
            <p><?php _e('No document requests found.', 'authdocs'); ?></p>
        </div>
    <?php else: ?>
        <div class="authdocs-requests-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'authdocs'); ?></th>
                        <th><?php _e('Requester Name', 'authdocs'); ?></th>
                        <th><?php _e('Email', 'authdocs'); ?></th>
                        <th class="authdocs-sortable" data-sort="document">
                            <?php _e('Document', 'authdocs'); ?>
                            <span class="dashicons dashicons-arrow-up-alt2 authdocs-sort-icon"></span>
                        </th>
                        <th class="authdocs-sortable" data-sort="file_link">
                            <?php _e('File Link', 'authdocs'); ?>
                            <span class="dashicons dashicons-arrow-up-alt2 authdocs-sort-icon"></span>
                        </th>
                        <th class="authdocs-sortable" data-sort="status">
                            <?php _e('Status', 'authdocs'); ?>
                            <span class="dashicons dashicons-arrow-up-alt2 authdocs-sort-icon"></span>
                        </th>
                        <th class="authdocs-sortable" data-sort="date">
                            <?php _e('Date', 'authdocs'); ?>
                            <span class="dashicons dashicons-arrow-up-alt2 authdocs-sort-icon"></span>
                        </th>
                        <th><?php _e('Actions', 'authdocs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): 
                        // Get document title for sorting/filtering
                        $document_title = AuthDocs\Database::get_document_title(intval($request->document_id));
                        $document_title = $document_title ?: sprintf(__('Document #%d', 'authdocs'), $request->document_id);
                        
                        // Get file link status for sorting
                        $file_link_status = '';
                        if ($request->status === 'inactive') {
                            $file_link_status = 'locked';
                        } elseif ($request->status === 'accepted' && $request->secure_hash) {
                            $file_link_status = 'available';
                        } else {
                            $file_link_status = 'pending';
                        }
                    ?>
                        <tr data-request-id="<?php echo esc_attr($request->id); ?>" 
                            data-document="<?php echo esc_attr($document_title); ?>"
                            data-file-link="<?php echo esc_attr($file_link_status); ?>"
                            data-status="<?php echo esc_attr($request->status); ?>"
                            data-date="<?php echo esc_attr($request->created_at); ?>"
                            data-search="<?php echo esc_attr(strtolower($request->requester_name . ' ' . $request->requester_email . ' ' . $document_title . ' ' . $request->status)); ?>">
                            <td data-label="Request ID"><?php echo esc_html($request->id); ?></td>
                            <td data-label="Requester Name"><?php echo esc_html($request->requester_name); ?></td>
                            <td data-label="Email"><?php echo esc_html($request->requester_email); ?></td>
                            <td data-label="Document">
                                <?php 
                                // Get document title using the robust helper method
                                $document_title = AuthDocs\Database::get_document_title(intval($request->document_id));
                                
                                // Debug logging
                                error_log("AuthDocs Debug - Request ID: {$request->id}, Document ID: {$request->document_id}, Final Title: " . ($document_title ?: 'EMPTY'));
                                ?>
                                <?php if ($document_title): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($request->document_id)); ?>" target="_blank">
                                        <?php echo esc_html($document_title); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($request->document_id)); ?>" target="_blank">
                                        <?php printf(__('Document #%d', 'authdocs'), $request->document_id); ?>
                                    </a>
                                    <br><small class="authdocs-status-note"><?php _e('Title not available', 'authdocs'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="File Link">
                                <?php 
                                $file_data = null;
                                if (isset($request->document_id) && is_numeric($request->document_id)) {
                                    $file_data = AuthDocs\Database::get_document_file(intval($request->document_id));
                                }
                                
                                if ($file_data && isset($file_data['url']) && isset($file_data['filename'])): 
                                    // Show special message for deactivated requests
                                    if ($request->status === 'inactive'):
                                        ?>
                                        <div class="authdocs-access-revoked">
                                            <span class="dashicons dashicons-lock"></span>
                                            <span class="authdocs-locked-label"><?php _e('Locked', 'authdocs'); ?></span>
                                        </div>
                                        <?php
                                    // Generate the full download link if request is accepted and has hash
                                    elseif ($request->status === 'accepted' && $request->secure_hash): 
                                        $download_url = home_url('?authdocs_download=' . $request->document_id . '&hash=' . $request->secure_hash . '&email=' . urlencode($request->requester_email) . '&request_id=' . $request->id);
                                        ?>
                                        <div class="authdocs-link-container">
                                            <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="authdocs-download-link" title="<?php _e('Click to view document', 'authdocs'); ?>">
                                                <?php _e('View Document', 'authdocs'); ?>
                                            </a>
                                            <button type="button" class="authdocs-copy-link" title="<?php _e('Copy link', 'authdocs'); ?>" data-link="<?php echo esc_attr($download_url); ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                            </button>
                                        </div>
                                                                         <?php else: ?>
                                         <div class="authdocs-link-container">
                                             <span class="dashicons dashicons-lock authdocs-link-lock"></span>
                                             <a href="<?php echo esc_url($file_data['url']); ?>" target="_blank" class="authdocs-file-link">
                                                 <?php _e('View File', 'authdocs'); ?>
                                             </a>
                                         </div>
                                         <br>
                                         <?php 
                                         $status_message = '';
                                         $status_class = 'authdocs-status-note';
                                         switch ($request->status) {
                                             case 'declined':
                                                 $status_message = __('Access declined', 'authdocs');
                                                 $status_class .= ' declined';
                                                 break;
                                             case 'inactive':
                                                 $status_message = __('Access revoked - File no longer available', 'authdocs');
                                                 $status_class .= ' inactive';
                                                 break;
                                             case 'pending':
                                                 $status_message = __('Request pending approval', 'authdocs');
                                                 $status_class .= ' pending';
                                                 break;
                                             default:
                                                 $status_message = __('Request not accepted yet', 'authdocs');
                                         }
                                         ?>
                                         <small class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_message); ?></small>
                                     <?php endif; ?>
                                <?php else: ?>
                                    <span class="authdocs-no-file"><?php _e('No file', 'authdocs'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php
                                $status_icons = [
                                    'pending' => 'dashicons-clock',
                                    'accepted' => 'dashicons-yes-alt',
                                    'declined' => 'dashicons-no-alt',
                                    'inactive' => 'dashicons-hidden'
                                ];
                                $status_colors = [
                                    'pending' => '#ffc107',
                                    'accepted' => '#28a745',
                                    'declined' => '#dc3545',
                                    'inactive' => '#6c757d'
                                ];
                                $icon = $status_icons[$request->status] ?? 'dashicons-info';
                                $color = $status_colors[$request->status] ?? '#6c757d';
                                ?>
                                <div class="authdocs-status-modern authdocs-status-<?php echo esc_attr($request->status); ?>">
                                    <span class="authdocs-status-icon" style="color: <?php echo esc_attr($color); ?>">
                                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                    </span>
                                    <span class="authdocs-status-text"><?php echo esc_html(ucfirst($request->status)); ?></span>
                                </div>
                            </td>
                            <td data-label="Date & Time">
                                <div class="authdocs-date-time">
                                    <div class="authdocs-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request->created_at))); ?></div>
                                    <div class="authdocs-time"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($request->created_at))); ?></div>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="authdocs-request-actions">
                                    <?php
                                    // Determine button states based on current status
                                    $accept_disabled = ($request->status === 'accepted' || $request->status === 'inactive');
                                    $decline_disabled = ($request->status === 'declined' || $request->status === 'inactive');
                                    // Toggle button should always be enabled to allow switching between states
                                    $toggle_disabled = false;
                                    ?>
                                    
                                    <!-- Accept Link -->
                                    <button type="button" class="authdocs-action-link authdocs-action-accept <?php echo $accept_disabled ? 'disabled' : ''; ?>" 
                                       data-action="accept" data-request-id="<?php echo esc_attr($request->id); ?>"
                                       title="<?php 
                                         if ($request->status === 'accepted') {
                                           echo __('Already accepted', 'authdocs');
                                         } elseif ($request->status === 'inactive') {
                                           echo __('Link is hidden - Show link first', 'authdocs');
                                         } else {
                                           echo __('Accept', 'authdocs');
                                         }
                                       ?>"
                                       <?php echo $accept_disabled ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </button>
                                    
                                    <!-- Decline Link -->
                                    <button type="button" class="authdocs-action-link authdocs-action-decline <?php echo $decline_disabled ? 'disabled' : ''; ?>" 
                                       data-action="decline" data-request-id="<?php echo esc_attr($request->id); ?>"
                                       title="<?php 
                                         if ($request->status === 'declined') {
                                           echo __('Already declined', 'authdocs');
                                         } elseif ($request->status === 'inactive') {
                                           echo __('Link is hidden - Show link first', 'authdocs');
                                         } else {
                                           echo __('Decline', 'authdocs');
                                         }
                                       ?>"
                                       <?php echo $decline_disabled ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                    
                                    <!-- Toggle Link Visibility -->
                                    <button type="button" class="authdocs-action-link authdocs-action-inactive <?php echo $toggle_disabled ? 'disabled' : ''; ?>" 
                                       data-action="inactive" data-request-id="<?php echo esc_attr($request->id); ?>"
                                       title="<?php echo $request->status === 'inactive' ? __('Link is hidden - Click to show', 'authdocs') : __('Link is visible - Click to hide', 'authdocs'); ?>"
                                       <?php echo $toggle_disabled ? 'disabled' : ''; ?>>
                                        <span class="dashicons <?php echo $request->status === 'inactive' ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
                                    </button>
                                    
                                    <!-- Delete Link -->
                                    <button type="button" class="authdocs-action-link authdocs-action-delete" 
                                       data-action="delete" data-request-id="<?php echo esc_attr($request->id); ?>"
                                       title="<?php _e('Delete request', 'authdocs'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="authdocs-table-footer">
                <div class="authdocs-pagination-info">
                    <span id="authdocs-pagination-info">
                        <?php 
                        $start = (($current_page - 1) * $per_page) + 1;
                        $end = min($current_page * $per_page, $total_requests);
                        printf(
                            __('Showing %1$d-%2$d of %3$d requests', 'authdocs'),
                            $start,
                            $end,
                            $total_requests
                        );
                        ?>
                    </span>
                </div>
                
                <div class="authdocs-pagination" id="authdocs-pagination">
                    <?php if ($total_pages > 1): ?>
                        <div class="authdocs-pagination-links">
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="page-numbers prev">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    <?php _e('Previous', 'authdocs'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', 1)); ?>" class="page-numbers">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="page-numbers dots">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="page-numbers current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" class="page-numbers"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="page-numbers dots">...</span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>" class="page-numbers"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="page-numbers next">
                                    <?php _e('Next', 'authdocs'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript functionality moved to admin.js for better performance and consistency -->
