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

/** Combined class for settings page and configurable fields. */
class ES_Admin_Settings {
    const SLUG = 'es-schema-settings';
    const SETTINGS_GROUP = 'es_schema_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_menu', [$this, 'hide_from_menu'], 999);
        add_action('admin_init', [$this, 'register_all_settings']);
    }

    public function add_settings_page() {
        add_options_page(
            __('Essential Schema', 'essential-schema'),
            __('Essential Schema', 'essential-schema'),
            'manage_options',
            self::SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function hide_from_menu() {
        remove_submenu_page('options-general.php', self::SLUG);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Essential Schema Settings', 'essential-schema'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::SETTINGS_GROUP);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_all_settings() {
        $sections = [
            'es_general' => [
                'title' => __('General Settings', 'essential-schema'),
                'fields' => [
                    ['type' => 'checkbox', 'key' => 'enable_article_schema', 'label' => __('Enable Article Schema', 'essential-schema'), 'desc' => __('Output article schema on single blog posts.', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_general'],
            ],
            'es_policy_pages' => [
                'title' => __('Policy Pages', 'essential-schema'),
                'fields' => [
                    ['type' => 'page', 'key' => 'faq_page_id', 'label' => __('FAQ Page', 'essential-schema'), 'desc' => __('Select your FAQ page. Schema output on this page.', 'essential-schema')],
                    ['type' => 'page', 'key' => 'returns_page_id', 'label' => __('Returns Policy Page', 'essential-schema'), 'desc' => __('Select your Returns Policy page. Used for URL in schema.', 'essential-schema')],
                    ['type' => 'page', 'key' => 'shipping_page_id', 'label' => __('Shipping Policy Page', 'essential-schema'), 'desc' => __('Select your Shipping Policy page.', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_policy_pages'],
            ],
            'es_org' => [
                'title' => __('Organization Settings', 'essential-schema'),
                'fields' => [
                    ['type' => 'select', 'key' => 'org_type', 'label' => __('Organization Type', 'essential-schema'), 'choices' => ['Organization', 'OnlineStore'], 'desc' => __('Choose schema type.', 'essential-schema')],
                    ['type' => 'text', 'key' => 'telephone', 'label' => __('Telephone', 'essential-schema'), 'desc' => __('e.g., +1-800-123-4567', 'essential-schema')],
                    ['type' => 'email', 'key' => 'contact_email', 'label' => __('Contact Email', 'essential-schema')],
                    ['type' => 'text', 'key' => 'street_address', 'label' => __('Street Address', 'essential-schema')],
                    ['type' => 'text', 'key' => 'address_locality', 'label' => __('City', 'essential-schema')],
                    ['type' => 'text', 'key' => 'address_region', 'label' => __('State/Province', 'essential-schema')],
                    ['type' => 'text', 'key' => 'postal_code', 'label' => __('Postal Code', 'essential-schema')],
                    ['type' => 'text', 'key' => 'address_country', 'label' => __('Country', 'essential-schema'), 'desc' => __('e.g., US', 'essential-schema')],
                    ['type' => 'textarea', 'key' => 'social_links', 'label' => __('Social Links', 'essential-schema'), 'desc' => __('One URL per line.', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_org'],
            ],
            'es_return_policies' => [
                'title' => __('Return Policies', 'essential-schema'),
                'callback' => [$this, 'section_callback_returns'],
                'fields' => [
                    ['type' => 'returns_repeater', 'key' => 'return_policies', 'label' => __('Return Policies', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_return_policies'],
            ],
            'es_faq' => [
                'title' => __('FAQ Settings', 'essential-schema'),
                'fields' => [
                    ['type' => 'repeater', 'key' => 'faq_items', 'label' => __('FAQ Items', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_faq'],
            ],
            'es_shipping' => [
                'title' => __('Shipping Settings', 'essential-schema'),
                'fields' => [
                    ['type' => 'shipping_repeater', 'key' => 'shipping_items', 'label' => __('Shipping Profiles', 'essential-schema')],
                ],
                'sanitize' => [$this, 'sanitize_shipping'],
            ],
        ];

        foreach ($sections as $opt => $section) {
            register_setting(self::SETTINGS_GROUP, $opt, ['sanitize_callback' => $section['sanitize']]);
            add_settings_section(
                $opt,
                esc_html( $section['title'] ),
                $section['callback'] ?? '__return_false',
                self::SETTINGS_GROUP
            );
            foreach ($section['fields'] as $field) {
                $this->add_field($opt, $field);
            }
        }
    }

    private function add_field($opt, $field) {
        $id = $opt . '_' . $field['key'];
        $label = $field['label'];
        $desc = $field['desc'] ?? '';
        $type = $field['type'];

        add_settings_field(
            $id,
            $label,
            function() use ($opt, $field, $desc, $type) {
                $options = get_option($opt, []);
                $value = $options[$field['key']] ?? ($field['default'] ?? '');
                $name = $opt . '[' . $field['key'] . ']';
                switch ($type) {
                    case 'checkbox':
                        echo '<input type="checkbox" name="' . esc_attr($name) . '" ' . checked($value, true, false) . ' />';
                        break;
                    case 'page':
                        wp_dropdown_pages([
                            'name' => esc_attr($name),
                            'selected' => esc_attr($value),
                            'show_option_none' => esc_html__('None', 'essential-schema'),
                            'option_none_value' => 0,
                        ]);
                        break;
                    case 'select':
                        echo '<select name="' . esc_attr($name) . '">';
                        foreach ($field['choices'] as $choice) {
                            echo '<option value="' . esc_attr($choice) . '" ' . selected($value, $choice, false) . '>' . esc_html($choice) . '</option>';
                        }
                        echo '</select>';
                        break;
                    case 'text':
                        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
                        break;
                    case 'email':
                        echo '<input type="email" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
                        break;
                    case 'textarea':
                        echo '<textarea name="' . esc_attr($name) . '" rows="5" class="regular-text">' . esc_textarea($value) . '</textarea>';
                        break;
                    case 'repeater':
                        $this->render_repeater_field($opt, $field['key'], $options);
                        break;
                    case 'returns_repeater':
                        $this->render_returns_repeater_field($opt, $field['key'], $options);
                        break;
                    case 'shipping_repeater':
                        $this->render_shipping_repeater_field($opt, $field['key'], $options);
                        break;
                }
                if ($desc) echo ' <p class="description">' . esc_html($desc) . '</p>';
            },
            self::SETTINGS_GROUP,
            $opt
        );
    }

    private function render_repeater_field($opt, $key, $options) {
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
        <?php $this->inline_repeater_js($opt, $key, '.es-repeater', 'es-repeater-item', 'es-add-item', 'es-remove-item', $this->get_repeater_template($opt, $key), false); ?>
        <?php
    }

    private function render_returns_repeater_field($opt, $key, $options) {
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
                        $refund_options = ['FullRefund', 'StoreCreditRefund', 'MoneyBackOrReplacement'];
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
        <?php $this->inline_repeater_js($opt, $key, '.es-returns-repeater', 'es-returns-repeater-item', 'es-returns-add-item', 'es-returns-remove-item', $this->get_returns_template($opt, $key), true); ?>
        <?php
    }

    private function render_shipping_repeater_field($opt, $key, $options) {
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
        <?php $this->inline_repeater_js($opt, $key, '.es-shipping-repeater', 'es-shipping-repeater-item', 'es-shipping-add-item', 'es-shipping-remove-item', $this->get_shipping_template($opt, $key), false); ?>
        <?php
    }

    private function inline_repeater_js($opt, $key, $repeater_class, $item_class, $add_button_class, $remove_button_class, $template, $has_select_checkbox = false) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var template = <?php echo wp_json_encode($template); ?>;
                $('<?php echo esc_js($repeater_class); ?>').on('click', '.<?php echo esc_js($add_button_class); ?>', function() {
                    var $repeater = $(this).closest('<?php echo esc_js($repeater_class); ?>');
                    var $lastItem = $repeater.find('.<?php echo esc_js($item_class); ?>').last();
                    var $newItem = $lastItem.length === 0 ? $(template) : $lastItem.clone(true);
                    if ($lastItem.length > 0) {
                        $newItem.find('input[type="text"], input[type="number"], textarea').val('');
                        if (<?php echo $has_select_checkbox ? 'true' : 'false'; ?>) {
                            $newItem.find('select').val($newItem.find('select option:first').val());
                            $newItem.find('input[type="checkbox"]').prop('checked', false);
                        }
                    }
                    var index = $repeater.find('.<?php echo esc_js($item_class); ?>').length;
                    $newItem.find('[name]').each(function() {
                        var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', name);
                    });
                    $repeater.find('.<?php echo esc_js($add_button_class); ?>').before($newItem);
                });

                $('<?php echo esc_js($repeater_class); ?>').on('click', '.<?php echo esc_js($remove_button_class); ?>', function() {
                    $(this).closest('.<?php echo esc_js($item_class); ?>').remove();
                });
            });
        </script>
        <?php
    }

    private function get_repeater_template($opt, $key) {
        return '<div class="es-repeater-item">'
            . '<p><label>' . esc_html__('Question', 'essential-schema') . '</label><br>'
            . '<input type="text" name="' . esc_attr($opt . '[' . $key . '][0][question]') . '" class="regular-text"></p>'
            . '<p><label>' . esc_html__('Answer', 'essential-schema') . '</label><br>'
            . '<textarea name="' . esc_attr($opt . '[' . $key . '][0][answer]') . '" rows="5" class="regular-text"></textarea></p>'
            . '<button type="button" class="button es-remove-item">' . esc_html__('Remove Item', 'essential-schema') . '</button>'
            . '</div>';
    }

    private function get_returns_template($opt, $key) {
        $template = '<div class="es-returns-repeater-item">'
            . '<p><label>' . esc_html__('Policy Name', 'essential-schema') . '</label><br>'
            . '<input type="text" name="' . esc_attr($opt . '[' . $key . '][0][policy_name]') . '" value="" class="regular-text">'
            . '<p class="description">' . esc_html__('e.g., Return Policy – US & Territories', 'essential-schema') . '</p></p>'
            . '<p><label>' . esc_html__('Return Policy Category', 'essential-schema') . '</label><br>'
            . '<select name="' . esc_attr($opt . '[' . $key . '][0][return_policy_category]') . '">'
            . '<option value="MerchantReturnFiniteReturnWindow">MerchantReturnFiniteReturnWindow</option>'
            . '<option value="MerchantReturnUnlimitedWindow">MerchantReturnUnlimitedWindow</option>'
            . '<option value="MerchantReturnNotPermitted">MerchantReturnNotPermitted</option>'
            . '</select></p>'
            . '<p><label>' . esc_html__('Return Days', 'essential-schema') . '</label><br>'
            . '<input type="number" name="' . esc_attr($opt . '[' . $key . '][0][days]') . '" value="" min="0" step="1" class="regular-text"></p>'
            . '<p><label>' . esc_html__('Return Fees', 'essential-schema') . '</label><br>'
            . '<select name="' . esc_attr($opt . '[' . $key . '][0][fees]') . '">'
            . '<option value="FreeReturn">FreeReturn</option>'
            . '<option value="ReturnFeesCustomerResponsibility">ReturnFeesCustomerResponsibility</option>'
            . '</select></p>'
            . '<p><label>' . esc_html__('Refund Type', 'essential-schema') . '</label><br>'
            . '<select name="' . esc_attr($opt . '[' . $key . '][0][refund_type]') . '">'
            . '<option value="FullRefund">FullRefund</option>'
            . '<option value="StoreCreditRefund">StoreCreditRefund</option>'
            . '<option value="MoneyBackOrReplacement">MoneyBackOrReplacement</option>'
            . '</select></p>'
            . '<p><label>' . esc_html__('Return Methods', 'essential-schema') . '</label><br>'
            . '<label><input type="checkbox" name="' . esc_attr($opt . '[' . $key . '][0][return_method][]') . '" value="ReturnByMail"> ReturnByMail</label><br>'
            . '<label><input type="checkbox" name="' . esc_attr($opt . '[' . $key . '][0][return_method][]') . '" value="ReturnInStore"> ReturnInStore</label><br>'
            . '<label><input type="checkbox" name="' . esc_attr($opt . '[' . $key . '][0][return_method][]') . '" value="ReturnAtKiosk"> ReturnAtKiosk</label><br>'
            . '<p class="description">' . esc_html__('Select all that apply.', 'essential-schema') . '</p></p>'
            . '<p><label>' . esc_html__('Return Description', 'essential-schema') . '</label><br>'
            . '<textarea name="' . esc_attr($opt . '[' . $key . '][0][description]') . '" rows="5" class="regular-text"></textarea>'
            . '<p class="description">' . esc_html__('e.g., Free returns within 30 days.', 'essential-schema') . '</p></p>'
            . '<p><label>' . esc_html__('Applicable Countries', 'essential-schema') . '</label><br>'
            . '<textarea name="' . esc_attr($opt . '[' . $key . '][0][countries]') . '" rows="5" class="regular-text"></textarea>'
            . '<p class="description">' . esc_html__('One ISO country code per line, e.g., US', 'essential-schema') . '</p></p>'
            . '<button type="button" class="button es-returns-remove-item">' . esc_html__('Remove Policy', 'essential-schema') . '</button>'
            . '</div>';
        return $template;
    }

    private function get_shipping_template($opt, $key) {
        return '<div class="es-shipping-repeater-item">'
            . '<p><label>' . esc_html__('Shipping Rate', 'essential-schema') . '</label><br>'
            . '<input type="number" step="0.01" name="' . esc_attr($opt . '[' . $key . '][0][rate]') . '" value="" class="regular-text"></p>'
            . '<p><label>' . esc_html__('Currency', 'essential-schema') . '</label><br>'
            . '<input type="text" name="' . esc_attr($opt . '[' . $key . '][0][currency]') . '" value="USD" class="small-text"></p>'
            . '<p><label>' . esc_html__('Description', 'essential-schema') . '</label><br>'
            . '<input type="text" name="' . esc_attr($opt . '[' . $key . '][0][description]') . '" value="" class="regular-text"></p>'
            . '<p><label>' . esc_html__('Applicable Countries', 'essential-schema') . '</label><br>'
            . '<textarea name="' . esc_attr($opt . '[' . $key . '][0][countries]') . '" rows="5" class="regular-text"></textarea>'
            . '<p class="description">' . esc_html__('One ISO country code per line, e.g., US', 'essential-schema') . '</p></p>'
            . '<p><label>' . esc_html__('Handling Time Min (days)', 'essential-schema') . '</label><br>'
            . '<input type="number" min="0" name="' . esc_attr($opt . '[' . $key . '][0][handling_min]') . '" value="" class="small-text"></p>'
            . '<p><label>' . esc_html__('Handling Time Max (days)', 'essential-schema') . '</label><br>'
            . '<input type="number" min="0" name="' . esc_attr($opt . '[' . $key . '][0][handling_max]') . '" value="" class="small-text"></p>'
            . '<p><label>' . esc_html__('Transit Time Min (days)', 'essential-schema') . '</label><br>'
            . '<input type="number" min="0" name="' . esc_attr($opt . '[' . $key . '][0][transit_min]') . '" value="" class="small-text"></p>'
            . '<p><label>' . esc_html__('Transit Time Max (days)', 'essential-schema') . '</label><br>'
            . '<input type="number" min="0" name="' . esc_attr($opt . '[' . $key . '][0][transit_max]') . '" value="" class="small-text"></p>'
            . '<button type="button" class="button es-shipping-remove-item">' . esc_html__('Remove Shipping Profile', 'essential-schema') . '</button>'
            . '</div>';
    }

    // Sanitize functions remain the same...

    public function section_callback_returns() {
        echo '<p>' . esc_html__('Fill only relevant fields based on the return policy category; empty or irrelevant fields will be ignored in schema output.', 'essential-schema') . '</p>';
    }
}
new ES_Admin_Settings();