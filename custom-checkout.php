<?php
/**
 * Plugin Name: Custom Checkout
 * Plugin URI:  https://yoursite.com
 * Description: A production-grade custom checkout page using WooCommerce Store API with a custom payment system.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: custom-checkout
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CCO_VERSION',     '1.0.0' );
define( 'CCO_PLUGIN_FILE', __FILE__ );
define( 'CCO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CCO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once CCO_PLUGIN_DIR . 'includes/class-cco-page.php';
require_once CCO_PLUGIN_DIR . 'includes/class-cco-assets.php';
require_once CCO_PLUGIN_DIR . 'includes/class-cco-api.php';
require_once CCO_PLUGIN_DIR . 'includes/class-cco-admin.php';

/**
 * Boot the plugin after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', function () {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Custom Checkout requires WooCommerce to be active.', 'custom-checkout' )
                . '</p></div>';
        } );
        return;
    }

    // This class extends WC_Payment_Gateway, so it MUST be loaded after WooCommerce.
    require_once CCO_PLUGIN_DIR . 'includes/class-cco-payment-gateway.php';

    CCO_Page::init();
    CCO_Assets::init();
    CCO_API::init();
    CCO_Admin::init();
} );

/**
 * Register the custom payment gateway with WooCommerce.
 */
add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
    if ( class_exists( 'CCO_Payment_Gateway' ) ) {
        $gateways[] = 'CCO_Payment_Gateway';
    }
    return $gateways;
} );

/**
 * Activation hook – flush rewrite rules so the virtual page works immediately.
 */
register_activation_hook( __FILE__, function () {
    CCO_Page::register_virtual_page();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
