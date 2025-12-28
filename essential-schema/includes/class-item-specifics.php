<?php
defined('ABSPATH') || exit;

/**
 * Class SB_Item_Specifics
 * Lean: injects a custom table only when per-product meta exists; otherwise Woo prints native content.
 */
class SB_Item_Specifics {

    const META_CONTENT = '_sb_item_specifics';

    /** Hooks. */
    public static function init() {
        add_action('add_meta_boxes_product', [__CLASS__, 'add_meta_box']);
        add_action('save_post_product', [__CLASS__, 'save_meta'], 10, 2);
        add_action('woocommerce_product_additional_information', [__CLASS__, 'inject_table'], 5, 1);
    }

    /** Meta box. */
    public static function add_meta_box() {
        add_meta_box(
            'sb_item_specifics',
            __('Item specifics', 'essential-schema'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'normal',
            'default'
        );
    }

    /** Meta box UI. */
    public static function render_meta_box($post) {
        wp_nonce_field('sb_item_specifics_nonce', 'sb_item_specifics_nonce');

        $content = get_post_meta($post->ID, self::META_CONTENT, true);

        echo '<p><label for="sb_item_specifics_content"><strong>' . esc_html__('Item specifics content', 'essential-schema') . '</strong></label></p>';
        echo '<textarea id="sb_item_specifics_content" name="sb_item_specifics_content" rows="10" style="width:100%;">' . esc_textarea($content) . '</textarea>';
        echo '<p class="description">' . esc_html__('One per line: Label: Value. Lists comma-separated. Basic HTML allowed in values.', 'essential-schema') . '</p>';
    }

    /** Save. */
    public static function save_meta($post_id, $post) {
        if ( ! isset($_POST['sb_item_specifics_nonce']) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['sb_item_specifics_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'sb_item_specifics_nonce' ) ) {
            return;
        }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }
        if ( 'product' !== $post->post_type ) {
            return;
        }
        if ( ! current_user_can('edit_post', $post_id) ) {
            return;
        }

        $content = isset($_POST['sb_item_specifics_content'])
            ? wp_kses_post( wp_unslash( $_POST['sb_item_specifics_content'] ) )
            : '';

        if ( $content ) {
            update_post_meta($post_id, self::META_CONTENT, $content);
        } else {
            delete_post_meta($post_id, self::META_CONTENT);
        }
    }

    /**
     * Inject custom table inside Woo’s Additional Information template.
     * Runs only when meta exists; otherwise native output remains.
     */
    public static function inject_table( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $product_id = $product->get_id();
        $content    = get_post_meta($product_id, self::META_CONTENT, true);

        if ( empty($content) ) {
            return; // Keep native attributes.
        }

        $pairs = self::parse_label_value_lines($content);
        if ( empty($pairs) ) {
            return; // Keep native attributes.
        }

        // Replace native attributes for this render.
        remove_action('woocommerce_product_additional_information', 'wc_display_product_attributes', 10);

        // Output table; styling comes from assets/css/item-specifics.css.
        echo '<table class="item-specs-table"><tbody>';
        foreach ( $pairs as $pair ) {
            echo '<tr><th>' . esc_html( $pair['label'] ) . '</th><td>' . wp_kses_post( $pair['value'] ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** Parse “Label: Value” lines. */
    protected static function parse_label_value_lines(string $content) : array {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $out   = [];

        foreach ( $lines as $line ) {
            $line = trim($line);
            if ( $line === '' ) {
                continue;
            }
            $parts = explode(':', $line, 2);
            if ( count($parts) !== 2 ) {
                continue;
            }
            $label = trim($parts[0]);
            $value = trim($parts[1]);
            if ( $label === '' || $value === '' ) {
                continue;
            }
            $out[] = ['label' => $label, 'value' => $value];
        }

        return $out;
    }
}

SB_Item_Specifics::init();