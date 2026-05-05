<?php
require_once 'C:/xampp/htdocs/peptide/wp-load.php';
global $wpdb;
$snippets = $wpdb->get_results("SELECT name, code FROM {$wpdb->prefix}snippets WHERE active = 1");
foreach ($snippets as $s) {
    if (stripos($s->code, 'tax') !== false) {
        echo "Snippet Name: " . $s->name . "\n";
        echo $s->code . "\n\n";
    }
}
