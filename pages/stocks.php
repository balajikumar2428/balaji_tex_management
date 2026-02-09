<?php
$pdo = DB::conn();
$company = get_company();
// Ensure new columns exist (ignore if already present)
try { $pdo->exec('ALTER TABLE stocks ADD COLUMN chippam_bags INTEGER DEFAULT 0'); } catch (Exception $e) {}
try { $pdo->exec('ALTER TABLE stocks ADD COLUMN bags INTEGER DEFAULT 0'); } catch (Exception $e) {}
// Migrate old bags_count to chippam_bags if needed
$pdo->exec('UPDATE stocks SET chippam_bags = COALESCE(bags_count, 0) WHERE chippam_bags = 0 AND bags_count IS NOT NULL');
$stmt = $pdo->prepare('SELECT y.id, y.name, IFNULL(s.weight_kg,0) as weight_kg, IFNULL(s.chippam_bags,0) as chippam_bags, IFNULL(s.bags,0) as bags
                       FROM yarn_types y LEFT JOIN stocks s ON s.yarn_type_id=y.id AND s.company_id=y.company_id
                       WHERE y.company_id=? ORDER BY y.name');
$stmt->execute([$company['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Stocks by Yarn Type</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Yarn Type</th>
            <th class="py-2">Net Stock (kg)</th>
            <th class="py-2">Chippam Bag</th>
            <th class="py-2">Bag</th>
            <th class="py-2">Avg Net/Bag</th>
            <th class="py-2">Production Entry</th>
            <th class="py-2">Sales (Chippams)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): 
            $totalBags = (int)$r['chippam_bags'] + (int)$r['bags'];
            $avg = $totalBags > 0 ? ((float)$r['weight_kg'] / $totalBags) : 0.0;
          ?>
            <tr class="border-t align-top">
              <td class="py-2 pr-4 font-medium"><?php echo e($r['name']); ?></td>
              <td class="py-2"><?php echo number_format((float)$r['weight_kg'], 3); ?></td>
              <td class="py-2"><?php echo (int)$r['chippam_bags']; ?></td>
              <td class="py-2"><?php echo (int)$r['bags']; ?></td>
              <td class="py-2"><?php echo number_format($avg, 3); ?></td>
              <td class="py-2">
                <div class="space-y-2">
                  <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1">
                      <input type="radio" name="prod_mode_<?php echo (int)$r['id']; ?>" value="chippam" checked onchange="toggleProdMode(<?php echo (int)$r['id']; ?>)">
                      <span>Chippam Bag</span>
                    </label>
                    <label class="flex items-center gap-1">
                      <input type="radio" name="prod_mode_<?php echo (int)$r['id']; ?>" value="bag" onchange="toggleProdMode(<?php echo (int)$r['id']; ?>)">
                      <span>Bag (50kg)</span>
                    </label>
                  </div>
                  <form method="post" id="form_chippam_<?php echo (int)$r['id']; ?>">
                    <input type="hidden" name="action" value="stock_prod_add">
                    <input type="hidden" name="yarn_type_id" value="<?php echo (int)$r['id']; ?>">
                    <label class="block text-xs text-gray-500">Enter gross weights (kg), separated by space/comma. Each bag will deduct 0.400 kg.</label>
                    <textarea name="bag_weights" rows="2" class="w-64 border rounded px-2 py-1" placeholder="e.g., 7.500 7.480 7.620"></textarea>
                    <div>
                      <button class="bg-indigo-600 text-white px-3 py-1 rounded">Add Production</button>
                    </div>
                  </form>
                  <form method="post" id="form_bag_<?php echo (int)$r['id']; ?>" style="display:none;">
                    <input type="hidden" name="action" value="stock_prod_add_bag">
                    <input type="hidden" name="yarn_type_id" value="<?php echo (int)$r['id']; ?>">
                    <label class="block text-xs text-gray-500">Number of 50kg bags to add (each deducts 0.400 kg wastage).</label>
                    <input name="bag_count" type="number" min="1" class="border rounded px-2 py-1 w-24" placeholder="bags">
                    <div>
                      <button class="bg-indigo-600 text-white px-3 py-1 rounded">Add Production</button>
                    </div>
                  </form>
                </div>
              </td>
              <td class="py-2">
                <form method="post" class="flex items-center gap-2">
                  <input type="hidden" name="action" value="stock_sell_bags">
                  <input type="hidden" name="yarn_type_id" value="<?php echo (int)$r['id']; ?>">
                  <input name="bags_to_sell" type="number" min="1" class="border rounded px-2 py-1 w-24" placeholder="0">
                  <button class="bg-rose-600 text-white px-3 py-1 rounded">Sell</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<script>
function toggleProdMode(id) {
  const mode = document.querySelector('input[name="prod_mode_' + id + '"]:checked').value;
  document.getElementById('form_chippam_' + id).style.display = mode === 'chippam' ? 'block' : 'none';
  document.getElementById('form_bag_' + id).style.display = mode === 'bag' ? 'block' : 'none';
}
</script>
</main>
</body>
</html>
