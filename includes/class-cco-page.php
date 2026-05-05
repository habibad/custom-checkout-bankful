<?php
/**
 * CCO_Page
 *
 * Creates a virtual WordPress page at /custom-checkout/ using rewrite rules.
 * This does NOT touch the default WooCommerce checkout page at all.
 */

defined( 'ABSPATH' ) || exit;

class CCO_Page {

    public static function init() {
        add_action( 'init',                  [ __CLASS__, 'register_virtual_page' ] );
        add_filter( 'query_vars',            [ __CLASS__, 'add_query_var' ] );
        add_action( 'template_redirect',     [ __CLASS__, 'render_template' ] );
        add_action( 'template_redirect',     [ __CLASS__, 'maybe_redirect_to_custom' ], 5 );
        add_filter( 'document_title_parts',  [ __CLASS__, 'set_page_title' ] );

        // Redirect default WooCommerce checkout if enabled.
        if ( get_option( 'cco_enabled' ) === 'yes' ) {
            add_filter( 'woocommerce_get_checkout_url', [ __CLASS__, 'get_url' ] );
        }
    }

    /**
     * Register a rewrite rule for /custom-checkout/
     * Does not create or modify any WordPress page post.
     */
    public static function register_virtual_page() {
        $slug = get_option( 'cco_slug', 'custom-checkout' );
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?cco_checkout=1',
            'top'
        );
    }

    public static function add_query_var( $vars ) {
        $vars[] = 'cco_checkout';
        return $vars;
    }

    /**
     * Redirect standard checkout to custom checkout if enabled.
     */
    public static function maybe_redirect_to_custom() {
        if ( get_option( 'cco_enabled', 'no' ) !== 'yes' ) {
            return;
        }

        if ( get_query_var( 'cco_checkout' ) ) {
            return;
        }

        // Check if we are on the WooCommerce checkout page.
        $checkout_page_id = (int) wc_get_page_id( 'checkout' );
        
        // Strictly only redirect if it's the actual checkout page ID and not a sub-endpoint.
        if ( $checkout_page_id > 0 && is_page( $checkout_page_id ) ) {
            if ( ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
                wp_safe_redirect( self::get_url() );
                exit;
            }
        }
    }

    /**
     * Intercept the request and render our custom template.
     */
    public static function render_template() {
        if ( ! get_query_var( 'cco_checkout' ) ) {
            return;
        }

        // Ensure WooCommerce session is started.
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        if ( ! is_null( WC()->cart ) ) {
            WC()->cart->calculate_totals();
        }

        // Load our template instead of any theme template.
        $selected_template = get_option( 'cco_template', 'checkout.php' );
        $template = CCO_PLUGIN_DIR . 'templates/' . $selected_template;

        if ( file_exists( $template ) ) {
            // Allow theme override: place file at {theme}/custom-checkout/{selected_template}
            $theme_override = locate_template( 'custom-checkout/' . $selected_template );
            load_template( $theme_override ?: $template, true );
            exit;
        }
    }

    public static function set_page_title( $parts ) {
        if ( get_query_var( 'cco_checkout' ) ) {
            $parts['title'] = __( 'Checkout', 'custom-checkout' );
        }
        return $parts;
    }

    /**
     * Returns the URL to our custom checkout page.
     */
    public static function get_url(): string {
        $slug = get_option( 'cco_slug', 'custom-checkout' );
        return home_url( '/' . $slug . '/' );
    }
}
