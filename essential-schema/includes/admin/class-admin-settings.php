<?php
// File: includes/admin/class-admin-settings.php
/**
 * Essential Schema – Settings Page + Configurable Fields
 * - Hidden settings page.
 * - Stores policy page IDs in `es_policy_pages`.
 * - Adds fields for organization, returns, shipping, FAQs, Articles.
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
    }
}
new ES_Settings_Page();

/** Registers all configurable fields. */
class ES_Config_Settings {
    public function __construct(){
        add_action('admin_init', [$this, 'register_fields']);
    }

    public function register_fields(){
        // General Settings
        register_setting(
            'es_schema_settings',
            'es_general',
            [ 'sanitize_callback' => [$this, 'sanitize_general'] ]
        );
        add_settings_section(
            'es_general',
            esc_html__('General Settings','essential-schema'),
            '__return_false',
            'es_schema_settings'
        );
        $this->add_checkbox_field('es_general', 'enable_article_schema', esc_html__('Enable Article Schema','essential-schema'), esc_html__('Output article schema on single blog posts.','essential-schema'));

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

    private function add_checkbox_field($opt, $key, $label, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? false;
                echo '<input type="checkbox" name="' . esc_attr($opt . '[' . $key . ']') . '" ' . checked($value, true, false) . ' />';
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_page_field($opt, $key, $label, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? 0;
                wp_dropdown_pages([
                    'name' => esc_attr($opt . '[' . $key . ']'),
                    'selected' => esc_attr($value),
                    'show_option_none' => esc_html__('None', 'essential-schema'),
                    'option_none_value' => 0,
                ]);
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_select_field($opt, $key, $label, $choices, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $choices, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? $choices[0];
                echo '<select name="' . esc_attr($opt . '[' . $key . ']') . '">';
                foreach ($choices as $choice) {
                    echo '<option value="' . esc_attr($choice) . '" ' . selected($value, $choice, false) . '>' . esc_html($choice) . '</option>';
                }
                echo '</select>';
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_text_field($opt, $key, $label, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? '';
                echo '<input type="text" name="' . esc_attr($opt . '[' . $key . ']') . '" value="' . esc_attr($value) . '" class="regular-text">';
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_email_field($opt, $key, $label, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? '';
                echo '<input type="email" name="' . esc_attr($opt . '[' . $key . ']') . '" value="' . esc_attr($value) . '" class="regular-text">';
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_textarea_field($opt, $key, $label, $desc = '') {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key, $desc) {
                $options = get_option($opt, []);
                $value = $options[$key] ?? '';
                echo '<textarea name="' . esc_attr($opt . '[' . $key . ']') . '" rows="5" class="regular-text">' . esc_textarea($value) . '</textarea>';
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_repeater_field($opt, $key, $label) {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key) {
                $options = get_option($opt, []);
                $items = $options[$key] ?? [];
                ?>
                <div class="es-repeater" role="region" aria-label="<?php esc_attr_e('FAQ Items Repeater', 'essential-schema'); ?>">
                    <?php foreach ($items as $i => $item): ?>
                        <div class="es-repeater-item">
                            <p><label><?php esc_html_e('Question', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][question]'); ?>" value="<?php echo esc_attr($item['question'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Answer', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][answer]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['answer'] ?? ''); ?></textarea></p>
                            <button type="button" class="button es-remove-item"><?php esc_html_e('Remove Item', 'essential-schema'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="button es-add-item"><?php esc_html_e('Add FAQ Item', 'essential-schema'); ?></button>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.es-repeater').on('click', '.es-add-item', function() {
                            var $repeater = $(this).closest('.es-repeater');
                            var $lastItem = $repeater.find('.es-repeater-item').last();
                            var $newItem;
                            if ($lastItem.length === 0) {
                                $newItem = $('<div class="es-repeater-item">' +
                                    '<p><label>Question</label><br>' +
                                    '<input type="text" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][question]'); ?>" class="regular-text"></p>' +
                                    '<p><label>Answer</label><br>' +
                                    '<textarea name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][answer]'); ?>" rows="5" class="regular-text"></textarea></p>' +
                                    '<button type="button" class="button es-remove-item">Remove Item</button>' +
                                    '</div>');
                            } else {
                                $newItem = $lastItem.clone(true);
                                $newItem.find('input[type="text"], textarea').val('');
                            }
                            var index = $repeater.find('.es-repeater-item').length;
                            $newItem.find('[name]').each(function() {
                                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', name);
                            });
                            $repeater.find('.es-add-item').before($newItem);
                        });

                        $('.es-repeater').on('click', '.es-remove-item', function() {
                            $(this).closest('.es-repeater-item').remove();
                        });
                    });
                </script>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_returns_repeater_field($opt, $key, $label) {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key) {
                $options = get_option($opt, []);
                $policies = $options[$key] ?? [];
                ?>
                <div class="es-returns-repeater" role="region" aria-label="<?php esc_attr_e('Return Policies Repeater', 'essential-schema'); ?>">
                    <?php foreach ($policies as $i => $item): ?>
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
                                    echo '<option value="' . esc_attr($cat) . '" ' . selected($selected_cat, $cat, false) . '>' . esc_html($cat) . '</option>';
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
                                    echo '<option value="' . esc_attr($fee) . '" ' . selected($selected_fees, $fee, false) . '>' . esc_html($fee) . '</option>';
                                }
                                ?>
                            </select></p>
                            <p><label><?php esc_html_e('Refund Type', 'essential-schema'); ?></label><br>
                            <select name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][refund_type]'); ?>">
                                <?php
                                $refund_options = ['FullRefund', 'StoreCreditRefund', 'ExchangeRefund'];
                                $selected_refund = $item['refund_type'] ?? $refund_options[0];
                                foreach ($refund_options as $refund) {
                                    echo '<option value="' . esc_attr($refund) . '" ' . selected($selected_refund, $refund, false) . '>' . esc_html($refund) . '</option>';
                                }
                                ?>
                            </select></p>
                            <p><label><?php esc_html_e('Return Methods', 'essential-schema'); ?></label><br>
                            <?php
                            $methods = ['ReturnByMail', 'ReturnInStore', 'ReturnAtKiosk'];
                            $selected_methods = (array) ($item['return_method'] ?? []);
                            foreach ($methods as $method) {
                                $checked = in_array($method, $selected_methods) ? 'checked' : '';
                                echo '<label><input type="checkbox" name="' . esc_attr($opt . '[' . $key . '][' . $i . '][return_method][]') . '" value="' . esc_attr($method) . '" ' . esc_attr($checked) . '> ' . esc_html($method) . '</label><br>';
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
                                    '<option value="ExchangeRefund">ExchangeRefund</option>' +
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
                            $(this).closest('.es-returns-repeater-item').remove();
                        });
                    });
                </script>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }

    private function add_shipping_repeater_field($opt, $key, $label) {
        add_settings_field(
            $opt . '_' . $key,
            $label,
            function() use ($opt, $key) {
                $options = get_option($opt, []);
                $items = $options[$key] ?? [];
                ?>
                <div class="es-shipping-repeater" role="region" aria-label="<?php esc_attr_e('Shipping Profiles Repeater', 'essential-schema'); ?>">
                    <?php foreach ($items as $i => $item): ?>
                        <div class="es-shipping-repeater-item">
                            <p><label><?php esc_html_e('Shipping Rate', 'essential-schema'); ?></label><br>
                            <input type="number" step="0.01" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][rate]'); ?>" value="<?php echo esc_attr($item['rate'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Currency', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][currency]'); ?>" value="<?php echo esc_attr($item['currency'] ?? 'USD'); ?>" class="small-text"></p>
                            <p><label><?php esc_html_e('Description', 'essential-schema'); ?></label><br>
                            <input type="text" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][description]'); ?>" value="<?php echo esc_attr($item['description'] ?? ''); ?>" class="regular-text"></p>
                            <p><label><?php esc_html_e('Applicable Countries', 'essential-schema'); ?></label><br>
                            <textarea name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][countries]'); ?>" rows="5" class="regular-text"><?php echo esc_textarea($item['countries'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('One ISO country code per line, e.g., US', 'essential-schema'); ?></p></p>
                            <p><label><?php esc_html_e('Handling Time Min (days)', 'essential-schema'); ?></label><br>
                            <input type="number" min="0" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][handling_min]'); ?>" value="<?php echo esc_attr($item['handling_min'] ?? ''); ?>" class="small-text"></p>
                            <p><label><?php esc_html_e('Handling Time Max (days)', 'essential-schema'); ?></label><br>
                            <input type="number" min="0" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][handling_max]'); ?>" value="<?php echo esc_attr($item['handling_max'] ?? ''); ?>" class="small-text"></p>
                            <p><label><?php esc_html_e('Transit Time Min (days)', 'essential-schema'); ?></label><br>
                            <input type="number" min="0" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][transit_min]'); ?>" value="<?php echo esc_attr($item['transit_min'] ?? ''); ?>" class="small-text"></p>
                            <p><label><?php esc_html_e('Transit Time Max (days)', 'essential-schema'); ?></label><br>
                            <input type="number" min="0" name="<?php echo esc_attr($opt . '[' . $key . '][' . $i . '][transit_max]'); ?>" value="<?php echo esc_attr($item['transit_max'] ?? ''); ?>" class="small-text"></p>
                            <button type="button" class="button es-shipping-remove-item"><?php esc_html_e('Remove Shipping Profile', 'essential-schema'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="button es-shipping-add-item"><?php esc_html_e('Add Shipping Profile', 'essential-schema'); ?></button>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.es-shipping-repeater').on('click', '.es-shipping-add-item', function() {
                            var $repeater = $(this).closest('.es-shipping-repeater');
                            var $lastItem = $repeater.find('.es-shipping-repeater-item').last();
                            var $newItem;
                            if ($lastItem.length === 0) {
                                $newItem = $('<div class="es-shipping-repeater-item">' +
                                    '<p><label>Shipping Rate</label><br>' +
                                    '<input type="number" step="0.01" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][rate]'); ?>" value="" class="regular-text"></p>' +
                                    '<p><label>Currency</label><br>' +
                                    '<input type="text" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][currency]'); ?>" value="USD" class="small-text"></p>' +
                                    '<p><label>Description</label><br>' +
                                    '<input type="text" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][description]'); ?>" value="" class="regular-text"></p>' +
                                    '<p><label>Applicable Countries</label><br>' +
                                    '<textarea name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][countries]'); ?>" rows="5" class="regular-text"></textarea>' +
                                    '<p class="description">One ISO country code per line, e.g., US</p></p>' +
                                    '<p><label>Handling Time Min (days)</label><br>' +
                                    '<input type="number" min="0" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][handling_min]'); ?>" value="" class="small-text"></p>' +
                                    '<p><label>Handling Time Max (days)</label><br>' +
                                    '<input type="number" min="0" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][handling_max]'); ?>" value="" class="small-text"></p>' +
                                    '<p><label>Transit Time Min (days)</label><br>' +
                                    '<input type="number" min="0" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][transit_min]'); ?>" value="" class="small-text"></p>' +
                                    '<p><label>Transit Time Max (days)</label><br>' +
                                    '<input type="number" min="0" name="' + '<?php echo esc_attr($opt . '[' . $key . '][0][transit_max]'); ?>" value="" class="small-text"></p>' +
                                    '<button type="button" class="button es-shipping-remove-item">Remove Shipping Profile</button>' +
                                    '</div>');
                            } else {
                                $newItem = $lastItem.clone(true);
                                $newItem.find('input[type="text"], input[type="number"], textarea').val('');
                            }
                            var index = $repeater.find('.es-shipping-repeater-item').length;
                            $newItem.find('[name]').each(function() {
                                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', name);
                            });
                            $repeater.find('.es-shipping-add-item').before($newItem);
                        });

                        $('.es-shipping-repeater').on('click', '.es-shipping-remove-item', function() {
                            $(this).closest('.es-shipping-repeater-item').remove();
                        });
                    });
                </script>
                <?php
            },
            'es_schema_settings',
            $opt
        );
    }

    public function sanitize_general($input) {
        $sanitized = [];
        $sanitized['enable_article_schema'] = isset($input['enable_article_schema']) ? (bool) $input['enable_article_schema'] : false;
        return $sanitized;
    }

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

    public function sanitize_return_policies($input) {
        $sanitized = [];
        if (isset($input['return_policies']) && is_array($input['return_policies'])) {
            foreach ($input['return_policies'] as $policy) {
                $clean_policy = [];
                $clean_policy['policy_name'] = isset($policy['policy_name']) ? sanitize_text_field($policy['policy_name']) : '';
                $clean_policy['return_policy_category'] = isset($policy['return_policy_category']) && in_array($policy['return_policy_category'], ['MerchantReturnFiniteReturnWindow', 'MerchantReturnUnlimitedWindow', 'MerchantReturnNotPermitted']) ? $policy['return_policy_category'] : '';
                $clean_policy['days'] = isset($policy['days']) ? absint($policy['days']) : '';
                $clean_policy['fees'] = isset($policy['fees']) && in_array($policy['fees'], ['FreeReturn', 'ReturnFeesCustomerResponsibility']) ? $policy['fees'] : '';
                $clean_policy['refund_type'] = isset($policy['refund_type']) && in_array($policy['refund_type'], ['FullRefund', 'StoreCreditRefund', 'ExchangeRefund']) ? $policy['refund_type'] : '';
                $clean_policy['return_method'] = isset($policy['return_method']) && is_array($policy['return_method']) ? array_map('sanitize_text_field', $policy['return_method']) : [];
                $clean_policy['description'] = isset($policy['description']) ? sanitize_textarea_field($policy['description']) : '';
                $clean_policy['countries'] = isset($policy['countries']) ? sanitize_textarea_field($policy['countries']) : '';
                if (!empty($clean_policy)) {
                    $sanitized['return_policies'][] = $clean_policy;
                }
            }
        }
        return $sanitized;
    }

    public function sanitize_faq($input) {
        $sanitized = [];
        if (isset($input['faq_items']) && is_array($input['faq_items'])) {
            foreach ($input['faq_items'] as $item) {
                $clean_item = [];
                $clean_item['question'] = isset($item['question']) ? sanitize_text_field($item['question']) : '';
                $clean_item['answer'] = isset($item['answer']) ? sanitize_textarea_field($item['answer']) : '';
                if (!empty($clean_item['question']) && !empty($clean_item['answer'])) {
                    $sanitized['faq_items'][] = $clean_item;
                }
            }
        }
        return $sanitized;
    }

    public function sanitize_shipping($input) {
        $sanitized = [];
        if (isset($input['shipping_items']) && is_array($input['shipping_items'])) {
            foreach ($input['shipping_items'] as $item) {
                $clean_item = [];
                $clean_item['rate'] = isset($item['rate']) ? floatval($item['rate']) : '';
                $clean_item['currency'] = isset($item['currency']) ? sanitize_text_field(strtoupper($item['currency'])) : 'USD';
                $clean_item['description'] = isset($item['description']) ? sanitize_text_field($item['description']) : '';
                $clean_item['countries'] = isset($item['countries']) ? sanitize_textarea_field($item['countries']) : '';
                $clean_item['handling_min'] = isset($item['handling_min']) ? absint($item['handling_min']) : '';
                $clean_item['handling_max'] = isset($item['handling_max']) ? absint($item['handling_max']) : '';
                $clean_item['transit_min'] = isset($item['transit_min']) ? absint($item['transit_min']) : '';
                $clean_item['transit_max'] = isset($item['transit_max']) ? absint($item['transit_max']) : '';
                if (!empty($clean_item)) {
                    $sanitized['shipping_items'][] = $clean_item;
                }
            }
        }
        return $sanitized;
    }

    public function section_callback_returns() {
        echo '<p>' . esc_html__('Fill only relevant fields based on the return policy category; empty or irrelevant fields will be ignored in schema output.', 'essential-schema') . '</p>';
    }
}
new ES_Config_Settings();