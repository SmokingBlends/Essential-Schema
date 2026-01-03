<?php
// File: includes/class-policy-schema.php
defined('ABSPATH') || exit;

class ES_Policy_Schema {
    public function __construct() {
        add_action('wp_head', [$this, 'output_returns_jsonld'], 99);
        add_action('wp_head', [$this, 'output_shipping_jsonld'], 99);
    }

    public function output_returns_jsonld(): void {
        $policy_pages = get_option('es_policy_pages', []);
        $returns_page_id = $policy_pages['returns_page_id'] ?? 0;
        if (!is_page($returns_page_id)) { return; }

        $return_policies = get_option('es_return_policies')['return_policies'] ?? [];

        foreach ($return_policies as $policy_item) {
            $policy = [
                '@context' => 'https://schema.org',
                '@type' => 'MerchantReturnPolicy',
            ];

            if (!empty($policy_item['policy_name'])) {
                $policy['name'] = $policy_item['policy_name'];
            }

            if (!empty($policy_item['return_policy_category'])) {
                $policy['returnPolicyCategory'] = 'https://schema.org/' . $policy_item['return_policy_category'];
            }

            if ($policy_item['return_policy_category'] === 'MerchantReturnFiniteReturnWindow' && !empty($policy_item['days'])) {
                $policy['merchantReturnDays'] = (int) $policy_item['days'];
            }

            if ($policy_item['return_policy_category'] !== 'MerchantReturnNotPermitted') {
                if (!empty($policy_item['fees'])) {
                    $policy['returnFees'] = 'https://schema.org/' . $policy_item['fees'];
                }

                if (!empty($policy_item['refund_type'])) {
                    $policy['refundType'] = 'https://schema.org/' . $policy_item['refund_type'];
                }

                if (!empty($policy_item['return_method'])) {
                    $methods = array_map(function($m) { return 'https://schema.org/' . $m; }, $policy_item['return_method']);
                    $policy['returnMethod'] = $methods;
                }
            }

            if (!empty($policy_item['description'])) {
                $policy['description'] = $policy_item['description'];
            }

            $countries = array_filter(array_map('trim', explode("\n", $policy_item['countries'] ?? '')));
            if (!empty($countries)) {
                $policy['applicableCountry'] = (count($countries) > 1) ? $countries : $countries[0];
            }

            if (count($policy) > 2) {
                echo "<script type='application/ld+json'>" . wp_json_encode($policy) . "</script>\n";
            }
        }
    }

    public function output_shipping_jsonld(): void {
        $policy_pages = get_option('es_policy_pages', []);
        $shipping_page_id = $policy_pages['shipping_page_id'] ?? 0;
        if (!is_page($shipping_page_id)) { return; }

        $shipping_opts = get_option('es_shipping', []);
        $shipping_items = $shipping_opts['shipping_items'] ?? [];

        foreach ($shipping_items as $item) {
            $policy = [
                '@context' => 'https://schema.org',
                '@type' => 'OfferShippingDetails',
            ];

            if (isset($item['rate'])) {
                $policy['shippingRate'] = [
                    '@type' => 'MonetaryAmount',
                    'value' => (float) $item['rate'],
                    'currency' => $item['currency'] ?? 'USD',
                ];
            }

            if (!empty($item['description'])) {
                $policy['description'] = $item['description'];
            }

            $countries = array_filter(array_map('trim', explode("\n", $item['countries'] ?? '')));
            if (!empty($countries)) {
                $destinations = array_map(function($country) {
                    return [
                        '@type' => 'DefinedRegion',
                        'addressCountry' => $country,
                    ];
                }, $countries);
                $policy['shippingDestination'] = $destinations;
            }

            $has_delivery = false;
            $delivery_time = [
                '@type' => 'ShippingDeliveryTime',
            ];

            if (isset($item['handling_min']) || isset($item['handling_max'])) {
                $delivery_time['handlingTime'] = [
                    '@type' => 'QuantitativeValue',
                    'minValue' => (int) ($item['handling_min'] ?? 0),
                    'maxValue' => (int) ($item['handling_max'] ?? 0),
                    'unitCode' => 'DAY',
                ];
                $has_delivery = true;
            }

            if (isset($item['transit_min']) || isset($item['transit_max'])) {
                $delivery_time['transitTime'] = [
                    '@type' => 'QuantitativeValue',
                    'minValue' => (int) ($item['transit_min'] ?? 0),
                    'maxValue' => (int) ($item['transit_max'] ?? 0),
                    'unitCode' => 'DAY',
                ];
                $has_delivery = true;
            }

            if ($has_delivery) {
                $policy['deliveryTime'] = $delivery_time;
            }

            if (count($policy) > 2) {
                echo "<script type='application/ld+json'>" . wp_json_encode($policy) . "</script>\n";
            }
        }
    }
}
new ES_Policy_Schema();