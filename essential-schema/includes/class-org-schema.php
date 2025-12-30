<?php
// File: includes/class-org-schema.php
defined('ABSPATH') || exit;

/**
 * Essential Schema – Organization Schema JSON-LD
 * Outputs selected Organization type markup on the homepage.
 */
class ES_Org_Schema {
    /** Flag to prevent duplicate output. */
    private static $outputted = false;

    /**
     * Constructor: hook JSON-LD output on the homepage.
     * Runs late to avoid interference.
     */
    public function __construct() {
        add_action('wp_head', [$this, 'output_jsonld'], 99);
    }

    /**
     * Build and echo Organization JSON-LD on the front page.
     */
    public function output_jsonld(): void {
        if ( ! is_front_page() || self::$outputted ) { return; }
        self::$outputted = true;

        $org_opts = get_option('es_org', []);
        $org_type = $org_opts['org_type'] ?? 'Organization';
        $telephone = $org_opts['telephone'] ?? '';
        $email = $org_opts['contact_email'] ?? get_option('admin_email');
        $price_range = $org_opts['price_range'] ?? '';
        $street_address = $org_opts['street_address'] ?? '';
        $address_locality = $org_opts['address_locality'] ?? '';
        $address_region = $org_opts['address_region'] ?? '';
        $postal_code = $org_opts['postal_code'] ?? '';
        $address_country = $org_opts['address_country'] ?? '';
        $socials = array_filter(array_map('trim', explode("\n", $org_opts['social_links'] ?? '')));

        // Cache key and duration (24 hours; adjust as needed).
        $cache_key = 'es_store_aggregate_rating';
        $cache_duration = DAY_IN_SECONDS;

        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            $average_rating = $cached_data['avg'];
            $review_count = $cached_data['count'];
        } else {
            // Fetch all approved review IDs.
            $reviews_query = new WP_Comment_Query([
                'status' => 'approve',
                'type' => 'review',
                'post_type' => 'product',  
                'fields' => 'ids',
                'number' => 0,
            ]);
            $review_ids = $reviews_query->get_comments();
            $review_count = 0;
            $ratings_sum = 0;

            if ( ! empty( $review_ids ) ) {
                foreach ( $review_ids as $review_id ) {
                    $rating = get_comment_meta( $review_id, 'rating', true );
                    if ( '' !== $rating && is_numeric( $rating ) ) {
                        $ratings_sum += (float) $rating;
                        $review_count++;
                    }
                }
            }

            $average_rating = ( $review_count > 0 ) ? number_format( $ratings_sum / $review_count, 1 ) : '0.0';

            // Cache the results.
            set_transient( $cache_key, [
                'avg' => $average_rating,
                'count' => $review_count
            ], $cache_duration );
        }

        // Conditionally prepare aggregateRating (omit if no reviews).
        $aggregate_rating = ( $review_count > 0 ) ? [
            '@type' => 'AggregateRating',
            'ratingValue' => $average_rating,
            'reviewCount' => $review_count,
        ] : null;

        $org = [
            '@context' => 'https://schema.org',
            '@type' => $org_type,
            'name' => get_bloginfo('name'),
            'url' => home_url(),
        ];

        if ($telephone) {
            $org['telephone'] = $telephone;
        }
        if ($email) {
            $org['email'] = $email;
        }
        if ($org_type === 'LocalBusiness' && $price_range) {
            $org['priceRange'] = $price_range;
        }
        if ($street_address || $address_locality || $address_region || $postal_code || $address_country) {
            $org['address'] = [
                '@type' => 'PostalAddress',
            ];
            if ($street_address) $org['address']['streetAddress'] = $street_address;
            if ($address_locality) $org['address']['addressLocality'] = $address_locality;
            if ($address_region) $org['address']['addressRegion'] = $address_region;
            if ($postal_code) $org['address']['postalCode'] = $postal_code;
            if ($address_country) $org['address']['addressCountry'] = $address_country;
        }
        if (!empty($socials)) {
            $org['sameAs'] = $socials;
        }
        if ($aggregate_rating) {
            $org['aggregateRating'] = $aggregate_rating;
        }
        $image_url = get_site_icon_url(512) ?: '';
        if ($image_url) {
            $org['image'] = $image_url;
        }

        echo "<script type='application/ld+json'>" . wp_json_encode($org) . "</script>\n";
    }
}
new ES_Org_Schema();