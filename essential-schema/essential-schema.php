<?php
/**
 * Plugin Name: Essential Schema
 * Description: Schema.org JSON-LD for Products, Returns, Shipping, and FAQs.
 * Version:     1.0.0
 * Author:      SmokingBlends.com
 * Author URI:  https://www.smokingblends.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: essential-schema
 *
 * Overview:
 * - Loads module classes and one hidden settings screen.
 * - Adds a Plugins-row “Settings” link that opens that screen.
 * Security:
 * - Capability checks on admin UI.
 * - Sanitize input and escape output in module UIs.
 */

defined('ABSPATH') || exit;

// Constants.
if ( ! defined('ES_PLUGIN_FILE') ) define('ES_PLUGIN_FILE', __FILE__);
if ( ! defined('ES_PLUGIN_DIR') )  define('ES_PLUGIN_DIR', plugin_dir_path(__FILE__));
if ( ! defined('ES_PLUGIN_URL') )  define('ES_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Add a “Settings” action link on the Plugins list row.
 *
 * @param string[] $links Existing action links.
 * @return string[] Links with Settings prepended.
 */
function es_plugin_action_links( $links ) {
    $url = admin_url('options-general.php?page=es-schema-settings'); // canonical slug
    array_unshift(
        $links,
        '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'essential-schema') . '</a>'
    );
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(ES_PLUGIN_FILE), 'es_plugin_action_links');

// Load admin screen and modules.
require_once ES_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';
// require_once ES_PLUGIN_DIR . 'includes/class-returns-schema.php';
require_once ES_PLUGIN_DIR . 'includes/class-faq-schema.php';
require_once ES_PLUGIN_DIR . 'includes/class-org-schema.php';
require_once ES_PLUGIN_DIR . 'includes/class-item-specifics.php'; // meta box + injection
require_once ES_PLUGIN_DIR . 'includes/class-article-schema.php';  
require_once ES_PLUGIN_DIR . 'includes/grok-product.php';  

// require_once ES_PLUGIN_DIR . 'includes/class-shipping-schema.php';
// require_once ES_PLUGIN_DIR . 'includes/class-product-schema.php';
/**
 * Enqueue the Item Specifics stylesheet only when the current product
 * has custom “Item specifics” meta content. Native output otherwise.
 */
function es_enqueue_item_specifics_css() {
    if ( ! function_exists('is_product') || ! is_product() ) return;

    $post_id = get_queried_object_id();
    if ( ! $post_id ) return;

    $meta = get_post_meta($post_id, '_sb_item_specifics', true);
    $has_custom = is_string($meta) && '' !== trim( wp_strip_all_tags( $meta ) );
    if ( ! $has_custom ) return;

    $rel  = 'assets/css/item-specifics.css';
    $path = ES_PLUGIN_DIR . $rel;
    $ver  = file_exists($path) ? filemtime($path) : null;

    wp_enqueue_style('es-item-specifics', plugins_url($rel, ES_PLUGIN_FILE), [], $ver);
}
add_action('wp_enqueue_scripts', 'es_enqueue_item_specifics_css');




// Bootstrap Article_Schema on frontend only.
require_once ES_PLUGIN_DIR . 'includes/class-article-schema.php';
add_action('init', function(){
  if(is_admin()) return; static $loaded=false; if($loaded) return; $loaded=true; new Article_Schema();
});
