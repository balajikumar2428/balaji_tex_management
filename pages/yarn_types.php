<?php
$pdo = DB::conn();
$company = get_company();
$stmt = $pdo->prepare('SELECT * FROM yarn_types WHERE company_id=? ORDER BY name');
$stmt->execute([$company['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3" id="form_title">Add Yarn Type</h2>
    <form method="post" class="space-y-3" id="yarn_type_form">
      <input type="hidden" name="action" value="yarn_type_add" id="form_action">
      <input type="hidden" name="id" value="" id="yarn_type_id">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Name (e.g., 2/40s)</label>
        <input name="name" class="w-full border rounded px-3 py-2" id="yarn_type_name" required>
      </div>
      <div class="flex gap-2">
        <button class="bg-indigo-600 text-white px-4 py-2 rounded" id="submit_button">Add</button>
        <button type="button" onclick="resetForm()" class="bg-gray-600 text-white px-4 py-2 rounded" id="cancel_button" style="display:none;">Cancel</button>
      </div>
    </form>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Yarn Types</h2>
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
                <div class="flex gap-2">
                  <button onclick="editYarnType(<?php echo (int)$r['id']; ?>, '<?php echo e($r['name']); ?>')" class="text-blue-600 hover:underline">Edit</button>
                  <form method="post" onsubmit="return confirm('Delete this yarn type?');" class="inline">
                    <input type="hidden" name="action" value="yarn_type_delete">
                    <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="text-red-600 hover:underline" type="submit">Delete</button>
                  </form>
                </div>
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

<script>
function editYarnType(id, name) {
  document.getElementById('form_title').textContent = 'Edit Yarn Type';
  document.getElementById('form_action').value = 'yarn_type_edit';
  document.getElementById('yarn_type_id').value = id;
  document.getElementById('yarn_type_name').value = name;
  document.getElementById('submit_button').textContent = 'Update';
  document.getElementById('cancel_button').style.display = 'inline-block';
  document.getElementById('yarn_type_name').focus();
}

function resetForm() {
  document.getElementById('form_title').textContent = 'Add Yarn Type';
  document.getElementById('form_action').value = 'yarn_type_add';
  document.getElementById('yarn_type_id').value = '';
  document.getElementById('yarn_type_name').value = '';
  document.getElementById('submit_button').textContent = 'Add';
  document.getElementById('cancel_button').style.display = 'none';
}
</script>
</html>
