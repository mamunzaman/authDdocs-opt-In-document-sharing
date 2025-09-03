<?php
declare(strict_types=1);

/**
 * Plugin Name: AuthDocs – Opt-In Document Sharing
 * Plugin URI: https://example.com/authdocs
 * Description: A secure WordPress plugin for sharing documents with opt-in authentication
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: authdocs
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUTHDOCS_VERSION', '1.0.0');
define('AUTHDOCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTHDOCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTHDOCS_PLUGIN_FILE', __FILE__);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AuthDocs\\';
    $base_dir = AUTHDOCS_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
add_action('plugins_loaded', function () {
    if (class_exists('AuthDocs\\Plugin')) {
        new AuthDocs\Plugin();
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    AuthDocs\Database::create_tables();
    AuthDocs\Database::create_document_post_type();
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
