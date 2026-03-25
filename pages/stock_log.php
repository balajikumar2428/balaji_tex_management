<?php
$pdo = DB::conn();
$company = get_company();
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Ensure new columns exist
try { $pdo->exec('ALTER TABLE stocks ADD COLUMN chippam_bags INTEGER DEFAULT 0'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE stocks ADD COLUMN bags INTEGER DEFAULT 0'); } catch (Exception $e) {}
$pdo->exec('UPDATE stocks SET chippam_bags = COALESCE(bags_count, 0) WHERE chippam_bags = 0 AND bags_count IS NOT NULL');

// Fetch log entries (individual bags)
$logStmt = $pdo->prepare('SELECT DATE(sl.created_at) as date, sl.yarn_type_name, sl.type, sl.bag_count, sl.net_weight
                             FROM stock_log sl
                             WHERE sl.company_id=? AND DATE(sl.created_at) BETWEEN ? AND ?
                             ORDER BY sl.created_at DESC');
$logStmt->execute([$company['id'], $from, $to]);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: show filter and count
error_log('Log query: company_id='.$company['id'].', from='.$from.', to='.$to);
error_log('Log rows found: '.count($logs));

// Totals by type
$typeStmt = $pdo->prepare('SELECT sl.type, SUM(sl.bag_count) as total_bags, SUM(sl.net_weight) as total_weight
                            FROM stock_log sl
                            WHERE sl.company_id=? AND DATE(sl.created_at) BETWEEN ? AND ?
                            GROUP BY sl.type');
$typeStmt->execute([$company['id'], $from, $to]);
$typeTotals = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
$chippamTotal = 0;
$bagTotal = 0;
$chippamWeight = 0;
$bagWeight = 0;
foreach ($typeTotals as $tt) {
    if ($tt['type'] === 'Chippam') {
        $chippamTotal = (int)$tt['total_bags'];
        $chippamWeight = (float)$tt['total_weight'];
    } elseif ($tt['type'] === 'Bag') {
        $bagTotal = (int)$tt['total_bags'];
        $bagWeight = (float)$tt['total_weight'];
    }
}
$totalBags = $chippamTotal + $bagTotal;
$totalWeight = $chippamWeight + $bagWeight;

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="stock_log_'.$from.'_to_'.$to.'.xls"');
echo '<table border="1">';
echo '<tr><th colspan="5">Stock Production Log</th></tr>';
echo '<tr><td colspan="5">Company: ' . htmlspecialchars($company['name']) . '</td></tr>';
echo '<tr><td colspan="5">Period: ' . htmlspecialchars($from . ' to ' . $to) . '</td></tr>';
echo '<tr><th>Date</th><th>Yarn Type</th><th>Type</th><th>Bags Added</th><th>Weight (kg)</th></tr>';
foreach ($logs as $log) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($log['date']) . '</td>';
    echo '<td>' . htmlspecialchars($log['yarn_type_name']) . '</td>';
    echo '<td>' . htmlspecialchars($log['type']) . '</td>';
    echo '<td>' . (int)$log['bag_count'] . '</td>';
    echo '<td>' . number_format((float)$log['net_weight'], 3) . '</td>';
    echo '</tr>';
}
echo '<tr><td colspan="3"><strong>Chippam Bags Total</strong></td>';
echo '<td><strong>' . $chippamTotal . '</strong></td>';
echo '<td><strong>' . number_format($chippamWeight, 3) . '</strong></td></tr>';
echo '<tr><td colspan="3"><strong>Regular Bags Total</strong></td>';
echo '<td><strong>' . $bagTotal . '</strong></td>';
echo '<td><strong>' . number_format($bagWeight, 3) . '</strong></td></tr>';
echo '<tr><td colspan="3"><strong>Grand Total</strong></td>';
echo '<td><strong>' . $totalBags . '</strong></td>';
echo '<td><strong>' . number_format($totalWeight, 3) . '</strong></td></tr>';
echo '</table>';
exit;
?>
