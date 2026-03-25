 <?php
$pdo = DB::conn();
$company = get_company();

// Get yarn types for dropdown
$yarn_stmt = $pdo->prepare('SELECT * FROM yarn_types WHERE company_id=? ORDER BY name');
$yarn_stmt->execute([$company['id']]);
$yarn_types = $yarn_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch purchased stocks
$purchased_stocks = list_purchased_stocks($company['id']);

?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold text-lg">Purchased Stocks</h2>
    </div>

    <!-- Add Purchased Stock Form -->
    <div class="mb-6 p-4 border rounded bg-indigo-50">
      <h3 class="font-medium mb-3 text-indigo-800">Add New Purchased Stock</h3>
      <form method="post" action="index.php" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="action" value="purchased_stock_add">
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm text-gray-700 font-medium mb-1">Date Purchased</label>
          <input name="date_purchased" type="date" class="w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200 focus:border-indigo-400 outline-none" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm text-gray-700 font-medium mb-1">Company / Supplier Name</label>
          <input name="supplier_name" type="text" class="w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200 focus:border-indigo-400 outline-none" placeholder="Enter supplier name" required>
        </div>
        
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm text-gray-700 font-medium mb-1">Yarn Type</label>
          <select name="yarn_type_id" class="w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200 focus:border-indigo-400 outline-none" required>
            <option value="">Select yarn type</option>
            <?php foreach ($yarn_types as $yt): ?>
              <option value="<?php echo (int)$yt['id']; ?>"><?php echo e($yt['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex-1 min-w-[150px]">
          <label class="block text-sm text-gray-700 font-medium mb-1">Number of Bags</label>
          <input name="bag_count" type="number" min="1" class="w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200 focus:border-indigo-400 outline-none" placeholder="e.g. 50" required>
        </div>
        
        <div class="flex-1 min-w-[150px]">
          <label class="block text-sm text-gray-700 font-medium mb-1">Weight per Bag</label>
          <input name="weight_per_bag" type="number" step="0.001" min="0.001" class="w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200 focus:border-indigo-400 outline-none" placeholder="e.g. 50.000" required>
        </div>
        
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded font-medium hover:bg-indigo-700 transition-colors shadow-sm">
          Save Purchase
        </button>
      </form>
    </div>

    <!-- Purchased Stocks List View -->
    <div>
      <h3 class="font-semibold text-lg mb-3 border-b pb-2">Purchase History</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 bg-gray-50 border-b">
              <th class="py-3 px-2">Date Purchased</th>
              <th class="py-3 px-2">Supplier Company</th>
              <th class="py-3 px-2">Yarn Type</th>
              <th class="py-3 px-2 text-right">No. Bags</th>
              <th class="py-3 px-2 text-right">Weight/Bag (kg)</th>
              <th class="py-3 px-2 text-right font-bold text-indigo-800">Total Weight (kg)</th>
              <th class="py-3 px-2 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($purchased_stocks)): ?>
              <tr>
                <td colspan="7" class="py-4 text-center text-gray-500 italic">No purchased stocks recorded yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($purchased_stocks as $ps): ?>
                <tr class="border-b hover:bg-gray-50 transition-colors">
                  <td class="py-2 px-2"><?php echo e($ps['date_purchased']); ?></td>
                  <td class="py-2 px-2 font-medium text-gray-800"><?php echo e($ps['supplier_name']); ?></td>
                  <td class="py-2 px-2"><?php echo e($ps['yarn_name']); ?></td>
                  <td class="py-2 px-2 text-right"><?php echo (int)($ps['bag_count']); ?></td>
                  <td class="py-2 px-2 text-right"><?php echo number_format($ps['weight_per_bag'], 3); ?></td>
                  <td class="py-2 px-2 text-right font-bold text-indigo-700"><?php echo number_format($ps['total_weight'], 3); ?></td>
                  <td class="py-2 px-2 text-center">
                    <form method="post" action="index.php" onsubmit="return confirm('Delete this purchase record?')" class="inline">
                      <input type="hidden" name="action" value="purchased_stock_delete">
                      <input type="hidden" name="id" value="<?php echo (int)$ps['id']; ?>">
                      <button type="submit" class="text-red-500 hover:text-red-700 p-1 border border-transparent hover:border-red-200 rounded transition-colors" title="Delete record">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
  </div>
</section>
