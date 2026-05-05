<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

WC()->frontend_includes();
WC()->session = new WC_Session_Handler();
WC()->session->init();
WC()->customer = new WC_Customer( get_current_user_id(), true );
WC()->cart = new WC_Cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( 839 ); // 83.95

WC()->cart->calculate_totals();

echo "Fees:\n";
print_r(WC()->cart->get_fees());
echo "\nTaxes total: " . WC()->cart->get_taxes_total() . "\n";
