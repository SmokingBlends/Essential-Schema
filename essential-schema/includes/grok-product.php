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

    $policy_pages = get_option('es_policy_pages', []);
    $price_valid_until = gmdate('Y-12-31', strtotime('+1 year'));
    $shipping_policy_url = $policy_pages['shipping_page_id'] ? get_permalink($policy_pages['shipping_page_id']) : '';
    $returns_url = $policy_pages['returns_page_id'] ? get_permalink($policy_pages['returns_page_id']) : '';
    $currency = get_woocommerce_currency(); // Prioritize product currency

    $policy_schema = new ES_Policy_Schema();
    $return_ids = $policy_schema->get_return_policy_ids();
    $return_policies = [];
    if (!empty($return_ids)) {
        $return_policies = array_map(function($id) {
            return ['@type' => 'MerchantReturnPolicy', '@id' => $id];
        }, $return_ids);
        $return_policies = (count($return_ids) === 1) ? $return_policies[0] : $return_policies;
    }
    $return_policies = apply_filters('custom_return_policy_schema', $return_policies, $product);

    $shipping_ids = $policy_schema->get_shipping_policy_ids();
    $shipping_details = [];
    if (!empty($shipping_ids)) {
        $shipping_details = array_map(function($id) {
            return ['@type' => 'OfferShippingDetails', '@id' => $id];
        }, $shipping_ids);
        $shipping_details = (count($shipping_ids) === 1) ? $shipping_details[0] : $shipping_details;
    }
    $shipping_details = apply_filters('custom_shipping_details_schema', $shipping_details, $product);

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
        if (!empty($return_policies)) $aggregate_offer['hasMerchantReturnPolicy'] = $return_policies;
        if (!empty($shipping_details)) $aggregate_offer['shippingDetails'] = $shipping_details;

        // Overwrite the offers with the new AggregateOffer
        $markup['offers'] = $aggregate_offer;

    } else {
        // For simple products, modify the existing Offer within the array
        if (isset($markup['offers'][0])) {
            $markup['offers'][0]['priceValidUntil'] = $price_valid_until;
            if (!empty($return_policies)) $markup['offers'][0]['hasMerchantReturnPolicy'] = $return_policies;
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

    $image_ids = [$product->get_image_id()];
    $gallery_ids = $product->get_gallery_image_ids();
    if ($gallery_ids) {
        $image_ids = array_merge($image_ids, $gallery_ids);
    }
    $markup['image'] = array_map('wp_get_attachment_url', array_filter($image_ids));

    return $markup;
}