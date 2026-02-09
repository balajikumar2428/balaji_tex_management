<?php
$company = $company ?? get_company();
$flash = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $company ? e($company['name']).' | ' : ''; ?>Balaji Tex Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
  <header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="text-xl font-semibold text-gray-800">Balaji Tex Management</div>
      <nav class="space-x-4 text-sm">
        <a class="text-gray-700 hover:text-black" href="index.php?page=companies">Companies</a>
        <?php if ($company): ?>
          <a class="text-gray-700 hover:text-black" href="index.php?page=dashboard">Dashboard</a>
          <a class="text-gray-700 hover:text-black" href="index.php?page=yarn_types">Yarn Types</a>
          <a class="text-gray-700 hover:text-black" href="index.php?page=stocks">Stocks</a>
          <a class="text-gray-700 hover:text-black" href="index.php?page=workers">Workers</a>
          <a class="text-gray-700 hover:text-black" href="index.php?page=work_logs">Work Logs</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if ($company): ?>
  <div class="bg-indigo-600 text-white">
    <div class="max-w-6xl mx-auto px-4 py-2 text-sm">Company: <span class="font-medium"><?php echo e($company['name']); ?></span></div>
  </div>
  <?php endif; ?>

  <?php if ($flash): ?>
  <div class="max-w-6xl mx-auto px-4 mt-4">
    <div class="bg-red-50 border border-red-200 text-red-800 rounded p-3 text-sm"><?php echo e($flash); ?></div>
  </div>
  <?php endif; ?>

  <main class="max-w-6xl mx-auto p-4">
