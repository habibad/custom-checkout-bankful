<?php
/**
 * CCO_Admin
 *
 * Handles the admin settings page for Custom Checkout.
 */

defined( 'ABSPATH' ) || exit;

class CCO_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Custom Checkout', 'custom-checkout' ),
            __( 'Custom Checkout', 'custom-checkout' ),
            'manage_options',
            'custom-checkout-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'cco_settings_group', 'cco_enabled' );
        register_setting( 'cco_settings_group', 'cco_slug', [
            'default' => 'custom-checkout',
            'sanitize_callback' => [ __CLASS__, 'sanitize_slug' ]
        ] );
        register_setting( 'cco_settings_group', 'cco_template', [
            'default' => 'checkout.php'
        ] );

        add_settings_section(
            'cco_main_section',
            __( 'General Settings', 'custom-checkout' ),
            null,
            'custom-checkout-settings'
        );

        add_settings_field(
            'cco_enabled',
            __( 'Enable Custom Checkout', 'custom-checkout' ),
            [ __CLASS__, 'render_enabled_field' ],
            'custom-checkout-settings',
            'cco_main_section'
        );

        add_settings_field(
            'cco_slug',
            __( 'Checkout URL Slug', 'custom-checkout' ),
            [ __CLASS__, 'render_slug_field' ],
            'custom-checkout-settings',
            'cco_main_section'
        );

        add_settings_field(
            'cco_template',
            __( 'Checkout Template', 'custom-checkout' ),
            [ __CLASS__, 'render_template_field' ],
            'custom-checkout-settings',
            'cco_main_section'
        );
    }

    public static function sanitize_slug( $slug ) {
        $slug = sanitize_title( $slug );
        if ( empty( $slug ) ) {
            $slug = 'custom-checkout';
        }
        
        // If slug changed, we'll need to flush rewrite rules.
        if ( get_option( 'cco_slug' ) !== $slug ) {
            add_action( 'shutdown', 'flush_rewrite_rules' );
        }
        
        return $slug;
    }

    public static function render_enabled_field() {
        $enabled = get_option( 'cco_enabled', 'no' );
        ?>
        <input type="checkbox" name="cco_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?>>
        <p class="description"><?php esc_html_e( 'Redirect default WooCommerce checkout to the custom checkout page.', 'custom-checkout' ); ?></p>
        <?php
    }

    public static function render_slug_field() {
        $slug = get_option( 'cco_slug', 'custom-checkout' );
        ?>
        <code><?php echo esc_url( home_url( '/' ) ); ?></code>
        <input type="text" name="cco_slug" value="<?php echo esc_attr( $slug ); ?>" class="regular-text">
        <p class="description"><?php esc_html_e( 'The URL slug for your custom checkout page. Default is custom-checkout.', 'custom-checkout' ); ?></p>
        <?php
    }

    public static function render_template_field() {
        $selected = get_option( 'cco_template', 'checkout.php' );
        $templates = self::get_available_templates();
        ?>
        <select name="cco_template">
            <?php foreach ( $templates as $file ) : ?>
                <option value="<?php echo esc_attr( $file ); ?>" <?php selected( $selected, $file ); ?>>
                    <?php echo esc_html( $file ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'Choose a template file from the plugin\'s templates folder.', 'custom-checkout' ); ?></p>
        <?php
    }

    private static function get_available_templates() {
        $path = CCO_PLUGIN_DIR . 'templates/';
        $files = glob( $path . '*.php' );
        $templates = [];

        if ( $files ) {
            foreach ( $files as $file ) {
                $templates[] = basename( $file );
            }
        }

        return $templates;
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Checkout Settings', 'custom-checkout' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cco_settings_group' );
                do_settings_sections( 'custom-checkout-settings' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Shortcuts', 'custom-checkout' ); ?></h2>
            <p>
                <strong><?php esc_html_e( 'Live Checkout URL:', 'custom-checkout' ); ?></strong> 
                <a href="<?php echo esc_url( home_url( '/' . get_option( 'cco_slug', 'custom-checkout' ) . '/' ) ); ?>" target="_blank">
                    <?php echo esc_url( home_url( '/' . get_option( 'cco_slug', 'custom-checkout' ) . '/' ) ); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
