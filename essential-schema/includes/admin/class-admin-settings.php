<?php
// File: includes/admin/class-admin-settings.php
/**
 * Essential Schema – Settings Page + Configurable Fields
 * - Hidden settings page.
 * - Stores policy page IDs in `es_policy_pages`.
 * - Adds fields for organization, returns, shipping, FAQ Q&A.
 * - Safe inputs. No admin notices.
 */

defined('ABSPATH') || exit;

/** Hidden settings page shell. */
class ES_Settings_Page {
    const SLUG = 'es-schema-settings';

    public function __construct(){
        add_action('admin_menu', [$this,'add_settings_page']);
        add_action('admin_menu', [$this,'hide_from_menu'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
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

    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_es-schema-settings') return;
        wp_enqueue_script('es-repeater-js', ES_PLUGIN_URL . 'assets/js/repeater.js', ['jquery'], '1.0', true);
    }
}
new ES_Settings_Page();

/** Registers all configurable fields. */
class ES_Config_Settings {
    public function __construct(){
        add_action('admin_init', [$this, 'register_fields']);
    }

    public function register_fields(){
        // Policy Pages
        register_setting(
            'es_schema_settings',
            'es_policy_pages',
            [ 'sanitize_callback' => [$this, 'sanitize_policy_pages'] ]
        );
        add_settings_section(
            'es_policy_pages',
            esc_html__('Policy Pages','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_page_field('es_policy_pages', 'faq_page_id',      esc_html__('FAQ Page','essential-schema'),            esc_html__('Select your FAQ page. Schema output on this page.','essential-schema'));
        $this->add_page_field('es_policy_pages', 'returns_page_id',  esc_html__('Returns Policy Page','essential-schema'),  esc_html__('Select your Returns Policy page. Used for URL in schema.','essential-schema'));
        $this->add_page_field('es_policy_pages', 'shipping_page_id', esc_html__('Shipping Policy Page','essential-schema'), esc_html__('Select your Shipping Policy page.','essential-schema'));

        // Organization Settings
        register_setting(
            'es_schema_settings',
            'es_org',
            [ 'sanitize_callback' => [$this, 'sanitize_org'] ]
        );
        add_settings_section(
            'es_org',
            esc_html__('Organization Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_select_field('es_org', 'org_type', esc_html__('Organization Type','essential-schema'), ['Organization', 'OnlineStore', 'LocalBusiness'], esc_html__('Choose schema type.','essential-schema'));
        $this->add_text_field('es_org', 'brand_name', esc_html__('Brand Name','essential-schema'), esc_html__('e.g., MyBrand.com','essential-schema'));
        $this->add_text_field('es_org', 'founding_date', esc_html__('Founding Date','essential-schema'), esc_html__('YYYY-MM-DD','essential-schema'));
        $this->add_email_field('es_org', 'contact_email', esc_html__('Contact Email','essential-schema'));
        $this->add_textarea_field('es_org', 'social_links', esc_html__('Social Links','essential-schema'), esc_html__('One URL per line.','essential-schema'));
        $this->add_textarea_field('es_org', 'accepted_payments', esc_html__('Accepted Payments','essential-schema'), esc_html__('One per line, e.g., CreditCard, PayPal','essential-schema'));
        $this->add_textarea_field('es_org', 'domestic_countries', esc_html__('Domestic Countries','essential-schema'), esc_html__('ISO codes, one per line, e.g., US','essential-schema'));
        $this->add_textarea_field('es_org', 'international_countries', esc_html__('International Countries','essential-schema'), esc_html__('ISO codes, one per line.','essential-schema'));

        // Domestic Returns Settings
        register_setting(
            'es_schema_settings',
            'es_domestic_returns',
            [ 'sanitize_callback' => [$this, 'sanitize_domestic_returns'] ]
        );
        add_settings_section(
            'es_domestic_returns',
            esc_html__('Domestic Returns Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_text_field('es_domestic_returns', 'policy_name', esc_html__('Domestic Policy Name','essential-schema'), esc_html__('e.g., Return Policy – US & Territories','essential-schema'));
        $this->add_number_field('es_domestic_returns', 'days', esc_html__('Domestic Return Days','essential-schema'));
        $this->add_select_field('es_domestic_returns', 'fees', esc_html__('Domestic Return Fees','essential-schema'), ['FreeReturn', 'ReturnFeesCustomerResponsibility']);

        // International Returns Settings
        register_setting(
            'es_schema_settings',
            'es_intl_returns',
            [ 'sanitize_callback' => [$this, 'sanitize_intl_returns'] ]
        );
        add_settings_section(
            'es_intl_returns',
            esc_html__('International Returns Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_text_field('es_intl_returns', 'policy_name', esc_html__('International Policy Name','essential-schema'), esc_html__('e.g., Return Policy – International','essential-schema'));
        $this->add_number_field('es_intl_returns', 'days', esc_html__('International Return Days','essential-schema'));
        $this->add_select_field('es_intl_returns', 'fees', esc_html__('International Return Fees','essential-schema'), ['FreeReturn', 'ReturnFeesCustomerResponsibility']);

        // FAQ Settings
        register_setting(
            'es_schema_settings',
            'es_faq',
            [ 'sanitize_callback' => [$this, 'sanitize_faq'] ]
        );
        add_settings_section(
            'es_faq',
            esc_html__('FAQ Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_repeater_field('es_faq', 'faq_items', esc_html__('FAQ Items','essential-schema'));

        // Shipping Settings
        register_setting(
            'es_schema_settings',
            'es_shipping',
            [ 'sanitize_callback' => [$this, 'sanitize_shipping'] ]
        );
        add_settings_section(
            'es_shipping',
            esc_html__('Shipping Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_url_field('es_shipping', 'policy_url', esc_html__('Shipping Policy URL','essential-schema'));
        $this->add_number_field('es_shipping', 'rate', esc_html__('Shipping Rate','essential-schema'), '', '0.01');
        $this->add_text_field('es_shipping', 'currency', esc_html__('Currency','essential-schema'), esc_html__('e.g., USD','essential-schema'));
        $this->add_number_field('es_shipping', 'handling_min', esc_html__('Handling Min Days','essential-schema'));
        $this->add_number_field('es_shipping', 'handling_max', esc_html__('Handling Max Days','essential-schema'));
        $this->add_number_field('es_shipping', 'transit_min', esc_html__('Transit Min Days','essential-schema'));
        $this->add_number_field('es_shipping', 'transit_max', esc_html__('Transit Max Days','essential-schema'));
        $this->add_text_field('es_shipping', 'description', esc_html__('Shipping Description','essential-schema'));
    }

    private function add_page_field($opt, $key, $label, $desc){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc){
                $opts     = get_option($opt);
                $selected = isset($opts[$key]) ? absint($opts[$key]) : 0;

                $html = wp_dropdown_pages([
                    'name'              => esc_attr( $opt . '[' . sanitize_key($key) . ']' ),
                    'selected'          => absint($selected),
                    'show_option_none'  => esc_html__('— Select a Page —','essential-schema'),
                    'option_none_value' => '',
                    'post_status'       => 'publish',
                    'sort_column'       => 'post_title',
                    'sort_order'        => 'ASC',
                    'echo'              => 0,
                ]);

                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_text_field($opt, $key, $label, $desc=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc){
                $val = get_option($opt)[$key] ?? '';
                printf('<input type="text" name="%s[%s]" value="%s" class="regular-text">', esc_attr($opt), esc_attr($key), esc_attr($val));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_email_field($opt, $key, $label, $desc=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc){
                $val = get_option($opt)[$key] ?? '';
                printf('<input type="email" name="%s[%s]" value="%s" class="regular-text">', esc_attr($opt), esc_attr($key), esc_attr($val));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_url_field($opt, $key, $label, $desc=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc){
                $val = get_option($opt)[$key] ?? '';
                printf('<input type="url" name="%s[%s]" value="%s" class="regular-text">', esc_attr($opt), esc_attr($key), esc_attr($val));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_number_field($opt, $key, $label, $desc='', $step='1'){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc, $step){
                $val = get_option($opt)[$key] ?? '';
                printf('<input type="number" name="%s[%s]" value="%s" min="0" step="%s">', esc_attr($opt), esc_attr($key), esc_attr($val), esc_attr($step));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_select_field($opt, $key, $label, $options, $desc=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $options, $desc){
                $val = get_option($opt)[$key] ?? '';
                echo '<select name="' . esc_attr($opt . '[' . $key . ']') . '">';
                foreach($options as $o){
                    printf('<option value="%s"%s>%s</option>', esc_attr($o), selected($val, $o, false), esc_html($o));
                }
                echo '</select>';
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_textarea_field($opt, $key, $label, $desc=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc){
                $val = get_option($opt)[$key] ?? '';
                printf('<textarea name="%s[%s]" rows="5" cols="50">%s</textarea>', esc_attr($opt), esc_attr($key), esc_textarea($val));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_repeater_field($opt, $key, $label){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key){
                $val = get_option($opt)[$key] ?? [];
                if (!is_array($val)) $val = [];
                ?>
                <div class="es-repeater" data-key="<?php echo esc_attr($key); ?>">
                    <?php foreach ($val as $i => $item): ?>
                        <div class="es-repeater-item">
                            <p><label><?php esc_html_e('Question', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][question]'); ?>" value="<?php echo esc_attr($item['question'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Answer', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][answer]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['answer'] ?? ''); ?></textarea></p>
                            <button type="button" class="button es-remove-item"><?php esc_html_e('Remove', 'essential-schema'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="button es-add-item"><?php esc_html_e('Add FAQ Item', 'essential-schema'); ?></button>
                </div>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }

    public function sanitize_policy_pages($input){
        $out = [];
        foreach (['faq_page_id','returns_page_id','shipping_page_id'] as $k){
            $out[$k] = isset($input[$k]) ? absint($input[$k]) : 0;
        }
        return $out;
    }

    public function sanitize_org($input){
        $out = [];
        $out['org_type'] = in_array($input['org_type'] ?? '', ['Organization', 'OnlineStore', 'LocalBusiness']) ? $input['org_type'] : 'OnlineStore';
        $out['brand_name'] = sanitize_text_field($input['brand_name'] ?? '');
        $out['founding_date'] = sanitize_text_field($input['founding_date'] ?? '');
        $out['contact_email'] = sanitize_email($input['contact_email'] ?? '');
        $out['social_links'] = sanitize_textarea_field($input['social_links'] ?? '');
        $out['accepted_payments'] = sanitize_textarea_field($input['accepted_payments'] ?? '');
        $out['domestic_countries'] = sanitize_textarea_field($input['domestic_countries'] ?? '');
        $out['international_countries'] = sanitize_textarea_field($input['international_countries'] ?? '');
        return $out;
    }

    public function sanitize_domestic_returns($input){
        $out = [];
        $out['policy_name'] = sanitize_text_field($input['policy_name'] ?? '');
        $out['days'] = absint($input['days'] ?? 0);
        $out['fees'] = in_array($input['fees'] ?? '', ['FreeReturn', 'ReturnFeesCustomerResponsibility']) ? $input['fees'] : 'FreeReturn';
        return $out;
    }

    public function sanitize_intl_returns($input){
        $out = [];
        $out['policy_name'] = sanitize_text_field($input['policy_name'] ?? '');
        $out['days'] = absint($input['days'] ?? 0);
        $out['fees'] = in_array($input['fees'] ?? '', ['FreeReturn', 'ReturnFeesCustomerResponsibility']) ? $input['fees'] : 'ReturnFeesCustomerResponsibility';
        return $out;
    }

    public function sanitize_faq($input){
        $out = [];
        $out['faq_items'] = [];
        if (isset($input['faq_items']) && is_array($input['faq_items'])) {
            foreach ($input['faq_items'] as $item) {
                $out['faq_items'][] = [
                    'question' => sanitize_text_field($item['question'] ?? ''),
                    'answer' => wp_kses_post($item['answer'] ?? ''),
                ];
            }
        }
        return $out;
    }

    public function sanitize_shipping($input){
        $out = [];
        $out['policy_url'] = esc_url_raw($input['policy_url'] ?? '');
        $out['rate'] = floatval($input['rate'] ?? 0);
        $out['currency'] = sanitize_text_field($input['currency'] ?? 'USD');
        $out['handling_min'] = absint($input['handling_min'] ?? 0);
        $out['handling_max'] = absint($input['handling_max'] ?? 0);
        $out['transit_min'] = absint($input['transit_min'] ?? 0);
        $out['transit_max'] = absint($input['transit_max'] ?? 0);
        $out['description'] = sanitize_text_field($input['description'] ?? '');
        return $out;
    }
}
new ES_Config_Settings();