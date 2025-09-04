<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing sales_report.php directly...\n";

// Simulate the sales_report.php logic
$start_date = date('Y-m-01'); // First day of current month
$end_date = date('Y-m-d'); // Today
$cashier_filter = '';

echo "Start date: $start_date\n";
echo "End date: $end_date\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=sim_kopi_2", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Build query conditions
    $where_conditions = ["s.status = 'completed'"];
    $params = [];
    
    if (!empty($start_date)) {
        $where_conditions[] = "DATE(s.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where_conditions[] = "DATE(s.created_at) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($cashier_filter)) {
        $where_conditions[] = "s.user_id = ?";
        $params[] = (int)$cashier_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    echo "Where clause: $where_clause\n";
    echo "Params: " . print_r($params, true) . "\n";
    
    // Get sales data
    $sales_query = "SELECT s.*, u.name as cashier_name,
                    COUNT(si.id) as total_items,
                    SUM(si.quantity) as total_quantity
                    FROM sales s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN sale_items si ON s.id = si.sale_id
                    {$where_clause}
                    GROUP BY s.id
                    ORDER BY s.created_at DESC";
    
    echo "Query: $sales_query\n\n";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($sales_query);
        $stmt->execute($params);
        $sales = $stmt;
    } else {
        $sales = $pdo->query($sales_query);
    }
    
    $count = 0;
    while ($sale = $sales->fetch()) {
        echo "Sale ID: {$sale['id']}, Invoice: {$sale['invoice_number']}, Cashier: {$sale['cashier_name']}, Amount: {$sale['final_amount']}\n";
        $count++;
    }
    
    echo "\nTotal sales found: $count\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
