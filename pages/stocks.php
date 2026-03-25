<?php
$pdo = DB::conn();
$company = get_company();

// Get date filter from URL or use empty for all dates
$date_filter_from = $_GET['date_from'] ?? '';
$date_filter_to = $_GET['date_to'] ?? '';

// Get yarn types for dropdown
$yarn_stmt = $pdo->prepare('SELECT * FROM yarn_types WHERE company_id=? ORDER BY name');
$yarn_stmt->execute([$company['id']]);
$yarn_types = $yarn_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch bag stocks based on date filter
if (empty($date_filter_from) && empty($date_filter_to)) {
    // Get all bag stocks
    $stmt = $pdo->prepare('SELECT s.*, yt.name as yarn_name 
                         FROM stocks s 
                         LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id 
                         WHERE s.stock_type = ?
                         ORDER BY s.date DESC, yt.name ASC, s.id ASC');
    $stmt->execute(['bag']);
} else {
    // Get bag stocks for specific date range
    $from = !empty($date_filter_from) ? $date_filter_from : '1970-01-01';
    $to = !empty($date_filter_to) ? $date_filter_to : '2099-12-31';

    $stmt = $pdo->prepare('SELECT s.*, yt.name as yarn_name 
                         FROM stocks s 
                         LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id 
                         WHERE s.stock_type = ? AND s.date BETWEEN ? AND ?
                         ORDER BY s.date DESC, yt.name ASC, s.id ASC');
    $stmt->execute(['bag', $from, $to]);
}
$bag_stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch chippam stocks based on date filter
if (empty($date_filter_from) && empty($date_filter_to)) {
    // Get all chippam stocks
    $stmt = $pdo->prepare('SELECT s.*, yt.name as yarn_name 
                         FROM stocks s 
                         LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id 
                         WHERE s.stock_type = ?
                         ORDER BY s.date DESC, yt.name ASC, s.id ASC');
    $stmt->execute(['chippam']);
} else {
    // Get chippam stocks for specific date range
    $from = !empty($date_filter_from) ? $date_filter_from : '1970-01-01';
    $to = !empty($date_filter_to) ? $date_filter_to : '2099-12-31';

    $stmt = $pdo->prepare('SELECT s.*, yt.name as yarn_name 
                         FROM stocks s 
                         LEFT JOIN yarn_types yt ON s.yarn_type_id = yt.id 
                         WHERE s.stock_type = ? AND s.date BETWEEN ? AND ?
                         ORDER BY s.date DESC, yt.name ASC, s.id ASC');
    $stmt->execute(['chippam', $from, $to]);
}
$chippam_stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate daily totals
$daily_bag_totals = [];
$daily_chippam_totals = [];

// Only calculate daily totals if we're not filtering by a specific date
if (empty($date_filter_from) && empty($date_filter_to)) {
    foreach ($bag_stocks as $stock) {
        $date = $stock['date'];
        if (!isset($daily_bag_totals[$date])) {
            $daily_bag_totals[$date] = [
                'total_bags' => 0,
                'total_weight' => 0
            ];
        }
        // For bag stocks: accumulate original weight first
        $daily_bag_totals[$date]['total_bags'] += $stock['total_bags'];
        $daily_bag_totals[$date]['total_weight'] += $stock['bag_weight'] * $stock['total_bags'];
    }

    // No wastage applied to bag stocks (cones)

    foreach ($chippam_stocks as $stock) {
        $date = $stock['date'];
        if (!isset($daily_chippam_totals[$date])) {
            $daily_chippam_totals[$date] = [
                'total_weight' => 0,
                'total_number' => 0
            ];
        }
        // For chippam: accumulate original weight and number first
        $daily_chippam_totals[$date]['total_weight'] += $stock['bag_weight'];
        $daily_chippam_totals[$date]['total_number'] += $stock['total_bags'];
    }

    // Apply wastage to daily totals after accumulation
    foreach ($daily_chippam_totals as $date => &$chippam_totals) {
        $total_wastage = $chippam_totals['total_number'] * 0.400;
        $chippam_totals['total_weight'] = $chippam_totals['total_weight'] - $total_wastage;
    }
}

// Overall Totals for Summary Display
$overall_stock_counters = [
    'bag' => ['qty' => 0, 'weight' => 0],
    'chippam' => ['qty' => 0, 'weight' => 0]
];

foreach ($bag_stocks as $s) {
    $overall_stock_counters['bag']['qty'] += $s['total_bags'];
    $overall_stock_counters['bag']['weight'] += $s['total_bags'] * $s['bag_weight'];
}

foreach ($chippam_stocks as $s) {
    $overall_stock_counters['chippam']['qty'] += $s['total_bags'];
    $overall_stock_counters['chippam']['weight'] += $s['bag_weight'];
}

// Get all dates that have stocks for the date picker
$stmt = $pdo->prepare('SELECT DISTINCT date FROM stocks ORDER BY date DESC');
$stmt->execute();
$available_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b">
      <div>
        <h2 class="font-semibold text-2xl text-gray-800">Stock Management</h2>
        <p class="text-sm text-gray-500">Manage production and sales of yarns</p>
      </div>
      
      <!-- Overall Weight Totals -->
      <div class="flex flex-wrap gap-4 mt-4 sm:mt-0">
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-center">
          <span class="text-xs text-blue-600 font-bold uppercase block">Total Weight (Bags)</span>
          <span class="text-xl font-extrabold text-blue-800 block"><?php echo number_format($overall_stock_counters['bag']['weight'], 2); ?> kg</span>
          <span class="text-xs text-blue-500 font-bold block pt-1 border-t border-blue-200 mt-1">Qty: <?php echo number_format($overall_stock_counters['bag']['qty']); ?> bags</span>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2 text-center">
          <span class="text-xs text-green-600 font-bold uppercase block">Total Weight (Chippams)</span>
          <span class="text-xl font-extrabold text-green-800 block"><?php echo number_format($overall_stock_counters['chippam']['weight'], 2); ?> kg</span>
          <span class="text-xs text-green-500 font-bold block pt-1 border-t border-green-200 mt-1">Qty: <?php echo number_format($overall_stock_counters['chippam']['qty']); ?> items</span>
        </div>
      </div>
      <div class="flex gap-3">
        <button onclick="showAddStockForm()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
          Add Stocks
        </button>
      </div>
    </div>
    
    <!-- Add New Stock Form -->
    <div id="add_stock_form" class="mb-6 p-4 border rounded bg-gray-50" style="display:none;">
      <h3 class="font-medium mb-3">Add New Stock</h3>
      <form method="post" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="action" value="add_stock">
        <div class="flex-1 min-w-0">
          <label class="block text-sm text-gray-600">Date</label>
          <input name="stock_date" type="date" class="w-full border rounded px-3 py-2" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="flex-1 min-w-0">
          <label class="block text-sm text-gray-600">Yarn Type</label>
          <select name="yarn_type_id" class="w-full border rounded px-3 py-2" required>
            <option value="">Select yarn type</option>
            <?php foreach ($yarn_types as $yt): ?>
              <option value="<?php echo (int)$yt['id']; ?>"><?php echo e($yt['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex-1 min-w-0">
          <label class="block text-sm text-gray-600">Stock Type</label>
          <select name="stock_type" class="w-full border rounded px-3 py-2" required onchange="toggleStockType(this.value)">
            <option value="">Select type</option>
            <option value="chippam">Chippam</option>
            <option value="bag">Bag (Cone)</option>
          </select>
        </div>
        <div id="chippam_fields" class="flex-1 min-w-0" style="display:none;">
          <label class="block text-sm text-gray-600">Total Weight (kg)</label>
          <input name="total_chippam_weight" type="text" class="w-full border rounded px-3 py-2" placeholder="100.000,50.000">
        </div>
        <div id="chippam_number_field" class="flex-1 min-w-0" style="display:none;">
          <label class="block text-sm text-gray-600">Total Number</label>
          <input name="total_chippam_number" type="text" class="w-full border rounded px-3 py-2" placeholder="10,5">
        </div>
        <div id="bag_fields" class="flex-1 min-w-0" style="display:none;">
          <label class="block text-sm text-gray-600">Total Bags</label>
          <input name="total_bags" type="text" class="w-full border rounded px-3 py-2" placeholder="10 or 5,5">
        </div>
        <div class="flex-1 min-w-0">
          <label class="block text-sm text-gray-600">Notes (Optional)</label>
          <input name="stock_notes" type="text" class="w-full border rounded px-3 py-2" placeholder="Add any notes here...">
        </div>
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Add Stock</button>
      </form>
    </div>


    <!-- Current Stocks Table -->
    <div class="mb-4 flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-600">Filter From</label>
        <input type="date" id="stock_date_from" class="border rounded px-2 py-1" value="<?php echo e($date_filter_from); ?>" onchange="filterStocksByDateRange()">
        <label class="text-sm text-gray-600 ml-2">To</label>
        <input type="date" id="stock_date_to" class="border rounded px-2 py-1" value="<?php echo e($date_filter_to); ?>" onchange="filterStocksByDateRange()">
        <button type="button" onclick="clearDateFilterRange()" class="ml-2 pl-2 text-sm text-blue-600 hover:text-blue-800 underline">Clear Filters</button>
      </div>
      <button onclick="deleteAllStocks()" class="bg-red-600 text-white px-4 py-1 rounded hover:bg-red-700 transition-colors ml-auto border">Delete All Stocks</button>
    </div>
    
    <!-- Download Section -->
    <div class="mb-4 p-4 border rounded bg-gray-50 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h4 class="font-medium mb-3">Download Stocks File</h4>
        <div class="flex items-center gap-4">
          <div>
            <label class="text-sm text-gray-600">From Date</label>
            <input type="date" id="dl_date_from" class="border rounded px-2 py-1" value="<?php echo !empty($date_filter_from) ? e($date_filter_from) : date('Y-m-01'); ?>">
          </div>
          <div>
            <label class="text-sm text-gray-600">To Date</label>
            <input type="date" id="dl_date_to" class="border rounded px-2 py-1" value="<?php echo !empty($date_filter_to) ? e($date_filter_to) : date('Y-m-d'); ?>">
          </div>
        </div>
      </div>
      <div>
        <button onclick="downloadHTML()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-medium">Download PDF/HTML</button>
      </div>
    </div>
    
    <!-- Bag Stocks -->
    <div class="mb-6">
      <h3 class="font-semibold text-lg mb-3 text-blue-600">Bag Stocks (Cone)</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600">
              <th class="py-2">Date</th>
              <th class="py-2">Yarn Type</th>
              <th class="py-2">Weight per Bag (kg)</th>
              <th class="py-2">Total Bags</th>
              <th class="py-2">Total Weight (kg)</th>
              <th class="py-2">Available</th>
              <th class="py-2">Notes</th>
              <th class="py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $current_date = '';
            $daily_total_shown = [];
            foreach ($bag_stocks as $r): 
              // Get yarn type name
              $yarn_name = 'Unknown';
              foreach ($yarn_types as $yt) {
                if ($yt['id'] == $r['yarn_type_id']) {
                  $yarn_name = $yt['name'];
                  break;
                }
              }
              
              $total_weight = $r['bag_weight'] * $r['total_bags'];
              $sold_weight = $r['sold_weight'] ?? 0;
              $available_weight = $total_weight - $sold_weight;
              
              // Show original weight (wastage applied at daily total level)
              $display_weight = $total_weight;
              
              $total_bags = $r['total_bags'];
              $sold_bags = $r['sold_bags'] ?? 0;
              $available_bags = $total_bags - $sold_bags;
            ?>
              <!-- Show daily total when date changes (before showing stocks for new date) -->
              <?php if ($current_date !== '' && $current_date !== $r['date']): ?>
                <?php if (isset($daily_bag_totals[$current_date]) && !in_array($current_date, $daily_total_shown)): ?>
                  <tr class="bg-gradient-to-r from-blue-50 to-blue-100 border-t-2 border-blue-300">
                    <td colspan="8" class="py-3 text-center">
                      <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
                        </svg>
                        <span class="text-blue-900 font-bold text-lg">Daily Total for <?php echo e($current_date); ?></span>
                      </div>
                      <div class="text-blue-700 font-semibold">
                        <span class="mr-4"><?php echo (int)$daily_bag_totals[$current_date]['total_bags']; ?> bags</span>
                        <span>= <?php echo number_format($daily_bag_totals[$current_date]['total_weight'], 3); ?> kg</span>
                      </div>
                    </td>
                  </tr>
                  <?php 
                  $daily_total_shown[] = $current_date;
                  ?>
                <?php endif; ?>
              <?php endif; ?>
              
              <tr class="border-t hover:bg-gray-50 cursor-pointer" onclick="showPreviousDayStocks(<?php echo (int)$r['yarn_type_id']; ?>, '<?php echo e($r['date']); ?>', 'bag')">
                <td class="py-2"><?php echo e($r['date']); ?></td>
                <td class="py-2 font-medium"><?php echo e($yarn_name); ?></td>
                <td class="py-2"><?php echo number_format($r['bag_weight'], 3); ?></td>
                <td class="py-2"><?php echo (int)$total_bags; ?></td>
                <td class="py-2"><?php echo number_format($display_weight, 3); ?></td>
                <td class="py-2">
                  <?php 
                  $available_bags_count = (int)($available_bags);
                  
                  if ($available_bags_count == 0) {
                      echo '0 bags';
                  } else {
                      echo $available_bags_count . ' bags';
                  }
                  ?>
                </td>
                <td class="py-2"><?php echo e($r['notes'] ?? ''); ?></td>
                <td class="py-2" onclick="event.stopPropagation()">
                  <form method="post" onsubmit="return confirm('Delete this stock record?')" class="inline">
                    <input type="hidden" name="action" value="delete_stock">
                    <input type="hidden" name="stock_id" value="<?php echo (int)$r['id']; ?>">
                    <button class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">Delete</button>
                  </form>
                </td>
              </tr>
              
              <?php 
              $current_date = $r['date'];
            endforeach; ?>
            
            <!-- Show final daily total for the last date -->
            <?php if (!empty($bag_stocks) && !empty($daily_bag_totals)): ?>
              <?php foreach ($daily_bag_totals as $date => $totals): ?>
                <?php if (!in_array($date, $daily_total_shown)): ?>
                  <tr class="bg-gradient-to-r from-blue-50 to-blue-100 border-t-2 border-blue-300">
                    <td colspan="8" class="py-3 text-center">
                      <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
                        </svg>
                        <span class="text-blue-900 font-bold text-lg">Daily Total for <?php echo e($date); ?></span>
                      </div>
                      <div class="text-blue-700 font-semibold">
                        <span class="mr-4"><?php echo (int)$totals['total_bags']; ?> bags</span>
                        <span>= <?php echo number_format($totals['total_weight'], 3); ?> kg</span>
                      </div>
                    </td>
                  </tr>
                  <?php 
                  $daily_total_shown[] = $date;
                  endif;
                ?>
              <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (empty($bag_stocks)): ?>
              <tr>
                <td colspan="8" class="py-4 text-center text-gray-500">No bag stocks found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        
        <!-- Show filtered date total for bag stocks -->
        <?php if ((!empty($date_filter_from) || !empty($date_filter_to)) && !empty($bag_stocks)): ?>
          <?php
          // Calculate total for filtered date
          $filtered_total_bags = 0;
          $filtered_total_weight = 0;
          foreach ($bag_stocks as $stock) {
              $filtered_total_bags += $stock['total_bags'];
              $filtered_total_weight += $stock['bag_weight'] * $stock['total_bags'];
          }
          // Apply wastage
          $filtered_wastage = $filtered_total_bags * 0.400;
          $filtered_total_weight = $filtered_total_weight - $filtered_wastage;
          ?>
          <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
            <div class="flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
              </svg>
              <span class="text-blue-900 font-bold text-lg">Total for <?php echo e($date_filter_from ? $date_filter_from : 'Start'); ?> to <?php echo e($date_filter_to ? $date_filter_to : 'End'); ?></span>
            </div>
            <div class="text-blue-700 font-semibold text-center mt-1">
              <span class="mr-4"><?php echo (int)$filtered_total_bags; ?> bags</span>
              <span>= <?php echo number_format($filtered_total_weight, 3); ?> kg</span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Chippam Stocks -->
    <div class="mb-6">
      <h3 class="font-semibold text-lg mb-3 text-green-600">Chippam Stocks</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600">
              <th class="py-2">Date</th>
              <th class="py-2">Yarn Type</th>
              <th class="py-2">Number</th>
              <th class="py-2">Weight (kg)</th>
              <th class="py-2">Available</th>
              <th class="py-2">Notes</th>
              <th class="py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $current_date = '';
            $daily_chippam_total_shown = [];
            foreach ($chippam_stocks as $r): 
              // Get yarn type name
              $yarn_name = 'Unknown';
              foreach ($yarn_types as $yt) {
                if ($yt['id'] == $r['yarn_type_id']) {
                  $yarn_name = $yt['name'];
                  break;
                }
              }
              
              $total_weight = $r['bag_weight']; // For chippam, bag_weight stores total weight
              $sold_weight = $r['sold_weight'] ?? 0;
              $available_weight = $total_weight - $sold_weight;
              
              // Show original weight (wastage applied at daily total level)
              $display_weight = $r['bag_weight'];
              
              $total_number = $r['total_bags']; // For chippam, this should be 1 (1 bag per entry)
              $sold_number = $r['sold_bags'] ?? 0;
              $available_number = $total_number - $sold_number;
            ?>
              <!-- Show daily total when date changes (before showing stocks for new date) -->
              <?php if ($current_date !== '' && $current_date !== $r['date']): ?>
                <?php if (isset($daily_chippam_totals[$current_date]) && !in_array($current_date, $daily_chippam_total_shown)): ?>
                  <tr class="bg-gradient-to-r from-green-50 to-green-100 border-t-2 border-green-300">
                    <td colspan="7" class="py-3 text-center">
                      <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
                        </svg>
                        <span class="text-green-900 font-bold text-lg">Daily Total for <?php echo e($current_date); ?></span>
                      </div>
                      <div class="text-green-700 font-semibold">
                        <span class="mr-4"><?php echo (int)$daily_chippam_totals[$current_date]['total_number']; ?> chippam</span>
                        <span>= <?php echo number_format($daily_chippam_totals[$current_date]['total_weight'], 3); ?> kg</span>
                      </div>
                    </td>
                  </tr>
                  <?php 
                  $daily_chippam_total_shown[] = $current_date;
                  ?>
                <?php endif; ?>
              <?php endif; ?>
              
              <tr class="border-t hover:bg-gray-50 cursor-pointer" onclick="showPreviousDayStocks(<?php echo (int)$r['yarn_type_id']; ?>, '<?php echo e($r['date']); ?>', 'chippam')">
                <td class="py-2"><?php echo e($r['date']); ?></td>
                <td class="py-2 font-medium"><?php echo e($yarn_name); ?></td>
                <td class="py-2"><?php echo (int)$total_number; ?></td>
                <td class="py-2"><?php echo number_format($display_weight, 3); ?></td>
                <td class="py-2">
                  <?php 
                  if ($available_number == 0 && $available_weight == 0) {
                      echo '0 chippam (0.000 kg)';
                  } else {
                      echo (int)$available_number . ' chippam (' . number_format($available_weight, 3) . ' kg)';
                  }
                  ?>
                </td>
                <td class="py-2"><?php echo e($r['notes'] ?? ''); ?></td>
                <td class="py-2" onclick="event.stopPropagation()">
                  <form method="post" onsubmit="return confirm('Delete this stock record?')" class="inline">
                    <input type="hidden" name="action" value="delete_stock">
                    <input type="hidden" name="stock_id" value="<?php echo (int)$r['id']; ?>">
                    <button class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">Delete</button>
                  </form>
                </td>
              </tr>
              
              <?php 
              $current_date = $r['date'];
            endforeach; ?>
            
            <!-- Show final daily total for the last date -->
            <?php if (!empty($chippam_stocks) && !empty($daily_chippam_totals)): ?>
              <?php foreach ($daily_chippam_totals as $date => $totals): ?>
                <?php if (!in_array($date, $daily_chippam_total_shown)): ?>
                  <tr class="bg-gradient-to-r from-green-50 to-green-100 border-t-2 border-green-300">
                    <td colspan="7" class="py-3 text-center">
                      <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
                        </svg>
                        <span class="text-green-900 font-bold text-lg">Daily Total for <?php echo e($date); ?></span>
                      </div>
                      <div class="text-green-700 font-semibold">
                        <span class="mr-4"><?php echo (int)$daily_chippam_totals[$date]['total_number']; ?> chippam</span>
                        <span>= <?php echo number_format($daily_chippam_totals[$date]['total_weight'], 3); ?> kg</span>
                      </div>
                    </td>
                  </tr>
                  <?php 
                  $daily_chippam_total_shown[] = $date;
                  endif;
                ?>
              <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (empty($chippam_stocks)): ?>
              <tr>
                <td colspan="7" class="py-4 text-center text-gray-500">No chippam stocks found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        
        <!-- Show filtered date total for chippam stocks -->
        <?php if ((!empty($date_filter_from) || !empty($date_filter_to)) && !empty($chippam_stocks)): ?>
          <?php
          // Calculate total for filtered date
          $filtered_total_bags = 0;
          $filtered_total_weight = 0;
          foreach ($chippam_stocks as $stock) {
              $filtered_total_bags += $stock['total_bags'];
              $filtered_total_weight += $stock['bag_weight'];
          }
          // Apply wastage
          $filtered_wastage = $filtered_total_bags * 0.400;
          $filtered_total_weight = $filtered_total_weight - $filtered_wastage;
          ?>
          <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded">
            <div class="flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6H4m0 0l6-6m-6 6V9a9 9 0 1118 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0a9 9 0 0018 0 9 9 9v2a3 3 0 006 0 3 3v0z"/>
              </svg>
              <span class="text-green-900 font-bold text-lg">Total for <?php echo e($date_filter_from ? $date_filter_from : 'Start'); ?> to <?php echo e($date_filter_to ? $date_filter_to : 'End'); ?></span>
            </div>
            <div class="text-green-700 font-semibold text-center mt-1">
              <span class="mr-4"><?php echo (int)$filtered_total_bags; ?> chippam</span>
              <span>= <?php echo number_format($filtered_total_weight, 3); ?> kg</span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Previous Day Stocks Modal -->
    <div id="previousDayModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
      <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[80vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Previous Day Stocks</h3>
              <button onclick="closePreviousDayModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            <div id="previousDayContent">
              <!-- Previous day stocks will be loaded here -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</section>

<script>
// Cross-browser compatibility fixes
if (!window.fetch) {
  // Polyfill for older browsers
  window.fetch = function(url, options) {
    options = options || {};
    return new Promise(function(resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open(options.method || 'GET', url, true);
      xhr.onload = function() {
        resolve({
          ok: xhr.status >= 200 && xhr.status < 300,
          status: xhr.status,
          json: function() { return JSON.parse(xhr.responseText); }
        });
      };
      xhr.onerror = function() { reject(new Error('Network request failed')); };
      xhr.send();
    });
  };
}

// Cross-browser event handling
function addEvent(element, event, func) {
  if (element.addEventListener) {
    element.addEventListener(event, func, false);
  } else if (element.attachEvent) {
    element.attachEvent('on' + event, func);
  }
}

// Cross-browser classList support
function hasClass(element, className) {
  return (' ' + element.className + ' ').indexOf(' ' + className + ' ') > -1;
}

function addClass(element, className) {
  if (!hasClass(element, className)) {
    if (element.classList) {
      element.classList.add(className);
    } else {
      element.className += ' ' + className;
    }
  }
}

function removeClass(element, className) {
  if (hasClass(element, className)) {
    if (element.classList) {
      element.classList.remove(className);
    } else {
      element.className = element.className.replace(new RegExp('(^|\\b)' + className.split(' ').join('|') + '(\\b|$)', 'gi'), ' ');
    }
  }
}

function filterStocksByDateRange() {
  var fromDate = document.getElementById('stock_date_from').value;
  var toDate = document.getElementById('stock_date_to').value;
  var query = [];
  if (fromDate) query.push('date_from=' + encodeURIComponent(fromDate));
  if (toDate) query.push('date_to=' + encodeURIComponent(toDate));
  
  if (query.length > 0) {
    window.location.href = 'index.php?page=stocks&' + query.join('&');
  } else {
    window.location.href = 'index.php?page=stocks';
  }
}

function clearDateFilterRange() {
  document.getElementById('stock_date_from').value = '';
  document.getElementById('stock_date_to').value = '';
  window.location.href = 'index.php?page=stocks';
}

function showAddStockForm() {
  document.getElementById('add_stock_form').style.display = 'block';
}


function showPreviousDayStocks(yarnTypeId, currentDate, stockType) {
  // Calculate previous day date
  var date = new Date(currentDate);
  date.setDate(date.getDate() - 1);
  var previousDate = date.toISOString().split('T')[0];
  
  // Show modal with loading
  var modal = document.getElementById('previousDayModal');
  var content = document.getElementById('previousDayContent');
  
  removeClass(modal, 'hidden');
  content.innerHTML = '<div class="text-center py-8">Loading...</div>';
  
  // Fetch previous day stocks via AJAX
  var url = 'api/previous_day_stocks.php?yarn_type_id=' + encodeURIComponent(yarnTypeId) + 
            '&date=' + encodeURIComponent(previousDate) + '&stock_type=' + encodeURIComponent(stockType);
  
  fetch(url)
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (data.success) {
        displayPreviousDayStocks(data.stocks, previousDate, stockType);
      } else {
        content.innerHTML = '<div class="text-center py-8 text-red-500"><p>' + data.error + '</p></div>';
      }
    })
    .catch(function(error) {
      content.innerHTML = '<div class="text-center py-8 text-red-500"><p>Error loading previous day stocks</p></div>';
    });
}

function closePreviousDayModal() {
  addClass(document.getElementById('previousDayModal'), 'hidden');
}

function displayPreviousDayStocks(stocks, date, stockType) {
  var html = '<div class="mb-4">' +
    '<h4 class="font-medium text-gray-700">Stocks for ' + date + '</h4>' +
    '<p class="text-sm text-gray-500">Stock Type: ' + (stockType === 'bag' ? 'Bag (Cone)' : 'Chippam') + '</p>' +
    '</div>';
  
  if (stocks.length === 0) {
    html += '<div class="text-center py-8 text-gray-500">' +
      '<p>No stocks found for ' + date + '</p>' +
      '</div>';
  } else {
    html += '<div class="overflow-x-auto">';
    html += '<table class="w-full text-sm border">';
    html += '<thead>';
    html += '<tr class="bg-gray-50">';
    
    if (stockType === 'bag') {
      html += '<th class="border px-3 py-2 text-left">Date</th>';
      html += '<th class="border px-3 py-2 text-left">Yarn Type</th>';
      html += '<th class="border px-3 py-2 text-left">Weight per Bag</th>';
      html += '<th class="border px-3 py-2 text-left">Total Bags</th>';
      html += '<th class="border px-3 py-2 text-left">Total Weight (kg)</th>';
      html += '<th class="border px-3 py-2 text-left">Available Bags</th>';
      html += '<th class="border px-3 py-2 text-left">Available Weight (kg)</th>';
    } else {
      html += '<th class="border px-3 py-2 text-left">Date</th>';
      html += '<th class="border px-3 py-2 text-left">Yarn Type</th>';
      html += '<th class="border px-3 py-2 text-left">Total</th>';
      html += '<th class="border px-3 py-2 text-left">Available</th>';
    }
    
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    for (var i = 0; i < stocks.length; i++) {
      var stock = stocks[i];
      html += '<tr class="border">';
      html += '<td class="border px-3 py-2">' + stock.date + '</td>';
      html += '<td class="border px-3 py-2 font-medium">' + stock.yarn_name + '</td>';
      
      if (stockType === 'bag') {
        var total_weight = stock.bag_weight * stock.total_bags;
        var sold_weight = stock.sold_weight || 0;
        var available_weight = total_weight - sold_weight;
        
        var total_bags = stock.total_bags;
        var sold_bags = stock.sold_bags || 0;
        var available_bags = total_bags - sold_bags;
        
        html += '<td class="border px-3 py-2">' + stock.bag_weight.toFixed(3) + '</td>';
        html += '<td class="border px-3 py-2">' + stock.total_bags + ' bags</td>';
        html += '<td class="border px-3 py-2">' + total_weight.toFixed(1) + '</td>';
        
        if (available_bags === 0) {
          html += '<td class="border px-3 py-2">0 bags</td>';
        } else {
          html += '<td class="border px-3 py-2">' + available_bags + ' bags</td>';
        }
        html += '<td class="border px-3 py-2">' + available_weight.toFixed(1) + ' kg</td>';
      } else {
        var total_weight = stock.bag_weight; // For chippam, bag_weight stores total weight
        var sold_weight = stock.sold_weight || 0;
        var available_weight = total_weight - sold_weight;
        
        var total_number = stock.total_bags; // For chippam, this represents quantity/number
        var sold_number = stock.sold_bags || 0;
        var available_number = total_number - sold_number;
        
        html += '<td class="border px-3 py-2">' + total_number + ' items (' + total_weight.toFixed(1) + ' kg)</td>';
        
        if (available_number === 0) {
          html += '<td class="border px-3 py-2">0 items (0.0 kg)</td>';
        } else {
          html += '<td class="border px-3 py-2">' + available_number + ' items (' + available_weight.toFixed(1) + ' kg)</td>';
        }
      }
      
      html += '</tr>';
    }
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
  }
  
  document.getElementById('previousDayContent').innerHTML = html;
}

function downloadHTML() {
  var from = document.getElementById('dl_date_from').value;
  var to = document.getElementById('dl_date_to').value;
  var url = 'index.php?page=stocks_download_new&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
  
  // Use anchor tag method instead of iframe for better modern browser compatibility
  var a = document.createElement('a');
  a.style.display = 'none';
  a.href = url;
  a.setAttribute('download', '');
  document.body.appendChild(a);
  a.click();
  
  // Clean up after download triggers
  setTimeout(function() {
    if (a && a.parentNode) {
      document.body.removeChild(a);
    }
  }, 500);
}

// Cross-browser form handling
function toggleStockType(type) {
  var chippamFields = document.getElementById('chippam_fields');
  var chippamNumberField = document.getElementById('chippam_number_field');
  var bagFields = document.getElementById('bag_fields');
  
  if (type === 'chippam') {
    chippamFields.style.display = 'block';
    chippamNumberField.style.display = 'block';
    bagFields.style.display = 'none';
  } else if (type === 'bag') {
    chippamFields.style.display = 'none';
    chippamNumberField.style.display = 'none';
    bagFields.style.display = 'block';
  }
}


function deleteAllStocks() {
  if (confirm('Are you sure you want to delete ALL stock records? This action cannot be undone.')) {
    // Create and submit form
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'delete_all_stocks';
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }
}

// Initialize on page load
addEvent(window, 'load', function() {
  // Any initialization code can go here
});
</script>
</main>
</body>
</html>
