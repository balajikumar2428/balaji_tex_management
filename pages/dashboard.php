<?php
$pdo = DB::conn();
$company = get_company();

$totals = [
  'yarn_types' => (int)$pdo->query("SELECT COUNT(*) FROM yarn_types")->fetchColumn(),
  'workers' => (int)$pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn(),
  'stock_kg' => (float)$pdo->query("SELECT IFNULL(SUM(bag_weight * total_bags),0) FROM stocks")->fetchColumn(),
  'today_amount' => 0.0,
];

// Worker list for panels
$workersList = $pdo->prepare('SELECT id, name FROM workers ORDER BY name');
$workersList->execute();
$workersList = $workersList->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM work_logs WHERE work_date=CURDATE()");
$stmt->execute();
$totals['today_amount'] = (float)$stmt->fetchColumn();
?>


<section class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
  <div class="bg-white p-4 rounded shadow">
    <div class="text-gray-500 text-sm">Yarn Types</div>
    <div class="text-2xl font-semibold"><?php echo e($totals['yarn_types']); ?></div>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <div class="text-gray-500 text-sm">Total Stock (kg)</div>
    <div class="text-2xl font-semibold"><?php echo number_format($totals['stock_kg'], 3); ?></div>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <div class="text-gray-500 text-sm">Today's Wages</div>
    <div class="text-2xl font-semibold">₹ <?php echo number_format($totals['today_amount'], 2); ?></div>
  </div>
</section>

<section class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <?php
      // Compute current week (Mon-Sat)
      $today = new DateTime();
      $dow = (int)$today->format('N'); // 1=Mon..7=Sun
      $today->modify('-' . ($dow - 1) . ' days');
      $dash_week_start = $today->format('Y-m-d');
      $dash_week_end = (new DateTime($dash_week_start))->modify('+5 days')->format('Y-m-d');

      // Work logs aggregated per worker per day in range
      $stmt = $pdo->prepare("SELECT worker_id, work_date, SUM(amount) AS amt
                             FROM work_logs WHERE work_date BETWEEN ? AND ?
                             GROUP BY worker_id, work_date");
      $stmt->execute([$dash_week_start, $dash_week_end]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $logsDays = []; // worker_id => set of dates worked
      $sumAmt = [];   // worker_id => total amount in week
      foreach ($rows as $r) {
        $wid = (int)$r['worker_id'];
        $dt = $r['work_date'];
        if (!isset($logsDays[$wid])) $logsDays[$wid] = [];
        $logsDays[$wid][$dt] = true;
        if (!isset($sumAmt[$wid])) $sumAmt[$wid] = 0.0;
        $sumAmt[$wid] += (float)$r['amt'];
      }

      // Advances remaining in range per worker
      $stmt = $pdo->prepare("SELECT worker_id, SUM(amount - paid_amount) AS adv
                             FROM advances WHERE settled=0 AND advance_date BETWEEN ? AND ?
                             GROUP BY worker_id");
      $stmt->execute([$dash_week_start, $dash_week_end]);
      $advRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $sumAdv = [];
      foreach ($advRows as $r) { $sumAdv[(int)$r['worker_id']] = (float)$r['adv']; }
    ?>
    <h2 class="font-semibold mb-3">Worker</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Worker</th>
            <th class="py-2 text-right">Leaves</th>
            <th class="py-2 text-right">Amount</th>
            <th class="py-2 text-right">Bought</th>
            <th class="py-2 text-right">Net</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($workersList)): ?>
            <tr><td class="py-2 text-gray-500" colspan="5">No workers added yet.</td></tr>
          <?php else: ?>
            <?php foreach ($workersList as $w): $wid=(int)$w['id']; $daysWorked = isset($logsDays[$wid]) ? count($logsDays[$wid]) : 0; $leaves = max(0, 6 - $daysWorked); $amt = $sumAmt[$wid] ?? 0.0; $bought = $sumAdv[$wid] ?? 0.0; $net = $amt - $bought; ?>
              <tr class="border-t">
                <td class="py-2"><?php echo e($w['name']); ?></td>
                <td class="py-2 text-right"><?php echo (int)$leaves; ?></td>
                <td class="py-2 text-right">₹ <?php echo number_format($amt, 2); ?></td>
                <td class="py-2 text-right"><?php echo $bought>0 ? ('<span class="text-red-600 font-medium">₹ '.number_format($bought, 2).'</span>') : '₹ '.number_format($bought, 2); ?></td>
                <td class="py-2 text-right">₹ <?php echo number_format($net, 2); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Stock by Yarn Type (kg)</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Yarn Type</th>
            <th class="py-2">Weight (kg)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $pdo->prepare("SELECT cotton_type as name, SUM(bag_weight * total_bags) as weight_kg FROM stocks GROUP BY cotton_type ORDER BY cotton_type");
          $stmt->execute();
          foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row): ?>
            <tr class="border-t">
              <td class="py-2"><?php echo e($row['name']); ?></td>
              <td class="py-2"><?php echo number_format((float)$row['weight_kg'], 3); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($stmt->rowCount() === 0): ?>
            <tr>
              <td colspan="2" class="py-4 text-center text-gray-500">No stock records found</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
</main>
</body>
</html>
