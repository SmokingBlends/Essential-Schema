<?php
// File: includes/class-org-schema.php
defined('ABSPATH') || exit;

/**
 * Essential Schema – Organization Schema JSON-LD
 * Outputs Organization markup on the homepage with embedded return policies.
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
     * - Uses Site Icon (square) for logo; fallback to Custom Logo.
     * - areaServed based on domestic + international countries.
     * - Embeds hasMerchantReturnPolicy directly as full objects.
     */
    public function output_jsonld(): void {
        if ( ! is_front_page() || self::$outputted ) { return; }
        self::$outputted = true;

        $org_opts = get_option('es_org', []);
        $policy_pages = get_option('es_policy_pages', []);
        $domestic_returns = get_option('es_domestic_returns', []);
        $intl_returns = get_option('es_intl_returns', []);
        $org_type = $org_opts['org_type'] ?? 'OnlineStore';
        $brand = $org_opts['brand_name'] ?? get_bloginfo('name');
        $founding = $org_opts['founding_date'] ?? '';
        $email = $org_opts['contact_email'] ?? get_option('admin_email');
        $socials = array_filter(array_map('trim', explode("\n", $org_opts['social_links'] ?? '')));
        $payments_str = $org_opts['accepted_payments'] ?? '';
        $payments = [];
        if (!empty($payments_str)) {
            foreach(array_map('trim', explode("\n", $payments_str)) as $p){
                if(str_starts_with($p, 'http')) $payments[] = $p;
                elseif($p === 'CreditCard') $payments[] = 'http://schema.org/CreditCard';
                else $payments[] = ['@type' => 'PaymentService', 'name' => $p];
            }
        }
        $domestic = array_filter(array_map('trim', explode("\n", $org_opts['domestic_countries'] ?? '')));
        $intl = array_filter(array_map('trim', explode("\n", $org_opts['international_countries'] ?? '')));
        $returns_url = $policy_pages['returns_page_id'] ? get_permalink($policy_pages['returns_page_id']) : '';
        $domestic_days = (int) ($domestic_returns['days'] ?? 0);
        $intl_days = (int) ($intl_returns['days'] ?? 0);
        $fees_dom = 'http://schema.org/' . ($domestic_returns['fees'] ?? 'FreeReturn');
        $fees_intl = 'http://schema.org/' . ($intl_returns['fees'] ?? 'ReturnFeesCustomerResponsibility');
        $domestic_policy_name = $domestic_returns['policy_name'] ?? 'Return Policy – Domestic';
        $intl_policy_name = $intl_returns['policy_name'] ?? 'Return Policy – International';

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
        // Define the return policies inline only if fields are set.
        $return_policies = [];
        if (!empty($domestic) && !empty($returns_url) && $domestic_days > 0) {
            $domestic_countries = array_map(function($code) { return ['@type' => 'Country', 'name' => $code]; }, $domestic);
            $return_policies[] = [
                '@type' => 'MerchantReturnPolicy',
                'name'  => $domestic_policy_name,
                'url'   => esc_url_raw( $returns_url ),
                'applicableCountry'    => $domestic_countries,
                'returnPolicyCategory' => 'http://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays'   => $domestic_days,
                'returnMethod'         => 'http://schema.org/ReturnByMail',
                'returnFees'           => $fees_dom,
                'refundType'           => 'http://schema.org/FullRefund',
            ];
        }
        if (!empty($intl) && !empty($returns_url) && $intl_days > 0) {
            $intl_countries = array_map(function($code) { return ['@type' => 'Country', 'name' => $code]; }, $intl);
            $return_policies[] = [
                '@type' => 'MerchantReturnPolicy',
                'name'  => $intl_policy_name,
                'url'   => esc_url_raw( $returns_url ),
                'applicableCountry'    => $intl_countries,
                'returnPolicyCategory' => 'http://schema.org/MerchantReturnFiniteReturnWindow',
                'merchantReturnDays'   => $intl_days,
                'returnMethod'         => 'http://schema.org/ReturnByMail',
                'returnFees'           => $fees_intl,
                'refundType'           => 'http://schema.org/FullRefund',
                'returnPolicyCountry' => ['@type' => 'Country', 'name' => 'US'],
            ];
        }
        // Enhanced areaServed with Country objects (merged domestic + international; removed addressCountry to fix warning).
        $area_served = [];
        foreach(array_merge($domestic, $intl) as $code){
            $area_served[] = [ '@type' => 'Country', 'name' => $code ];
        }
        $store = [
            '@type' => $org_type,
            '@id'   => esc_url_raw( $org_id ),
            'name'  => $brand,
            'url'   => esc_url_raw( $home ),
            'sameAs'=> $socials,
            'areaServed' => $area_served,
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer service',
                    'email' => $email,
                ],
            ],
        ];
        if (!empty($founding)) {
            $store['foundingDate'] = $founding;
        }
        if (!empty($payments)) {
            $store['acceptedPaymentMethod'] = $payments;
        }
        if (!empty($return_policies)) {
            $store['hasMerchantReturnPolicy'] = $return_policies;
        }
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