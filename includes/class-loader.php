<?php

if (!defined('ABSPATH')) {
    exit;
}

class xMojipick_Loader
{
    const IMAGE_EXTS = ['svg', 'png', 'gif', 'avif', 'webp', 'jpg', 'jpeg'];

    private $packs = null;
    private $map = null;

    public function get_packs()
    {
        if ($this->packs !== null) {
            return $this->packs;
        }

        $disabled = (array) get_option('xmojipick_disabled_packs', []);

        /* Try transient cache first (stores all packs before disabled filtering) */
        $all_packs = get_transient('xmojipick_packs');

        if ($all_packs === false) {
            $all_packs = $this->load_packs_from_disk();
            set_transient('xmojipick_packs', $all_packs, HOUR_IN_SECONDS);
        }

        /* Filter out disabled packs */
        $packs = [];
        foreach ($all_packs as $pack) {
            if (!in_array($pack['id'], $disabled, true)) {
                $packs[] = $pack;
            }
        }

        $this->packs = $packs;
        return $this->packs;
    }

    /**
     * Load and parse all packs from disk (no caching, no disabled filtering).
     */
    private function load_packs_from_disk()
    {
        $dir   = XMOJIPICK_DIR . 'assets/packs/';
        $files = glob($dir . 'pack-*.json');

        if (!$files) {
            return [];
        }

        $packs = [];
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data || empty($data['emojis'])) {
                continue;
            }

            $pack_id = basename($file, '.json');

            $pack_path = substr($pack_id, 5); // Strip 'pack-' prefix

            foreach ($data['emojis'] as &$emoji) {
                if (!empty($emoji['file'])) {
                    $emoji['url'] = XMOJIPICK_URL . 'assets/packs/' . $pack_path . '/' . $emoji['file'];
                }
            }
            unset($emoji);

            $data['id']        = $pack_id;
            $data['path']      = $pack_path;
            $data['is_inline'] = false;

            $packs[] = $data;
        }

        usort($packs, function ($a, $b) {
            return ($a['sort'] ?? 99) - ($b['sort'] ?? 99);
        });

        return $packs;
    }

    /**
     * Clear the pack data transient cache.
     */
    public static function clear_cache()
    {
        delete_transient('xmojipick_packs');
    }

    public function get_emoji_map()
    {
        $map   = [];
        $slugs_count = [];

        foreach ($this->get_packs() as $pack) {
            foreach ($pack['emojis'] as $emoji) {
                $slug = $emoji['slug'] ?? '';
                if (!$slug) {
                    continue;
                }
                if (!isset($slugs_count[$slug])) {
                    $slugs_count[$slug] = [];
                }
                $slugs_count[$slug][] = [
                    'pack' => $pack['id'],
                    'emoji' => $emoji,
                ];
            }
        }

        foreach ($slugs_count as $slug => $entries) {
            $map[$slug] = $entries[0]['emoji'];
            if (count($entries) > 1) {
                foreach ($entries as $entry) {
                    $prefix = str_replace('pack-', '', $entry['pack']);
                    $map[$prefix . '-' . $slug] = $entry['emoji'];
                }
            }
        }

        return $map;
    }

    /* ── Static utilities for admin scanning ── */

    public static function scan_folders()
    {
        $packs_dir = XMOJIPICK_DIR . 'assets/packs/';
        $results   = [];

        if (!is_dir($packs_dir)) {
            return $results;
        }

        $dirs = glob($packs_dir . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $folder = basename($dir);
            $images = self::scan_images_in($dir);
            $json_exists = file_exists($packs_dir . 'pack-' . $folder . '.json');
            $results[] = [
                'folder'      => $folder,
                'image_count' => count($images),
                'json_exists' => $json_exists,
                'preview'     => array_slice($images, 0, 6),
            ];
        }

        return $results;
    }

    public static function scan_images_in($dir)
    {
        $files = [];
        foreach (scandir($dir) as $f) {
            if ($f[0] === '.') {
                continue;
            }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, self::IMAGE_EXTS, true)) {
                $files[] = $f;
            }
        }
        sort($files);
        return $files;
    }

    public static function generate_pack_json($folder, $pack_name, $sort)
    {
        $dir    = XMOJIPICK_DIR . 'assets/packs/' . $folder;
        $images = self::scan_images_in($dir);

        if (empty($images)) {
            return false;
        }

        $emojis = [];
        foreach ($images as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $slug = self::make_slug($name);

            $emoji = [
                'slug' => $slug,
                'name' => $name,
            ];

            $emoji['file'] = $file;

            $emojis[] = $emoji;
        }

        $pack_data = [
            'name'   => $pack_name ?: $folder,
            'sort'   => absint($sort),
            'emojis' => $emojis,
        ];

        $json_path = XMOJIPICK_DIR . 'assets/packs/pack-' . $folder . '.json';
        return file_put_contents(
            $json_path,
            wp_json_encode($pack_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    public static function make_slug($name)
    {
        $slug = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $name);
        $slug = trim($slug, '-');
        return $slug ?: 'emoji';
    }

    /**
     * Convert raw SVG content to a base64-encoded data URI.
     */
    public static function svg_to_data_uri($svg)
    {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function clean_svg($svg)
    {
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);
        $svg = preg_replace('/<!--.*?-->/s', '', $svg);

        // Security: strip dangerous elements
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
        $svg = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svg);
        $svg = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $svg);
        $svg = preg_replace('/<embed\b[^>]*\/?>/is', '', $svg);
        $svg = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $svg);

        // Security: strip on* event handler attributes
        $svg = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $svg);

        // Security: strip javascript: and data: URIs in href/xlink:href/src attributes
        $svg = preg_replace('/(<[^>]+\s)(href|xlink:href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '$1', $svg);
        $svg = preg_replace('/(<[^>]+\s)(href|xlink:href|src)\s*=\s*["\']?\s*data:(?!image\/(?:png|gif|jpeg|jpg|svg\+xml|webp|avif)[;,])[^"\'>\s]*/i', '$1', $svg);

        // Cosmetic: strip unnecessary attributes
        $svg = preg_replace('/\s+(t|p-id|class)="[^"]*"/', '', $svg);
        $svg = preg_replace('/\s(width|height)="[^"]*"/', '', $svg);
        $svg = preg_replace('/\s+/', ' ', $svg);
        return trim($svg);
    }

    /* ── Emoji rendering (merged from class-renderer.php) ── */

    private function get_map()
    {
        if ($this->map === null) {
            $this->map = $this->get_emoji_map();
        }
        return $this->map;
    }

    public function replace($text)
    {
        if (!is_string($text) || $text === '' || strpos($text, ':') === false) {
            return $text;
        }
        if (strpos($text, 'xmojipick-inline') !== false) {
            return $text;
        }

        return $this->replace_emojis($text, [$this, 'render_inline']);
    }

    public function replace_email($args)
    {
        if (empty($args['message']) || !is_string($args['message']) || strpos($args['message'], ':') === false) {
            return $args;
        }
        if (strpos($args['message'], '<img ') !== false && strpos($args['message'], 'data:image/svg+xml;base64,') !== false) {
            return $args;
        }

        $result = $this->replace_emojis($args['message'], [$this, 'render_email']);
        if ($result !== $args['message']) {
            $args['message'] = $result;
            $args['headers'] = array_merge(
                (array) ($args['headers'] ?? []),
                ['Content-Type: text/html; charset=UTF-8']
            );
        }

        return $args;
    }

    private function replace_emojis($text, callable $render_fn)
    {
        $map = $this->get_map();
        if (empty($map)) {
            return $text;
        }

        return preg_replace_callback('/:([\p{L}\p{N}_-]+):/u', function ($m) use ($map, $render_fn) {
            $slug = $m[1];
            if (!isset($map[$slug])) {
                return $m[0];
            }
            return call_user_func($render_fn, $map[$slug]);
        }, $text);
    }

    private function render_inline($emoji)
    {
        $bg_url = '';
        if (!empty($emoji['url'])) {
            $bg_url = esc_url_raw($emoji['url']);
        }
        if (!$bg_url) {
            return '';
        }
        $slug = $emoji['slug'] ?? '';
        return '<span class="xmojipick-inline" role="img" aria-label="'
            . esc_attr($emoji['name'])
            . '" data-slug="' . esc_attr($slug)
            . '" style="display:inline-block!important;width:1.4em!important;height:1.4em!important;'
            . 'max-width:28px!important;max-height:28px!important;vertical-align:middle!important;'
            . "background-image:url('" . $bg_url . "')!important;"
            . 'background-size:contain!important;background-position:center!important;'
            . 'background-repeat:no-repeat!important;'
            . 'border:none!important;margin:0 1px!important;line-height:0!important;"></span>';
    }

    private function render_email($emoji)
    {
        if (!empty($emoji['url'])) {
            return '<img src="' . esc_attr($emoji['url']) . '" alt="'
                . esc_attr($emoji['name'])
                . '" width="28" height="28" style="vertical-align:middle;" />';
        }
        return '';
    }
}
