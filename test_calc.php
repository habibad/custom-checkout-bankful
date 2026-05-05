<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

WC()->frontend_includes();
WC()->session = new WC_Session_Handler();
WC()->session->init();
WC()->customer = new WC_Customer( get_current_user_id(), true );
WC()->cart = new WC_Cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( 839 ); // Add Sermorelin

// Apply the filter
$inject_tax_location = static function() {
    return [ 'AU', 'NSW', '', '' ];
};
add_filter( 'woocommerce_customer_taxable_address', $inject_tax_location, PHP_INT_MAX );

WC()->cart->calculate_totals();

echo "Tax total: " . WC()->cart->get_taxes_total() . "\n";
echo "Tax lines: "; print_r(WC()->cart->get_tax_totals());
