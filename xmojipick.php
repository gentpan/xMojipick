<?php
/**
 * Plugin Name: xMojipick
 * Plugin URI:  https://github.com/gentpan/xMojipick
 * Description: 多表情包表情插件，支持 SVG/PNG/GIF/AVIF/WebP，评论区/后台/邮件渲染
 * Version:     1.0.1
 * Author:      xMojipick
 * License:     GPL-2.0-or-later
 * Text Domain: xmojipick
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XMOJIPICK_VERSION', '1.0.1');
define('XMOJIPICK_DIR', plugin_dir_path(__FILE__));
define('XMOJIPICK_URL', plugin_dir_url(__FILE__));

require_once XMOJIPICK_DIR . 'includes/class-loader.php';
require_once XMOJIPICK_DIR . 'includes/class-comment.php';
require_once XMOJIPICK_DIR . 'includes/class-admin.php';

add_filter('kses_allowed_protocols', function ($protocols) {
    if (!in_array('data', $protocols, true)) {
        $protocols[] = 'data';
    }
    return $protocols;
});

add_action('plugins_loaded', function () {
    load_plugin_textdomain('xmojipick', false, dirname(plugin_basename(__FILE__)) . '/languages');

    $loader = new xMojipick_Loader();
    new xMojipick_Comment($loader);

    add_filter('get_comment_text', [$loader, 'replace'], 5);
    add_filter('comment_text', [$loader, 'replace'], 5);
    add_filter('get_comment_excerpt', [$loader, 'replace'], 5);
    add_filter('comment_excerpt', [$loader, 'replace'], 5);
    add_filter('wp_mail', [$loader, 'replace_email']);

    add_filter('wp_lazy_loading_enabled', function ($default, $tag_name, $context) {
        if ($tag_name === 'img' && $context === 'comment_text') {
            return false;
        }
        return $default;
    }, 10, 3);

    if (is_admin()) {
        new xMojipick_Admin($loader);
    }
});
