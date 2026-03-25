<?php
/**
 * API Endpoint for Previous Day Stocks
 * Returns stocks for the previous day of a given stock entry
 */

require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
$company = get_company();
if (!$company) {
    echo json_encode(['error' => 'No company found']);
    exit;
}

$yarn_type_id = (int)($_GET['yarn_type_id'] ?? 0);
$date = trim($_GET['date'] ?? '');
$stock_type = trim($_GET['stock_type'] ?? '');

if ($yarn_type_id <= 0 || $date === '' || $stock_type === '') {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $pdo = DB::conn();
    
    // Get previous day stocks for the specific yarn type and stock type
    $stmt = $pdo->prepare('SELECT s.*, yt.name as yarn_name 
                         FROM stocks s 
                         LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id 
                         WHERE s.yarn_type_id = ? AND s.date = ? AND s.stock_type = ?
                         ORDER BY s.date DESC');
    $stmt->execute([$yarn_type_id, $date, $stock_type]);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stocks' => $stocks]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
