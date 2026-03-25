<?php
// Prevent any layout inclusion and ensure clean output
define('DIRECT_DOWNLOAD', true);

// Enable output buffering for better cross-browser compatibility
ob_start();

// Get database connection
require_once __DIR__ . '/../app/db.php';
$pdo = DB::conn();

// Get and validate date parameters
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $from) || !DateTime::createFromFormat('Y-m-d', $to)) {
    die('Invalid date format');
}

// Fetch stocks for the date range, ordered by stock type then date
$stmt = $pdo->prepare('SELECT s.date, yt.name as yarn_type_name, s.stock_type, s.total_bags, s.bag_weight, s.sold_bags, s.sold_weight, s.notes
                     FROM stocks s
                     LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id
                     WHERE DATE(s.date) BETWEEN ? AND ?
                     ORDER BY s.stock_type ASC, s.date ASC');
$stmt->execute([$from, $to]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log the parameters and results for troubleshooting
error_log("Download request - From: $from, To: $to, Records found: " . count($stocks));

// Separate stocks by type
$bag_stocks = [];
$chippam_stocks = [];

foreach ($stocks as $stock) {
    if ($stock['stock_type'] === 'bag') {
        $bag_stocks[] = $stock;
    } else {
        $chippam_stocks[] = $stock;
    }
}

// Generate clean HTML content
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Stocks Management Report</title>
    <style>
        /* CSS Reset for consistency */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            margin: 20px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .header { text-align: center; margin-bottom: 30px; }
        .main-title { font-size: 25px; font-weight: bold; border: 2px solid black; padding: 10px; display: inline-block; }
        .company-name { font-size: 16px; font-weight: bold; margin-top: 10px; }
        .date-range { font-size: 14px; color: #666; margin-top: 5px; }
        .section-title { text-align: center; font-weight: bold; padding: 10px; margin: 20px 0 10px 0; }
        .chippam-title { background-color: #E6F3FF; }
        .cones-title { background-color: #FFE6E6; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
            table-layout: fixed;
        }
        th, td { 
            border: 1px solid black; 
            padding: 8px; 
            text-align: left; 
            vertical-align: top;
        }
        th { 
            background-color: #f2f2f2; 
            font-weight: bold;
            position: -webkit-sticky;
            position: sticky;
            top: 0;
        }
        .number-cell { 
            text-align: right; 
            font-family: "Courier New", monospace;
        }
        .total-row { 
            background-color: #f9f9f9; 
            font-weight: bold; 
        }
        .no-data { 
            text-align: center; 
            color: red; 
            font-weight: bold; 
            padding: 20px; 
        }
        
        /* Print optimizations */
        @media print {
            body { margin: 10px; }
            .header { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }
        
        /* Cross-browser fixes */
        @-ms-viewport { width: device-width; }
        @-webkit-viewport { width: device-width; }
        @-moz-viewport { width: device-width; }
        @-ms-viewport { width: device-width; }
        @viewport { width: device-width; }
    </style>
</head>
<body>

<div class="header">
    <div class="main-title">STOCKS MANAGEMENT</div>
    <div class="company-name">BALAJI TEX</div>
    <div class="date-range">Period: ' . htmlspecialchars($from . ' to ' . $to) . '</div>
</div>';

// Check if we have data
if (empty($chippam_stocks) && empty($bag_stocks)) {
    $html .= '<div class="no-data">No stock data found for the selected date range: ' . htmlspecialchars($from . ' to ' . $to) . '</div>';
    $html .= '<div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">';
    $html .= 'Debug: Query executed with dates ' . htmlspecialchars($from) . ' to ' . htmlspecialchars($to) . '<br>';
    $html .= 'Total records found: ' . count($stocks) . '</div>';
} else {
    // CHIPPAM STOCKS Section
    if (!empty($chippam_stocks)) {
        $html .= '<div class="section-title chippam-title">CHIPPAM STOCKS</div>';
        $html .= '<table>
            <tr>
                <th>Date</th>
                <th>Yarn Type</th>
                <th>Weight per Bag (kg)</th>
                <th>Total Chippam</th>
                <th>Total Weight (kg)</th>
                <th>Available Chippam</th>
                <th>Available Weight (kg)</th>
                <th>Notes</th>
            </tr>';
        
        $chippam_total_weight = 0;
        $chippam_total_number = 0;
        $chippam_available_weight = 0;
        $chippam_available_number = 0;
        
        foreach ($chippam_stocks as $stock) {
            $total_weight = $stock['bag_weight'];
            $sold_weight = $stock['sold_weight'] ?? 0;
            $available_weight = $total_weight - $sold_weight;
            
            $total_number = $stock['total_bags'];
            $sold_number = $stock['sold_bags'] ?? 0;
            $available_number = $total_number - $sold_number;
            
            // Apply wastage calculation for chippam (0.400 per bag)
            $total_wastage = $total_number * 0.400;
            $total_weight_after_wastage = max(0, $total_weight - $total_wastage);
            
            // For available weight, deduct only the wastage for the unsold items
            $available_wastage = $available_number * 0.400;
            $available_weight_after_wastage = max(0, $available_weight - $available_wastage);
            
            $chippam_total_weight += $total_weight_after_wastage;
            $chippam_total_number += $total_number;
            $chippam_available_weight += $available_weight_after_wastage;
            $chippam_available_number += $available_number;
            
            $html .= '<tr>
                <td>' . htmlspecialchars($stock['date']) . '</td>
                <td>' . htmlspecialchars($stock['yarn_type_name']) . '</td>
                <td class="number-cell">' . number_format($stock['bag_weight'], 3) . '</td>
                <td class="number-cell">' . (int)$total_number . '</td>
                <td class="number-cell">' . number_format($total_weight_after_wastage, 3) . '</td>
                <td class="number-cell">' . (int)$available_number . '</td>
                <td class="number-cell">' . number_format($available_weight_after_wastage, 3) . '</td>
                <td>' . htmlspecialchars($stock['notes'] ?? '') . '</td>
            </tr>';
        }
        
        // Chippam Total Row
        $html .= '<tr class="total-row">
            <td colspan="3" style="text-align: right;">TOTAL (after 0.4kg wastage/chippam)</td>
            <td class="number-cell">' . (int)$chippam_total_number . '</td>
            <td class="number-cell">' . number_format($chippam_total_weight, 3) . '</td>
            <td class="number-cell" style="color: #059669; font-size: 16px;">' . (int)$chippam_available_number . '</td>
            <td class="number-cell" style="color: #059669; font-size: 16px;">' . number_format($chippam_available_weight, 3) . ' kg</td>
            <td></td>
        </tr>';
        $html .= '</table>';
    }

    // Empty row separator if both sections exist
    if (!empty($chippam_stocks) && !empty($bag_stocks)) {
        $html .= '<br>';
    }

    // CONE STOCKS (BAGS) Section
    if (!empty($bag_stocks)) {
        $html .= '<div class="section-title cones-title">CONE STOCKS (BAGS)</div>';
        $html .= '<table>
            <tr>
                <th>Date</th>
                <th>Yarn Type</th>
                <th>Weight per Bag (kg)</th>
                <th>Total Bags</th>
                <th>Total Weight (kg)</th>
                <th>Available Bags</th>
                <th>Available Weight (kg)</th>
                <th>Notes</th>
            </tr>';
        
        $bag_total_weight = 0;
        $bag_total_bags = 0;
        $bag_available_weight = 0;
        $bag_available_bags = 0;
        
        foreach ($bag_stocks as $stock) {
            $total_weight = $stock['bag_weight'] * $stock['total_bags'];
            $sold_weight = $stock['sold_weight'] ?? 0;
            $available_weight = $total_weight - $sold_weight;
            
            $total_bags = $stock['total_bags'];
            $sold_bags = $stock['sold_bags'] ?? 0;
            $available_bags = $total_bags - $sold_bags;
            
            // No wastage for cone stocks
            $bag_total_weight += $total_weight;
            $bag_total_bags += $total_bags;
            $bag_available_weight += $available_weight;
            $bag_available_bags += $available_bags;
            
            $html .= '<tr>
                <td>' . htmlspecialchars($stock['date']) . '</td>
                <td>' . htmlspecialchars($stock['yarn_type_name']) . '</td>
                <td class="number-cell">' . number_format($stock['bag_weight'], 3) . '</td>
                <td class="number-cell">' . (int)$total_bags . '</td>
                <td class="number-cell">' . number_format($total_weight, 3) . '</td>
                <td class="number-cell">' . (int)$available_bags . '</td>
                <td class="number-cell">' . number_format($available_weight, 3) . '</td>
                <td>' . htmlspecialchars($stock['notes'] ?? '') . '</td>
            </tr>';
        }
        
        // Bag Total Row
        $html .= '<tr class="total-row">
            <td colspan="3" style="text-align: right;">TOTAL</td>
            <td class="number-cell">' . (int)$bag_total_bags . '</td>
            <td class="number-cell">' . number_format($bag_total_weight, 3) . '</td>
            <td class="number-cell" style="color: #2563ea; font-size: 16px;">' . (int)$bag_available_bags . '</td>
            <td class="number-cell" style="color: #2563ea; font-size: 16px;">' . number_format($bag_available_weight, 3) . ' kg</td>
            <td></td>
        </tr>';
        $html .= '</table>';
    }
}

// Complete the HTML properly for browser compatibility
$html .= '</body>
</html>';

// Clean output buffer and get content length
ob_end_clean();

// Set headers for HTML download (cross-browser compatible)
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="stocks_management_'.$from.'_to_'.$to.'_'.time().'.html"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . strlen($html));
header('Connection: close');
header('X-Content-Type-Options: nosniff');

// Output HTML content
echo $html;
exit;
?>
