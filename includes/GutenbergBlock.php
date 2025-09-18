<?php
declare(strict_types=1);

namespace ProtectedDocs;

class GutenbergBlock
{
    public function __construct()
    {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('init', [$this, 'migrate_old_blocks'], 20); // Run after block registration
    }

    public function register_block(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('protecteddocs/document-grid', [
            'attributes' => [
                'columns' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'limit' => [
                    'type' => 'number',
                    'default' => 12,
                ],
                'loadMoreLimit' => [
                    'type' => 'number',
                    'default' => 12,
                ],
                'paginationType' => [
                    'type' => 'string',
                    'default' => 'classic',
                ],
                'featuredImage' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'paginationStyle' => [
                    'type' => 'string',
                    'default' => 'classic',
                ],
                'restriction' => [
                    'type' => 'string',
                    'default' => 'all',
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showDate' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'date',
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                ],
                'colorPalette' => [
                    'type' => 'string',
                    'default' => 'default',
                ],
                'columnsDesktop' => [
                    'type' => 'number',
                    'default' => 5,
                ],
                'columnsTablet' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'columnsMobile' => [
                    'type' => 'number',
                    'default' => 1,
                ],
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'protecteddocs-gutenberg-block',
            'editor_style' => 'protecteddocs-gutenberg-block-editor',
        ]);
        
        // Register the old block name as an alias for backward compatibility
        register_block_type('authdocs/document-grid', [
            'attributes' => [
                'columns' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'limit' => [
                    'type' => 'number',
                    'default' => 12,
                ],
                'loadMoreLimit' => [
                    'type' => 'number',
                    'default' => 12,
                ],
                'paginationType' => [
                    'type' => 'string',
                    'default' => 'classic',
                ],
                'featuredImage' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'paginationStyle' => [
                    'type' => 'string',
                    'default' => 'classic',
                ],
                'restriction' => [
                    'type' => 'string',
                    'default' => 'all',
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'date',
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                ],
                'columnsDesktop' => [
                    'type' => 'number',
                    'default' => 5,
                ],
                'columnsTablet' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'columnsMobile' => [
                    'type' => 'number',
                    'default' => 1,
                ],
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'protecteddocs-gutenberg-block',
            'editor_style' => 'protecteddocs-gutenberg-block-editor',
        ]);
    }

    public function enqueue_block_editor_assets(): void
    {
        wp_enqueue_script(
            'protecteddocs-gutenberg-block',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/js/gutenberg-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            PROTECTEDDOCS_VERSION,
            true
        );

        wp_enqueue_style(
            'protecteddocs-gutenberg-block-editor',
            PROTECTEDDOCS_PLUGIN_URL . 'assets/css/gutenberg-block-editor.css',
            ['wp-edit-blocks'],
            PROTECTEDDOCS_VERSION
        );

        // Localize script with translations
        wp_localize_script('protecteddocs-gutenberg-block', 'protecteddocs_block', [
            'title' => __('ProtectedDocs Document Grid', 'protecteddocs'),
            'description' => __('Display a grid of documents with customizable settings', 'protecteddocs'),
            'category' => __('ProtectedDocs', 'protecteddocs'),
            'icon' => 'grid-view',
        ]);
    }

    public function render_block(array $attributes): string
    {
        // Get simple values from attributes
        $columns = $attributes['columns'] ?? 3;
        $limit = $attributes['limit'] ?? 12;
        $load_more_limit = $attributes['loadMoreLimit'] ?? 12;

        // Convert block attributes to shortcode format
        $shortcode_atts = [
            'columns' => $columns,
            'columns_desktop' => $attributes['columnsDesktop'] ?? 5,
            'columns_tablet' => $attributes['columnsTablet'] ?? 3,
            'columns_mobile' => $attributes['columnsMobile'] ?? 1,
            'limit' => $limit,
            'load_more_limit' => $load_more_limit,
            'pagination_style' => $attributes['paginationStyle'] ?? 'classic',
            'pagination_type' => $attributes['paginationType'] ?? 'classic',
            'featured_image' => ($attributes['featuredImage'] ?? true) ? 'yes' : 'no',
            'pagination' => $attributes['paginationStyle'] === 'none' ? 'no' : 'yes',
            'restriction' => $attributes['restriction'] ?? 'all',
            'show_description' => ($attributes['showDescription'] ?? true) ? 'yes' : 'no',
            'show_date' => ($attributes['showDate'] ?? true) ? 'yes' : 'no',
            'orderby' => $attributes['orderby'] ?? 'date',
            'order' => $attributes['order'] ?? 'DESC',
            'color_palette' => $attributes['colorPalette'] ?? 'default',
        ];

        // Handle pagination style mapping
        if ($attributes['paginationStyle'] === 'load_more') {
            $shortcode_atts['pagination'] = 'yes';
            $shortcode_atts['pagination_style'] = 'load_more';
            $shortcode_atts['pagination_type'] = 'ajax';
        } elseif ($attributes['paginationStyle'] === 'classic') {
            $shortcode_atts['pagination'] = 'yes';
            $pagination_type = $attributes['paginationType'] ?? 'classic';
            
            // Keep classic style but set the correct pagination type
            $shortcode_atts['pagination_style'] = 'classic';
            $shortcode_atts['pagination_type'] = $pagination_type;
        } elseif ($attributes['paginationStyle'] === 'none') {
            $shortcode_atts['pagination'] = 'no';
            $shortcode_atts['pagination_style'] = 'classic';
            $shortcode_atts['pagination_type'] = 'ajax'; // Default for no pagination
        }

        // Build shortcode string
        $shortcode_string = '[authdocs_grid';
        foreach ($shortcode_atts as $key => $value) {
            $shortcode_string .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode_string .= ']';

        // For block rendering, return a placeholder to avoid CSS output issues
        // The shortcode will be executed on the frontend
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST') || 
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false)) {
            return '<div class="authdocs-block-placeholder" data-shortcode="' . esc_attr($shortcode_string) . '">' . 
                   __('ProtectedDocs Document Grid - Content will be loaded on the frontend', 'protecteddocs') . 
                   '</div>';
        }
        
        // Execute shortcode only on frontend
        return do_shortcode($shortcode_string);
    }
    
    /**
     * Migrate old blocks from authdocs/document-grid to protecteddocs/document-grid
     */
    public function migrate_old_blocks(): void
    {
        // Only run migration once
        if (get_option('protecteddocs_blocks_migrated', false)) {
            return;
        }
        
        global $wpdb;
        
        // Find all posts that contain the old block
        $posts_with_old_blocks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} 
                 WHERE post_content LIKE %s 
                 AND post_type IN ('post', 'page')",
                '%authdocs/document-grid%'
            )
        );
        
        if (!empty($posts_with_old_blocks)) {
            foreach ($posts_with_old_blocks as $post) {
                // Replace old block name with new block name
                $new_content = str_replace(
                    'authdocs/document-grid',
                    'protecteddocs/document-grid',
                    $post->post_content
                );
                
                // Update the post content
                $wpdb->update(
                    $wpdb->posts,
                    ['post_content' => $new_content],
                    ['ID' => $post->ID],
                    ['%s'],
                    ['%d']
                );
            }
        }
        
        // Mark migration as completed
        update_option('protecteddocs_blocks_migrated', true);
    }

}
