<?php
declare(strict_types=1);

namespace AuthDocs;

class Shortcode
{
    public function __construct()
    {
        add_shortcode('authdocs', [$this, 'render_shortcode']);
        add_shortcode('authdocs_grid', [$this, 'render_grid_shortcode']);
        add_action('init', [$this, 'handle_secure_download']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function render_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'restricted' => 'no'
        ], $atts, 'authdocs');

        $document_id = intval($atts['id']);
        $restricted = $atts['restricted'] === 'yes';

        if (!$document_id || get_post_type($document_id) !== 'document') {
            return '<p>' . __('Invalid document ID', 'authdocs') . '</p>';
        }

        $document = get_post($document_id);
        if (!$document || $document->post_status !== 'publish') {
            return '<p>' . __('Document not found', 'authdocs') . '</p>';
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            return '<p>' . __('No file attached to this document', 'authdocs') . '</p>';
        }

        ob_start();
        ?>
        <div class="authdocs-document" data-document-id="<?php echo esc_attr($document_id); ?>" data-restricted="<?php echo esc_attr($restricted ? 'yes' : 'no'); ?>">
            <div class="authdocs-document-header">
                <h3 class="authdocs-document-title"><?php echo esc_html($document->post_title); ?></h3>
                <?php if ($document->post_content): ?>
                    <div class="authdocs-document-description">
                        <?php echo wp_kses_post($document->post_content); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="authdocs-document-actions">
                <?php if ($restricted): ?>
                    <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document_id); ?>">
                        <?php _e('Request Access', 'authdocs'); ?>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url($file_data['url']); ?>" class="authdocs-download-btn" download>
                        <?php _e('Download Document', 'authdocs'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render grid view shortcode with pagination
     */
    public function render_grid_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'limit' => 12,
            'restriction' => 'all', // all, restricted, unrestricted
            'columns' => 3,
            'show_description' => 'yes',
            'show_date' => 'yes',
            'orderby' => 'date', // date, title
            'order' => 'DESC',
            'pagination' => 'yes' // yes, no
        ], $atts, 'authdocs_grid');
        
        $limit = intval($atts['limit']);
        $restriction = sanitize_text_field($atts['restriction']);
        $columns = intval($atts['columns']);
        $show_description = $atts['show_description'] === 'yes';
        $show_date = $atts['show_date'] === 'yes';
        $orderby = sanitize_text_field($atts['orderby']);
        $order = sanitize_text_field($atts['order']);
        $pagination = $atts['pagination'] === 'yes';
        
        // Validate inputs
        if ($limit < 1) $limit = 12;
        if ($columns < 1 || $columns > 6) $columns = 3;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if (!in_array($orderby, ['date', 'title'])) $orderby = 'date';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';
        
        // Get current page from URL parameter
        $current_page = isset($_GET['authdocs_page']) ? max(1, intval($_GET['authdocs_page'])) : 1;
        
        $documents = Database::get_published_documents($limit, $restriction, $current_page, $orderby, $order);
        $total_documents = Database::get_published_documents_count($restriction);
        $total_pages = ceil($total_documents / $limit);
        
        if (empty($documents)) {
            return '<p class="authdocs-no-documents">' . __('No documents found.', 'authdocs') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="authdocs-grid-container" 
             data-limit="<?php echo esc_attr($limit); ?>"
             data-restriction="<?php echo esc_attr($restriction); ?>"
             data-columns="<?php echo esc_attr($columns); ?>"
             data-show-description="<?php echo esc_attr($show_description ? 'yes' : 'no'); ?>"
             data-show-date="<?php echo esc_attr($show_date ? 'yes' : 'no'); ?>"
             data-orderby="<?php echo esc_attr($orderby); ?>"
             data-order="<?php echo esc_attr($order); ?>"
             data-current-page="<?php echo esc_attr($current_page); ?>"
             data-total-pages="<?php echo esc_attr($total_pages); ?>"
             data-total-documents="<?php echo esc_attr($total_documents); ?>">
            
            <div class="authdocs-grid" data-columns="<?php echo esc_attr($columns); ?>">
                <?php foreach ($documents as $document): ?>
                    <div class="authdocs-grid-item">
                        <div class="authdocs-grid-item-content">
                            <div class="authdocs-grid-item-header">
                                <h3 class="authdocs-grid-item-title">
                                    <?php echo esc_html($document['title']); ?>
                                </h3>
                                <?php if ($show_date): ?>
                                    <div class="authdocs-grid-item-date">
                                        <?php echo esc_html($document['date']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($show_description && !empty($document['description'])): ?>
                                <div class="authdocs-grid-item-description">
                                    <?php echo wp_kses_post(wp_trim_words($document['description'], 20)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="authdocs-grid-item-actions">
                                <?php if ($document['restricted']): ?>
                                    <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>">
                                        <?php _e('Request Access', 'authdocs'); ?>
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" download>
                                        <?php _e('Download', 'authdocs'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($pagination && $total_pages > 1): ?>
                <div class="authdocs-pagination">
                    <div class="authdocs-pagination-info">
                        <?php 
                        $start = (($current_page - 1) * $limit) + 1;
                        $end = min($current_page * $limit, $total_documents);
                        printf(__('Showing %d-%d of %d documents', 'authdocs'), $start, $end, $total_documents);
                        ?>
                    </div>
                    
                    <div class="authdocs-pagination-links">
                        <?php if ($current_page > 1): ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-prev" data-page="<?php echo esc_attr($current_page - 1); ?>">
                                <?php _e('Previous', 'authdocs'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <div class="authdocs-pagination-numbers">
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="1">1</button>
                                <?php if ($start_page > 2): ?>
                                    <span class="authdocs-pagination-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <button type="button" class="authdocs-pagination-btn authdocs-pagination-number <?php echo $i === $current_page ? 'active' : ''; ?>" data-page="<?php echo esc_attr($i); ?>">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="authdocs-pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="<?php echo esc_attr($total_pages); ?>"><?php echo $total_pages; ?></button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-next" data-page="<?php echo esc_attr($current_page + 1); ?>">
                                <?php _e('Next', 'authdocs'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend assets for grid functionality
     */
    public function enqueue_frontend_assets(): void
    {
        if (has_shortcode(get_the_content(), 'authdocs_grid')) {
            wp_enqueue_script('authdocs-frontend', plugin_dir_url(__FILE__) . '../assets/js/frontend.js', ['jquery'], '1.0.0', true);
            wp_localize_script('authdocs-frontend', 'authdocs_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('authdocs_frontend_nonce')
            ]);
        }
    }

    public function handle_secure_download(): void
    {
        // Check if this is a document download request
        if (!isset($_GET['authdocs_download'])) {
            return;
        }

        $document_id = intval($_GET['authdocs_download']);
        $hash = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        $request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : null;
        $filename = isset($_GET['filename']) ? sanitize_file_name($_GET['filename']) : '';

        // Block access if no hash is provided (direct file access attempt)
        if (empty($hash)) {
            wp_die(__('Access denied. Valid authorization required.', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
        }

        // Validate the secure access first (this will check hash and basic validity)
        error_log("AuthDocs: Download validation - Document ID: {$document_id}, Email: {$email}, Hash: {$hash}, Request ID: {$request_id}");
        if (!Database::validate_secure_access($hash, $email, $document_id, $request_id)) {
            error_log("AuthDocs: Download validation failed - Invalid or expired download link");
            wp_die(__('Invalid or expired download link', 'authdocs'), __('Access Denied', 'authdocs'), ['response' => 403]);
        }
        error_log("AuthDocs: Download validation successful");

        // Check if request is accessible (not inactive) after hash validation
        if ($request_id && !Database::is_request_accessible($request_id)) {
            // Log the attempt to access a deactivated file
            error_log("AuthDocs: Attempted access to deactivated file - Request ID: {$request_id}, Document ID: {$document_id}, Email: {$email}");
            wp_die(__('File access has been deactivated. Please contact the administrator.', 'authdocs'), __('File Not Available', 'authdocs'), ['response' => 403]);
        }

        // Get the request details to log access
        $request = Database::get_request_by_hash($hash);
        if ($request) {
            // Log the access attempt (optional - for audit trail)
            error_log("AuthDocs: Document access granted for request ID {$request->id}, document ID {$document_id}, email {$email}");
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            wp_die(__('File not found', 'authdocs'), __('Error', 'authdocs'), ['response' => 404]);
        }

        if (!file_exists($file_data['path'])) {
            wp_die(__('File not found on server', 'authdocs'), __('Error', 'authdocs'), ['response' => 404]);
        }

        // Use filename from URL if provided, otherwise use the original filename
        $download_filename = !empty($filename) ? $filename : $file_data['filename'];
        
        // Get file extension to determine content type
        $file_extension = strtolower(pathinfo($file_data['path'], PATHINFO_EXTENSION));
        
        // Set appropriate headers based on file type
        if ($file_extension === 'pdf') {
            // For PDFs, display in browser instead of downloading
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $download_filename . '"');
            header('Content-Length: ' . filesize($file_data['path']));
            header('Cache-Control: public, max-age=3600');
        } else {
            // For other files, use appropriate content type but don't force download
            $content_type = $this->get_content_type($file_extension);
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . $download_filename . '"');
            header('Content-Length: ' . filesize($file_data['path']));
            header('Cache-Control: public, max-age=3600');
        }

        // Output file
        readfile($file_data['path']);
        exit;
    }

    private function get_content_type(string $extension): string
    {
        $content_types = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];

        return $content_types[$extension] ?? 'application/octet-stream';
    }
}
