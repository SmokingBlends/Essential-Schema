<?php
// File: includes/class-faq-schema.php
defined('ABSPATH') || exit;

/**
 * Essential Schema — FAQ Schema
 * Outputs FAQPage JSON-LD from settings Q/A fields.
 */

class ES_FAQ_Schema {

    /**
     * Hook outputs.
     */
    public function __construct() {
        add_action('wp_head', [ $this, 'output_schema' ], 20);
    }

    /**
     * Print FAQPage JSON-LD if Q/A items exist.
     */
    public function output_schema(): void {
        $faq = get_option('es_faq')['faq_items'] ?? [];
        if (empty($faq)) return;

        $mainEntity = [];
        foreach ($faq as $item) {
            if (empty($item['question']) || empty($item['answer'])) continue;
            $mainEntity[] = [
                '@type' => 'Question',
                'name'  => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['answer'],
                ],
            ];
        }

        if (empty($mainEntity)) return;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD must remain raw
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}

new ES_FAQ_Schema();