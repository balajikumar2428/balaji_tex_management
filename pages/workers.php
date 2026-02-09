<?php
$pdo = DB::conn();
$company = get_company();
$stmt = $pdo->prepare('SELECT * FROM workers WHERE company_id=? ORDER BY name');
$stmt->execute([$company['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Add Worker</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="worker_add">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Worker Name</label>
        <input name="name" class="w-full border rounded px-3 py-2" required>
      </div>
      <button class="bg-indigo-600 text-white px-4 py-2 rounded">Add</button>
    </form>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Workers</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Name</th>
            <th class="py-2 w-20">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="py-2"><?php echo e($r['name']); ?></td>
              <td class="py-2">
                <form method="post" onsubmit="return confirm('Delete this worker? This will also remove their work logs.');">
                  <input type="hidden" name="action" value="worker_delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="text-red-600 hover:underline" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
</main>
</body>
</html>
