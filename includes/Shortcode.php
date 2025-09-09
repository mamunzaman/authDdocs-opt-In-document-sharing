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
        add_action('init', [$this, 'handle_secure_file']);
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
                                        <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                                            <svg class="authdocs-lock-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <span><?php _e('Request Access', 'protecteddocs'); ?></span>
                                        </button>
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

        // Display file in browser instead of downloading
        $this->display_file_in_browser($document_id, $file_path, $file_data['filename']);
    }
    
    /**
     * Handle secure file serving for embedding
     */
    public function handle_secure_file(): void
    {
        if (!isset($_GET['authdocs_file']) || !isset($_GET['token'])) {
            return;
        }

        $document_id = intval($_GET['authdocs_file']);
        $token = sanitize_text_field($_GET['token']);

        if (!Tokens::verify_download_token($document_id, $token)) {
            wp_die(__('Invalid file link', 'protecteddocs'), __('Access Error', 'protecteddocs'), ['response' => 403]);
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            wp_die(__('File not found', 'protecteddocs'), __('Access Error', 'protecteddocs'), ['response' => 404]);
        }

        $file_path = $file_data['path'] ?? null;
        if (!$file_path || !file_exists($file_path)) {
            wp_die(__('File not found on server', 'protecteddocs'), __('Access Error', 'protecteddocs'), ['response' => 404]);
        }

        // Serve file for embedding (inline display)
        $this->serve_file_inline($file_path, $file_data['filename']);
    }

    /**
     * Display file in browser instead of downloading
     */
    private function display_file_in_browser(int $document_id, string $file_path, string $file_name): void
    {
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
        
        // Get document information
        $document = get_post($document_id);
        $document_title = $document ? $document->post_title : $file_name;
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for HTML page
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Generate secure file URL for embedding
        $file_url = $this->get_secure_file_url($document_id, $file_path);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($document_title); ?> - <?php bloginfo('name'); ?></title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                }
                .document-header {
                    background: #fff;
                    padding: 15px 20px;
                    border-bottom: 1px solid #ddd;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .document-title {
                    margin: 0;
                    font-size: 18px;
                    color: #333;
                }
                .document-meta {
                    margin: 5px 0 0 0;
                    font-size: 14px;
                    color: #666;
                }
                .document-container {
                    height: calc(100vh - 80px);
                    background: #fff;
                    margin: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .document-viewer {
                    width: 100%;
                    height: 100%;
                    border: none;
                }
                .download-btn {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #0073aa;
                    color: white;
                    padding: 10px 15px;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    z-index: 1000;
                }
                .download-btn:hover {
                    background: #005a87;
                    color: white;
                }
                .error-message {
                    padding: 40px;
                    text-align: center;
                    color: #666;
                }
                .pdf-viewer-container {
                    position: relative;
                    width: 100%;
                    height: 100%;
                }
                .pdf-viewer {
                    border: none;
                    background: #f8f9fa;
                }
                .pdf-viewer-fallback {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 2;
                }
                .office-viewer-container {
                    position: relative;
                    width: 100%;
                    height: 100%;
                }
                .office-viewer {
                    border: none;
                    background: #f8f9fa;
                }
                .office-viewer-loading {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1;
                }
                .office-viewer-fallback {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 2;
                }
                .loading-message {
                    text-align: center;
                    color: #666;
                    font-size: 16px;
                }
                .loading-spinner {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #0073aa;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-right: 10px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="document-header">
                <h1 class="document-title"><?php echo esc_html($document_title); ?></h1>
                <p class="document-meta"><?php echo esc_html($file_name); ?> • <?php echo size_format(filesize($file_path)); ?></p>
            </div>
            
            <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>
                📥 Download
            </a>
            
            <!-- Debug info (remove in production) -->
            <div style="position: fixed; top: 60px; right: 20px; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; z-index: 1001; max-width: 300px;">
                <strong>Debug Info:</strong><br>
                File URL: <?php echo esc_html($file_url); ?><br>
                File Extension: <?php echo esc_html($file_extension); ?><br>
                MIME Type: <?php echo esc_html($mime_type); ?>
            </div>
            
            <div class="document-container">
                <?php if (in_array($file_extension, ['pdf'])): ?>
                    <!-- PDF Viewer -->
                    <div class="pdf-viewer-container">
                        <iframe src="<?php echo esc_url($file_url); ?>" class="document-viewer pdf-viewer" type="application/pdf" sandbox="allow-same-origin allow-scripts allow-forms"></iframe>
                        <div class="pdf-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>PDF Preview</h3>
                                <p>This PDF cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>Download PDF</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['doc', 'docx'])): ?>
                    <!-- Word Document Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                            sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox"
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading document preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Document Preview</h3>
                                <p>This document cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>Download Document</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['ppt', 'pptx'])): ?>
                    <!-- PowerPoint Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                            sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox"
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading presentation preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Presentation Preview</h3>
                                <p>This presentation cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>Download Presentation</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (in_array($file_extension, ['xls', 'xlsx'])): ?>
                    <!-- Excel Viewer -->
                    <div class="office-viewer-container">
                        <iframe 
                            src="https://view.officeapps.live.com/op/embed.aspx?src=<?php echo urlencode($file_url); ?>&wdAr=1.7777777777777777" 
                            class="document-viewer office-viewer" 
                            sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox"
                            allowfullscreen>
                        </iframe>
                        <div class="office-viewer-loading">
                            <div class="loading-message">
                                <div class="loading-spinner"></div>
                                Loading spreadsheet preview...
                            </div>
                        </div>
                        <div class="office-viewer-fallback" style="display: none;">
                            <div class="error-message">
                                <h3>Spreadsheet Preview</h3>
                                <p>This spreadsheet cannot be previewed in the browser.</p>
                                <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>Download Spreadsheet</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Fallback for other file types -->
                    <div class="error-message">
                        <h3>Preview not available</h3>
                        <p>This file type cannot be previewed in the browser.</p>
                        <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download>Download File</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
                // Handle iframe loading errors and provide fallback
                document.addEventListener('DOMContentLoaded', function() {
                    // Handle PDF viewers
                    const pdfViewers = document.querySelectorAll('.pdf-viewer');
                    pdfViewers.forEach(function(iframe) {
                        const container = iframe.closest('.pdf-viewer-container');
                        const fallback = container.querySelector('.pdf-viewer-fallback');
                        
                        // Set a timeout to show fallback if iframe doesn't load
                        const timeout = setTimeout(function() {
                            if (fallback) {
                                fallback.style.display = 'flex';
                                iframe.style.display = 'none';
                            }
                        }, 10000); // 10 seconds timeout
                        
                        // Handle iframe load success
                        iframe.addEventListener('load', function() {
                            clearTimeout(timeout);
                        });
                        
                        // Handle iframe load error
                        iframe.addEventListener('error', function() {
                            clearTimeout(timeout);
                            if (fallback) {
                                fallback.style.display = 'flex';
                                iframe.style.display = 'none';
                            }
                        });
                    });
                    
                    // Handle Office viewers
                    const officeViewers = document.querySelectorAll('.office-viewer');
                    
                    officeViewers.forEach(function(iframe) {
                        const container = iframe.closest('.office-viewer-container');
                        const loading = container.querySelector('.office-viewer-loading');
                        const fallback = container.querySelector('.office-viewer-fallback');
                        
                        // Set a timeout to show fallback if iframe doesn't load
                        const timeout = setTimeout(function() {
                            if (loading) loading.style.display = 'none';
                            if (fallback) {
                                fallback.style.display = 'flex';
                                iframe.style.display = 'none';
                            }
                        }, 15000); // 15 seconds timeout
                        
                        // Handle iframe load success
                        iframe.addEventListener('load', function() {
                            clearTimeout(timeout);
                            // Hide loading indicator
                            if (loading) loading.style.display = 'none';
                            
                            // Check if iframe actually loaded content
                            try {
                                if (iframe.contentDocument && iframe.contentDocument.body) {
                                    // Iframe loaded successfully
                                    if (fallback) {
                                        fallback.style.display = 'none';
                                    }
                                }
                            } catch (e) {
                                // Cross-origin error, but iframe might still be working
                                console.log('Iframe loaded (cross-origin)');
                            }
                        });
                        
                        // Handle iframe load error
                        iframe.addEventListener('error', function() {
                            clearTimeout(timeout);
                            if (loading) loading.style.display = 'none';
                            if (fallback) {
                                fallback.style.display = 'flex';
                                iframe.style.display = 'none';
                            }
                        });
                    });
                    
                    // Suppress console errors from Office Online viewer
                    window.addEventListener('error', function(e) {
                        if (e.message && e.message.includes('web-client-content-script')) {
                            e.preventDefault();
                            return false;
                        }
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Get secure file URL for embedding
     */
    private function get_secure_file_url(int $document_id, string $file_path): string
    {
        $token = Tokens::generate_download_token($document_id);
        return add_query_arg([
            'authdocs_file' => $document_id,
            'token' => $token
        ], home_url('/'));
    }
    
    /**
     * Serve file inline for embedding
     */
    private function serve_file_inline(string $file_path, string $file_name): void
    {
        $file_size = filesize($file_path);
        $mime_type = wp_check_filetype($file_path)['type'] ?: 'application/octet-stream';
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for inline display
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        
        // Add CORS headers for iframe embedding
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // For PDFs, ensure proper MIME type
        if ($file_extension === 'pdf') {
            header('Content-Type: application/pdf');
        }

        // Output file
        readfile($file_path);
        exit;
    }
    
    /**
     * Serve file for download (kept for download button)
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
