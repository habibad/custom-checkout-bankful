<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

$cart = new WC_Cart();
$cart->add_to_cart(839);

$tax_rates = WC_Tax::get_rates('');
echo "Tax Rates from WC_Tax::get_rates(''):\n";
print_r($tax_rates);

$location = WC_Tax::get_tax_location('');
echo "Tax Location:\n";
print_r($location);

$cart->calculate_totals();

echo "Cart tax totals:\n";
print_r($cart->get_tax_totals());
echo "Cart Taxes Array:\n";
print_r($cart->get_taxes());

