<?php
/**
 * Plugin Name: Essential Schema
 * Description: Schema.org JSON-LD for Organization, Products, Returns, Shipping, FAQs, Articles.
 * Version:     1.0.0
 * Author:      xAI
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: essential-schema
 *
 * Overview:
 * - Loads module classes and settings screen.
 * - Adds a Plugins-row “Settings” link.
 * Security:
 * - Capability checks on admin UI.
 * - Sanitize input and escape output.
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
require_once ES_PLUGIN_DIR . 'includes/class-faq-schema.php';
require_once ES_PLUGIN_DIR . 'includes/class-org-schema.php';
require_once ES_PLUGIN_DIR . 'includes/class-article-schema.php';  
require_once ES_PLUGIN_DIR . 'includes/grok-product.php';  
require_once ES_PLUGIN_DIR . 'includes/class-policy-schema.php';  

// Bootstrap Article_Schema on frontend only.
add_action('init', function(){
  if(is_admin()) return; static $loaded=false; if($loaded) return; $loaded=true; new Article_Schema();
});