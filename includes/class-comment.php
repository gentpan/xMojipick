<?php

if (!defined('ABSPATH')) {
    exit;
}

class xMojipick_Comment
{
    private $loader;
    private $picker_output = false;

    public function __construct(xMojipick_Loader $loader)
    {
        $this->loader = $loader;
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_head', [$this, 'output_packs_json'], 99);
        add_action('comment_form_after_fields', [$this, 'output_picker_html']);
        add_action('comment_form_logged_in_after', [$this, 'output_picker_html']);
        add_action('comment_form_top', [$this, 'output_picker_html']);
        add_action('comment_form_after', [$this, 'output_picker_html']);
    }

    public function enqueue()
    {
        /*
         * Always load JS/CSS on every page for PJAX/SPA theme compatibility.
         * Themes like Lared, Westlife etc. use PJAX to navigate without full
         * page reloads.  If the script is only loaded on singular pages, it
         * won't be present after PJAX navigation from the homepage to a post.
         * The script self-exits early when no comment textarea is found.
         */
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        $css_file = 'assets/css/xmojipick' . $suffix . '.css';
        $js_file  = 'assets/js/xmojipick' . $suffix . '.js';

        $style_ver  = file_exists(XMOJIPICK_DIR . $css_file)
            ? filemtime(XMOJIPICK_DIR . $css_file)
            : XMOJIPICK_VERSION;
        $script_ver = file_exists(XMOJIPICK_DIR . $js_file)
            ? filemtime(XMOJIPICK_DIR . $js_file)
            : XMOJIPICK_VERSION;

        wp_enqueue_style(
            'xmojipick',
            XMOJIPICK_URL . $css_file,
            [],
            $style_ver
        );
        wp_enqueue_script(
            'xmojipick',
            XMOJIPICK_URL . $js_file,
            [],
            $script_ver,
            true
        );
        $settings = [
            'columns'              => 8,
            'emojiSize'            => 28,
            'textareaSelectors'    => [
                '#comment',
                'textarea[name="comment"]',
                '.comment-form textarea',
                '#commentform textarea',
                '.comments-area textarea',
                '.comment-respond textarea',
                'form.comment-form textarea',
            ],
            'commentRootSelectors' => [
                '.comment-content',
                '.comment-body',
                '.comment_content',
                '.comment-text',
                '.comment-list li',
                '.comments-area .comment',
                '.comments-area article',
            ],
        ];

        $settings['triggerIcon'] = [
            'type' => 'img',
            'url'  => 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMTAyNCAxMDI0IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTUxMiA0Mi42NjY2NjdjMjU5LjIgMCA0NjkuMzMzMzMzIDIxMC4xMzMzMzMgNDY5LjMzMzMzMyA0NjkuMzMzMzMzcy0yMTAuMTMzMzMzIDQ2OS4zMzMzMzMtNDY5LjMzMzMzMyA0NjkuMzMzMzMzUzQyLjY2NjY2NyA3NzEuMiA0Mi42NjY2NjcgNTEyIDI1Mi44IDQyLjY2NjY2NyA1MTIgNDIuNjY2NjY3eiBtMCA0Mi42NjY2NjZDMjc2LjM1MiA4NS4zMzMzMzMgODUuMzMzMzMzIDI3Ni4zNTIgODUuMzMzMzMzIDUxMnMxOTEuMDE4NjY3IDQyNi42NjY2NjcgNDI2LjY2NjY2NyA0MjYuNjY2NjY3IDQyNi42NjY2NjctMTkxLjAxODY2NyA0MjYuNjY2NjY3LTQyNi42NjY2NjdTNzQ3LjY0OCA4NS4zMzMzMzMgNTEyIDg1LjMzMzMzM3pNMjk4LjY2NjY2NyA1NzYuMjU2YTIxLjMzMzMzMyAyMS4zMzMzMzMgMCAwIDEgMjUuMTczMzMzIDE2LjY4MjY2NyAxOTIuMDg1MzMzIDE5Mi4wODUzMzMgMCAwIDAgMzc2LjMyIDAuMTcwNjY2IDIxLjMzMzMzMyAyMS4zMzMzMzMgMCAwIDEgNDEuODEzMzMzIDguNTMzMzM0IDIzNC43NTIgMjM0Ljc1MiAwIDAgMS00NTkuOTQ2NjY2LTAuMjU2IDIxLjMzMzMzMyAyMS4zMzMzMzMgMCAwIDEgMTYuNjQtMjUuMTMwNjY3ek0zMzAuNjY2NjY3IDM0MS4zMzMzMzNhNTMuMzMzMzMzIDUzLjMzMzMzMyAwIDEgMSAwIDEwNi42NjY2NjcgNTMuMzMzMzMzIDUzLjMzMzMzMyAwIDAgMSAwLTEwNi42NjY2Njd6IG0zNjIuNjY2NjY2IDBhNTMuMzMzMzMzIDUzLjMzMzMzMyAwIDEgMSAwIDEwNi42NjY2NjcgNTMuMzMzMzMzIDUzLjMzMzMzMyAwIDAgMSAwLTEwNi42NjY2Njd6IiBmaWxsPSIjODg4ODg4Ij48L3BhdGg+PC9zdmc+',
        ];

        /*
         * Pack data is delivered via a <head> JSON element (output_packs_json)
         * instead of wp_localize_script.  This allows PJAX/SPA themes to
         * update it during head-switch when navigating between pages.
         * wp_localize_script output lives in the footer and is never touched
         * by PJAX, so packs would be stale after navigation.
         */
        wp_localize_script('xmojipick', 'xmojipickSettings', $settings);
    }

    /**
     * Output packs JSON in <head> so PJAX/SPA head-switch can update it.
     *
     * wp_localize_script puts data next to the script in the footer,
     * which PJAX never touches.  A <head> JSON element lets the PJAX
     * head-switch automatically deliver fresh pack data when navigating
     * between pages (e.g. homepage → post).
     *
     * On non-singular pages an empty element is output as a placeholder;
     * PJAX will replace it with the full data from the target page.
     */
    public function output_packs_json()
    {
        $json = '';
        if (is_singular() && comments_open()) {
            $js_packs = $this->build_js_packs();
            if ($js_packs) {
                $json = wp_json_encode($js_packs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        echo '<script type="application/json" id="xmojipick-packs-json">' . $json . '</script>';
    }

    /**
     * Build the JS-ready packs array from the loader's pack data.
     *
     * @return array<int, array{id: string, name: string, is_inline: bool, emojis: array}>
     */
    private function build_js_packs(): array
    {
        $packs    = $this->loader->get_packs();
        $js_packs = [];
        foreach ($packs as $pack) {
            $js_pack = [
                'id'        => $pack['id'],
                'name'      => $pack['name'],
                'is_inline' => false,
                'emojis'    => [],
            ];
            foreach ($pack['emojis'] as $emoji) {
                $js_emoji = [
                    'slug' => $emoji['slug'] ?? '',
                    'name' => $emoji['name'] ?? ($emoji['slug'] ?? ''),
                ];
                if (!empty($emoji['url'])) {
                    $js_emoji['src'] = $emoji['url'];
                }
                $js_pack['emojis'][] = $js_emoji;
            }
            $js_packs[] = $js_pack;
        }
        return $js_packs;
    }

    public function output_picker_html()
    {
        if ($this->picker_output) {
            return;
        }
        $this->picker_output = true;

        $packs = $this->loader->get_packs();
        if (empty($packs)) {
            return;
        }

        echo '<div id="xmojipick-picker" style="display:none;">';

        /* ── Grids (top) ── */
        $first = true;
        foreach ($packs as $pack) {
            $active   = $first ? ' xmojipick-active' : '';
            $lazy     = !$first ? ' data-lazy="1"' : '';

            printf(
                '<div class="xmojipick-grid%s" data-pack="%s"%s>',
                $active,
                esc_attr($pack['id']),
                $lazy
            );

            foreach ($pack['emojis'] as $emoji) {
                $slug = $emoji['slug'] ?? '';
                $name = $emoji['name'] ?? $slug;

                echo '<button type="button" class="xmojipick-item" data-code="' . esc_attr($slug) . '" title="' . esc_attr($name) . '">';

                if (!empty($emoji['url'])) {
                    $src_attr = ($first || empty($lazy)) ? 'src' : 'data-src';
                    printf(
                        '<img %s="%s" alt="%s" loading="lazy" data-no-lazy="1" data-skip-lazy="1" class="no-lazy skip-lazy" />',
                        $src_attr,
                        esc_attr($emoji['url']),
                        esc_attr($name)
                    );
                }

                echo '</button>';
            }

            echo '</div>';
            $first = false;
        }

        /* ── Footer: tabs + badge ── */
        echo '<div class="xmojipick-header">';
        echo '<div class="xmojipick-tabs">';
        $first = true;
        foreach ($packs as $pack) {
            $active = $first ? ' xmojipick-active' : '';
            $icon   = $this->get_tab_icon($pack);
            printf(
                '<button type="button" class="xmojipick-tab%s" data-pack="%s" title="%s">%s</button>',
                $active,
                esc_attr($pack['id']),
                esc_attr($pack['name']),
                $icon
            );
            $first = false;
        }
        echo '</div>';
        echo '<a class="xmojipick-badge" href="https://github.com/gentpan/xMojipick" target="_blank" rel="noopener noreferrer">xMojipick</a>';
        echo '</div>';

        echo '</div>';
    }

    private function get_tab_icon($pack)
    {
        if (empty($pack['emojis'])) {
            return esc_html(mb_substr($pack['name'], 0, 1));
        }
        $first = $pack['emojis'][0];
        if (!empty($first['url'])) {
            return sprintf(
                '<span class="xmojipick-tab-icon" style="background-image:url(\'%s\')"></span>',
                esc_url_raw($first['url'])
            );
        }
        return esc_html(mb_substr($pack['name'], 0, 1));
    }
}
