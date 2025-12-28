<?php
/**
 * Essential Schema – Settings Page + Policy Page Selectors
 * - Hidden settings page.
 * - Stores faq_page_id, returns_page_id, shipping_page_id in option `es_policy_pages`.
 * - Safe dropdown rendering. No admin notices.
 */

defined('ABSPATH') || exit;

/** Hidden settings page shell. */
class ES_Settings_Page {
    const SLUG = 'es-schema-settings';

    public function __construct(){
        add_action('admin_menu', [$this,'add_settings_page']);
        add_action('admin_menu', [$this,'hide_from_menu'], 999);
    }

    public function add_settings_page(){
        add_options_page(
            __('Essential Schema','essential-schema'),
            __('Essential Schema','essential-schema'),
            'manage_options',
            self::SLUG,
            [$this,'render_settings_page']
        );
    }

    public function hide_from_menu(){
        remove_submenu_page('options-general.php', self::SLUG);
    }

    public function render_settings_page(){
        if ( ! current_user_can('manage_options') ) return; ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Essential Schema Settings','essential-schema'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('es_schema_settings');
                do_settings_sections('es_schema_settings');
                submit_button();
                ?>
            </form>
        </div>
    <?php }
}
new ES_Settings_Page();

/** Registers FAQ / Returns / Shipping dropdowns into `es_policy_pages`. */
class ES_Policy_Page_Settings {
    private static $option_key = 'es_policy_pages';

    public function __construct(){
        add_action('admin_init', [$this, 'register_policy_fields']);
    }

    public function register_policy_fields(){
        register_setting(
            'es_schema_settings',
            self::$option_key,
            [ 'sanitize_callback' => [$this, 'sanitize'] ]
        );

        add_settings_section(
            'es_policy_pages',
            esc_html__('Policy Pages','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );

        $this->add_page_field('faq_page_id',      esc_html__('FAQ Page','essential-schema'),            esc_html__('Select your FAQ page. Schema disabled if empty.','essential-schema'));
        $this->add_page_field('returns_page_id',  esc_html__('Returns Policy Page','essential-schema'),  esc_html__('Select your Returns Policy page. Schema disabled if empty.','essential-schema'));
        $this->add_page_field('shipping_page_id', esc_html__('Shipping Policy Page','essential-schema'), esc_html__('Select your Shipping Policy page. Schema disabled if empty.','essential-schema'));
    }

    /** Add a single safe wp_dropdown_pages field. */
    private function add_page_field($field_key, $label, $desc){
        add_settings_field(
            $field_key,
            $label,
            function() use ($field_key, $desc){
                $opts     = get_option(self::$option_key);
                $selected = isset($opts[$field_key]) ? absint($opts[$field_key]) : 0;

                $html = wp_dropdown_pages([
                    'name'              => esc_attr( self::$option_key . '[' . sanitize_key($field_key) . ']' ),
                    'selected'          => absint($selected),
                    'show_option_none'  => esc_html__('— Select a Page —','essential-schema'),
                    'option_none_value' => '',
                    'post_status'       => 'publish',
                    'sort_column'       => 'post_title',
                    'sort_order'        => 'ASC',
                    'echo'              => 0,
                ]);

                /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated <select>/<option> */
                echo $html;

                printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            'es_policy_pages'
        );
    }

    /** Sanitize stored page IDs. No notices. */
    public function sanitize($input){
        $out  = [];
        foreach (['faq_page_id','returns_page_id','shipping_page_id'] as $k){
            $out[$k] = isset($input[$k]) ? absint($input[$k]) : 0;
        }
        return $out;
    }
}
new ES_Policy_Page_Settings();