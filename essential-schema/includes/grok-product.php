<?php
/**
 * Plugin Name: WooCommerce Product Schema Adder
 * Description: Adds only missing schema.org elements (itemCondition and conditional hasMerchantReturnPolicy) to product pages, embedding standard US shipping details.
 * Version: 1.6.8
 * Author: Your Name
 */

// Hook into the WooCommerce structured data filter to modify the existing schema
add_filter('woocommerce_structured_data_product', 'add_missing_schema_to_product', 10, 2);

function add_missing_schema_to_product($markup, $product) {
    // Bail if not a valid product
    if (!is_a($product, 'WC_Product')) {
        return $markup;
    }

    $price_valid_until = gmdate('Y-12-31', strtotime('+1 year'));
    $shipping_policy_url = 'https://www.smokingblends.com/shipping/';
    $currency = get_woocommerce_currency();

    // Define the merged return policy (embedded, no ID reference, applies to all products)
    $return_policy = [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => ['US', 'PR', 'VI', 'UM'],
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 30,
        'returnMethod' => 'https://schema.org/ReturnByMail',
        'returnFees' => 'https://schema.org/FreeReturn',
        'refundType' => 'https://schema.org/FullRefund',
        'description' => 'Free returns within 30 days of delivery. Items must be unused and in original packaging.',
        'url' => 'https://www.smokingblends.com/return-policy/',
    ];

    // Common shipping elements
    $shipping_destination = [
        '@type' => 'DefinedRegion',
        'addressCountry' => 'US',
    ];

    $delivery_time = [
        '@type' => 'ShippingDeliveryTime',
        'handlingTime' => [
            '@type' => 'QuantitativeValue',
            'minValue' => 1,
            'maxValue' => 1,
            'unitCode' => 'DAY',
        ],
        'transitTime' => [
            '@type' => 'QuantitativeValue',
            'minValue' => 2,
            'maxValue' => 5,
            'unitCode' => 'DAY',
        ],
    ];

    // Base shipping rate as simple MonetaryAmount (avoids ShippingRateSettings validation issues)
    $shipping_rate = [
        '@type' => 'MonetaryAmount',
        'value' => 3.75,
        'currency' => $currency,
    ];

    // Single OfferShippingDetails using the simple rate
    $shipping_details = [
        [
            '@type' => 'OfferShippingDetails',
            'description' => 'Flat rate ground shipping, free on orders over $35.',
            'shippingRate' => $shipping_rate,
            'shippingDestination' => $shipping_destination,
            'deliveryTime' => $delivery_time,
            'shippingSettingsLink' => $shipping_policy_url,
        ]
    ];

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

            // Get the display name for the size attribute
            $size_slug = $variation_data['attributes']['attribute_pa_size'] ?? '';
            $size_term = get_term_by('slug', $size_slug, 'pa_size');
            $size_name = $size_term ? $size_term->name : $size_slug;

            $var_price = $variation->get_price('edit');
            $var_availability = $variation->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

            $variation_offer = [
                '@type' => 'Offer',
                'name' => $size_name,
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
            'itemCondition' => 'https://schema.org/NewCondition',
            'priceValidUntil' => $price_valid_until,
            'hasMerchantReturnPolicy' => $return_policy,
            'shippingDetails' => $shipping_details,
        ];

        // Overwrite the offers with the new AggregateOffer, maintaining WooCommerce's structure
        $markup['offers'][0] = $aggregate_offer;

    } else {
        // For simple products, modify the existing Offer within the array
        if (isset($markup['offers'][0])) {
            $markup['offers'][0]['itemCondition'] = 'https://schema.org/NewCondition';
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