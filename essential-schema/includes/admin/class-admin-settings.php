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
        wp_enqueue_script('es-shipping-repeater-js', ES_PLUGIN_URL . 'assets/js/shipping-repeater.js', ['jquery'], '1.0', true);
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
        $this->add_select_field('es_org', 'org_type', esc_html__('Organization Type','essential-schema'), ['Organization', 'OnlineStore'], esc_html__('Choose schema type.','essential-schema'));
        $this->add_text_field('es_org', 'telephone', esc_html__('Telephone','essential-schema'), esc_html__('e.g., +1-800-123-4567','essential-schema'));
        $this->add_email_field('es_org', 'contact_email', esc_html__('Contact Email','essential-schema'));
        $this->add_text_field('es_org', 'street_address', esc_html__('Street Address','essential-schema'));
        $this->add_text_field('es_org', 'address_locality', esc_html__('City','essential-schema'));
        $this->add_text_field('es_org', 'address_region', esc_html__('State/Province','essential-schema'));
        $this->add_text_field('es_org', 'postal_code', esc_html__('Postal Code','essential-schema'));
        $this->add_text_field('es_org', 'address_country', esc_html__('Country','essential-schema'), esc_html__('e.g., US','essential-schema'));
        $this->add_textarea_field('es_org', 'social_links', esc_html__('Social Links','essential-schema'), esc_html__('One URL per line.','essential-schema'));

        // Return Policies Settings
        register_setting(
            'es_schema_settings',
            'es_return_policies',
            [ 'sanitize_callback' => [$this, 'sanitize_return_policies'] ]
        );
        add_settings_section(
            'es_return_policies',
            esc_html__('Return Policies','essential-schema'),
            [$this, 'section_callback_returns'],
            'es_schema_settings'
        );
        $this->add_returns_repeater_field('es_return_policies', 'return_policies', esc_html__('Return Policies','essential-schema'));

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
        $this->add_shipping_repeater_field('es_shipping', 'shipping_items', esc_html__('Shipping Profiles','essential-schema'));
    }

    public function section_callback_returns() {
        echo '<p>' . esc_html__('Fill only relevant fields based on the return policy category; empty or irrelevant fields will be ignored in schema output.', 'essential-schema') . '</p>';
    }

    // Helper functions (as per original, with updates for sanitize)
    public function sanitize_policy_pages($input) {
        $sanitized = [];
        foreach (['faq_page_id', 'returns_page_id', 'shipping_page_id'] as $key) {
            $sanitized[$key] = isset($input[$key]) ? absint($input[$key]) : 0;
        }
        return $sanitized;
    }

    public function sanitize_org($input) {
        $sanitized = [];
        $sanitized['org_type'] = isset($input['org_type']) && in_array($input['org_type'], ['Organization', 'OnlineStore']) ? $input['org_type'] : 'Organization';
        $sanitized['telephone'] = isset($input['telephone']) ? sanitize_text_field($input['telephone']) : '';
        $sanitized['contact_email'] = isset($input['contact_email']) ? sanitize_email($input['contact_email']) : '';
        $sanitized['street_address'] = isset($input['street_address']) ? sanitize_text_field($input['street_address']) : '';
        $sanitized['address_locality'] = isset($input['address_locality']) ? sanitize_text_field($input['address_locality']) : '';
        $sanitized['address_region'] = isset($input['address_region']) ? sanitize_text_field($input['address_region']) : '';
        $sanitized['postal_code'] = isset($input['postal_code']) ? sanitize_text_field($input['postal_code']) : '';
        $sanitized['address_country'] = isset($input['address_country']) ? sanitize_text_field($input['address_country']) : '';
        $sanitized['social_links'] = isset($input['social_links']) ? sanitize_textarea_field($input['social_links']) : '';
        return $sanitized;
    }

    public function sanitize_return_policies($input){
        $out = [];
        $out['return_policies'] = [];
        if (isset($input['return_policies']) && is_array($input['return_policies'])) {
            foreach ($input['return_policies'] as $policy) {
                $sanitized_policy = [];
                $sanitized_policy['policy_name'] = sanitize_text_field($policy['policy_name'] ?? '');
                $sanitized_policy['return_policy_category'] = in_array($policy['return_policy_category'] ?? '', ['MerchantReturnFiniteReturnWindow', 'MerchantReturnUnlimitedWindow', 'MerchantReturnNotPermitted']) ? $policy['return_policy_category'] : 'MerchantReturnFiniteReturnWindow';
                if ($sanitized_policy['return_policy_category'] === 'MerchantReturnFiniteReturnWindow') {
                    $sanitized_policy['days'] = absint($policy['days'] ?? 0);
                }
                if ($sanitized_policy['return_policy_category'] !== 'MerchantReturnNotPermitted') {
                    $sanitized_policy['fees'] = in_array($policy['fees'] ?? '', ['FreeReturn', 'ReturnFeesCustomerResponsibility']) ? $policy['fees'] : 'FreeReturn';
                    $sanitized_policy['refund_type'] = in_array($policy['refund_type'] ?? '', ['FullRefund', 'StoreCreditRefund', 'MoneyBackOrReplacement']) ? $policy['refund_type'] : 'FullRefund';
                    $allowed_methods = ['ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk'];
                    $sanitized_policy['return_method'] = array_filter(array_map('sanitize_text_field', (array) ($policy['return_method'] ?? [])), function($v) use ($allowed_methods) { return in_array($v, $allowed_methods); });
                    if (empty($sanitized_policy['return_method'])) $sanitized_policy['return_method'] = ['ReturnByMail'];
                }
                $sanitized_policy['description'] = sanitize_textarea_field($policy['description'] ?? '');
                $sanitized_policy['countries'] = sanitize_textarea_field($policy['countries'] ?? '');
                $out['return_policies'][] = $sanitized_policy;
            }
        }
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
        $out['shipping_items'] = [];
        if (isset($input['shipping_items']) && is_array($input['shipping_items'])) {
            foreach ($input['shipping_items'] as $item) {
                $out['shipping_items'][] = [
                    'rate' => floatval($item['rate'] ?? 0),
                    'currency' => sanitize_text_field($item['currency'] ?? 'USD'),
                    'handling_min' => absint($item['handling_min'] ?? 0),
                    'handling_max' => absint($item['handling_max'] ?? 0),
                    'transit_min' => absint($item['transit_min'] ?? 0),
                    'transit_max' => absint($item['transit_max'] ?? 0),
                    'description' => sanitize_text_field($item['description'] ?? ''),
                    'countries' => sanitize_textarea_field($item['countries'] ?? ''),
                ];
            }
        }
        return $out;
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
                    'show_option_none'  => esc_html__( '— Select a Page —', 'essential-schema' ),
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

    private function add_number_field($opt, $key, $label, $desc='', $step='1', $wrap_class=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $desc, $step, $wrap_class){
                $val = get_option($opt)[$key] ?? '';
                $wrap_start = $wrap_class ? '<div class="' . esc_attr($wrap_class) . '">' : '';
                $wrap_end = $wrap_class ? '</div>' : '';
                echo wp_kses_post($wrap_start);
                printf('<input type="number" name="%s[%s]" value="%s" min="0" step="%s">', esc_attr($opt), esc_attr($key), esc_attr($val), esc_attr($step));
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
                echo wp_kses_post($wrap_end);
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_select_field($opt, $key, $label, $options, $desc='', $id='', $wrap_class=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $options, $desc, $id, $wrap_class){
                $val = get_option($opt)[$key] ?? $options[0];
                $id_attr = $id ? ' id="' . esc_attr($id) . '"' : '';
                $wrap_start = $wrap_class ? '<div class="' . esc_attr($wrap_class) . '">' : '';
                $wrap_end = $wrap_class ? '</div>' : '';
                echo wp_kses_post($wrap_start);
                echo '<select name="' . esc_attr($opt . '[' . $key . ']') . '"' . $id_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                foreach($options as $o){
                    printf('<option value="%s"%s>%s</option>', esc_attr($o), selected($val, $o, false), esc_html($o));
                }
                echo '</select>';
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
                echo wp_kses_post($wrap_end);
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

    private function add_checkbox_field($opt, $key, $label, $options, $desc='', $wrap_class=''){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key, $options, $desc, $wrap_class){
                $vals = (array) (get_option($opt)[$key] ?? []);
                $wrap_start = $wrap_class ? '<div class="' . esc_attr($wrap_class) . '">' : '';
                $wrap_end = $wrap_class ? '</div>' : '';
                echo wp_kses_post($wrap_start);
                foreach($options as $o){
                    $checked = in_array($o, $vals) ? 'checked' : '';
                    printf('<label><input type="checkbox" name="%s[%s][]" value="%s" %s> %s</label><br>', esc_attr($opt), esc_attr($key), esc_attr($o), esc_attr($checked), esc_html($o));
                }
                if($desc) printf('<p class="description">%s</p>', esc_html($desc));
                echo wp_kses_post($wrap_end);
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

    private function add_shipping_repeater_field($opt, $key, $label){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key){
                $val = get_option($opt)[$key] ?? [];
                if (!is_array($val)) $val = [];
                ?>
                <div class="es-shipping-repeater" data-key="<?php echo esc_attr($key); ?>">
                    <?php foreach ($val as $i => $item): ?>
                        <div class="es-shipping-repeater-item">
                            <p><label><?php esc_html_e('Shipping Rate', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][rate]'); ?>" value="<?php echo esc_attr($item['rate'] ?? ''); ?>" min="0" step="0.01" class="regular-text"></p>
                            <p><label><?php esc_html_e('Currency', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][currency]'); ?>" value="<?php echo esc_attr($item['currency'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Handling Min Days', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][handling_min]'); ?>" value="<?php echo esc_attr($item['handling_min'] ?? ''); ?>" min="0" class="regular-text"></p>
                            <p><label><?php esc_html_e('Handling Max Days', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][handling_max]'); ?>" value="<?php echo esc_attr($item['handling_max'] ?? ''); ?>" min="0" class="regular-text"></p>
                            <p><label><?php esc_html_e('Transit Min Days', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][transit_min]'); ?>" value="<?php echo esc_attr($item['transit_min'] ?? ''); ?>" min="0" class="regular-text"></p>
                            <p><label><?php esc_html_e('Transit Max Days', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][transit_max]'); ?>" value="<?php echo esc_attr($item['transit_max'] ?? ''); ?>" min="0" class="regular-text"></p>
                            <p><label><?php esc_html_e('Shipping Description', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][description]'); ?>" value="<?php echo esc_attr($item['description'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Shipping Countries', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][countries]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['countries'] ?? ''); ?></textarea></p>
                            <button type="button" class="button es-shipping-remove-item"><?php esc_html_e('Remove Profile', 'essential-schema'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="button es-shipping-add-item"><?php esc_html_e('Add Shipping Profile', 'essential-schema'); ?></button>
                </div>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_returns_repeater_field($opt, $key, $label){
        add_settings_field(
            $key,
            $label,
            function() use ($opt, $key){
                $val = get_option($opt)[$key] ?? [];
                if (!is_array($val)) $val = [];
                ?>
                <div class="es-returns-repeater" data-key="<?php echo esc_attr($key); ?>">
                    <?php foreach ($val as $i => $item): ?>
                        <div class="es-returns-repeater-item">
                            <p><label><?php esc_html_e('Policy Name', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][policy_name]'); ?>" value="<?php echo esc_attr($item['policy_name'] ?? ''); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('e.g., Return Policy – US & Territories', 'essential-schema'); ?></p></p>
                            <p><label><?php esc_html_e('Return Policy Category', 'essential-schema'); ?></label><br>
                            <select name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][return_policy_category]'); ?>">
                                <?php
                                $categories = ['MerchantReturnFiniteReturnWindow', 'MerchantReturnUnlimitedWindow', 'MerchantReturnNotPermitted'];
                                $selected_cat = $item['return_policy_category'] ?? $categories[0];
                                foreach ($categories as $cat) {
                                    $selected = selected($selected_cat, $cat, false);
                                    echo '<option value="' . esc_attr($cat) . '" ' . $selected . '>' . esc_html($cat) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                ?>
                            </select></p>
                            <p><label><?php esc_html_e('Return Days', 'essential-schema'); ?></label><br>
                            <input type="number" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][days]'); ?>" value="<?php echo esc_attr($item['days'] ?? ''); ?>" min="0" step="1" class="regular-text"></p>
                            <p><label><?php esc_html_e('Return Fees', 'essential-schema'); ?></label><br>
                            <select name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][fees]'); ?>">
                                <?php
                                $fees_options = ['FreeReturn', 'ReturnFeesCustomerResponsibility'];
                                $selected_fees = $item['fees'] ?? $fees_options[0];
                                foreach ($fees_options as $fee) {
                                    $selected = selected($selected_fees, $fee, false);
                                    echo '<option value="' . esc_attr($fee) . '" ' . $selected . '>' . esc_html($fee) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                ?>
                            </select></p>
                            <p><label><?php esc_html_e('Refund Type', 'essential-schema'); ?></label><br>
                            <select name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][refund_type]'); ?>">
                                <?php
                                $refund_options = ['FullRefund', 'StoreCreditRefund', 'MoneyBackOrReplacement'];
                                $selected_refund = $item['refund_type'] ?? $refund_options[0];
                                foreach ($refund_options as $refund) {
                                    $selected = selected($selected_refund, $refund, false);
                                    echo '<option value="' . esc_attr($refund) . '" ' . $selected . '>' . esc_html($refund) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                ?>
                            </select></p>
                            <p><label><?php esc_html_e('Return Methods', 'essential-schema'); ?></label><br>
                            <?php
                            $methods = ['ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk'];
                            $selected_methods = (array) ($item['return_method'] ?? []);
                            foreach ($methods as $method) {
                                $checked = in_array($method, $selected_methods) ? 'checked' : '';
                                echo '<label><input type="checkbox" name="' . esc_attr($opt . '[' . $key . '][' . $i . '][return_method][]') . '" value="' . esc_attr($method) . '" ' . $checked . '> ' . esc_html($method) . '</label><br>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                            <p class="description"><?php esc_html_e('Select all that apply.', 'essential-schema'); ?></p></p>
                            <p><label><?php esc_html_e('Return Description', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][description]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('e.g., Free returns within 30 days.', 'essential-schema'); ?></p></p>
                            <p><label><?php esc_html_e('Applicable Countries', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][countries]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['countries'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('One ISO country code per line, e.g., US', 'essential-schema'); ?></p></p>
                            <button type="button" class="button es-returns-remove-item"><?php esc_html_e('Remove Policy', 'essential-schema'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="button es-returns-add-item"><?php esc_html_e('Add Return Policy', 'essential-schema'); ?></button>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.es-returns-repeater').on('click', '.es-returns-add-item', function() {
                            var $repeater = $(this).closest('.es-returns-repeater');
                            var $lastItem = $repeater.find('.es-returns-repeater-item').last();
                            var $newItem;
                            if ($lastItem.length === 0) {
                                $newItem = $('<div class="es-returns-repeater-item">' +
                                    '<p><label>Policy Name</label><br>' +
                                    '<input type="text" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][policy_name]'); ?>" value="" class="regular-text">' +
                                    '<p class="description">e.g., Return Policy – US & Territories</p></p>' +
                                    '<p><label>Return Policy Category</label><br>' +
                                    '<select name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][return_policy_category]'); ?>">' +
                                    '<option value="MerchantReturnFiniteReturnWindow">MerchantReturnFiniteReturnWindow</option>' +
                                    '<option value="MerchantReturnUnlimitedWindow">MerchantReturnUnlimitedWindow</option>' +
                                    '<option value="MerchantReturnNotPermitted">MerchantReturnNotPermitted</option>' +
                                    '</select></p>' +
                                    '<p><label>Return Days</label><br>' +
                                    '<input type="number" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][days]'); ?>" value="" min="0" step="1" class="regular-text"></p>' +
                                    '<p><label>Return Fees</label><br>' +
                                    '<select name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][fees]'); ?>">' +
                                    '<option value="FreeReturn">FreeReturn</option>' +
                                    '<option value="ReturnFeesCustomerResponsibility">ReturnFeesCustomerResponsibility</option>' +
                                    '</select></p>' +
                                    '<p><label>Refund Type</label><br>' +
                                    '<select name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][refund_type]'); ?>">' +
                                    '<option value="FullRefund">FullRefund</option>' +
                                    '<option value="StoreCreditRefund">StoreCreditRefund</option>' +
                                    '<option value="MoneyBackOrReplacement">MoneyBackOrReplacement</option>' +
                                    '</select></p>' +
                                    '<p><label>Return Methods</label><br>' +
                                    '<label><input type="checkbox" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][return_method][]'); ?>" value="ReturnByMail"> ReturnByMail</label><br>' +
                                    '<label><input type="checkbox" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][return_method][]'); ?>" value="ReturnInStore"> ReturnInStore</label><br>' +
                                    '<label><input type="checkbox" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][return_method][]'); ?>" value="ReturnAtKiosk"> ReturnAtKiosk</label><br>' +
                                    '<p class="description">Select all that apply.</p></p>' +
                                    '<p><label>Return Description</label><br>' +
                                    '<textarea name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][description]'); ?>" rows="5" class="regular-text"></textarea>' +
                                    '<p class="description">e.g., Free returns within 30 days.</p></p>' +
                                    '<p><label>Applicable Countries</label><br>' +
                                    '<textarea name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][countries]'); ?>" rows="5" class="regular-text"></textarea>' +
                                    '<p class="description">One ISO country code per line, e.g., US</p></p>' +
                                    '<button type="button" class="button es-returns-remove-item">Remove Policy</button>' +
                                    '</div>');
                            } else {
                                $newItem = $lastItem.clone(true);
                                $newItem.find('input[type="text"], input[type="number"], textarea').val('');
                                $newItem.find('select').val($newItem.find('select option:first').val());
                                $newItem.find('input[type="checkbox"]').prop('checked', false);
                            }
                            var index = $repeater.find('.es-returns-repeater-item').length;
                            $newItem.find('[name]').each(function() {
                                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', name);
                            });
                            $repeater.find('.es-returns-add-item').before($newItem);
                        });

                        $('.es-returns-repeater').on('click', '.es-returns-remove-item', function() {
                            if ($('.es-returns-repeater-item').length > 0) {
                                $(this).closest('.es-returns-repeater-item').remove();
                            }
                        });
                    });
                </script>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }
}
new ES_Config_Settings();