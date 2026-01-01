<?php
// File: includes/grok-product.php
defined('ABSPATH') || exit;
// Hook into the WooCommerce structured data filter to modify the existing schema
add_filter('woocommerce_structured_data_product', 'add_missing_schema_to_product', 10, 2);

function add_missing_schema_to_product($markup, $product) {
    // Bail if not a valid product
    if (!is_a($product, 'WC_Product')) {
        return $markup;
    }

    $shipping_opts = get_option('es_shipping', []);
    $domestic_returns = get_option('es_domestic_returns', []);
    $policy_pages = get_option('es_policy_pages', []);

    $price_valid_until = gmdate('Y-12-31', strtotime('+1 year'));
    $shipping_policy_url = $policy_pages['shipping_page_id'] ? get_permalink($policy_pages['shipping_page_id']) : '';
    $currency = get_woocommerce_currency(); // Prioritize product currency
    $countries = array_filter(array_map('trim', explode("\n", $shipping_opts['countries'] ?? '')));

    // Define the merged return policy (embedded, no ID reference, applies to all products)
    $returns_url = $policy_pages['returns_page_id'] ? get_permalink($policy_pages['returns_page_id']) : home_url('/return-policy/');
    $return_policy_category = $domestic_returns['return_policy_category'] ?? '';
    $return_policy = [];
    if ($return_policy_category) {
        $return_policy = [
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => 'https://schema.org/' . $return_policy_category,
            'url' => $returns_url,
        ];
        if ($return_policy_category !== 'MerchantReturnNotPermitted') {
            if (!empty($countries)) $return_policy['applicableCountry'] = $countries;
            $return_methods = (array) ($domestic_returns['return_method'] ?? []);
            if (!empty($return_methods)) {
                $return_methods_schema = array_map(function($method) {
                    return 'https://schema.org/' . $method;
                }, $return_methods);
                $return_policy['returnMethod'] = count($return_methods_schema) === 1 ? $return_methods_schema[0] : $return_methods_schema;
            }
            if (!empty($domestic_returns['fees'])) $return_policy['returnFees'] = 'https://schema.org/' . $domestic_returns['fees'];
            if (!empty($domestic_returns['refund_type'])) $return_policy['refundType'] = 'https://schema.org/' . $domestic_returns['refund_type'];
        }
        if ($return_policy_category === 'MerchantReturnFiniteReturnWindow' && !empty($domestic_returns['days'])) {
            $return_policy['merchantReturnDays'] = (int) $domestic_returns['days'];
        }
        if (!empty($domestic_returns['description'])) $return_policy['description'] = $domestic_returns['description'];
    }

    // Single OfferShippingDetails with just the link if URL present
    $shipping_details = [];
    if ($shipping_policy_url) {
        $shipping_details = [
            [
                '@type' => 'OfferShippingDetails',
                'shippingSettingsLink' => $shipping_policy_url,
            ]
        ];
    }

    if ($product->is_type('variable')) {
        // For variable products, use AggregateOffer with individual Offers inside
        $offers = [];
        $low_price = $product->get_variation_price('min', false);
        $high_price = $product->get_variation_price('max', false);

        foreach ($product->get_available_variations() as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if (!$variation) {
                continue;
            }

            // Generalize variation name from attributes
            $attributes_display = [];
            foreach ($variation_data['attributes'] as $attr_key => $attr_value) {
                $attr_name = str_replace('attribute_', '', $attr_key);
                $term = get_term_by('slug', $attr_value, $attr_name);
                $attr_label = wc_attribute_label($attr_name);
                $value_name = $term ? $term->name : $attr_value;
                $attributes_display[] = $attr_label . ': ' . $value_name;
            }
            $var_name = implode(', ', $attributes_display);

            $var_price = $variation->get_price('edit');
            $var_availability = $variation->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

            $variation_offer = [
                '@type' => 'Offer',
                'name' => $var_name,
                'price' => $var_price,
                'priceCurrency' => $currency,
                'availability' => $var_availability,
                'url' => $variation->get_permalink(),
                'sku' => $variation->get_sku(),
            ];

            $offers[] = $variation_offer;
        }

        // Set to AggregateOffer with the individual offers inside, and common properties at the aggregate level
        $aggregate_offer = [
            '@type' => 'AggregateOffer',
            'lowPrice' => $low_price,
            'highPrice' => $high_price,
            'priceCurrency' => $currency,
            'offerCount' => count($offers),
            'offers' => $offers,
            'priceValidUntil' => $price_valid_until,
        ];
        if (!empty($return_policy)) $aggregate_offer['hasMerchantReturnPolicy'] = $return_policy;
        if (!empty($shipping_details)) $aggregate_offer['shippingDetails'] = $shipping_details;

        // Overwrite the offers with the new AggregateOffer
        $markup['offers'] = $aggregate_offer;

    } else {
        // For simple products, modify the existing Offer within the array
        if (isset($markup['offers'][0])) {
            $markup['offers'][0]['priceValidUntil'] = $price_valid_until;
            if (!empty($return_policy)) $markup['offers'][0]['hasMerchantReturnPolicy'] = $return_policy;
            if (!empty($shipping_details)) $markup['offers'][0]['shippingDetails'] = $shipping_details;
            // Optionally remove seller if not needed
            unset($markup['offers'][0]['seller']);
        }
    }

    // Add worstRating and bestRating to aggregateRating if present
    if (isset($markup['aggregateRating'])) {
        $markup['aggregateRating']['worstRating'] = 1;
        $markup['aggregateRating']['bestRating'] = 5;
    }

    unset($markup['review']);

    return $markup;
}