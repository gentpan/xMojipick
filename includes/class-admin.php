<?php

if (!defined('ABSPATH')) {
    exit;
}

class xMojipick_Admin
{
    private $loader;

    /**
     * Allowed HTML tags/attributes for SVG output via wp_kses().
     */
    private static function allowed_svg_tags()
    {
        $common = [
            'id'        => true,
            'class'     => true,
            'style'     => true,
            'fill'      => true,
            'stroke'    => true,
            'stroke-width'      => true,
            'stroke-linecap'    => true,
            'stroke-linejoin'   => true,
            'stroke-miterlimit' => true,
            'stroke-dasharray'  => true,
            'stroke-dashoffset' => true,
            'stroke-opacity'    => true,
            'fill-opacity'      => true,
            'fill-rule'         => true,
            'clip-rule'         => true,
            'opacity'           => true,
            'transform'         => true,
            'clip-path'         => true,
            'mask'              => true,
            'filter'            => true,
        ];

        return [
            'svg'  => array_merge($common, [
                'xmlns'       => true,
                'xmlns:xlink' => true,
                'viewbox'     => true,
                'width'       => true,
                'height'      => true,
                'preserveaspectratio' => true,
                'version'     => true,
                'aria-hidden' => true,
                'role'        => true,
                'focusable'   => true,
            ]),
            'g'        => $common,
            'path'     => array_merge($common, ['d' => true]),
            'circle'   => array_merge($common, ['cx' => true, 'cy' => true, 'r' => true]),
            'ellipse'  => array_merge($common, ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true]),
            'rect'     => array_merge($common, ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true]),
            'line'     => array_merge($common, ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true]),
            'polyline' => array_merge($common, ['points' => true]),
            'polygon'  => array_merge($common, ['points' => true]),
            'text'     => array_merge($common, ['x' => true, 'y' => true, 'dx' => true, 'dy' => true, 'text-anchor' => true, 'font-size' => true, 'font-family' => true, 'font-weight' => true]),
            'tspan'    => array_merge($common, ['x' => true, 'y' => true, 'dx' => true, 'dy' => true]),
            'defs'     => $common,
            'use'      => array_merge($common, ['href' => true, 'xlink:href' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true]),
            'symbol'   => array_merge($common, ['viewbox' => true]),
            'clippath' => $common,
            'lineargradient' => array_merge($common, ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientunits' => true, 'gradienttransform' => true]),
            'radialgradient' => array_merge($common, ['cx' => true, 'cy' => true, 'r' => true, 'fx' => true, 'fy' => true, 'gradientunits' => true, 'gradienttransform' => true]),
            'stop'     => array_merge($common, ['offset' => true, 'stop-color' => true, 'stop-opacity' => true]),
            'title'    => [],
            'desc'     => [],
            'pattern'  => array_merge($common, ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'patternunits' => true, 'patterntransform' => true]),
        ];
    }

    public function __construct(xMojipick_Loader $loader)
    {
        $this->loader = $loader;
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_head', [$this, 'output_inline_admin_assets']);
        add_action('wp_ajax_xmojipick_scan_folders', [$this, 'ajax_scan']);
        add_action('wp_ajax_xmojipick_generate_json', [$this, 'ajax_generate']);
        add_filter('pre_update_option_xmojipick_disabled_packs', [$this, 'sanitize_disabled'], 10, 2);
    }

    public function add_menu()
    {
        add_management_page(
            'xMojipick',
            'xMojipick',
            'manage_options',
            'xmojipick',
            [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        register_setting('xmojipick_packs', 'xmojipick_disabled_packs');
    }

    public function sanitize_disabled($value, $old)
    {
        /*
         * The form checkboxes represent ENABLED packs (checked = enabled).
         * We need to invert: disabled = all packs minus the submitted (enabled) ones.
         */
        $all_json = glob(XMOJIPICK_DIR . 'assets/packs/pack-*.json') ?: [];
        $all_ids  = array_map(function ($f) { return basename($f, '.json'); }, $all_json);

        $enabled  = is_array($value) ? array_filter($value, 'is_string') : [];
        $disabled = array_values(array_diff($all_ids, $enabled));

        xMojipick_Loader::clear_cache();
        return $disabled;
    }

    public function enqueue_admin_assets($hook)
    {
        if ($hook === 'tools_page_xmojipick') {
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

            $css_file = 'assets/css/xmojipick-admin' . $suffix . '.css';
            $js_file  = 'assets/js/xmojipick-admin' . $suffix . '.js';

            $css_ver = file_exists(XMOJIPICK_DIR . $css_file)
                ? filemtime(XMOJIPICK_DIR . $css_file)
                : XMOJIPICK_VERSION;
            $js_ver = file_exists(XMOJIPICK_DIR . $js_file)
                ? filemtime(XMOJIPICK_DIR . $js_file)
                : XMOJIPICK_VERSION;

            wp_enqueue_style(
                'xmojipick-admin',
                XMOJIPICK_URL . $css_file,
                [],
                $css_ver
            );
            wp_enqueue_script(
                'xmojipick-admin',
                XMOJIPICK_URL . $js_file,
                [],
                $js_ver,
                true
            );
            wp_localize_script('xmojipick-admin', 'xmojipickAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php', 'relative'),
                'nonce'   => wp_create_nonce('xmojipick_admin'),
            ]);
        }
    }

    public function output_inline_admin_assets()
    {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        if (in_array($screen->id, ['edit-comments', 'comment', 'dashboard'], true)) {
            echo $this->get_comment_inline_styles();
        }
    }

    public function render_page()
    {
        $packs    = $this->loader->get_packs();
        $disabled = (array) get_option('xmojipick_disabled_packs', []);
        $all_json = glob(XMOJIPICK_DIR . 'assets/packs/pack-*.json');

        ?>
        <div class="wrap xmojipick-shell">

            <!-- Hero -->
            <div class="xmojipick-hero">
                <div class="xmojipick-hero-main">
                    <div class="xmojipick-page-head">
                        <h1>
                            <span class="xmojipick-title-mark"><span class="dashicons dashicons-smiley"></span></span>
                            <span class="xmojipick-title-text">xMojipick</span>
                        </h1>
                        <div class="xmojipick-page-meta">
                            <span class="xmojipick-meta-badge xmojipick-badge-kicker">Emoji Plugin</span>
                            <span class="xmojipick-meta-badge xmojipick-badge-version">v<?php echo esc_html(XMOJIPICK_VERSION); ?></span>
                            <a class="xmojipick-meta-badge xmojipick-badge-author" href="https://xifeng.net" target="_blank" rel="noopener noreferrer">西风</a>
                            <a class="xmojipick-meta-badge xmojipick-badge-repo" href="https://github.com/gentpan/xMojipick" target="_blank" rel="noopener noreferrer">GitHub</a>
                        </div>
                    </div>
                    <p class="xmojipick-hero-desc"><?php esc_html_e('多表情包表情插件，支持 SVG / PNG / GIF / AVIF / WebP，评论区 · 后台 · 邮件渲染', 'xmojipick'); ?></p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="xmojipick-tabs-wrap">
                <div class="xmojipick-admin-tabs">
                    <a href="#packs" class="xmojipick-admin-tab is-active" data-tab="packs">
                        <span class="xmojipick-tab-icon"><span class="dashicons dashicons-images-alt2"></span></span>
                        <?php esc_html_e('表情包管理', 'xmojipick'); ?>
                    </a>
                    <a href="#scanner" class="xmojipick-admin-tab" data-tab="scanner">
                        <span class="xmojipick-tab-icon"><span class="dashicons dashicons-search"></span></span>
                        <?php esc_html_e('文件扫描器', 'xmojipick'); ?>
                    </a>
                </div>
            </div>

            <!-- Pack Management -->
            <div id="tab-packs" class="xmojipick-tab-panel is-active">
                <div class="xmojipick-panel-inner">
                    <form method="post" action="options.php">
                        <?php settings_fields('xmojipick_packs'); ?>
                        <input type="hidden" name="xmojipick_disabled_packs" value="" />
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width:50px"><?php esc_html_e('启用', 'xmojipick'); ?></th>
                                    <th><?php esc_html_e('名称', 'xmojipick'); ?></th>
                                    <th><?php esc_html_e('类型', 'xmojipick'); ?></th>
                                    <th><?php esc_html_e('数量', 'xmojipick'); ?></th>
                                    <th><?php esc_html_e('预览', 'xmojipick'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($all_json): foreach ($all_json as $file):
                                $data    = json_decode(file_get_contents($file), true);
                                if (!$data) continue;
                                $pid     = basename($file, '.json');
                                $is_dis  = in_array($pid, $disabled, true);
                                $count   = count($data['emojis'] ?? []);
                                $type    = (!empty($data['emojis'][0]['svg'])) ? 'SVG' : 'Image';
                                $path    = str_replace('pack-', '', $pid);
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="xmojipick_disabled_packs[]" value="<?php echo esc_attr($pid); ?>" <?php checked(!$is_dis); ?> /></td>
                                    <td><?php echo esc_html($data['name'] ?? $pid); ?></td>
                                    <td><?php echo $type; ?></td>
                                    <td><?php echo $count; ?></td>
                                    <td class="xmojipick-preview-cell">
                                        <?php
                                        $preview = array_slice($data['emojis'] ?? [], 0, 10);
                                        foreach ($preview as $e) {
                                            if (!empty($e['svg'])) {
                                                echo '<span class="xmojipick-admin-emoji">' . wp_kses($e['svg'], self::allowed_svg_tags()) . '</span>';
                                            } elseif (!empty($e['file'])) {
                                                $url = XMOJIPICK_URL . 'assets/packs/' . $path . '/' . $e['file'];
                                                echo '<span class="xmojipick-admin-emoji"><img src="' . esc_url($url) . '" /></span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                        <?php submit_button(__('保存设置', 'xmojipick')); ?>
                    </form>
                </div>
            </div>

            <!-- Scanner -->
            <div id="tab-scanner" class="xmojipick-tab-panel">
                <div class="xmojipick-panel-inner">
                    <p><?php esc_html_e('扫描 assets/packs/ 目录中的图片文件夹，自动生成 JSON 配置。', 'xmojipick'); ?></p>
                    <button type="button" class="button button-primary" id="xmojipick-scan-btn"><?php esc_html_e('扫描文件夹', 'xmojipick'); ?></button>
                    <div id="xmojipick-scan-results"></div>
                </div>
            </div>

        </div>
        <?php
    }

    private function get_comment_inline_styles()
    {
        return <<<'CSS'
<style id="xmojipick-admin-comment-inline">
.comment .xmojipick-inline,
#the-comment-list .xmojipick-inline,
#dashboard_right_now .xmojipick-inline,
#latest-comments .xmojipick-inline {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    vertical-align: middle !important;
    display: inline-block !important;
    background-size: contain !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
    border: none !important;
    margin: 0 1px !important;
    line-height: 0 !important;
}
</style>
CSS;
    }

    public function ajax_scan()
    {
        check_ajax_referer('xmojipick_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('无权限', 'xmojipick'));
        }
        wp_send_json_success(xMojipick_Loader::scan_folders());
    }

    public function ajax_generate()
    {
        check_ajax_referer('xmojipick_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('无权限', 'xmojipick'));
        }
        $folder = sanitize_file_name(wp_unslash($_POST['folder'] ?? ''));
        $name   = sanitize_text_field(wp_unslash($_POST['pack_name'] ?? $folder));
        $sort   = absint(wp_unslash($_POST['sort'] ?? 99));

        if (!$folder) {
            wp_send_json_error(__('缺少文件夹名', 'xmojipick'));
        }

        $result = xMojipick_Loader::generate_pack_json($folder, $name, $sort);
        if ($result) {
            xMojipick_Loader::clear_cache();
            /* translators: %s: JSON filename */
            wp_send_json_success(['message' => sprintf(__('已生成 %s', 'xmojipick'), 'pack-' . $folder . '.json')]);
        } else {
            wp_send_json_error(__('生成失败，请检查文件夹是否包含图片', 'xmojipick'));
        }
    }
}
