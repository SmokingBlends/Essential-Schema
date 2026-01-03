<?php
// File: includes/class-faq-schema.php
defined('ABSPATH') || exit;

/**
 * Essential Schema – FAQ Schema JSON-LD
 * Outputs FAQ markup on the selected FAQ page using settings repeater.
 */
class ES_FAQ_Schema {
    /** Flag to prevent duplicate output. */
    private static $outputted = false;

    /**
     * Constructor: hook JSON-LD output on the selected FAQ page.
     * Runs late to avoid interference.
     */
    public function __construct() {
        add_action('wp_head', [$this, 'output_jsonld'], 99);
    }

    /**
     * Build and echo FAQ JSON-LD on the selected FAQ page.
     */
    public function output_jsonld(): void {
        $policy_pages = get_option('es_policy_pages', []);
        $faq_page_id = $policy_pages['faq_page_id'] ?? 0;

        if ( ! is_page($faq_page_id) || self::$outputted ) { return; }
        self::$outputted = true;

        $faq_opts = get_option('es_faq', []);
        $faq_items = $faq_opts['faq_items'] ?? [];

        if (empty($faq_items)) { return; }

        $faq = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'url' => get_permalink($faq_page_id),
            'mainEntity' => [],
        ];

        foreach ($faq_items as $item) {
            $faq['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        echo "\n<script type=\"application/ld+json\">"
           . wp_json_encode( $faq, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
           . "</script>\n";
    }
}
new ES_FAQ_Schema();