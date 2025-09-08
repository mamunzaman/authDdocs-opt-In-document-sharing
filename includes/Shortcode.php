<?php
declare(strict_types=1);

namespace ProtectedDocs;

class Shortcode
{
    public function __construct()
    {
        add_shortcode('protecteddocs', [$this, 'render_shortcode']);
        add_shortcode('authdocs_grid', [$this, 'render_grid_shortcode']);
        add_action('init', [$this, 'handle_secure_download']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Render single document shortcode
     */
    public function render_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'restricted' => 'no'
        ], $atts, 'protecteddocs');

        $document_id = intval($atts['id']);
        $restricted = $atts['restricted'] === 'yes';
        $instance_id = 'authdocs-' . uniqid();

        if (!$document_id || get_post_type($document_id) !== 'document') {
            return '<p>' . __('Invalid document ID', 'protecteddocs') . '</p>';
        }

        $document = get_post($document_id);
        if (!$document || $document->post_status !== 'publish') {
            return '<p>' . __('Document not found', 'protecteddocs') . '</p>';
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            return '<p>' . __('No file attached to this document', 'protecteddocs') . '</p>';
        }

        // Get color palette settings
        $settings = new Settings();
        $color_palette = $settings->get_color_palette_data();
        
        $this->enqueue_dynamic_styles($instance_id, $color_palette);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="authdocs-document" data-document-id="<?php echo esc_attr($document_id); ?>" data-restricted="<?php echo esc_attr($restricted ? 'yes' : 'no'); ?>">
            <div class="authdocs-document-content">
                <h3 class="authdocs-document-title"><?php echo esc_html($document->post_title); ?></h3>
            <div class="authdocs-document-actions">
                <?php if ($restricted): ?>
                        <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document_id); ?>">
                            <?php _e('Request Access', 'protecteddocs'); ?>
                    </button>
                <?php else: ?>
                        <a href="<?php echo esc_url($this->get_secure_download_url($document_id)); ?>" class="authdocs-download-btn">
                            <?php _e('Download', 'protecteddocs'); ?>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render document grid shortcode
     */
    public function render_grid_shortcode(array $atts): string
    {
        // Default attributes
        $atts = shortcode_atts([
            'columns' => 3,
            'columns_desktop' => 5,
            'columns_tablet' => 3,
            'columns_mobile' => 1,
            'limit' => 12,
            'load_more_limit' => 12,
            'pagination_style' => 'classic',
            'pagination_type' => 'classic',
            'featured_image' => 'yes',
            'restriction' => 'all',
            'show_description' => 'yes',
            'show_date' => 'yes',
            'orderby' => 'date',
            'order' => 'DESC',
            'color_palette' => 'default'
        ], $atts, 'authdocs_grid');
        
        // Sanitize and validate inputs
        $columns = intval($atts['columns']);
        $columns_desktop = intval($atts['columns_desktop']);
        $columns_tablet = intval($atts['columns_tablet']);
        $columns_mobile = intval($atts['columns_mobile']);
        $limit = intval($atts['limit']);
        $load_more_limit = intval($atts['load_more_limit']);
        $pagination_style = sanitize_text_field($atts['pagination_style']);
        $pagination_type = sanitize_text_field($atts['pagination_type']);
        $restriction = sanitize_text_field($atts['restriction']);
        $show_description = $atts['show_description'] === 'yes';
        $show_date = $atts['show_date'] === 'yes';
        $orderby = sanitize_text_field($atts['orderby']);
        $order = sanitize_text_field($atts['order']);
        $color_palette = sanitize_text_field($atts['color_palette']);
        $show_featured_image = $atts['featured_image'] === 'yes';
        
        $instance_id = 'authdocs-container-' . uniqid();
        
        // Validate inputs
        if ($limit < 1) $limit = 12;
        if ($columns < 1 || $columns > 6) $columns = 3;
        if ($columns_desktop < 1 || $columns_desktop > 6) $columns_desktop = 5;
        if ($columns_tablet < 1 || $columns_tablet > 6) $columns_tablet = 3;
        if ($columns_mobile < 1 || $columns_mobile > 6) $columns_mobile = 1;
        if ($load_more_limit < 1) $load_more_limit = 12;
        
        // Get current page for pagination
        $current_page = 1;
        if (isset($_GET['authdocs_page']) && is_numeric($_GET['authdocs_page'])) {
            $current_page = max(1, intval($_GET['authdocs_page']));
        }
        
        // Get documents
        $documents = $this->get_documents($limit, $current_page, $restriction, $orderby, $order);
        $total_documents = $this->get_total_documents($restriction);
        $total_pages = ceil($total_documents / $limit);
        
        // Get color palette data
        $settings = new Settings();
        $color_palette_data = $settings->get_color_palette_data($color_palette);
        $color_palette_key = $color_palette;
        
        // Enqueue dynamic styles
        $this->enqueue_dynamic_styles($instance_id, $color_palette_data);
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>" class="authdocs-grid-container" 
             data-limit="<?php echo esc_attr($limit); ?>"
             data-restriction="<?php echo esc_attr($restriction); ?>"
             data-columns="<?php echo esc_attr($columns); ?>"
             data-columns-desktop="<?php echo esc_attr($columns_desktop); ?>"
             data-columns-tablet="<?php echo esc_attr($columns_tablet); ?>"
             data-columns-mobile="<?php echo esc_attr($columns_mobile); ?>"
             data-show-description="<?php echo esc_attr($show_description ? 'yes' : 'no'); ?>"
             data-show-date="<?php echo esc_attr($show_date ? 'yes' : 'no'); ?>"
             data-orderby="<?php echo esc_attr($orderby); ?>"
             data-order="<?php echo esc_attr($order); ?>"
             data-featured-image="<?php echo esc_attr($show_featured_image ? 'yes' : 'no'); ?>"
             data-pagination-style="<?php echo esc_attr($pagination_style); ?>"
             data-pagination-type="<?php echo esc_attr($pagination_type); ?>"
             data-color-palette="<?php echo esc_attr($color_palette_key); ?>"
             data-current-page="<?php echo esc_attr($current_page); ?>"
             data-total-pages="<?php echo esc_attr($total_pages); ?>"
             data-total-documents="<?php echo esc_attr($total_documents); ?>"
             data-load-more-limit="<?php echo esc_attr($load_more_limit); ?>">
            
            <div class="authdocs-grid">
                <?php foreach ($documents as $document): 
                    // Get featured image for background (only if enabled)
                    $featured_image = '';
                    $card_style = '';
                    if ($show_featured_image) {
                        $featured_image = get_the_post_thumbnail_url($document['id'], 'large');
                        if ($featured_image) {
                            $card_style = 'style="background-image: url(' . esc_url($featured_image) . ');"';
                        }
                    }
                ?>
                    <!-- Fresh Clean Card -->
                    <article class="authdocs-card" <?php echo $card_style; ?> data-color-palette="<?php echo esc_attr($color_palette); ?>">
                        <!-- Card Content Overlay -->
                        <div class="authdocs-card-content">
                            <h3 class="authdocs-card-title"><?php echo esc_html($document['title']); ?></h3>
                            <?php if ($show_date): ?>
                                <div class="authdocs-card-date"><?php echo esc_html($document['date']); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Status Indicator -->
                        <div class="authdocs-card-status">
                            <?php if ($document['restricted']): ?>
                                <div class="authdocs-status-badge authdocs-status-locked">
                                    <svg class="authdocs-lock-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                    </svg>
                                    <span><?php _e('Locked', 'protecteddocs'); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="authdocs-status-badge authdocs-status-unlocked">
                                    <svg class="authdocs-unlock-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h4c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/>
                                    </svg>
                                    <span><?php _e('Unlocked', 'protecteddocs'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Hover Overlay with Icon -->
                        <div class="authdocs-card-hover-overlay">
                            <div class="authdocs-overlay-icon">
                                <?php if ($document['restricted']): ?>
                                    <svg class="authdocs-lock-icon" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                    </svg>
                                    <span class="authdocs-overlay-text"><?php _e('Request Access', 'protecteddocs'); ?></span>
                                <?php else: ?>
                                    <svg class="authdocs-download-icon" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                    </svg>
                                    <span class="authdocs-overlay-text"><?php _e('Download', 'protecteddocs'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                                <!-- Action Overlay -->
                                <div class="authdocs-card-action-overlay">
                                    <?php if ($document['restricted']): ?>
                                        <a href="<?php echo esc_url($this->get_access_request_url($document['id'])); ?>" class="authdocs-request-access-btn" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                                            <svg class="authdocs-lock-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <span><?php _e('Request Access', 'protecteddocs'); ?></span>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($this->get_secure_download_url($document['id'])); ?>" class="authdocs-download-btn" title="<?php _e('Download Document', 'protecteddocs'); ?>">
                                            <svg class="authdocs-download-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                                            </svg>
                                            <span><?php _e('Download', 'protecteddocs'); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <?php if ($pagination_style === 'load_more'): ?>
                    <!-- Load More Pagination -->
                    <div class="authdocs-load-more">
                        <div class="authdocs-pagination-info">
                            <?php 
                            $start = (($current_page - 1) * $limit) + 1;
                            $end = min($current_page * $limit, $total_documents);
                            printf(__('Showing %d-%d of %d documents', 'protecteddocs'), $start, $end, $total_documents);
                            ?>
                        </div>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <button type="button" class="authdocs-load-more-btn" data-current-limit="<?php echo esc_attr($limit); ?>" data-restriction="<?php echo esc_attr($restriction); ?>" data-load-more-limit="<?php echo esc_attr($load_more_limit); ?>" data-featured-image="<?php echo esc_attr($show_featured_image ? 'yes' : 'no'); ?>">
                                <?php _e('Load More Documents', 'protecteddocs'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Classic Pagination -->
                    <div class="authdocs-pagination authdocs-classic-pagination" data-pagination-type="<?php echo esc_attr($pagination_type); ?>">
                        <div class="authdocs-pagination-info">
                            <?php 
                            $start = (($current_page - 1) * $limit) + 1;
                            $end = min($current_page * $limit, $total_documents);
                            printf(__('Showing %d-%d of %d documents', 'protecteddocs'), $start, $end, $total_documents);
                            ?>
                        </div>
                        
                        <div class="authdocs-pagination-links">
                            <?php if ($pagination_type === 'ajax'): ?>
                                <!-- AJAX Pagination with buttons -->
                                <?php if ($current_page > 1): ?>
                                    <button type="button" class="authdocs-pagination-btn authdocs-pagination-prev" data-page="<?php echo esc_attr($current_page - 1); ?>">
                                        <?php _e('Previous', 'protecteddocs'); ?>
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
                                        <?php if ($i === $current_page): ?>
                                            <span class="authdocs-pagination-btn authdocs-pagination-number active"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <button type="button" class="authdocs-pagination-btn authdocs-pagination-number" data-page="<?php echo esc_attr($i); ?>"><?php echo $i; ?></button>
                                        <?php endif; ?>
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
                                        <?php _e('Next', 'protecteddocs'); ?>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Classic Pagination with links -->
                                <?php 
                                $current_url = remove_query_arg('authdocs_page');
                                ?>
                                <?php if ($current_page > 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg('authdocs_page', $current_page - 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-prev">
                                        <?php _e('Previous', 'protecteddocs'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="authdocs-pagination-numbers">
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg('authdocs_page', 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number">1</a>
                                        <?php if ($start_page > 2): ?>
                                            <span class="authdocs-pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <?php if ($i === $current_page): ?>
                                            <span class="authdocs-pagination-btn authdocs-pagination-number active"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url(add_query_arg('authdocs_page', $i, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <span class="authdocs-pagination-ellipsis">...</span>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(add_query_arg('authdocs_page', $total_pages, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-number"><?php echo $total_pages; ?></a>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?php echo esc_url(add_query_arg('authdocs_page', $current_page + 1, $current_url)); ?>" class="authdocs-pagination-btn authdocs-pagination-next">
                                        <?php _e('Next', 'protecteddocs'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get documents for display
     */
    private function get_documents(int $limit, int $page, string $restriction, string $orderby, string $order): array
    {
        $args = [
            'post_type' => 'document',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'meta_query' => []
        ];

        // Add restriction filter
        if ($restriction === 'restricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '='
            ];
        } elseif ($restriction === 'unrestricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '!='
            ];
        }

        $query = new \WP_Query($args);
        $documents = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $documents[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'date' => get_the_date(),
                    'restricted' => get_post_meta($post_id, '_authdocs_restricted', true) === 'yes'
                ];
            }
            wp_reset_postdata();
        }

        return $documents;
    }

    /**
     * Get total documents count
     */
    private function get_total_documents(string $restriction): int
    {
        $args = [
            'post_type' => 'document',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => []
        ];

        // Add restriction filter
        if ($restriction === 'restricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '='
            ];
        } elseif ($restriction === 'unrestricted') {
            $args['meta_query'][] = [
                'key' => '_authdocs_restricted',
                'value' => 'yes',
                'compare' => '!='
            ];
        }

        $query = new \WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get secure download URL
     */
    private function get_secure_download_url(int $document_id): string
    {
        $token = Tokens::generate_download_token($document_id);
        return add_query_arg([
            'authdocs_download' => $document_id,
            'token' => $token
        ], home_url());
    }

    /**
     * Get access request URL for a document
     */
    private function get_access_request_url(int $document_id): string
    {
        return add_query_arg([
            'authdocs_access' => '1',
            'document_id' => $document_id
        ], home_url());
    }

    /**
     * Handle secure download
     */
    public function handle_secure_download(): void
    {
        if (!isset($_GET['authdocs_download']) || !isset($_GET['token'])) {
            return;
        }

        $document_id = intval($_GET['authdocs_download']);
        $token = sanitize_text_field($_GET['token']);

        if (!Tokens::verify_download_token($document_id, $token)) {
            wp_die(__('Invalid download link', 'protecteddocs'), __('Download Error', 'protecteddocs'), ['response' => 403]);
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            wp_die(__('File not found', 'protecteddocs'), __('Download Error', 'protecteddocs'), ['response' => 404]);
        }

        $file_path = $file_data['path'] ?? null;
        if (!$file_path || !file_exists($file_path)) {
            wp_die(__('File not found on server', 'protecteddocs'), __('Download Error', 'protecteddocs'), ['response' => 404]);
        }

        // Log download
        Logs::log_download($document_id, get_current_user_id());

        // Serve file
        $this->serve_file($file_path, $file_data['filename']);
    }

    /**
     * Serve file for download
     */
    private function serve_file(string $file_path, string $file_name): void
    {
        $file_size = filesize($file_path);
        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Output file
        readfile($file_path);
        exit;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_script(
            'protecteddocs-frontend-js',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/js/protecteddocs-frontend.js',
            ['jquery'],
            PROTECTEDDOCS_VERSION,
            true
        );

        wp_enqueue_style(
            'protecteddocs-frontend-css',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/css/protecteddocs-frontend.css',
            [],
            PROTECTEDDOCS_VERSION
        );

        // Localize script
        wp_localize_script('protecteddocs-frontend-js', 'protecteddocs_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('protecteddocs_frontend_nonce'),
            'loading_label' => __('Loading...', 'protecteddocs'),
            'load_more_label' => __('Load More Documents', 'protecteddocs'),
            'submitting_label' => __('Submitting...', 'protecteddocs'),
            'error_message' => __('An error occurred. Please try again.', 'protecteddocs'),
            'success_message' => __('Request submitted successfully!', 'protecteddocs')
        ]);
    }

    /**
     * Enqueue dynamic styles for color palette
     */
    private function enqueue_dynamic_styles(string $instance_id, array $color_palette): void
    {
        $css = "
        #{$instance_id} .authdocs-request-access-btn,
        #{$instance_id} .authdocs-download-btn,
        #{$instance_id} .authdocs-load-more-btn,
        #{$instance_id} .authdocs-pagination-btn {
            background: {$color_palette['background']} !important;
            color: {$color_palette['text']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        #{$instance_id} .authdocs-request-access-btn:hover,
        #{$instance_id} .authdocs-download-btn:hover,
        #{$instance_id} .authdocs-load-more-btn:hover,
        #{$instance_id} .authdocs-pagination-btn:hover {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border-color: {$color_palette['primary']} !important;
        }
        
        #{$instance_id} .authdocs-pagination-btn.active {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border-color: {$color_palette['primary']} !important;
        }
        
        #{$instance_id} .authdocs-card-title {
            color: {$color_palette['text']} !important;
        }
        
        #{$instance_id} .authdocs-card-date {
            color: {$color_palette['text_secondary']} !important;
        }
        ";

        wp_add_inline_style('protecteddocs-frontend-css', $css);
    }
}
