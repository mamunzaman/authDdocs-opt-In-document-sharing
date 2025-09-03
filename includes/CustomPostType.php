<?php
declare(strict_types=1);

namespace AuthDocs;

class CustomPostType
{
    public function __construct()
    {
        add_action('init', [$this, 'register_document_post_type']);
        add_action('add_meta_boxes', [$this, 'add_document_meta_boxes']);
        add_action('save_post', [$this, 'save_document_meta']);
        add_action('admin_notices', [$this, 'show_shortcode_notice']);
    }

    public function register_document_post_type(): void
    {
        $labels = [
            'name' => __('Documents', 'authdocs'),
            'singular_name' => __('Document', 'authdocs'),
            'menu_name' => __('Documents', 'authdocs'),
            'add_new' => __('Add New Document', 'authdocs'),
            'add_new_item' => __('Add New Document', 'authdocs'),
            'edit_item' => __('Edit Document', 'authdocs'),
            'new_item' => __('New Document', 'authdocs'),
            'view_item' => __('View Document', 'authdocs'),
            'search_items' => __('Search Documents', 'authdocs'),
            'not_found' => __('No documents found', 'authdocs'),
            'not_found_in_trash' => __('No documents found in trash', 'authdocs'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor'],
            'show_in_rest' => false,
        ];

        register_post_type('document', $args);
    }

    public function add_document_meta_boxes(): void
    {
        add_meta_box(
            'authdocs_document_settings',
            __('Document Settings', 'authdocs'),
            [$this, 'document_settings_callback'],
            'document',
            'normal',
            'high'
        );

        add_meta_box(
            'authdocs_shortcode_display',
            __('Shortcode', 'authdocs'),
            [$this, 'shortcode_display_callback'],
            'document',
            'side',
            'high'
        );
    }

    public function document_settings_callback($post): void
    {
        wp_nonce_field('authdocs_document_meta', 'authdocs_document_meta_nonce');
        
        $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
        $file_id = get_post_meta($post->ID, '_authdocs_file_id', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="authdocs_file"><?php _e('Document File', 'authdocs'); ?></label>
                </th>
                <td>
                    <input type="hidden" id="authdocs_file_id" name="authdocs_file_id" value="<?php echo esc_attr($file_id); ?>" />
                    <button type="button" class="button" id="authdocs_upload_button">
                        <?php _e('Select Document', 'authdocs'); ?>
                    </button>
                    <div id="authdocs_file_preview">
                        <?php if ($file_id): ?>
                            <?php echo wp_get_attachment_link($file_id, 'thumbnail', false, true, false); ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="authdocs_restricted"><?php _e('Require Opt-in', 'authdocs'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="authdocs_restricted" name="authdocs_restricted" value="yes" <?php checked($restricted, 'yes'); ?> />
                        <?php _e('Require opt-in form before access', 'authdocs'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            var file_frame;
            
            $('#authdocs_upload_button').on('click', function(e) {
                e.preventDefault();
                
                if (file_frame) {
                    file_frame.open();
                    return;
                }
                
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: '<?php _e('Select Document', 'authdocs'); ?>',
                    button: {
                        text: '<?php _e('Use this document', 'authdocs'); ?>',
                    },
                    multiple: false,
                    library: {
                        type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                    }
                });
                
                file_frame.on('select', function() {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    $('#authdocs_file_id').val(attachment.id);
                    $('#authdocs_file_preview').html('<p><a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a></p>');
                });
                
                file_frame.open();
            });
        });
        </script>
        <?php
    }

    public function shortcode_display_callback($post): void
    {
        if ($post->post_status === 'publish') {
            $restricted = get_post_meta($post->ID, '_authdocs_restricted', true);
            $restricted_param = $restricted === 'yes' ? 'yes' : 'no';
            $shortcode = sprintf('[authdocs id="%d" restricted="%s"]', $post->ID, $restricted_param);
            ?>
            <p><strong><?php _e('Shortcode:', 'authdocs'); ?></strong></p>
            <input type="text" value="<?php echo esc_attr($shortcode); ?>" readonly style="width: 100%;" onclick="this.select();" />
            <p class="description"><?php _e('Copy this shortcode to display the document on your pages.', 'authdocs'); ?></p>
            <?php
        } else {
            ?>
            <p><?php _e('Publish the document to generate the shortcode.', 'authdocs'); ?></p>
            <?php
        }
    }

    public function save_document_meta($post_id): void
    {
        if (!isset($_POST['authdocs_document_meta_nonce']) || 
            !wp_verify_nonce($_POST['authdocs_document_meta_nonce'], 'authdocs_document_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['authdocs_file_id'])) {
            update_post_meta($post_id, '_authdocs_file_id', intval($_POST['authdocs_file_id']));
        }

        if (isset($_POST['authdocs_restricted'])) {
            update_post_meta($post_id, '_authdocs_restricted', 'yes');
        } else {
            update_post_meta($post_id, '_authdocs_restricted', 'no');
        }
    }

    public function show_shortcode_notice(): void
    {
        global $post_type;
        
        if ($post_type === 'document' && isset($_GET['post']) && get_post_status($_GET['post']) === 'publish') {
            $restricted = get_post_meta($_GET['post'], '_authdocs_restricted', true);
            $restricted_param = $restricted === 'yes' ? 'yes' : 'no';
            $shortcode = sprintf('[authdocs id="%d" restricted="%s"]', $_GET['post'], $restricted_param);
            ?>
            <div class="notice notice-info">
                <p><strong><?php _e('Document Shortcode:', 'authdocs'); ?></strong> <code><?php echo esc_html($shortcode); ?></code></p>
            </div>
            <?php
        }
    }
}
