<?php
/**
 * Plugin Name: RIG Accessible Forms
 * Plugin URI: https://ruralimpactgroup.com
 * Description: Fully accessible forms plugin focused on both public and admin experiences. WCAG 2.2 AA minded. Minimal, no-frills, keyboard-first.
 * Version: 0.4.0
 * Author: The Rural Impact Group
 * Author URI: https://ruralimpactgroup.com
 * License: GPLv2 or later
 * Text Domain: rigaf
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RIGAF_VERSION', '0.4.0' );
define( 'RIGAF_PATH', plugin_dir_path( __FILE__ ) );
define( 'RIGAF_URL', plugin_dir_url( __FILE__ ) );

require_once RIGAF_PATH . 'includes/class-rigaf-plugin.php';

function rigaf_init_plugin() {
    \RIGAF\Plugin::instance();
}
add_action( 'plugins_loaded', 'rigaf_init_plugin' );

register_activation_hook( __FILE__, function() {
    \RIGAF\Plugin::instance()->activate();
});
