<?php
$pdo = DB::conn();
$company = get_company();

// Get yarn types for dropdown
$yarn_stmt = $pdo->prepare('SELECT * FROM yarn_types WHERE company_id=? ORDER BY name');
$yarn_stmt->execute([$company['id']]);
$yarn_types = $yarn_stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate available stocks by yarn type and stock type for global selling (FIFO)
$available_stocks_stmt = $pdo->prepare('SELECT yarn_type_id, stock_type, SUM(total_bags - sold_bags) as total_avail_qty, SUM((total_bags * bag_weight) - sold_weight) as total_avail_weight
                                    FROM stocks 
                                    GROUP BY yarn_type_id, stock_type');
$available_stocks_stmt->execute();
$aggregated_stocks = $available_stocks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map aggregated stocks for easy JS access
$available_data = [];
foreach ($aggregated_stocks as $as) {
    if ($as['total_avail_qty'] > 0) {
        $available_data[] = [
            'yarn_type_id' => $as['yarn_type_id'],
            'stock_type' => $as['stock_type'],
            'available_qty' => (int)$as['total_avail_qty'],
            'available_weight' => (float)$as['total_avail_weight']
        ];
    }
}

// Fetch sales history
$sales_stmt = $pdo->prepare('SELECT ss.*, yt.name as yarn_name 
                            FROM stock_sales ss
                            JOIN yarn_types yt ON ss.yarn_type_id = yt.id
                            WHERE ss.company_id = ?
                            ORDER BY ss.sold_date DESC, ss.created_at DESC
                            LIMIT 50');
$sales_stmt->execute([$company['id']]);
$sales_history = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-6 rounded shadow border-t-4 border-orange-500">
    <div class="flex items-center justify-between mb-6 pb-4 border-b">
      <div>
        <h2 class="font-semibold text-2xl text-gray-800">Sales Stock</h2>
        <p class="text-sm text-gray-500">Deduct stock using FIFO (Calculated by Total Weight)</p>
      </div>
      <div class="bg-orange-50 px-4 py-2 rounded border border-orange-200">
        <span class="text-xs text-orange-600 font-bold uppercase block text-center">Ready to Sell</span>
        <span class="text-xl font-extrabold text-orange-800 text-center block"><?php echo count($available_data); ?> Items</span>
      </div>
    </div>

    <!-- Sell Stock Form (FIFO Global) -->
    <div class="mb-8 p-6 border-2 border-orange-100 rounded-xl bg-gradient-to-br from-white to-orange-50 shadow-sm">
      <h3 class="font-bold text-lg mb-4 text-orange-800 flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Transaction Details
      </h3>
      <form method="post" class="space-y-6">
        <input type="hidden" name="action" value="sell_stock">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Yarn Type</label>
            <select name="yarn_type_id" id="sell_yarn_type" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" required onchange="updateAvailableInfo()">
              <option value="">Select Yarn</option>
              <?php foreach ($yarn_types as $yt): ?>
                <option value="<?php echo (int)$yt['id']; ?>"><?php echo e($yt['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Stock Type</label>
            <select name="stock_type" id="sell_stock_type" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" required onchange="updateAvailableInfo()">
              <option value="">Select Type</option>
              <option value="bag">Bag (Cone)</option>
              <option value="chippam">Chippam</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Selling Date</label>
            <input name="sell_date" type="date" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" value="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div>
            <label id="sell_qty_label" class="block text-sm font-bold text-gray-700 mb-1">Quantity</label>
            <input name="sell_bags" id="sell_qty_input" type="number" min="1" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" placeholder="Qty" required>
            <small id="sell_qty_available" class="text-orange-600 font-bold mt-1 block"></small>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-start">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Weight per Item (kg)</label>
            <input name="sell_weight_per_unit" id="sell_weight_input" type="number" step="0.001" min="0.001" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" placeholder="50.000" required>
            <small id="sell_weight_available" class="text-gray-500 text-xs">Enter exact weight per unit</small>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Notes (Optional)</label>
            <input name="sell_notes" type="text" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 focus:border-orange-500 outline-none transition-all" placeholder="e.g. Sold to ABC Corp">
          </div>
          <div class="flex items-center">
            <div class="bg-orange-100 p-4 rounded-xl border-2 border-orange-200 w-full text-center shadow-inner">
               <span class="text-xs text-orange-600 font-bold uppercase block mb-1">Total Selling Weight</span>
               <span id="sell_total_weight_display" class="text-3xl font-black text-orange-700">0.000 kg</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end border-t pt-6">
          <button type="submit" class="bg-orange-600 text-white px-12 py-3 rounded-xl shadow-lg hover:bg-orange-700 font-bold transform hover:scale-105 transition-all text-lg">
            Confirm & Deduct Stock
          </button>
        </div>
      </form>
    </div>

    <!-- Sales History Section -->
    <div class="mt-12">
      <h3 class="font-bold text-xl mb-6 text-gray-800 flex items-center">
        <svg class="w-6 h-6 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
        </svg>
        Recent Sales History
      </h3>
      <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full text-left border-collapse">
          <thead class="bg-gray-50">
            <tr>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700">Date</th>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700">Yarn Type</th>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700">Type</th>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700">Qty</th>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700">Weight/Unit</th>
              <th class="py-4 px-4 border-b text-sm font-bold text-gray-700 text-right">Total Weight</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($sales_history)): ?>
              <tr>
                <td colspan="6" class="py-12 text-center text-gray-400 italic bg-gray-50">
                  <div class="flex flex-col items-center">
                    <svg class="w-12 h-12 mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    No sales history found.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($sales_history as $sale): ?>
                <tr class="hover:bg-orange-50/30 transition-colors">
                  <td class="py-4 px-4 text-sm text-gray-600"><?php echo e($sale['sold_date']); ?></td>
                  <td class="py-4 px-4 text-sm font-bold text-gray-900"><?php echo e($sale['yarn_name']); ?></td>
                  <td class="py-4 px-4 text-sm">
                    <span class="px-2 py-1 rounded-md text-xs font-bold <?php echo $sale['stock_type'] === 'bag' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'; ?>">
                      <?php echo e($sale['stock_type'] === 'bag' ? 'Bag' : 'Chippam'); ?>
                    </span>
                  </td>
                  <td class="py-4 px-4 text-sm font-medium"><?php echo (int)$sale['quantity']; ?></td>
                  <td class="py-4 px-4 text-sm text-gray-600"><?php echo number_format($sale['weight_per_unit'], 3); ?></td>
                  <td class="py-4 px-4 text-sm font-black text-orange-700 text-right"><?php echo number_format($sale['total_weight'], 3); ?> kg</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<script>
// Data for JavaScript filtering
var availableData = <?php echo json_encode($available_data); ?>;

function updateAvailableInfo() {
  var yarnId = document.getElementById('sell_yarn_type').value;
  var stockType = document.getElementById('sell_stock_type').value;
  var qtyAvailable = document.getElementById('sell_qty_available');
  var weightInput = document.getElementById('sell_weight_input');
  
  if (!yarnId || !stockType) {
    qtyAvailable.innerText = '';
    return;
  }
  
  var stock = availableData.find(function(s) {
    return s.yarn_type_id == yarnId && s.stock_type == stockType;
  });
  
  if (stock) {
    qtyAvailable.innerText = 'Total Weight Available: ' + stock.available_weight.toFixed(3) + ' kg';
    if (stockType === 'bag') {
        weightInput.value = '50.000';
    } else {
        weightInput.value = (stock.available_weight / stock.available_qty).toFixed(3);
    }
  } else {
    qtyAvailable.innerText = 'Out of Stock';
    weightInput.value = '';
  }
  calculateTotalSellWeight();
}

function calculateTotalSellWeight() {
    var qty = parseFloat(document.getElementById('sell_qty_input').value) || 0;
    var weight = parseFloat(document.getElementById('sell_weight_input').value) || 0;
    var total = qty * weight;
    document.getElementById('sell_total_weight_display').innerText = total.toFixed(3) + ' kg';
}

// Attach listeners for live calculation
if (document.getElementById('sell_qty_input')) {
    document.getElementById('sell_qty_input').addEventListener('input', calculateTotalSellWeight);
}
if (document.getElementById('sell_weight_input')) {
    document.getElementById('sell_weight_input').addEventListener('input', calculateTotalSellWeight);
}
</script>
