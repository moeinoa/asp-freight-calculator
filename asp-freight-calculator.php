<?php
/**
 * Plugin Name:       ASP Freight Calculator
 * Description:       Calculates intercity freight rates by vehicle type using the Neshan API.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Moein Akbari
 * Author URI:        https://moein-akbari.ir/
 * Plugin URI: https://github.com/moeinoa/
 * Text Domain:       asp-freight
 * Domain Path:       /languages
 * License:           GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'ASP_FREIGHT_VER',  '1.1.0' );
define( 'ASP_FREIGHT_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ASP_FREIGHT_URL',  plugin_dir_url( __FILE__ ) );
define( 'ASP_FREIGHT_DATA', ASP_FREIGHT_DIR . 'data/calculator-data.json' );

require_once ASP_FREIGHT_DIR . 'includes/class-admin.php';
require_once ASP_FREIGHT_DIR . 'includes/class-antispam.php';
require_once ASP_FREIGHT_DIR . 'includes/class-ajax.php';
require_once ASP_FREIGHT_DIR . 'includes/class-shortcode.php';

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'asp-freight', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    new ASP_Freight_Admin();
    new ASP_Freight_Ajax();
    new ASP_Freight_Shortcode();
} );
