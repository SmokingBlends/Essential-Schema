<?php
/**
 * Essential Schema — FAQ Schema (no settings UI)
 *
 * Purpose: Output a valid FAQPage JSON-LD on the selected FAQ page only.
 * Storage: Reads Settings option `es_policy_pages['faq_page_id']`; falls back to legacy `es_faq_page`.
 * Output: Prints JSON-LD in <head> via `wp_head` (priority 20) only when viewing the FAQ page.
 * Parsing: Extracts Q/A from markup with `.faq-item` containers,
 *          finds question in `.faq-question` (prefers nested `.faq-text`) and answer in `.faq-answer`.
 * Caching: Transient `es_faq_schema_{ID}` for 12 hours; cleared when the FAQ page is updated.
 * Safety: No settings fields are registered here. JSON-LD is echoed raw by design.
 *
 * Usage:
 *  - Ensure your Settings screen stores the FAQ page ID in `es_policy_pages['faq_page_id']`.
 *  - Place FAQ content using the expected class names on that page.
 *  - Include this class in your plugin; it will auto-hook.
 */

defined('ABSPATH') || exit;

class ES_FAQ_Schema {

	/**
	 * Resolve the active FAQ page ID.
	 * Reads `es_policy_pages['faq_page_id']`, falls back to legacy `es_faq_page`.
	 *
	 * @return int FAQ page ID or 0 if none.
	 */
	private static function get_faq_page_id(): int {
		$pages = get_option('es_policy_pages');
		$id = absint($pages['faq_page_id'] ?? 0);
		if (!$id) {
			$id = absint(get_option('es_faq_page')); // legacy fallback
		}
		return $id;
	}

	/**
	 * Hook outputs and cache invalidation.
	 * - `wp_head` prints the schema only on the FAQ page.
	 * - `save_post` clears the cached JSON when that page changes.
	 */
	public function __construct() {
		add_action('wp_head',   [ $this, 'output_schema' ], 20);
		add_action('save_post', [ $this, 'flush_cache_on_save' ], 10, 1);
	}

	/**
	 * Clear cached JSON when the FAQ page is updated.
	 *
	 * @param int $post_id Saved post ID.
	 * @return void
	 */
	public function flush_cache_on_save( $post_id ): void {
		if ( wp_is_post_revision($post_id) ) return;
		$faq_page_id = self::get_faq_page_id();
		if ( $faq_page_id && (int)$post_id === $faq_page_id ) {
			delete_transient($this->cache_key($faq_page_id));
		}
	}

	/**
	 * Print FAQPage JSON-LD in the <head> only on the configured page.
	 *
	 * @return void
	 */
	public function output_schema(): void {
		if ( ! is_page() ) return;
		$faq_page_id = self::get_faq_page_id();
		if ( ! $faq_page_id ) return;
		if ( get_queried_object_id() !== $faq_page_id ) return;

		$json = $this->build_schema_json($faq_page_id);
		if ( ! $json ) return;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD must remain raw
		echo '<script type="application/ld+json">' . $json . '</script>';
	}

	/**
	 * Build the FAQPage JSON-LD string and cache it.
	 * - Loads page content.
	 * - Strips scripts/styles/code blocks.
	 * - Extracts up to 50 Q/A pairs from `.faq-item`.
	 *
	 * @param int $faq_page_id Page ID containing the FAQ markup.
	 * @return string JSON string or empty string on failure.
	 */
	private function build_schema_json( int $faq_page_id ): string {
		$cache_key = $this->cache_key($faq_page_id);
		$cached = get_transient($cache_key);
		if ( is_string($cached) && $cached !== '' ) return $cached;

		$content = get_post_field('post_content', $faq_page_id);
		if ( empty($content) ) return '';

		$html = apply_filters('the_content', $content);
		if ( ! is_string($html) || $html === '' ) return '';

		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
		$xpath = new DOMXPath($dom);

		// Remove non-content nodes.
		foreach ( [ '//script', '//style', '//pre', '//code' ] as $expr ) {
			foreach ( $xpath->query($expr) as $n ) {
				$n->parentNode->removeChild($n);
			}
		}

		$items = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' faq-item ')]");
		if ( ! $items || $items->length === 0 ) {
			libxml_clear_errors();
			libxml_use_internal_errors(false);
			return '';
		}

		$faqs = [];
		$seen_q = [];
		$max_items = 50;

		foreach ( $items as $item ) {
			// Question: prefer nested .faq-text inside .faq-question
			$qNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' faq-question ')]//*[contains(@class,'faq-text')]", $item);
			if ( $qNode->length === 0 ) {
				$qNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' faq-question ')]", $item);
			}
			// Answer
			$aNode = $xpath->query(".//div[contains(@class,'faq-answer')]", $item);

			if ( $qNode->length === 0 || $aNode->length === 0 ) continue;

			$question = $this->clean_plain_text($qNode->item(0)->textContent);
			$answer   = $this->clean_plain_text($aNode->item(0)->textContent);

			if ( $question === '' || $answer === '' ) continue;
			if ( isset($seen_q[ md5($question) ]) ) continue;

			$faqs[] = [
				'@type' => 'Question',
				'name'  => $question,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answer,
				],
			];
			$seen_q[ md5($question) ] = true;

			if ( count($faqs) >= $max_items ) break;
		}

		libxml_clear_errors();
		libxml_use_internal_errors(false);

		if ( empty($faqs) ) return '';

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $faqs,
		];

		$json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		set_transient($cache_key, $json, 12 * HOUR_IN_SECONDS);
		return $json;
	}

	/**
	 * Build the transient key for a given FAQ page.
	 *
	 * @param int $faq_page_id Page ID.
	 * @return string Transient key string.
	 */
	private function cache_key( int $faq_page_id ): string {
		return 'es_faq_schema_' . $faq_page_id;
	}

	/**
	 * Normalize and clean plain text for schema fields.
	 * - Remove odd characters.
	 * - Decode HTML entities.
	 * - Collapse whitespace.
	 *
	 * @param string $text Raw text.
	 * @return string Cleaned text.
	 */
	private function clean_plain_text( $text ): string {
		$text = (string) $text;
		$text = str_replace([ '`', '﻿' ], '', $text);
		$text = wp_specialchars_decode($text, ENT_QUOTES);
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim($text);
	}
}

new ES_FAQ_Schema();