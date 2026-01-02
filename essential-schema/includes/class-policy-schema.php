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

        $domestic_returns = get_option('es_domestic_returns', []);
        $org_opts = get_option('es_org', []);
        $address_country = $org_opts['address_country'] ?? 'US';

        $policy = [
            '@context' => 'https://schema.org',
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => $address_country,
        ];

        if (!empty($domestic_returns['policy_name'])) {
            $policy['name'] = $domestic_returns['policy_name'];
        }

        if (!empty($domestic_returns['return_policy_category'])) {
            $policy['returnPolicyCategory'] = 'https://schema.org/' . $domestic_returns['return_policy_category'];
        }

        if (!empty($domestic_returns['days']) && $domestic_returns['return_policy_category'] === 'MerchantReturnFiniteReturnWindow') {
            $policy['merchantReturnDays'] = (int) $domestic_returns['days'];
        }

        if (!empty($domestic_returns['fees'])) {
            $policy['returnFees'] = 'https://schema.org/' . $domestic_returns['fees'];
        }

        if (!empty($domestic_returns['refund_type'])) {
            $policy['refundType'] = 'https://schema.org/' . $domestic_returns['refund_type'];
        }

        if (!empty($domestic_returns['return_method'])) {
            $methods = [];
            foreach ($domestic_returns['return_method'] as $method) {
                $methods[] = 'https://schema.org/' . $method;
            }
            $policy['returnMethod'] = $methods;
        }

        if (!empty($domestic_returns['description'])) {
            $policy['description'] = $domestic_returns['description'];
        }

        echo "<script type='application/ld+json'>" . wp_json_encode($policy) . "</script>\n";
    }

    public function output_shipping_jsonld(): void {
        $policy_pages = get_option('es_policy_pages', []);
        $shipping_page_id = $policy_pages['shipping_page_id'] ?? 0;
        if (!is_page($shipping_page_id)) { return; }

        $shipping_opts = get_option('es_shipping', []);
        $shipping_items = $shipping_opts['shipping_items'] ?? [];

        foreach ($shipping_items as $item) {
            $countries = array_filter(array_map('trim', explode("\n", $item['countries'] ?? 'US')));

            $policy = [
                '@context' => 'https://schema.org',
                '@type' => 'OfferShippingDetails',
            ];

            if (isset($item['rate']) && $item['rate'] > 0) {
                $policy['shippingRate'] = [
                    '@type' => 'MonetaryAmount',
                    'value' => (float) $item['rate'],
                    'currency' => $item['currency'] ?? 'USD',
                ];
            }

            if (!empty($item['description'])) {
                $policy['description'] = $item['description'];
            }

            if (!empty($countries)) {
                $destinations = array_map(function($country) {
                    return [
                        '@type' => 'DefinedRegion',
                        'addressCountry' => $country,
                    ];
                }, $countries);
                $policy['shippingDestination'] = $destinations;
            }

            if (isset($item['handling_min']) && $item['handling_min'] > 0 || isset($item['handling_max']) && $item['handling_max'] > 0 || isset($item['transit_min']) && $item['transit_min'] > 0 || isset($item['transit_max']) && $item['transit_max'] > 0) {
                $policy['deliveryTime'] = [
                    '@type' => 'ShippingDeliveryTime',
                    'handlingTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => (int) ($item['handling_min'] ?? 0),
                        'maxValue' => (int) ($item['handling_max'] ?? 0),
                        'unitCode' => 'DAY',
                    ],
                    'transitTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => (int) ($item['transit_min'] ?? 0),
                        'maxValue' => (int) ($item['transit_max'] ?? 0),
                        'unitCode' => 'DAY',
                    ],
                ];
            }

            if (!empty($policy) && count($policy) > 2) { // Ensure not empty
                echo "<script type='application/ld+json'>" . wp_json_encode($policy) . "</script>\n";
            }
        }
    }
}
new ES_Policy_Schema();