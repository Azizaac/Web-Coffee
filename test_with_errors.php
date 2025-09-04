<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sim_kopi_2", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully\n";
    
    $result = $pdo->query("SELECT s.*, u.name as cashier_name FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'completed'");
    echo "Query executed\n";
    
    $count = 0;
    while ($row = $result->fetch()) {
        echo "Sale: {$row['invoice_number']}, Cashier: {$row['cashier_name']}, Amount: {$row['final_amount']}\n";
        $count++;
    }
    echo "Total found: $count\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
