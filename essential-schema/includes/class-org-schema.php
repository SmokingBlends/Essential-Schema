<?php
/**
 * Essential Schema – OnlineStore JSON-LD
 * Outputs OnlineStore markup on the homepage with embedded return policies.
 */
defined('ABSPATH') || exit;

class ES_Org_Schema {
    /** Flag to prevent duplicate output. */
    private static $outputted = false;

    /** Country lists. */
    private const DOMESTIC = ['US','PR','VI','UM'];
    private const INTERNATIONAL = [
        'HK','IL','JP','AU','AT','BE','BG','HR','CZ','FR','GR','HU','EE','FI','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','GB','GE','CA'
    ];
    /** Returns page URL for linking in policies. */
    private const RETURNS_URL = 'https://www.smokingblends.com/return-policy/';

    /**
     * Constructor: hook JSON-LD output on the homepage.
     * Runs late to avoid interference.
     */
    public function __construct() {
        add_action('wp_head', [$this, 'output_jsonld'], 99);
    }

    /**
     * Build and echo OnlineStore JSON-LD on the front page.
     * - Uses Site Icon (square) for logo; falls back to Custom Logo.
     * - areaServed based on domestic + international countries.
     * - Embeds hasMerchantReturnPolicy directly as full objects.
     */
    public function output_jsonld(): void {
        if ( ! is_front_page() || self::$outputted ) { return; }
        self::$outputted = true;

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
                'post_type' => 'product',  // Added: Ensures product reviews are targeted
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

        $home   = trailingslashit( home_url() );
        $org_id = $home . '#org';
        // Prefer Site Icon (square); fallback to Custom Logo.
        $logo_url = '';
        if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
            $logo_url = get_site_icon_url( 512 );
        } else {
            $logo_id = get_theme_mod( 'custom_logo' );
            if ( $logo_id ) {
                $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            }
        }
        // Define the return policies inline.
        $policy_us = [
            '@type' => 'MerchantReturnPolicy',
            'name'  => 'Return Policy – US & Territories',
            'url'   => esc_url_raw( self::RETURNS_URL ),
            'applicableCountry'    => self::DOMESTIC,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => 30,
            'returnMethod'         => 'https://schema.org/ReturnByMail',
            'returnFees'           => 'https://schema.org/FreeReturn',
            'refundType'           => 'https://schema.org/FullRefund',
        ];
        $policy_intl = [
            '@type' => 'MerchantReturnPolicy',
            'name'  => 'Return Policy – International',
            'url'   => esc_url_raw( self::RETURNS_URL ),
            'applicableCountry'    => self::INTERNATIONAL,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => 30,
            'returnMethod'         => 'https://schema.org/ReturnByMail',
            'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
            'refundType'           => 'https://schema.org/FullRefund',
            'returnPolicyCountry' => 'US',
        ];
        // Enhanced areaServed with Country objects (merged domestic + international; removed addressCountry to fix warning).
        $area_served = [
            [ '@type' => 'Country', 'name' => 'United States' ],
            [ '@type' => 'Country', 'name' => 'Puerto Rico' ],
            [ '@type' => 'Country', 'name' => 'United States Virgin Islands' ],
            [ '@type' => 'Country', 'name' => 'United States Minor Outlying Islands' ],
            [ '@type' => 'Country', 'name' => 'Hong Kong' ],
            [ '@type' => 'Country', 'name' => 'Israel' ],
            [ '@type' => 'Country', 'name' => 'Japan' ],
            [ '@type' => 'Country', 'name' => 'Australia' ],
            [ '@type' => 'Country', 'name' => 'Austria' ],
            [ '@type' => 'Country', 'name' => 'Belgium' ],
            [ '@type' => 'Country', 'name' => 'Bulgaria' ],
            [ '@type' => 'Country', 'name' => 'Croatia' ],
            [ '@type' => 'Country', 'name' => 'Czechia' ],
            [ '@type' => 'Country', 'name' => 'France' ],
            [ '@type' => 'Country', 'name' => 'Greece' ],
            [ '@type' => 'Country', 'name' => 'Hungary' ],
            [ '@type' => 'Country', 'name' => 'Estonia' ],
            [ '@type' => 'Country', 'name' => 'Finland' ],
            [ '@type' => 'Country', 'name' => 'Italy' ],
            [ '@type' => 'Country', 'name' => 'Latvia' ],
            [ '@type' => 'Country', 'name' => 'Lithuania' ],
            [ '@type' => 'Country', 'name' => 'Luxembourg' ],
            [ '@type' => 'Country', 'name' => 'Malta' ],
            [ '@type' => 'Country', 'name' => 'Netherlands' ],
            [ '@type' => 'Country', 'name' => 'Poland' ],
            [ '@type' => 'Country', 'name' => 'Portugal' ],
            [ '@type' => 'Country', 'name' => 'Romania' ],
            [ '@type' => 'Country', 'name' => 'Slovakia' ],
            [ '@type' => 'Country', 'name' => 'Slovenia' ],
            [ '@type' => 'Country', 'name' => 'Spain' ],
            [ '@type' => 'Country', 'name' => 'Sweden' ],
            [ '@type' => 'Country', 'name' => 'United Kingdom' ],
            [ '@type' => 'Country', 'name' => 'Georgia' ],
            [ '@type' => 'Country', 'name' => 'Canada' ],
        ];
        $store = [
            '@type' => 'OnlineStore',
            '@id'   => esc_url_raw( $org_id ),
            'name'  => 'SmokingBlends.com',
            'url'   => esc_url_raw( $home ),
            'sameAs'=> [
                'https://www.pinterest.com/smokingblends/',
                'https://x.com/smoking_blends',
                'https://www.facebook.com/SmokingBlends/',
                'https://www.youtube.com/@SmokingBlends',
                'https://www.instagram.com/realsmokingblends/',
                'https://www.tiktok.com/@smokingblends',
            ],
            'areaServed' => $area_served,
            'foundingDate' => '1999-01-01',
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer service',
                    'email' => 'sales@smokingblends.com',
                ],
            ],
            'acceptedPaymentMethod' => [
                'https://schema.org/CreditCard',
                [ '@type' => 'PaymentService', 'name' => 'PayPal' ],
                [ '@type' => 'PaymentService', 'name' => 'Apple Pay' ],
                [ '@type' => 'PaymentService', 'name' => 'Google Pay' ],
                [ '@type' => 'PaymentService', 'name' => 'Venmo' ],
                [ '@type' => 'PaymentService', 'name' => 'E-check' ],
            ],
            'hasMerchantReturnPolicy' => [ $policy_us, $policy_intl ],
        ];
        // Conditionally add aggregateRating.
        if ( $aggregate_rating ) {
            $store['aggregateRating'] = $aggregate_rating;
        }
        // Omit logo if no URL found (avoids "logo": null)
        if ( $logo_url ) {
            $store['logo'] = [ '@type' => 'ImageObject', 'url' => esc_url_raw( $logo_url ) ];
        }
        $graph = [
            '@context' => 'https://schema.org',
            '@graph'   => [ $store ], // Removed WebSite schema to avoid duplication.
        ];
        echo "\n<script type=\"application/ld+json\">"
           . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
           . "</script>\n";
    }
}
new ES_Org_Schema();