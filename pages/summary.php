<?php
$pdo = DB::conn();
$company = get_company();

// Fetch summary dashboard data
$summary = get_yarn_production_summary($company['id']);

?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-6 rounded shadow">
    <div class="flex items-center justify-between mb-6 pb-4 border-b">
      <h2 class="font-semibold text-2xl text-gray-800">Production vs Purchased Summary</h2>
    </div>

    <!-- Production vs Purchased Summary Dashboard -->
    <?php if (empty($summary)): ?>
        <div class="text-center py-12 text-gray-500 italic">
            No stock data available yet to generate a summary. Start by adding Purchased Stocks or Production Stocks!
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          <?php foreach ($summary as $data): ?>
            <div class="border-2 rounded-lg p-5 <?php echo $data['pending_kg'] > 0 ? 'bg-amber-50 border-amber-300 shadow-sm' : 'bg-green-50 border-green-300 shadow-sm'; ?>">
              <div class="font-bold text-xl text-gray-800 mb-3 border-b-2 pb-2 <?php echo $data['pending_kg'] > 0 ? 'border-amber-200' : 'border-green-200'; ?>">
                <?php echo e($data['yarn_name']); ?>
              </div>
              
              <div class="space-y-3">
                  <div class="text-base text-gray-700 flex justify-between items-center bg-white p-2 border rounded">
                    <span class="font-medium">Total Purchased:</span>
                    <span class="font-bold text-indigo-700"><?php echo number_format($data['purchased_kg'], 3); ?> kg</span>
                  </div>
                  
                  <div class="text-base text-gray-700 flex justify-between items-center bg-white p-2 border rounded">
                    <span class="font-medium">Total Finished:</span>
                    <span class="font-bold text-blue-700"><?php echo number_format($data['finished_kg'], 3); ?> kg</span>
                  </div>
                  
                  <div class="mt-4 pt-3 border-t-2 flex justify-between items-center text-lg <?php echo $data['pending_kg'] > 0 ? 'text-amber-700 border-amber-200 font-extrabold' : 'text-green-700 border-green-200 font-extrabold'; ?>">
                    <span>Pending Quota:</span>
                    <span class="bg-white px-3 py-1 rounded shadow-sm border <?php echo $data['pending_kg'] > 0 ? 'border-amber-200' : 'border-green-200'; ?>">
                        <?php echo number_format($data['pending_kg'], 3); ?> kg
                    </span>
                  </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
  </div>
</section>
