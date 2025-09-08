<?php
declare(strict_types=1);

namespace AuthDocs;

class GutenbergBlock
{
    public function __construct()
    {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
    }

    public function register_block(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('authdocs/document-grid', [
            'attributes' => [
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
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'authdocs-gutenberg-block',
            'editor_style' => 'authdocs-gutenberg-block-editor',
        ]);
    }

    public function enqueue_block_editor_assets(): void
    {
        wp_enqueue_script(
            'authdocs-gutenberg-block',
            AUTHDOCS_PLUGIN_URL . 'assets/js/gutenberg-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            AUTHDOCS_VERSION,
            true
        );

        wp_enqueue_style(
            'authdocs-gutenberg-block-editor',
            AUTHDOCS_PLUGIN_URL . 'assets/css/gutenberg-block-editor.css',
            ['wp-edit-blocks'],
            AUTHDOCS_VERSION
        );

        // Localize script with translations
        wp_localize_script('authdocs-gutenberg-block', 'authdocs_block', [
            'title' => __('AuthDocs Document Grid', 'authdocs'),
            'description' => __('Display a grid of documents with customizable settings', 'authdocs'),
            'category' => __('AuthDocs', 'authdocs'),
            'icon' => 'grid-view',
        ]);
    }

    public function render_block(array $attributes): string
    {
        // Auto-calculate columns based on limit
        $limit = $attributes['limit'] ?? 12;
        $columns = $this->calculate_columns($limit);

        // Convert block attributes to shortcode format
        $shortcode_atts = [
            'columns' => $columns,
            'limit' => $limit,
            'load_more_limit' => $attributes['loadMoreLimit'] ?? 12,
            'pagination_style' => $attributes['paginationStyle'] ?? 'classic',
            'pagination_type' => $attributes['paginationType'] ?? 'classic',
            'featured_image' => ($attributes['featuredImage'] ?? true) ? 'yes' : 'no',
            'pagination' => $attributes['paginationStyle'] === 'none' ? 'no' : 'yes',
            'restriction' => $attributes['restriction'] ?? 'all',
            'show_description' => ($attributes['showDescription'] ?? true) ? 'yes' : 'no',
            'show_date' => ($attributes['showDate'] ?? true) ? 'yes' : 'no',
            'orderby' => $attributes['orderby'] ?? 'date',
            'order' => $attributes['order'] ?? 'DESC',
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

        // Execute shortcode
        return do_shortcode($shortcode_string);
    }

    /**
     * Calculate optimal number of columns based on documents per page
     */
    private function calculate_columns(int $documents_per_page): int
    {
        if ($documents_per_page <= 4) {
            return 2;
        } elseif ($documents_per_page <= 9) {
            return 3;
        } elseif ($documents_per_page <= 16) {
            return 4;
        } elseif ($documents_per_page <= 25) {
            return 5;
        } else {
            return 6;
        }
    }
}
