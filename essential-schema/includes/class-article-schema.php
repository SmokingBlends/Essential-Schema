<?php
defined('ABSPATH') || exit;
/**
 * Article_Schema
 * BlogPosting JSON-LD on single posts only. No breadcrumbs. Uses Site Icon for logo.
 */
class Article_Schema {

    /** Hook BlogPosting output into <head> at priority 20. */
    public function __construct() {
        add_action('wp_head', [$this,'print_schema'], 20);
    }

    /**
     * Build and print BlogPosting JSON-LD.
     * Guards: first wp_head only; singular post; not feed/search/404.
     */
    public function print_schema() {
        $general_opts = get_option('es_general', []);
        if (empty($general_opts['enable_article_schema'])) return;

        static $printed = false;
        if ($printed) return;
        if (current_filter() !== 'wp_head' || is_feed() || is_search() || is_404()) return;
        if (!is_singular('post')) return;
        $post_id = get_queried_object_id();
        if (!$post_id) return;
        $printed = true;

        $url      = get_permalink($post_id);
        $headline = $this->get_headline($post_id);
        $desc     = $this->get_description($post_id);
        $pub      = get_the_date('c', $post_id);
        $mod      = get_the_modified_date('c', $post_id);
        $img      = get_the_post_thumbnail_url($post_id, 'full');

        $author_id = (int) get_post_field('post_author', $post_id);
        $author = [
            '@type' => 'Person',
            'name'  => get_the_author_meta('display_name', $author_id),
            'url'   => get_author_posts_url($author_id),
        ];

        $section = '';
        $cats = get_the_terms($post_id, 'category');
        if (!is_wp_error($cats) && $cats) {
            $section = implode(', ', wp_list_pluck($cats, 'name'));
        }

        $publisher = $this->build_publisher();

        $data = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => $url ],
            'headline'         => $headline,
            'description'      => $desc,
            'datePublished'    => $pub,
            'dateModified'     => $mod,
            'author'           => $author,
            'publisher'        => $publisher,
        ];
        if ($img)     $data['image'] = $img;
        if ($section) $data['articleSection'] = $section;

        echo '<script type="application/ld+json">' .
             wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
             '</script>' . "\n";
    }

    /** Build Organization publisher using Site Icon (512). */
    private function build_publisher(): array {
        $publisher = ['@type' => 'Organization', 'name' => get_bloginfo('name')];
        if ($logo = $this->get_logo_imageobject()) $publisher['logo'] = $logo;
        return $publisher;
    }

    /** Return ImageObject for Site Icon with width/height. */
    private function get_logo_imageobject(): ?array {
        if (has_site_icon()) {
            $id   = (int) get_option('site_icon');
            $url  = get_site_icon_url(512);
            $meta = $id ? wp_get_attachment_metadata($id) : [];
            $width = (int) ($meta['width'] ?? 512);
            $height = (int) ($meta['height'] ?? 512);
            return [
                '@type'  => 'ImageObject',
                'url'    => esc_url_raw($url),
                'width'  => $width,
                'height' => $height,
            ];
        }
        return null;
    }

    /** Get trimmed headline (≤110 chars). */
    private function get_headline(int $post_id): string {
        $t = get_the_title($post_id);
        if (function_exists('wp_html_excerpt')) return wp_html_excerpt($t, 110, '…');
        $len_func = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substr_func = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        return $len_func($t) > 110 ? $substr_func($t, 0, 110) . '…' : $t;
    }

    /** Get description: excerpt or stripped content, trimmed to ≤320 chars. */
    private function get_description(int $post_id): string {
        $d = get_the_excerpt($post_id);
        if (!$d) $d = wp_strip_all_tags(get_post_field('post_content', $post_id));
        if (function_exists('wp_html_excerpt')) return wp_html_excerpt($d, 320, '…');
        $len_func = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substr_func = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        return $len_func($d) > 320 ? $substr_func($d, 0, 320) . '…' : $d;
    }
}