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
                    <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document_id); ?>" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                        <svg class="authdocs-lock-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <a href="<?php echo esc_url($file_data['url']); ?>" class="authdocs-download-btn" download>
                        <?php _e('Download Document', 'protecteddocs'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render grid view shortcode with pagination
     * 
     * Shortcode parameters:
     * - limit: Total number of documents to display (default: 12)
     * - restriction: Filter by restriction status - 'all', 'restricted', 'unrestricted' (default: 'all')
     * - columns: Number of columns in grid layout 1-6 (default: 3)
     * - show_description: Show document descriptions - 'yes', 'no' (default: 'yes')
     * - show_date: Show document dates - 'yes', 'no' (default: 'yes')
     * - orderby: Sort by - 'date', 'title' (default: 'date')
     * - order: Sort order - 'ASC', 'DESC' (default: 'DESC')
     * - pagination: Enable pagination - 'yes', 'no' (default: 'yes')
     * - load_more_limit: Number of items to load each time Load More is clicked (default: 12)
     * 
     * Usage examples:
     * [authdocs_grid limit="20" columns="4"]
     * [authdocs_grid limit="20" columns="4" load_more_limit="8"]
     * [authdocs_grid restriction="restricted" load_more_limit="6"]
     * [authdocs_grid featured_image="no" columns="2"]
     */
    public function render_grid_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'limit' => 12,
            'restriction' => 'all', // all, restricted, unrestricted
            'columns' => 3,
            'columns_desktop' => 5, // Desktop columns (1280px+)
            'columns_tablet' => 3, // Tablet columns (768px-1279px)
            'columns_mobile' => 1, // Mobile columns (below 768px)
            'show_description' => 'yes',
            'show_date' => 'yes',
            'orderby' => 'date', // date, title
            'order' => 'DESC',
            'pagination' => 'yes', // yes, no
            'pagination_style' => 'classic', // classic, load_more
            'pagination_type' => 'ajax', // ajax, classic
            'load_more_limit' => 12, // Number of additional items to load each time Load More is clicked
            'featured_image' => 'yes', // yes, no - whether to show featured image as card background
            'color_palette' => 'default' // default, black_white_blue, black_gray
        ], $atts, 'authdocs_grid');
        
        $limit = intval($atts['limit']);
        $restriction = sanitize_text_field($atts['restriction']);
        $columns = intval($atts['columns']);
        $columns_desktop = intval($atts['columns_desktop']);
        $columns_tablet = intval($atts['columns_tablet']);
        $columns_mobile = intval($atts['columns_mobile']);
        $show_description = $atts['show_description'] === 'yes';
        $show_date = $atts['show_date'] === 'yes';
        $orderby = sanitize_text_field($atts['orderby']);
        $order = sanitize_text_field($atts['order']);
        $pagination = $atts['pagination'] === 'yes';
        $pagination_style = sanitize_text_field($atts['pagination_style']);
        $pagination_type = sanitize_text_field($atts['pagination_type']);
        $load_more_limit = intval($atts['load_more_limit']);
        $show_featured_image = $atts['featured_image'] === 'yes';
        $color_palette = sanitize_text_field($atts['color_palette']);
        $instance_id = 'authdocs-grid-' . uniqid();
        
        // Validate inputs
        if ($limit < 1) $limit = 12;
        if ($columns < 1 || $columns > 6) $columns = 3;
        if ($columns_desktop < 1 || $columns_desktop > 6) $columns_desktop = 5;
        if ($columns_tablet < 1 || $columns_tablet > 4) $columns_tablet = 3;
        if ($columns_mobile < 1 || $columns_mobile > 2) $columns_mobile = 1;
        if (!in_array($restriction, ['all', 'restricted', 'unrestricted'])) $restriction = 'all';
        if (!in_array($orderby, ['date', 'title'])) $orderby = 'date';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';
        if ($load_more_limit < 1) $load_more_limit = 12;
        if (!in_array($color_palette, ['default', 'black_white_blue', 'black_gray'])) $color_palette = 'default';
        
        // Get current page from URL parameter
        $current_page = isset($_GET['authdocs_page']) ? max(1, intval($_GET['authdocs_page'])) : 1;
        
        $documents = Database::get_published_documents($limit, $restriction, $current_page, $orderby, $order);
        $total_documents = Database::get_published_documents_count($restriction);
        $total_pages = ceil($total_documents / $limit);
        
        if (empty($documents)) {
            return '<p class="authdocs-no-documents">' . __('No documents found.', 'protecteddocs') . '</p>';
        }
        
        // Get color palette settings
        $settings = new Settings();
        
        // Store the original palette key for JavaScript
        $color_palette_key = $color_palette;
        
        // Use custom color palette if specified, otherwise use frontend settings
        if ($color_palette !== 'default') {
            $color_palette_data = $settings->get_color_palette_data($color_palette);
        } else {
            $color_palette_data = $settings->get_color_palette_data();
        }
        
        
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
            
            <div class="authdocs-grid" data-columns="<?php echo esc_attr($columns); ?>">
                <?php foreach ($documents as $document): 
                    // Get featured image for background (only if enabled)
                    $featured_image = '';
                    $card_style = '';
                    if ($show_featured_image) {
                        $featured_image = get_the_post_thumbnail_url($document['id'], 'large');
                        if ($featured_image) {
                            $card_style = 'style="background-image: url(' . esc_url($featured_image) . '); background-size: cover; background-position: center; background-repeat: no-repeat;"';
                        }
                    }
                ?>
                    <!-- Card -->
                    <article class="card" <?php echo $card_style; ?>>
                        <div class="card-body">
                            <?php if (!$featured_image): ?>
                                <div class="card-icon" aria-hidden="true">
                                    <?php 
                                    $file_type = $document['file_data']['type'] ?? 'file';
                                    echo Database::get_file_type_icon_svg($file_type);
                                    ?>
                                </div>
                            <?php endif; ?>
                            <h3 class="card-title"><?php echo esc_html($document['title']); ?></h3>
                            <?php if ($show_description && !empty($document['description'])): ?>
                                <p class="card-desc"><?php echo wp_kses_post(wp_trim_words($document['description'], 15)); ?></p>
                            <?php endif; ?>
                            <?php if ($show_date): ?>
                                <div class="card-date"><?php echo esc_html($document['date']); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Overlay -->
                        <div class="card-overlay" aria-hidden="true">
                            <div class="overlay-content">
                                <?php if ($document['restricted']): ?>
                                    <button type="button" class="authdocs-request-access-btn" data-document-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Request Access', 'protecteddocs'); ?>">
                                        <svg class="authdocs-lock-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                        </svg>
                                    </button>
                                <?php else: 
                                    $file_type = $document['file_data']['type'] ?? 'file';
                                    $link_behavior = Database::get_file_link_behavior($file_type);
                                    $link_attributes = 'target="' . esc_attr($link_behavior['target']) . '" title="' . esc_attr($link_behavior['title']) . '"';
                                    if ($link_behavior['download']) {
                                        $link_attributes .= ' download';
                                    }
                                ?>
                                    <a href="<?php echo esc_url($document['file_data']['url']); ?>" class="authdocs-download-btn" <?php echo $link_attributes; ?>>
                                        <svg class="authdocs-open-icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <?php if ($pagination && $total_pages > 1): ?>
                <?php 
                // Use shortcode parameters instead of global settings
                // $settings = new Settings();
                // $pagination_style = $settings->get_pagination_style();
                // $pagination_type = $settings->get_pagination_type();
                ?>
                <?php if ($pagination_style === 'load_more'): ?>
                    <!-- Load More Pagination -->
                    <div class="authdocs-grid-load-more">
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
                                // Build the base URL for pagination links
                                $current_url = add_query_arg([
                                    'authdocs_page' => false,
                                    'paged' => false
                                ]);
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
     * Enqueue frontend assets for grid functionality
     */
    public function enqueue_frontend_assets(): void
    {
        // Check if any AuthDocs shortcodes are present
        $content = get_the_content();
        $has_shortcodes = has_shortcode($content, 'protecteddocs') || has_shortcode($content, 'authdocs_grid');
        
        if ($has_shortcodes) {
            // Always enqueue CSS for shortcodes
            wp_enqueue_style('protecteddocs-frontend-css', plugin_dir_url(__FILE__) . '../assets/css/protecteddocs-frontend.css', [], '1.0.0');
            
            // Enqueue JavaScript for grid functionality
            if (has_shortcode($content, 'authdocs_grid')) {
                wp_enqueue_script('protecteddocs-frontend-js', plugin_dir_url(__FILE__) . '../assets/js/protecteddocs-frontend.js', ['jquery'], '1.0.0', true);
                wp_localize_script('protecteddocs-frontend-js', 'protecteddocs_frontend', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('protecteddocs_frontend_nonce')
                ]);
            }
        }
    }
    
    /**
     * Enqueue dynamic styles based on color palette
     */
    private function enqueue_dynamic_styles(string $instance_id, array $color_palette): void
    {
        $css = $this->generate_dynamic_css($instance_id, $color_palette);
        
        // Check if this is an AJAX request, REST API request, admin context, or block rendering - if so, don't output CSS
        if (wp_doing_ajax() || is_admin() || defined('REST_REQUEST') || 
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) ||
            (isset($_SERVER['HTTP_X_WP_NONCE']) && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'], 'wp_rest'))) {
            // For AJAX/REST/block rendering requests, we'll handle CSS differently
            // The CSS will be applied via the frontend JavaScript
            return;
        }
        
        // Output CSS directly in HTML for maximum specificity (only for frontend non-AJAX requests)
        echo '<style type="text/css" id="authdocs-dynamic-' . esc_attr($instance_id) . '">' . $css . '</style>';
    }
    
    /**
     * Generate dynamic CSS based on color palette
     */
    private function generate_dynamic_css(string $instance_id, array $color_palette): string
    {
        $css = "
        #{$instance_id} .authdocs-document {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
        }
        
        #{$instance_id} .authdocs-document-title {
            color: {$color_palette['text']} !important;
        }
        
        #{$instance_id} .authdocs-document-description {
            color: {$color_palette['text_secondary']} !important;
        }
        
        #{$instance_id} .authdocs-request-access-btn,
        #{$instance_id} .authdocs-download-btn {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        #{$instance_id} .authdocs-request-access-btn {
            min-width: 48px;
            padding: 12px;
        }
        
        #{$instance_id} .authdocs-lock-icon {
            width: 16px;
            height: 16px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }
        
        #{$instance_id} .authdocs-request-access-btn:hover .authdocs-lock-icon {
            transform: scale(1.1);
        }
        
        #{$instance_id} .authdocs-request-access-btn:hover,
        #{$instance_id} .authdocs-download-btn:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
        }
        
        /* Card styling with color palette */
        #{$instance_id} .card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
        }
        
        #{$instance_id} .card-title {
            color: {$color_palette['text']} !important;
        }
        
        #{$instance_id} .card-desc {
            color: {$color_palette['text_secondary']} !important;
        }
        
        #{$instance_id} .card-date {
            color: {$color_palette['text_secondary']} !important;
        }
        
        /* Pagination styling with color palette */
        #{$instance_id} .authdocs-pagination-btn {
            background: {$color_palette['background']} !important;
            color: {$color_palette['text']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
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
        
        #{$instance_id} .authdocs-load-more-btn {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        #{$instance_id} .authdocs-load-more-btn:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        /* Lock icon and button styling */
        #{$instance_id} .authdocs-request-access-btn {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        #{$instance_id} .authdocs-request-access-btn:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        #{$instance_id} .authdocs-lock-icon {
            color: {$color_palette['secondary']} !important;
        }
        
        #{$instance_id} .card:hover .authdocs-request-access-btn .authdocs-lock-icon {
            color: {$color_palette['background']} !important;
        }
        
        #{$instance_id} .card:hover .card-overlay {
            background: {$color_palette['primary']}20 !important;
            backdrop-filter: blur(2px);
        }
        
        /* Download button styling */
        #{$instance_id} .authdocs-download-btn {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        #{$instance_id} .authdocs-download-btn:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        #{$instance_id} .authdocs-open-icon {
            color: {$color_palette['secondary']} !important;
        }
        
        #{$instance_id} .authdocs-download-btn:hover .authdocs-open-icon {
            color: {$color_palette['background']} !important;
        }
        
        #{$instance_id} .card:hover .card-title {
            filter: blur(1px);
            transition: filter 0.3s ease;
        }
        
        #{$instance_id} .card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        #{$instance_id} .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        #{$instance_id} .card-body {
            padding: 20px;
            position: relative;
            z-index: 2;
        }
        
        #{$instance_id} .card[style*='background-image'] .card-body {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(2px);
            border-radius: {$color_palette['border_radius']};
        }
        
        #{$instance_id} .card[style*='background-image'] .card-title {
            color: {$color_palette['text']} !important;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        #{$instance_id} .card[style*='background-image'] .card-desc {
            color: {$color_palette['text_secondary']} !important;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        #{$instance_id} .card[style*='background-image'] .card-date {
            color: {$color_palette['text_secondary']} !important;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }
        
        #{$instance_id} .card-icon {
            font-size: 24px;
            display: block;
            margin-bottom: 12px;
        }
        
        #{$instance_id} .card-title {
            color: {$color_palette['text']} !important;
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
        }
        
        #{$instance_id} .card-desc {
            color: {$color_palette['text_secondary']} !important;
            margin: 0 0 8px 0;
            font-size: 14px;
            line-height: 1.4;
        }
        
        #{$instance_id} .card-date {
            color: {$color_palette['text_secondary']} !important;
            font-size: 12px;
            margin-top: 8px;
        }
        
        #{$instance_id} .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 3;
        }
        
        #{$instance_id} .card:hover .card-overlay {
            opacity: 1;
        }
        
        #{$instance_id} .overlay-content {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #{$instance_id} .card-overlay .authdocs-request-access-btn,
        #{$instance_id} .card-overlay .authdocs-download-btn {
            background: transparent;
            border: 2px solid {$color_palette['secondary']};
            color: {$color_palette['secondary']};
            padding: 12px 16px;
            border-radius: {$color_palette['border_radius']};
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        #{$instance_id} .card-overlay .authdocs-request-access-btn:hover,
        #{$instance_id} .card-overlay .authdocs-download-btn:hover {
            background: {$color_palette['secondary']};
            color: {$color_palette['primary']};
            transform: scale(1.05);
        }
        
        #{$instance_id} .authdocs-pagination {
            background: {$color_palette['background_secondary']};
            border-radius: {$color_palette['border_radius']};
        }
        
        #{$instance_id} .authdocs-pagination-info {
            color: {$color_palette['text_secondary']};
        }
        
        #{$instance_id} .authdocs-pagination-btn {
            background: {$color_palette['background']};
            color: {$color_palette['text']};
            border: 1px solid {$color_palette['border']};
            border-radius: {$color_palette['border_radius']};
        }
        
        #{$instance_id} .authdocs-pagination-btn:hover {
            background: {$color_palette['background_secondary']};
            color: {$color_palette['primary']};
        }
        
        #{$instance_id} .authdocs-pagination-btn.active {
            background: {$color_palette['primary']};
            color: {$color_palette['secondary']};
        }
        
        #{$instance_id} .authdocs-load-more-btn {
            background: {$color_palette['primary']};
            color: {$color_palette['secondary']};
            border: 1px solid {$color_palette['primary']};
            border-radius: {$color_palette['border_radius']};
            padding: 12px 24px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        #{$instance_id} .authdocs-load-more-btn:hover {
            background: {$color_palette['text']};
            color: {$color_palette['background']};
        }
        
        #{$instance_id} .authdocs-no-documents {
            background: {$color_palette['background_secondary']};
            color: {$color_palette['text_secondary']};
            border-radius: {$color_palette['border_radius']};
        }
        
        /* Responsive design for mobile devices */
        @media (max-width: 768px) {
            #{$instance_id} .authdocs-grid {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-grid-item {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-grid-item-content {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-grid-item-title {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-grid-item-description {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-grid-item-actions {
                text-align: center;
            }
            
            #{$instance_id} .authdocs-request-access-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-width: auto;
                padding: 12px 16px;
            }
            
            #{$instance_id} .authdocs-pagination {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            
            #{$instance_id} .authdocs-pagination-links {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            #{$instance_id} .authdocs-load-more-btn {
                width: 100%;
                max-width: 200px;
                margin: 0 auto;
            }
        }
        
        @media (max-width: 480px) {
            #{$instance_id} .authdocs-request-access-btn {
                padding: 10px 14px;
                font-size: 0.875rem;
            }
            
            #{$instance_id} .authdocs-pagination-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            
            #{$instance_id} .authdocs-load-more-btn {
                padding: 12px 16px;
                font-size: 0.875rem;
            }
        }
        
        /* Popup styling with color palette */
        .authdocs-modal-card {
            background: {$color_palette['background']} !important;
            border: 1px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
            box-shadow: {$color_palette['shadow']} !important;
        }
        
        .authdocs-modal-header {
            border-bottom: 1px solid {$color_palette['border']} !important;
        }
        
        .authdocs-modal-icon {
            background: {$color_palette['primary']} !important;
        }
        
        .authdocs-modal-title {
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-modal-close {
            background: {$color_palette['background_secondary']} !important;
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-modal-close:hover {
            background: {$color_palette['border']} !important;
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-modal-description {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-form-label {
            color: {$color_palette['text']} !important;
        }
        
        .authdocs-form-label svg {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-form-input {
            background: {$color_palette['background']} !important;
            color: {$color_palette['text']} !important;
            border: 2px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-form-input:focus {
            border-color: {$color_palette['primary']} !important;
            box-shadow: 0 0 0 3px rgba(" . $this->hex_to_rgb($color_palette['primary']) . ", 0.1) !important;
        }
        
        .authdocs-form-input::placeholder {
            color: {$color_palette['text_secondary']} !important;
        }
        
        .authdocs-modal-footer {
            border-top: 1px solid {$color_palette['border']} !important;
        }
        
        .authdocs-btn-primary {
            background: {$color_palette['primary']} !important;
            color: {$color_palette['secondary']} !important;
            border: 1px solid {$color_palette['primary']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-btn-primary:hover {
            background: {$color_palette['text']} !important;
            color: {$color_palette['background']} !important;
            border-color: {$color_palette['text']} !important;
        }
        
        .authdocs-btn-outline {
            background: transparent !important;
            color: {$color_palette['text_secondary']} !important;
            border: 2px solid {$color_palette['border']} !important;
            border-radius: {$color_palette['border_radius']} !important;
        }
        
        .authdocs-btn-outline:hover {
            background: {$color_palette['background_secondary']} !important;
            border-color: {$color_palette['text_secondary']} !important;
            color: {$color_palette['text']} !important;
        }
        ";
        
        return $css;
    }

    /**
     * Convert hex color to RGB values
     */
    private function hex_to_rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "$r, $g, $b";
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

        // Get document information for error pages
        $document = get_post($document_id);

        // Block access if no hash is provided (direct file access attempt)
        if (empty($hash)) {
            // Try to get filename from document file data
            $file_data = Database::get_document_file($document_id);
            $document_info = [
                'title' => $document ? $document->post_title : __('Unknown Document', 'protecteddocs'),
                'filename' => $file_data ? $file_data['filename'] : ''
            ];
            LinkHandler::render_error_page(__('Access denied. Valid authorization required.', 'protecteddocs'), __('Access Denied', 'protecteddocs'), 403, $document_info);
            exit;
        }

        // Validate the secure access first (this will check hash and basic validity)
        error_log("AuthDocs: Download validation - Document ID: {$document_id}, Email: {$email}, Hash: {$hash}, Request ID: {$request_id}");
        if (!Database::validate_secure_access($hash, $email, $document_id, $request_id)) {
            error_log("AuthDocs: Download validation failed - Invalid or expired download link");
            // Try to get filename from document file data
            $file_data = Database::get_document_file($document_id);
            $document_info = [
                'title' => $document ? $document->post_title : __('Unknown Document', 'protecteddocs'),
                'filename' => $file_data ? $file_data['filename'] : ''
            ];
            LinkHandler::render_error_page(__('Invalid or expired download link', 'protecteddocs'), __('Access Denied', 'protecteddocs'), 403, $document_info);
            exit;
        }
        error_log("AuthDocs: Download validation successful");

        // Check if request is accessible (not inactive) after hash validation
        if ($request_id && !Database::is_request_accessible($request_id)) {
            // Log the attempt to access a deactivated file
            error_log("AuthDocs: Attempted access to deactivated file - Request ID: {$request_id}, Document ID: {$document_id}, Email: {$email}");
            // Try to get filename from document file data
            $file_data = Database::get_document_file($document_id);
            $document_info = [
                'title' => $document ? $document->post_title : __('Unknown Document', 'protecteddocs'),
                'filename' => $file_data ? $file_data['filename'] : ''
            ];
            LinkHandler::render_error_page(__('File access has been deactivated. Please contact the administrator.', 'protecteddocs'), __('File Not Available', 'protecteddocs'), 403, $document_info);
            exit;
        }

        // Get the request details to log access
        $request = Database::get_request_by_hash($hash);
        if ($request) {
            // Log the access attempt (optional - for audit trail)
            error_log("AuthDocs: Document access granted for request ID {$request->id}, document ID {$document_id}, email {$email}");
        }

        $file_data = Database::get_document_file($document_id);
        if (!$file_data) {
            $document_info = [
                'title' => $document ? $document->post_title : __('Unknown Document', 'protecteddocs'),
                'filename' => ''
            ];
            LinkHandler::render_error_page(__('File not found', 'protecteddocs'), __('Error', 'protecteddocs'), 404, $document_info);
            exit;
        }

        if (!file_exists($file_data['path'])) {
            $document_info = [
                'title' => $document ? $document->post_title : __('Unknown Document', 'protecteddocs'),
                'filename' => $file_data['filename'] ?? ''
            ];
            LinkHandler::render_error_page(__('File not found on server', 'protecteddocs'), __('Error', 'protecteddocs'), 404, $document_info);
            exit;
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
