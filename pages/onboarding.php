<?php
?>
<section class="max-w-lg mx-auto mt-12">
  <div class="bg-white shadow rounded p-6">
    <h1 class="text-xl font-semibold mb-4">Create Your Company</h1>
    <form method="post" class="space-y-4">
      <input type="hidden" name="action" value="company_create" />
      <div>
        <label class="block text-sm text-gray-700 mb-1">Company Name</label>
        <input name="name" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring" placeholder="e.g., Balaji Tex" required />
      </div>
      <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Create</button>
    </form>
  </div>
</section>
</main>
</body>
</html>
