<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';

$product = wc_get_product(839);
$excl = wc_get_price_excluding_tax($product);
$incl = wc_get_price_including_tax($product);

echo "Excl: $excl\n";
echo "Incl: $incl\n";
echo "Diff: " . ($incl - $excl) . "\n";
