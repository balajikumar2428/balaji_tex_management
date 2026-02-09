<?php
$companies = list_companies();
$current = get_company();
?>
<section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Select Company</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="company_select">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Companies</label>
        <select name="company_id" class="w-full border rounded px-3 py-2" required>
          <option value="">-- Select Company --</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php echo ($current && $current['id']==$c['id'])?'selected':'';?>>
              <?php echo e($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="bg-indigo-600 text-white px-4 py-2 rounded">Use Selected</button>
    </form>

    <?php if ($current): ?>
    <div class="mt-4 text-sm text-gray-700">Current company: <span class="font-medium"><?php echo e($current['name']); ?></span></div>
    <?php endif; ?>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Create New Company</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="action" value="company_create">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Company Name</label>
        <input name="name" class="w-full border rounded px-3 py-2" placeholder="e.g., Balaji Tex 2" required>
      </div>
      <button class="bg-green-600 text-white px-4 py-2 rounded">Create Company</button>
    </form>
  </div>
</section>
</main>
</body>
</html>
