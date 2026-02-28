<?php
/**
 * Plugin Name: PNG Overlay Gallery (Shortcode + Block)
 * Description: Square-grid gallery for transparent PNGs with per-image links, recolor/tint for black PNGs, responsive columns, per-image hover labels, shortcode + Gutenberg block.
 * Version:     1.3.0
 * Author:      Spencer Allen
 * License:     GPL-2.0+
 * Text Domain: png-overlay-gallery
 */

if ( ! defined('ABSPATH') ) { exit; }

if ( ! class_exists('POG_PNG_Overlay_Gallery') ) :

class POG_PNG_Overlay_Gallery {
    const OPTION_KEY = 'pog_options';
    const VERSION    = '1.3.0';

    public function __construct() {
        add_action('admin_menu',            [$this, 'add_settings_page']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_action('wp_enqueue_scripts',    [$this, 'enqueue_front_assets']);

        add_shortcode('png_overlay_gallery', [$this, 'shortcode']);

        add_action('init',                  [$this, 'register_block']);
    }

    private function is_wc_active() { return class_exists('WooCommerce'); }

    /* ------------------------------
     * Settings / Admin
     * ------------------------------ */
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
                'columns_sm'       => 2,
                'columns_md'       => 3,
                'columns_lg'       => 4,
                'gap'              => 10,
                'hover'            => 'zoom', // zoom|shadow|none
                'images'           => []      // array of [id, url, product_id, fg, bg, recolor, label]
            ],
        ]);
    }

    public function sanitize_options($input) {
        $out = [];
        $out['overlay_color']    = isset($input['overlay_color']) ? sanitize_hex_color($input['overlay_color']) : '#000000';
        $out['background_color'] = isset($input['background_color']) ? sanitize_hex_color($input['background_color']) : '#ffffff';
        $out['columns']          = isset($input['columns']) ? max(1, min(12, absint($input['columns']))) : 4;
        $out['columns_sm']       = isset($input['columns_sm']) ? max(1, min(8, absint($input['columns_sm']))) : 2;
        $out['columns_md']       = isset($input['columns_md']) ? max(1, min(12, absint($input['columns_md']))) : 3;
        $out['columns_lg']       = isset($input['columns_lg']) ? max(1, min(12, absint($input['columns_lg']))) : 4;
        $out['gap']              = isset($input['gap']) ? max(0, min(64, absint($input['gap']))) : 10;
        $hover                   = isset($input['hover']) ? sanitize_key($input['hover']) : 'zoom';
        $out['hover']            = in_array($hover, ['zoom','shadow','none'], true) ? $hover : 'zoom';

        $out['images'] = [];
        if ( ! empty($input['images']) && is_array($input['images']) ) {
            foreach ($input['images'] as $row) {
                $id = isset($row['id']) ? absint($row['id']) : 0;
                if ( ! $id ) { continue; }
                if ( get_post_mime_type($id) !== 'image/png' ) { continue; }

                $url     = isset($row['url']) ? esc_url_raw($row['url']) : '';
                $pid     = isset($row['product_id']) ? absint($row['product_id']) : 0;
                $fg      = isset($row['fg']) ? sanitize_hex_color($row['fg']) : '';
                $bg      = isset($row['bg']) ? sanitize_hex_color($row['bg']) : '';
                $recolor = ! empty($row['recolor']) ? 1 : 0;
                $label   = isset($row['label']) ? sanitize_text_field($row['label']) : '';

                $out['images'][] = [
                    'id'         => $id,
                    'url'        => $url,
                    'product_id' => $pid,
                    'fg'         => $fg,
                    'bg'         => $bg,
                    'recolor'    => $recolor,
                    'label'      => $label,
                ];
            }
        }
        if (count($out['images']) > 600) { $out['images'] = array_slice($out['images'], 0, 600); }

        return $out;
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_png-overlay-gallery') { return; }

        wp_enqueue_media();
        wp_enqueue_style('pog-admin', plugin_dir_url(__FILE__) . 'admin.css', [], self::VERSION);
        wp_enqueue_script('pog-admin-js', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], self::VERSION, true);

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
                $products[] = [ 'id' => $pid, 'title' => html_entity_decode( get_the_title($pid), ENT_QUOTES ), 'url' => get_permalink($pid) ];
            }
        }

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
                'fgLabel'      => __('Foreground color', 'png-overlay-gallery'),
                'bgLabel'      => __('Tile background', 'png-overlay-gallery'),
                'recolor'      => __('Recolor (tint) using foreground', 'png-overlay-gallery'),
                'label'        => __('Hover label (optional)', 'png-overlay-gallery'),
                'tabGallery'   => __('Gallery', 'png-overlay-gallery'),
                'tabHelp'      => __('Instructions', 'png-overlay-gallery'),
            ],
        ]);
    }

    public function render_settings_page() {
        if ( ! current_user_can('manage_options') ) { return; }
        $opts      = get_option(self::OPTION_KEY, []);
        $overlay   = isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000';
        $bg        = isset($opts['background_color']) ? $opts['background_color'] : '#ffffff';
        $columns   = isset($opts['columns']) ? absint($opts['columns']) : 4; // legacy single
        $cols_sm   = isset($opts['columns_sm']) ? absint($opts['columns_sm']) : 2;
        $cols_md   = isset($opts['columns_md']) ? absint($opts['columns_md']) : 3;
        $cols_lg   = isset($opts['columns_lg']) ? absint($opts['columns_lg']) : 4;
        $gap       = isset($opts['gap']) ? absint($opts['gap']) : 10;
        $hover     = isset($opts['hover']) ? sanitize_key($opts['hover']) : 'zoom';
        $images    = isset($opts['images']) && is_array($opts['images']) ? $opts['images'] : [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PNG Overlay Gallery Settings', 'png-overlay-gallery'); ?></h1>

            <h2 class="nav-tab-wrapper pog-tabs">
                <a href="#" class="nav-tab nav-tab-active" data-tab="pog-tab-gallery"><?php esc_html_e('Gallery', 'png-overlay-gallery'); ?></a>
                <a href="#" class="nav-tab" data-tab="pog-tab-instructions"><?php esc_html_e('Instructions', 'png-overlay-gallery'); ?></a>
            </h2>

            <div id="pog-tab-gallery" class="pog-tab-panel is-active">
                <form method="post" action="options.php">
                    <?php settings_fields('pog_options_group'); ?>

                    <div class="pog-field pog-inline">
                        <div>
                            <label><strong><?php esc_html_e('Responsive Columns', 'png-overlay-gallery'); ?></strong></label>
                            <div class="pog-inline">
                                <label>SM<br/><input type="number" min="1" max="8" name="<?php echo esc_attr(self::OPTION_KEY); ?>[columns_sm]" value="<?php echo esc_attr($cols_sm); ?>"/></label>
                                <label>MD<br/><input type="number" min="1" max="12" name="<?php echo esc_attr(self::OPTION_KEY); ?>[columns_md]" value="<?php echo esc_attr($cols_md); ?>"/></label>
                                <label>LG<br/><input type="number" min="1" max="12" name="<?php echo esc_attr(self::OPTION_KEY); ?>[columns_lg]" value="<?php echo esc_attr($cols_lg); ?>"/></label>
                            </div>
                            <div class="pog-small"><?php esc_html_e('SM: <640px, MD: 640–1023px, LG: ≥1024px', 'png-overlay-gallery'); ?></div>
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
                        <label for="pog_overlay"><strong><?php esc_html_e('Default PNG Overlay Color', 'png-overlay-gallery'); ?></strong></label><br/>
                        <input type="color" id="pog_overlay" name="<?php echo esc_attr(self::OPTION_KEY); ?>[overlay_color]" value="<?php echo esc_attr($overlay); ?>"/>
                        <div class="pog-small"><?php esc_html_e('Used when per-image recolor is disabled.', 'png-overlay-gallery'); ?></div>
                    </div>

                    <div class="pog-field">
                        <label for="pog_bg"><strong><?php esc_html_e('Default Tile Background', 'png-overlay-gallery'); ?></strong></label><br/>
                        <input type="color" id="pog_bg" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($bg); ?>"/>
                    </div>

                    <div class="pog-field">
                        <strong><?php esc_html_e('Images (PNG only)', 'png-overlay-gallery'); ?></strong><br/>
                        <button type="button" class="button button-primary" id="pog-select-images"><?php esc_html_e('Select PNG Images', 'png-overlay-gallery'); ?></button>
                        <div class="pog-small"><?php esc_html_e('Set URL or Woo product, optional recolor + per-image background, and optional hover label.', 'png-overlay-gallery'); ?></div>

                        <div class="pog-images" id="pog-images-list">
                            <?php
                            $idx = 0;
                            foreach ( $images as $row ) {
                                $id  = absint($row['id']);
                                if ( ! $id || get_post_mime_type($id) !== 'image/png' ) { continue; }

                                $url   = isset($row['url']) ? esc_url($row['url']) : '';
                                $pid   = isset($row['product_id']) ? absint($row['product_id']) : 0;
                                $fg    = isset($row['fg']) ? sanitize_hex_color($row['fg']) : '';
                                $bg_i  = isset($row['bg']) ? sanitize_hex_color($row['bg']) : '';
                                $rec   = ! empty($row['recolor']);
                                $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
                                $thumb = wp_get_attachment_image_url($id, 'medium');
                                $alt   = get_post_meta($id, '_wp_attachment_image_alt', true);
                                ?>
                                <div class="pog-item" data-id="<?php echo esc_attr($id); ?>">
                                    <img class="thumb" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($alt); ?>" />
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][id]" value="<?php echo esc_attr($id); ?>"/>

                                    <div class="pog-inline">
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
                                                    foreach ( $product_posts as $p ) : ?>
                                                        <option value="<?php echo esc_attr($p); ?>" <?php selected($pid, $p); ?>><?php echo esc_html( wp_trim_words( get_the_title($p), 10, '…' ) ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pog-inline">
                                        <label>
                                            <?php esc_html_e('Foreground color', 'png-overlay-gallery'); ?><br/>
                                            <input type="color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][fg]" value="<?php echo esc_attr($fg ?: '#000000'); ?>"/>
                                        </label>
                                        <label>
                                            <?php esc_html_e('Tile background', 'png-overlay-gallery'); ?><br/>
                                            <input type="color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][bg]" value="<?php echo esc_attr($bg_i ?: $bg); ?>"/>
                                        </label>
                                        <label style="margin-top:6px;">
                                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][recolor]" value="1" <?php checked($rec, true); ?> />
                                            <?php esc_html_e('Recolor (tint) using foreground', 'png-overlay-gallery'); ?>
                                        </label>
                                    </div>

                                    <div class="pog-inline" style="margin-top:6px; width:100%">
                                        <label style="flex:1 1 320px;">
                                            <?php esc_html_e('Hover label (optional)', 'png-overlay-gallery'); ?><br/>
                                            <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[images][<?php echo esc_attr($idx); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="e.g., Product name or tagline" style="width:100%"/>
                                        </label>
                                    </div>

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

            <div id="pog-tab-instructions" class="pog-tab-panel">
                <div class="pog-help">
                    <h2><?php esc_html_e('How to Use', 'png-overlay-gallery'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('Click “Select PNG Images” to add transparent PNGs.', 'png-overlay-gallery'); ?></li>
                        <li><?php esc_html_e('Add a Link URL or choose a WooCommerce Product (optional).', 'png-overlay-gallery'); ?></li>
                        <li><?php esc_html_e('To recolor black PNGs, check “Recolor (tint)” and pick a Foreground color. Set a per-image Tile background if needed.', 'png-overlay-gallery'); ?></li>
                        <li><?php esc_html_e('Set a Hover label to show a caption on hover.', 'png-overlay-gallery'); ?></li>
                        <li><?php esc_html_e('Choose responsive columns for SM / MD / LG breakpoints.', 'png-overlay-gallery'); ?></li>
                        <li><?php esc_html_e('Insert the gallery via the block “PNG Overlay Gallery” or the shortcode.', 'png-overlay-gallery'); ?></li>
                    </ol>

                    <h3><?php esc_html_e('Shortcode', 'png-overlay-gallery'); ?></h3>
                    <pre><code>[png_overlay_gallery gap="10" overlay="#000000" bg="#ffffff" size="large" target="_self" rel="noopener" hover="zoom" cols_sm="2" cols_md="3" cols_lg="4"]</code></pre>
                    <p class="pog-small"><?php esc_html_e('Tip: You can override the default image selection per page by passing attachment IDs via ids="123,456".', 'png-overlay-gallery'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------
     * Front-end CSS
     * ------------------------------ */
    public function enqueue_front_assets() {
        wp_register_style('pog-front', false, [], self::VERSION);
        wp_enqueue_style('pog-front');

        $css = '
        .pog-gallery { display:grid; gap:var(--pog-gap,10px); }
        /* Responsive columns */
        @media (max-width: 639px){ .pog-gallery { grid-template-columns: repeat(var(--pog-cols-sm,2), minmax(0,1fr)); } }
        @media (min-width: 640px) and (max-width: 1023px){ .pog-gallery { grid-template-columns: repeat(var(--pog-cols-md,3), minmax(0,1fr)); } }
        @media (min-width: 1024px){ .pog-gallery { grid-template-columns: repeat(var(--pog-cols-lg,4), minmax(0,1fr)); } }

        .pog-item { position:relative; aspect-ratio:1/1; background:var(--pog-bg, #ffffff); border-radius:8px; overflow:hidden; }
        .pog-item a, .pog-item .pog-static { position:absolute; inset:0; display:block; }
        .pog-item .pog-overlay-layer { position:absolute; inset:0; background:var(--pog-overlay, #000000); z-index:1; }
        .pog-item img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; z-index:2; display:block; transition:transform .28s ease, filter .28s ease, box-shadow .28s ease; }

        /* Recolor via CSS mask */
        .pog-item .pog-ink-layer { position:absolute; inset:0; background:var(--pog-ink, #000000); z-index:2; 
           -webkit-mask-size: contain; -webkit-mask-position: center; -webkit-mask-repeat: no-repeat;
           mask-size: contain; mask-position: center; mask-repeat: no-repeat; transition:transform .28s ease, box-shadow .28s ease; }

        /* Hover label */
        .pog-label { position:absolute; left:8px; right:8px; bottom:8px; background:rgba(0,0,0,.6); color:#fff; font-size:14px; line-height:1.3; padding:6px 8px; border-radius:6px; z-index:3; opacity:0; transform:translateY(6px); transition:opacity .25s ease, transform .25s ease; pointer-events:none; }
        .pog-item:hover .pog-label { opacity:1; transform:translateY(0); }

        /* Hover effects */
        .pog-hover-zoom .pog-item:hover img, .pog-hover-zoom .pog-item:hover .pog-ink-layer { transform:scale(1.05); }
        .pog-hover-shadow .pog-item:hover img, .pog-hover-shadow .pog-item:hover .pog-ink-layer { box-shadow:0 10px 24px rgba(0,0,0,.18); }
        .pog-hover-none .pog-item:hover img, .pog-hover-none .pog-item:hover .pog-ink-layer { transform:none; box-shadow:none; }

        @supports (mask-image: linear-gradient(#000,#000)) or (-webkit-mask-image: linear-gradient(#000,#000)) {
            .pog-recolor img { display:none; }
        }

        @supports not (aspect-ratio: 1/1) {
            .pog-item::before { content:""; display:block; padding-top:100%; }
            .pog-item > * { position:absolute; inset:0; }
        }
        ';
        wp_add_inline_style('pog-front', $css);
    }

    /* ------------------------------
     * Rendering helpers
     * ------------------------------ */
    private function build_items_from_atts_and_options($atts, $opts) {
        $items = [];
        if ( ! empty($atts['ids']) ) {
            $ids = array_filter(array_map('absint', is_array($atts['ids']) ? $atts['ids'] : explode(',', $atts['ids'])));
            foreach ( $ids as $id ) {
                if ( get_post_mime_type($id) !== 'image/png' ) { continue; }
                $items[] = [ 'id' => $id, 'url' => '', 'product_id' => 0, 'fg' => '', 'bg' => '', 'recolor' => 0, 'label' => '' ];
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
                        'fg'         => isset($row['fg']) ? sanitize_hex_color($row['fg']) : '',
                        'bg'         => isset($row['bg']) ? sanitize_hex_color($row['bg']) : '',
                        'recolor'    => ! empty($row['recolor']) ? 1 : 0,
                        'label'      => isset($row['label']) ? sanitize_text_field($row['label']) : '',
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
        $gap      = max(0, min(64, absint($args['gap'])));
        $overlay  = sanitize_hex_color($args['overlay']) ?: '#000000';
        $bg       = sanitize_hex_color($args['bg']) ?: '#ffffff';
        $size     = sanitize_key($args['size']);
        $target   = in_array($args['target'], ['_self', '_blank'], true) ? $args['target'] : '_self';
        $rel      = sanitize_text_field($args['rel']);
        $hover    = in_array($args['hover'], ['zoom','shadow','none'], true) ? $args['hover'] : 'zoom';
        $cols_sm  = max(1, min(8, isset($args['cols_sm']) ? absint($args['cols_sm']) : 2));
        $cols_md  = max(1, min(12, isset($args['cols_md']) ? absint($args['cols_md']) : 3));
        $cols_lg  = max(1, min(12, isset($args['cols_lg']) ? absint($args['cols_lg']) : 4));

        if ( empty($items) ) {
            return '<div class="pog-gallery-empty">'.esc_html__('No PNG images found for gallery.', 'png-overlay-gallery').'</div>';
        }

        ob_start();
        $style = sprintf('--pog-gap:%1$dpx; --pog-cols-sm:%2$d; --pog-cols-md:%3$d; --pog-cols-lg:%4$d;', $gap, $cols_sm, $cols_md, $cols_lg);
        $hover_class = 'pog-hover-' . $hover;
        ?>
        <div class="pog-gallery <?php echo esc_attr($hover_class); ?>" style="<?php echo esc_attr($style); ?>">
            <?php foreach ($items as $it):
                $src = wp_get_attachment_image_src($it['id'], $size);
                if ( ! $src ) { continue; }
                $alt      = get_post_meta($it['id'], '_wp_attachment_image_alt', true);
                $href     = $this->resolve_href($it);
                $tile_bg  = $it['bg'] ?: $bg;
                $ink      = $it['fg'] ?: '';
                $recolor  = ! empty($it['recolor']);
                $label    = isset($it['label']) ? $it['label'] : '';
                ?>
                <div class="pog-item <?php echo $recolor ? 'pog-recolor' : ''; ?>" style="--pog-bg: <?php echo esc_attr($tile_bg); ?>; <?php echo $recolor && $ink ? '--pog-ink: '.esc_attr($ink).';' : ''; ?>">
                    <?php if ($href): ?><a href="<?php echo esc_url($href); ?>" target="<?php echo esc_attr($target); ?>" rel="<?php echo esc_attr($rel); ?>"<?php echo $label ? ' aria-label="'.esc_attr($label).'"' : ''; ?>><?php endif; ?>
                        <?php if ($recolor && $ink): ?>
                            <span class="pog-ink-layer" style="-webkit-mask-image:url('<?php echo esc_url($src[0]); ?>'); mask-image:url('<?php echo esc_url($src[0]); ?>');"></span>
                        <?php else: ?>
                            <span class="pog-overlay-layer" aria-hidden="true" style="--pog-overlay: <?php echo esc_attr($overlay); ?>;"></span>
                            <img src="<?php echo esc_url($src[0]); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" decoding="async" />
                        <?php endif; ?>
                        <?php if ($label): ?><span class="pog-label"><?php echo esc_html($label); ?></span><?php endif; ?>
                    <?php if ($href): ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ------------------------------
     * Shortcode & Block
     * ------------------------------ */
    public function shortcode($atts = [], $content = null) {
        $opts = get_option(self::OPTION_KEY, []);
        $defaults = [
            'ids'     => '',
            'gap'     => isset($opts['gap']) ? (int)$opts['gap'] : 10,
            'overlay' => isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000',
            'bg'      => isset($opts['background_color']) ? $opts['background_color'] : '#ffffff',
            'size'    => 'large',
            'target'  => '_self',
            'rel'     => 'noopener',
            'hover'   => isset($opts['hover']) ? $opts['hover'] : 'zoom',
            'cols_sm' => isset($opts['columns_sm']) ? (int)$opts['columns_sm'] : 2,
            'cols_md' => isset($opts['columns_md']) ? (int)$opts['columns_md'] : 3,
            'cols_lg' => isset($opts['columns_lg']) ? (int)$opts['columns_lg'] : 4,
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
                    'gap'     => isset($attributes['gap']) ? $attributes['gap'] : (isset($opts['gap']) ? (int)$opts['gap'] : 10),
                    'overlay' => isset($attributes['overlay']) ? $attributes['overlay'] : (isset($opts['overlay_color']) ? $opts['overlay_color'] : '#000000'),
                    'bg'      => isset($attributes['bg']) ? $attributes['bg'] : (isset($opts['background_color']) ? $opts['background_color'] : '#ffffff'),
                    'size'    => isset($attributes['size']) ? $attributes['size'] : 'large',
                    'target'  => isset($attributes['target']) ? $attributes['target'] : '_self',
                    'rel'     => isset($attributes['rel']) ? $attributes['rel'] : 'noopener',
                    'hover'   => isset($attributes['hover']) ? $attributes['hover'] : (isset($opts['hover']) ? $opts['hover'] : 'zoom'),
                    'cols_sm' => isset($attributes['columnsSm']) ? $attributes['columnsSm'] : (isset($opts['columns_sm']) ? (int)$opts['columns_sm'] : 2),
                    'cols_md' => isset($attributes['columnsMd']) ? $attributes['columnsMd'] : (isset($opts['columns_md']) ? (int)$opts['columns_md'] : 3),
                    'cols_lg' => isset($attributes['columnsLg']) ? $attributes['columnsLg'] : (isset($opts['columns_lg']) ? (int)$opts['columns_lg'] : 4),
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
                'ids'       => [ 'type' => 'array', 'items' => [ 'type' => 'number' ], 'default' => [] ],
                'gap'       => [ 'type' => 'number', 'default' => 10 ],
                'overlay'   => [ 'type' => 'string', 'default' => '#000000' ],
                'bg'        => [ 'type' => 'string', 'default' => '#ffffff' ],
                'size'      => [ 'type' => 'string', 'default' => 'large' ],
                'target'    => [ 'type' => 'string', 'default' => '_self' ],
                'rel'       => [ 'type' => 'string', 'default' => 'noopener' ],
                'hover'     => [ 'type' => 'string', 'default' => 'zoom' ],
                'columnsSm' => [ 'type' => 'number', 'default' => 2 ],
                'columnsMd' => [ 'type' => 'number', 'default' => 3 ],
                'columnsLg' => [ 'type' => 'number', 'default' => 4 ],
            ],
            'title'       => __('PNG Overlay Gallery', 'png-overlay-gallery'),
            'description' => __('Responsive PNG gallery with per-image links, recolor, and hover labels.', 'png-overlay-gallery'),
            'category'    => 'widgets',
            'icon'        => 'format-gallery',
            'supports'    => [ 'align' => true ],
        ]);
    }
}

new POG_PNG_Overlay_Gallery();

endif;
