<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Document Requests', 'authdocs'); ?></h1>
    
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
                        <th><?php _e('Document', 'authdocs'); ?></th>
                        <th><?php _e('File Link', 'authdocs'); ?></th>
                        <th><?php _e('Status', 'authdocs'); ?></th>
                        <th><?php _e('Date', 'authdocs'); ?></th>
                        <th><?php _e('Actions', 'authdocs'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr data-request-id="<?php echo esc_attr($request->id); ?>">
                            <td data-label="Request ID"><?php echo esc_html($request->id); ?></td>
                            <td data-label="Requester Name"><?php echo esc_html($request->requester_name); ?></td>
                            <td data-label="Email"><?php echo esc_html($request->requester_email); ?></td>
                            <td data-label="Document">
                                <?php if ($request->document_title): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($request->document_id)); ?>" target="_blank">
                                        <?php echo esc_html($request->document_title); ?>
                                    </a>
                                <?php else: ?>
                                    <?php _e('Document not found', 'authdocs'); ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="File Link">
                                <?php 
                                $file_data = null;
                                if (isset($request->document_id) && is_numeric($request->document_id)) {
                                    $file_data = AuthDocs\Database::get_document_file(intval($request->document_id));
                                }
                                
                                if ($file_data && isset($file_data['url']) && isset($file_data['filename'])): 
                                    // Generate the full download link if request is accepted and has hash
                                    if ($request->status === 'accepted' && $request->secure_hash): 
                                        $download_url = home_url('/?authdocs_download=' . $request->document_id . '&hash=' . $request->secure_hash . '&email=' . urlencode($request->requester_email) . '&request_id=' . $request->id);
                                        ?>
                                        <a href="<?php echo esc_url($download_url); ?>" target="_blank" class="authdocs-download-link" title="<?php _e('Click to view document', 'authdocs'); ?>">
                                            <?php echo esc_html($file_data['filename']); ?>
                                        </a>
                                        <br>
                                        <small class="authdocs-link-preview"><?php echo esc_html(substr($download_url, 0, 50) . '...'); ?></small>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($file_data['url']); ?>" target="_blank" class="authdocs-file-link">
                                            <?php echo esc_html($file_data['filename']); ?>
                                        </a>
                                        <br>
                                        <small class="authdocs-status-note"><?php _e('Request not accepted yet', 'authdocs'); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="authdocs-no-file"><?php _e('No file', 'authdocs'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <span class="authdocs-status authdocs-status-<?php echo esc_attr($request->status); ?>">
                                    <?php echo esc_html(ucfirst($request->status)); ?>
                                </span>
                            </td>
                            <td data-label="Date & Time">
                                <div class="authdocs-date-time">
                                    <div class="authdocs-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($request->created_at))); ?></div>
                                    <div class="authdocs-time"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($request->created_at))); ?></div>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="authdocs-request-actions">
                                    <!-- Accept Link -->
                                    <a href="#" class="authdocs-action-link authdocs-action-accept" data-action="accept" data-request-id="<?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <span class="action-text"><?php echo $request->status === 'accepted' ? __('Re-accept', 'authdocs') : __('Accept', 'authdocs'); ?></span>
                                    </a>
                                    
                                    <!-- Decline Link -->
                                    <a href="#" class="authdocs-action-link authdocs-action-decline" data-action="decline" data-request-id="<?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                        <span class="action-text"><?php echo $request->status === 'declined' ? __('Re-decline', 'authdocs') : __('Decline', 'authdocs'); ?></span>
                                    </a>
                                    
                                    <!-- Deactivate Link -->
                                    <a href="#" class="authdocs-action-link authdocs-action-inactive" data-action="inactive" data-request-id="<?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-hidden"></span>
                                        <span class="action-text"><?php echo $request->status === 'inactive' ? __('Keep Inactive', 'authdocs') : __('Deactivate', 'authdocs'); ?></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="authdocs-pagination">
                    <div class="authdocs-pagination-info">
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
                    </div>
                    
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
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript functionality moved to admin.js for better performance and consistency -->
