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
    $product_opts = get_option('es_product', []);

    $price_valid_until = gmdate('Y-12-31', strtotime('+1 year'));
    $shipping_policy_url = $policy_pages['shipping_page_id'] ? get_permalink($policy_pages['shipping_page_id']) : home_url('/shipping/');
    $currency = $shipping_opts['currency'] ?? get_woocommerce_currency();
    $countries = array_filter(array_map('trim', explode("\n", $shipping_opts['countries'] ?? 'US')));

    // Define the merged return policy (embedded, no ID reference, applies to all products)
    $returns_url = $policy_pages['returns_page_id'] ? get_permalink($policy_pages['returns_page_id']) : home_url('/return-policy/');
    $return_policy_category = $domestic_returns['return_policy_category'] ?? 'MerchantReturnFiniteReturnWindow';
    $return_policy = [
        '@type' => 'MerchantReturnPolicy',
        'returnPolicyCategory' => 'https://schema.org/' . $return_policy_category,
        'url' => $returns_url,
    ];
    if ($return_policy_category !== 'MerchantReturnNotPermitted') {
        $return_policy['applicableCountry'] = $countries;
        $return_methods = (array) ($domestic_returns['return_method'] ?? ['ReturnByMail']);
        $return_methods_schema = array_map(function($method) {
            return 'https://schema.org/' . $method;
        }, $return_methods);
        $return_policy['returnMethod'] = count($return_methods_schema) === 1 ? $return_methods_schema[0] : $return_methods_schema;
        $return_policy['returnFees'] = 'https://schema.org/' . ($domestic_returns['fees'] ?? 'FreeReturn');
        $return_policy['refundType'] = 'https://schema.org/' . ($domestic_returns['refund_type'] ?? 'FullRefund');
    }
    if ($return_policy_category === 'MerchantReturnFiniteReturnWindow') {
        $return_policy['merchantReturnDays'] = (int) ($domestic_returns['days'] ?? 30);
    }
    $return_policy['description'] = $domestic_returns['description'] ?? 'Free returns within 30 days of delivery. Items must be unused and in original packaging.';

    // Shipping destinations as array
    $shipping_destinations = array_map(function($country) {
        return [
            '@type' => 'DefinedRegion',
            'addressCountry' => $country,
        ];
    }, $countries);

    $delivery_time = [
        '@type' => 'ShippingDeliveryTime',
        'handlingTime' => [
            '@type' => 'QuantitativeValue',
            'minValue' => (int) ($shipping_opts['handling_min'] ?? 1),
            'maxValue' => (int) ($shipping_opts['handling_max'] ?? 1),
            'unitCode' => 'DAY',
        ],
        'transitTime' => [
            '@type' => 'QuantitativeValue',
            'minValue' => (int) ($shipping_opts['transit_min'] ?? 2),
            'maxValue' => (int) ($shipping_opts['transit_max'] ?? 5),
            'unitCode' => 'DAY',
        ],
    ];

    // Base shipping rate as simple MonetaryAmount (avoids ShippingRateSettings validation issues)
    $shipping_rate = [
        '@type' => 'MonetaryAmount',
        'value' => (float) ($shipping_opts['rate'] ?? 3.75),
        'currency' => $currency,
    ];

    // Single OfferShippingDetails using the simple rate
    $shipping_details = [
        [
            '@type' => 'OfferShippingDetails',
            'description' => $shipping_opts['description'] ?? 'Flat rate ground shipping, free on orders over $35.',
            'shippingRate' => $shipping_rate,
            'shippingDestination' => count($shipping_destinations) === 1 ? $shipping_destinations[0] : $shipping_destinations,
            'deliveryTime' => $delivery_time,
            'shippingSettingsLink' => $shipping_policy_url,
        ]
    ];

    $item_condition = 'https://schema.org/' . ($product_opts['item_condition'] ?? 'NewCondition');

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
            'itemCondition' => $item_condition,
            'priceValidUntil' => $price_valid_until,
            'hasMerchantReturnPolicy' => $return_policy,
            'shippingDetails' => $shipping_details,
        ];

        // Overwrite the offers with the new AggregateOffer
        $markup['offers'] = $aggregate_offer;

    } else {
        // For simple products, modify the existing Offer within the array
        if (isset($markup['offers'][0])) {
            $markup['offers'][0]['itemCondition'] = $item_condition;
            $markup['offers'][0]['priceValidUntil'] = $price_valid_until;
            $markup['offers'][0]['hasMerchantReturnPolicy'] = $return_policy;
            $markup['offers'][0]['shippingDetails'] = $shipping_details;
            // Optionally remove seller if not needed
            unset($markup['offers'][0]['seller']);
        }
    }

    // Add worstRating and bestRating to aggregateRating if present
    if (isset($markup['aggregateRating'])) {
        $markup['aggregateRating']['worstRating'] = 1;
        $markup['aggregateRating']['bestRating'] = 5;
    }

    // Override reviews to include up to 100 (remove WooCommerce's default limit of 5)
    $comments = get_comments(
        array(
            'post_id'     => $product->get_id(),
            'status'      => 'approve',
            'post_status' => 'publish',
            'post_type'   => 'product',
            'parent'      => 0,
            'number'      => 100, // Reasonable limit for performance
        )
    );

    $markup['review'] = array(); // Clear existing limited reviews

    foreach ( $comments as $comment ) {
        $rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
        if ( $rating > 0 ) { // Filter for rated reviews only
            $review = array(
                '@type'         => 'Review',
                'datePublished' => $comment->comment_date,
                'description'   => $comment->comment_content,
                'reviewRating'  => array(
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ),
                'author'        => array(
                    '@type' => 'Person',
                    'name'  => $comment->comment_author,
                ),
            );

            // Optionally add verified status if you want (WooCommerce doesn't by default in schema, but you can)
            if ( wc_review_is_from_verified_owner( $comment->comment_ID ) ) {
                $review['author']['name'] .= ' (verified owner)';
            }

            $markup['review'][] = $review;
        }
    }

    return $markup;
}