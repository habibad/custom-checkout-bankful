<?php
/**
 * CCO_Assets
 *
 * Enqueues CSS and JS only on the custom checkout page.
 * Nothing is loaded on the default WC checkout or any other page.
 */

defined( 'ABSPATH' ) || exit;

class CCO_Assets {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue() {
        if ( ! get_query_var( 'cco_checkout' ) ) {
            return; // Only load on our page.
        }

        // --- Styles ---
        wp_enqueue_style(
            'cco-checkout',
            CCO_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            CCO_VERSION
        );

        // --- Scripts ---
        wp_enqueue_script(
            'cco-checkout',
            CCO_PLUGIN_URL . 'assets/js/checkout.js',
            [ 'jquery' ],
            CCO_VERSION,
            true   // footer
        );

        // Pass data from PHP to JS.
        wp_localize_script( 'cco-checkout', 'CCO', [
            'storeApiBase' => esc_url( home_url( '/wp-json/wc/store/v1' ) ),
            'apiBase'      => esc_url( home_url( '/wp-json/cco/v1' ) ),
            'wpNonce'      => wp_create_nonce( 'wp_rest' ),          // WP REST API nonce (for cookie auth)
            'nonce'        => wp_create_nonce( 'wc_store_api' ),     // WC Store API nonce
            'ajaxNonce'    => wp_create_nonce( 'cco_ajax' ),         // our own AJAX nonce
            'currency'     => get_woocommerce_currency_symbol(),
            'currencyCode' => get_woocommerce_currency(),            // e.g. "AUD", "USD"
            'i18n'         => [
                'placing_order'  => __( 'Placing order…', 'custom-checkout' ),
                'order_failed'   => __( 'Order failed. Please try again.', 'custom-checkout' ),
                'fill_required'  => __( 'Please fill in all required fields.', 'custom-checkout' ),
            ],
        ] );
    }
}
