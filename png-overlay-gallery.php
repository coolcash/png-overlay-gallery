<?php
/**
 * Plugin Name: PNG Overlay Gallery (Shortcode + Block)
 * Description: A square-grid gallery for transparent PNGs with configurable overlay & background colors, per-item links (URL or WooCommerce product), a shortcode, and a Gutenberg block.
 * Version:     1.1.0
 * Author:      Spencer Allen
 * License:     GPL-2.0+
 * Text Domain: png-overlay-gallery
 */

if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('POG_PNG_Overlay_Gallery') ) :

class POG_PNG_Overlay_Gallery {
    const OPTION_KEY = 'pog_options';
    const VERSION    = '1.1.0';

    public function __construct() {
        // Admin
        add_action('admin_menu',            [$this, 'add_settings_page']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Front
        add_action('wp_enqueue_scripts',    [$this, 'enqueue_front_assets']);

        // Shortcode
        add_shortcode('png_overlay_gallery', [$this, 'shortcode']);

        // Block
        add_action('init',                  [$this, 'register_block']);
    }

    private function is_wc_active() {
        return class_exists('WooCommerce');
    }

    public function add_settings_page() {
        add_options_page(
            __('PNG Overlay Gallery', 'png-overlay-gallery'),
            __('PNG Overlay Gallery', 'png-overlay-gallery'),
            'manage_options',
            'png-overlay-gallery',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('pog_options_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default'           => [
                'overlay_color'    => '#000000',
                'background_color' => '#ffffff',
                'columns'          => 4,
                'gap'              => 10,
                'hover'            => 'zoom', // zoom|shadow|none
                'images'           => []      // array of [id, url, product_id]
            ],
        ]);
    }

    public function sanitize_options($input) {
        $out = [];
        $out['overlay_color']    = isset($input['overlay_color']) ? sanitize_hex_color($input['overlay_color']) : '#000000';
        $out['background_color'] = isset($input['background_color']) ? sanitize_hex_color($input['background_color']) : '#ffffff';
        $out['columns']          = isset($input['columns']) ? max(1, min(12, absint($input['columns']))) : 4;
        $out['gap']              = isset($input['gap']) ? max(0, min(64, absint($input['gap']))) : 10;
        $hover                   = isset($input['hover']) ? sanitize_key($input['hover']) : 'zoom';
        $out['hover']            = in_array($hover, ['zoom','shadow','none'], true) ? $hover : 'zoom';

        $out['images'] = [];
        if ( ! empty($input['images']) && is_array($input['images']) ) {
            foreach ($input['images'] as $row) {
                $id = isset($row['id']) ? absint($row['id']) : 0;
                if ( ! $id ) { continue; }
                if ( get_post_mime_type($id) !== 'image/png' ) { continue; }

                $url = isset($row['url']) ? esc_url_raw($row['url']) : '';
                $pid = isset($row['product_id']) ? absint($row['product_id']) : 0;

                $out['images'][] = [
                    'id'         => $id,
                    'url'        => $url,
                    'product_id' => $pid,
                ];
            }
        }
        if (count($out['images']) > 400) {
            $out['images'] = array_slice($out['images'], 0, 400);
        }

        return $out;
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_png-overlay-gallery') { return; }

        wp_enqueue_media();
        wp_enqueue_style('pog-admin', plugin_dir_url(__FILE__) . 'admin.css', [], self::VERSION);

        $products = [];
        if ( $this->is_wc_active() ) {
            $product_posts = get_posts([
                'post_type'      => 'product',
                'posts_per_page' => 200,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]);
            foreach ($product_posts as $pid) {
                $products[] = [
                    'id'    => $pid,
                    'title' => html_entity_decode( get_the_title($pid), ENT_QUOTES ),
                    'url'   => get_permalink($pid),
                ];
            }
        }

        wp_enqueue_script(
            'pog-admin-js',
            plugin_dir_url(__FILE__) . 'admin.js',
            ['jquery'],
            self::VERSION,
            true
        );
        wp_localize_script('pog-admin-js', 'POG_ADMIN', [
            'optionKey'     => self::OPTION_KEY,
            'productActive' => (bool) $this->is_wc_active(),
            'products'      => $products,
            'i18n'          => [
                'selectImages' => __('Select PNG Images', 'png-overlay-gallery'),
                'useImages'    => __('Use selected images', 'png-overlay-gallery'),
                'linkURL'      => __('Link URL (optional)', 'png-overlay-gallery'),
                'product'      => __('Product (optional)', 'png-overlay-gallery'),
                'remove'       => __('Remove', 'png-overlay-gallery'),
                'idLabel'      => __('ID', 'png-overlay-gallery'),
            ],
        ]);
    }

    public function render_settings_page() {
        if ( ! current_user_can('manage_options') ) { return; }
        $opts      = get_option(self::OPTION_KEY, []);
        $overlay   = isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000';
        $bg        = isset($opts['background_color']) ? $opts['background_color'] : '#ffffff';
        $columns   = isset($opts['columns']) ? absint($opts['columns']) : 4;
        $gap       = isset($opts['gap']) ? absint($opts['gap']) : 10;
        $hover     = isset($opts['hover']) ? sanitize_key($opts['hover']) : 'zoom';
        $images    = isset($opts['images']) && is_array($opts['images']) ? $opts['images'] : [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PNG Overlay Gallery Settings', 'png-overlay-gallery'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('pog_options_group'); ?>

                <div class="pog-field">
                    <label for="pog_overlay"><strong><?php esc_html_e('PNG Overlay Color', 'png-overlay-gallery'); ?></strong></label><br/>
                    <input type="color" id="pog_overlay" name="<?php echo esc_attr(self::OPTION_KEY); ?>[overlay_color]" value="<?php echo esc_attr($overlay); ?>"/>
                    <div class="pog-small"><?php esc_html_e('Solid color behind the PNG, visible through transparent regions.', 'png-overlay-gallery'); ?></div>
                </div>

                <div class="pog-field">
                    <label for="pog_bg"><strong><?php esc_html_e('Tile Background Color', 'png-overlay-gallery'); ?></strong></label><br/>
                    <input type="color" id="pog_bg" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($bg); ?>"/>
                    <div class="pog-small"><?php esc_html_e('Background of each square grid tile.', 'png-overlay-gallery'); ?></div>
                </div>

                <div class="pog-field pog-inline">
                    <div>
                        <label for="pog_columns"><strong><?php esc_html_e('Columns', 'png-overlay-gallery'); ?></strong></label><br/>
                        <input type="number" id="pog_columns" min="1" max="12" name="<?php echo esc_attr(self::OPTION_KEY); ?>[columns]" value="<?php echo esc_attr($columns); ?>"/>
                    </div>
                    <div>
                        <label for="pog_gap"><strong><?php esc_html_e('Gap (px)', 'png-overlay-gallery'); ?></strong></label><br/>
                        <input type="number" id="pog_gap" min="0" max="64" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gap]" value="<?php echo esc_attr($gap); ?>"/>
                    </div>
                    <div>
                        <label for="pog_hover"><strong><?php esc_html_e('Hover Effect', 'png-overlay-gallery'); ?></strong></label><br/>
                        <select id="pog_hover" name="<?php echo esc_attr(self::OPTION_KEY); ?>[hover]">
                            <option value="zoom"   <?php selected($hover, 'zoom');   ?>><?php esc_html_e('Zoom', 'png-overlay-gallery'); ?></option>
                            <option value="shadow" <?php selected($hover, 'shadow'); ?>><?php esc_html_e('Shadow', 'png-overlay-gallery'); ?></option>
                            <option value="none"   <?php selected($hover, 'none');   ?>><?php esc_html_e('None', 'png-overlay-gallery'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="pog-field">
                    <strong><?php esc_html_e('Images (PNG only)', 'png-overlay-gallery'); ?></strong><br/>
                    <button type="button" class="button button-primary" id="pog-select-images"><?php esc_html_e('Select PNG Images', 'png-overlay-gallery'); ?></button>
                    <div class="pog-small"><?php esc_html_e('Add a URL or choose a WooCommerce product per image (product permalink will be used if selected).', 'png-overlay-gallery'); ?></div>

                    <div class="pog-images" id="pog-images-list">
                        <?php
                        $idx = 0;
                        foreach ( $images as $row ) {
                            $id  = absint($row['id']);
                            if ( ! $id || get_post_mime_type($id) !== 'image/png' ) { continue; }

                            $url   = isset($row['url']) ? esc_url($row['url']) : '';
                            $pid   = isset($row['product_id']) ? absint($row['product_id']) : 0;
                            $thumb = wp_get_attachment_image_url($id, 'medium');
                            $alt   = get_post_meta($id, '_wp_attachment_image_alt', true);
                            ?>
                            <div class="pog-item" data-id="<?php echo esc_attr($id); ?>">
                                <img class="thumb" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>" />
                                <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][id]" value="<?php echo esc_attr($id); ?>"/>

                                <label>
                                    <?php esc_html_e('Link URL (optional)', 'png-overlay-gallery'); ?><br/>
                                    <input class="url-input" type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][url]" value="<?php echo esc_url($url); ?>" placeholder="https://example.com/product"/>
                                </label>

                                <?php if ( $this->is_wc_active() ) : ?>
                                    <label>
                                        <?php esc_html_e('Product (optional)', 'png-overlay-gallery'); ?><br/>
                                        <select class="product-select" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][product_id]">
                                            <option value="0"><?php esc_html_e('— None —', 'png-overlay-gallery'); ?></option>
                                            <?php
                                            $product_posts = get_posts([
                                                'post_type'      => 'product',
                                                'posts_per_page' => 200,
                                                'post_status'    => 'publish',
                                                'orderby'        => 'title',
                                                'order'          => 'ASC',
                                                'fields'         => 'ids',
                                            ]);
                                            foreach ( $product_posts as $p ) :
                                                ?>
                                                <option value="<?php echo esc_attr($p); ?>" <?php selected($pid, $p); ?>>
                                                    <?php echo esc_html( wp_trim_words( get_the_title($p), 10, '…' ) ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endif; ?>

                                <div class="pog-controls">
                                    <span class="pog-small"><?php esc_html_e('ID', 'png-overlay-gallery'); ?>: <?php echo esc_html($id); ?></span>
                                    <button type="button" class="button button-secondary pog-remove"><?php esc_html_e('Remove', 'png-overlay-gallery'); ?></button>
                                </div>
                            </div>
                            <?php
                            $idx++;
                        }
                        ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_front_assets() {
        wp_register_style('pog-front', false, [], self::VERSION);
        wp_enqueue_style('pog-front');

        $css = '
        .pog-gallery { display:grid; grid-template-columns:repeat(var(--pog-columns,4), minmax(0,1fr)); gap:var(--pog-gap,10px); }
        .pog-item { position:relative; aspect-ratio:1/1; background:var(--pog-bg, #ffffff); border-radius:8px; overflow:hidden; }
        .pog-item a, .pog-item .pog-static { position:absolute; inset:0; display:block; }
        .pog-item .pog-overlay-layer { position:absolute; inset:0; background:var(--pog-overlay, #000000); z-index:1; }
        .pog-item img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; z-index:2; display:block; transition:transform .28s ease, filter .28s ease, box-shadow .28s ease; }

        .pog-hover-zoom .pog-item:hover img { transform:scale(1.05); }
        .pog-hover-shadow .pog-item:hover img { box-shadow:0 10px 24px rgba(0,0,0,.18); }
        .pog-hover-none .pog-item:hover img { transform:none; box-shadow:none; }

        @supports not (aspect-ratio: 1/1) {
            .pog-item::before { content:""; display:block; padding-top:100%; }
            .pog-item > * { position:absolute; inset:0; }
        }
        ';
        wp_add_inline_style('pog-front', $css);
    }

    private function build_items_from_atts_and_options($atts, $opts) {
        $items = [];
        if ( ! empty($atts['ids']) ) {
            $ids = array_filter(array_map('absint', is_array($atts['ids']) ? $atts['ids'] : explode(',', $atts['ids'])));
            foreach ( $ids as $id ) {
                if ( get_post_mime_type($id) !== 'image/png' ) { continue; }
                $items[] = [ 'id' => $id, 'url' => '', 'product_id' => 0 ];
            }
        } else {
            if ( ! empty($opts['images']) && is_array($opts['images']) ) {
                foreach ( $opts['images'] as $row ) {
                    $id = absint($row['id']);
                    if ( ! $id || get_post_mime_type($id) !== 'image/png' ) { continue; }
                    $items[] = [
                        'id'         => $id,
                        'url'        => isset($row['url']) ? esc_url($row['url']) : '',
                        'product_id' => isset($row['product_id']) ? absint($row['product_id']) : 0,
                    ];
                }
            }
        }
        return $items;
    }

    private function resolve_href($item) {
        if ( ! empty($item['product_id']) && get_post_status($item['product_id']) === 'publish' ) {
            return get_permalink($item['product_id']);
        }
        if ( ! empty($item['url']) ) {
            return esc_url($item['url']);
        }
        return '';
    }

    private function render_gallery_html($items, $args) {
        $columns = max(1, min(12, absint($args['columns'])));
        $gap     = max(0, min(64, absint($args['gap'])));
        $overlay = sanitize_hex_color($args['overlay']) ?: '#000000';
        $bg      = sanitize_hex_color($args['bg']) ?: '#ffffff';
        $size    = sanitize_key($args['size']);
        $target  = in_array($args['target'], ['_self', '_blank'], true) ? $args['target'] : '_self';
        $rel     = sanitize_text_field($args['rel']);
        $hover   = in_array($args['hover'], ['zoom','shadow','none'], true) ? $args['hover'] : 'zoom';

        if ( empty($items) ) {
            return '<div class="pog-gallery-empty">'.esc_html__('No PNG images found for gallery.', 'png-overlay-gallery').'</div>';
        }

        ob_start();
        $style = sprintf(
            '--pog-overlay:%1$s; --pog-bg:%2$s; --pog-columns:%3$d; --pog-gap:%4$dpx;',
            esc_attr($overlay),
            esc_attr($bg),
            $columns,
            $gap
        );
        $hover_class = 'pog-hover-' . $hover;
        ?>
        <div class="pog-gallery <?php echo esc_attr($hover_class); ?>" style="<?php echo esc_attr($style); ?>">
            <?php foreach ($items as $it):
                $src = wp_get_attachment_image_src($it['id'], $size);
                if ( ! $src ) { continue; }
                $alt  = get_post_meta($it['id'], '_wp_attachment_image_alt', true);
                $href = $this->resolve_href($it);
                ?>
                <div class="pog-item">
                    <?php if ($href): ?>
                        <a href="<?php echo esc_url($href); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>">
                            <span class="pog-overlay-layer" aria-hidden="true"></span>
                            <img src="<?php echo esc_url($src[0]); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async" />
                        </a>
                    <?php else: ?>
                        <span class="pog-static">
                            <span class="pog-overlay-layer" aria-hidden="true"></span>
                            <img src="<?php echo esc_url($src[0]); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async" />
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode($atts = [], $content = null) {
        $opts = get_option(self::OPTION_KEY, []);
        $defaults = [
            'ids'     => '',
            'columns' => isset($opts['columns']) ? (int)$opts['columns'] : 4,
            'gap'     => isset($opts['gap']) ? (int)$opts['gap'] : 10,
            'overlay' => isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000',
            'bg'      => isset($opts['background_color']) ? $opts['background_color'] : '#ffffff',
            'size'    => 'large',
            'target'  => '_self',
            'rel'     => 'noopener',
            'hover'   => isset($opts['hover']) ? $opts['hover'] : 'zoom',
        ];
        $atts = shortcode_atts($defaults, $atts, 'png_overlay_gallery');

        $items = $this->build_items_from_atts_and_options($atts, $opts);

        return $this->render_gallery_html($items, $atts);
    }

    public function register_block() {
        wp_register_script(
            'pog-block-js',
            plugin_dir_url(__FILE__) . 'block.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
            self::VERSION,
            true
        );

        register_block_type('pog/png-overlay-gallery', [
            'api_version'      => 2,
            'editor_script'    => 'pog-block-js',
            'render_callback'  => function($attributes, $content) {
                $opts = get_option(self::OPTION_KEY, []);
                $args = [
                    'columns' => isset($attributes['columns']) ? $attributes['columns'] : (isset($opts['columns']) ? (int)$opts['columns'] : 4),
                    'gap'     => isset($attributes['gap']) ? $attributes['gap'] : (isset($opts['gap']) ? (int)$opts['gap'] : 10),
                    'overlay' => isset($attributes['overlay']) ? $attributes['overlay'] : (isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000'),
                    'bg'      => isset($attributes['bg']) ? $attributes['bg'] : (isset($opts['background_color']) ? $opts['background_color'] : '#ffffff'),
                    'size'    => isset($attributes['size']) ? $attributes['size'] : 'large',
                    'target'  => isset($attributes['target']) ? $attributes['target'] : '_self',
                    'rel'     => isset($attributes['rel']) ? $attributes['rel'] : 'noopener',
                    'hover'   => isset($attributes['hover']) ? $attributes['hover'] : (isset($opts['hover']) ? $opts['hover'] : 'zoom'),
                ];
                if ( ! empty($attributes['ids']) && is_array($attributes['ids']) ) {
                    $atts_ids = implode(',', array_map('absint', $attributes['ids']));
                    $args['ids'] = $atts_ids;
                } else {
                    $args['ids'] = '';
                }

                $items = $this->build_items_from_atts_and_options($args, $opts);
                return $this->render_gallery_html($items, $args);
            },
            'attributes' => [
                'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'number' ], 'default' => [] ],
                'columns' => [ 'type' => 'number', 'default' => 4 ],
                'gap'     => [ 'type' => 'number', 'default' => 10 ],
                'overlay' => [ 'type' => 'string', 'default' => '#000000' ],
                'bg'      => [ 'type' => 'string', 'default' => '#ffffff' ],
                'size'    => [ 'type' => 'string', 'default' => 'large' ],
                'target'  => [ 'type' => 'string', 'default' => '_self' ],
                'rel'     => [ 'type' => 'string', 'default' => 'noopener' ],
                'hover'   => [ 'type' => 'string', 'default' => 'zoom' ],
            ],
            'title'       => __('PNG Overlay Gallery', 'png-overlay-gallery'),
            'description' => __('Square grid gallery for transparent PNGs with overlay/background colors and optional links.', 'png-overlay-gallery'),
            'category'    => 'widgets',
            'icon'        => 'format-gallery',
            'supports'    => [ 'align' => true ],
        ]);
    }
}

new POG_PNG_Overlay_Gallery();

endif;
