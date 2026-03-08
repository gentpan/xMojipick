<?php
/**
 * xMojipick Uninstall
 *
 * Cleans up all plugin data when the plugin is deleted via the WordPress admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('xmojipick_disabled_packs');

// Remove transient cache
delete_transient('xmojipick_packs');
