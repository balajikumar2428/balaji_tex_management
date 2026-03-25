<?php
$pdo = DB::conn();
$company = get_company();
// Get all workers from existing table structure
$workers = $pdo->prepare('SELECT id, name FROM workers ORDER BY name');
$workers->execute();
$workers = $workers->fetchAll(PDO::FETCH_ASSOC);

// Date filtering logic
$filter_type = $_GET['filter_type'] ?? 'weekly'; // 'weekly' or 'custom'
$week_start = $_GET['week_start'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

if ($filter_type === 'custom' && $from_date && $to_date) {
  $start_date = $from_date;
  $end_date = $to_date;
} else {
  // Weekly view: pick week starting Monday (Mon-Sat)
  if (!$week_start) {
    $today = new DateTime();
    $dow = (int)$today->format('N'); // 1=Mon..7=Sun
    $today->modify('-' . ($dow - 1) . ' days');
    $week_start = $today->format('Y-m-d');
  }
  $start_date = $week_start;
  $end_date = (new DateTime($week_start))->modify('+5 days')->format('Y-m-d');
}

// Fetch aggregated amounts and warps by date and worker
$stmt = $pdo->prepare("SELECT work_date, worker_id, SUM(amount) AS amt, SUM(warps_count) AS warps, MAX(id) AS last_id
                       FROM work_logs WHERE work_date BETWEEN ? AND ?
                       GROUP BY work_date, worker_id");
$stmt->execute([$start_date, $end_date]);
$agg = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unsettled advances aggregated by date and worker in range (use remaining amount = amount - paid_amount)
$stmt = $pdo->prepare("SELECT advance_date AS work_date, worker_id, SUM(amount - paid_amount) AS adv
                       FROM advances WHERE settled=0 AND advance_date BETWEEN ? AND ?
                       GROUP BY advance_date, worker_id");
$stmt->execute([$start_date, $end_date]);
$advAgg = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build date range list
$dates = [];
if ($filter_type === 'custom') {
  // For custom date range, build all dates between from_date and to_date
  $d = new DateTime($start_date);
  $end = new DateTime($end_date);
  while ($d <= $end) {
    $dates[] = $d->format('Y-m-d');
    $d->modify('+1 day');
  }
} else {
  // Weekly view: Mon..Sat
  $d = new DateTime($start_date);
  for ($i=0; $i<6; $i++) {
    $dates[] = $d->format('Y-m-d');
    $d->modify('+1 day');
  }
}

// Map worker_id -> name
$workerNames = [];
foreach ($workers as $w) { $workerNames[(int)$w['id']] = $w['name']; }

// Build per-day maps for the week
$logsMap = [];
foreach ($agg as $r) {
  $dt = $r['work_date']; $wid=(int)$r['worker_id'];
  $warps = (int)$r['warps']; $amt = (float)$r['amt'];
  $rate = $warps > 0 ? ($amt / $warps) : 0.0; // average rate
  if (!isset($logsMap[$dt])) $logsMap[$dt] = [];
  $logsMap[$dt][$wid] = ['warps'=>$warps, 'amount'=>$amt, 'rate'=>$rate, 'id'=>(int)$r['last_id']];
}
$advMap = [];
foreach ($advAgg as $r) {
  $dt = $r['work_date']; $wid=(int)$r['worker_id']; $adv=(float)$r['adv'];
  if (!isset($advMap[$dt])) $advMap[$dt] = [];
  $advMap[$dt][$wid] = $adv;
}
?>
<section class="mt-6 grid grid-cols-1 gap-6">
  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Add Work Log</h2>
    <form method="post" class="grid md:grid-cols-5 gap-3">
      <input type="hidden" name="action" value="worklog_add">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Worker</label>
        <select id="form_worker" name="worker_id" class="w-full border rounded px-3 py-2" required>
          <option value="">Select worker</option>
          <?php foreach ($workers as $w): $wid=(int)$w['id']; ?>
            <option value="<?php echo $wid; ?>"><?php echo e($w['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Date</label>
        <input id="form_date" name="work_date" type="date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Warps Count</label>
        <input name="warps_count" type="number" min="1" class="w-full border rounded px-3 py-2" value="" required>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Rate per Warp (₹)</label>
        <input name="rate_per_warp" type="number" step="0.01" min="0.01" class="w-full border rounded px-3 py-2" value="" required>
      </div>
      <div class="flex items-end gap-2">
        <button class="bg-indigo-600 text-white px-4 py-2 rounded">Add</button>
      </div>
    </form>
  </div>

  

  <div class="bg-white p-4 rounded shadow">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-semibold"><?php echo $filter_type === 'custom' ? 'Custom Date Range View' : 'Weekly View (Mon–Sat)'; ?></h2>
      <div class="flex items-center gap-4">
        <!-- Filter Type Toggle -->
        <div class="flex items-center gap-2 text-sm">
          <label class="flex items-center gap-1">
            <input type="radio" name="filter_type" value="weekly" onchange="this.form.submit()" <?php echo $filter_type === 'weekly' ? 'checked' : ''; ?>>
            Weekly
          </label>
          <label class="flex items-center gap-1">
            <input type="radio" name="filter_type" value="custom" onchange="this.form.submit()" <?php echo $filter_type === 'custom' ? 'checked' : ''; ?>>
            Custom Range
          </label>
        </div>
        
        <?php if ($filter_type === 'weekly'): ?>
          <!-- Weekly Navigation -->
          <form method="get" class="flex items-center gap-2 text-sm">
            <input type="hidden" name="page" value="work_logs">
            <input type="hidden" name="filter_type" value="weekly">
            <?php $prev=(new DateTime($start_date))->modify('-7 days')->format('Y-m-d'); $next=(new DateTime($start_date))->modify('+7 days')->format('Y-m-d'); ?>
            <a class="px-3 py-1 border rounded" href="index.php?page=work_logs&filter_type=weekly&week_start=<?php echo e($prev); ?>">Prev week</a>
            <label>Week start (Mon)</label>
            <input name="week_start" type="date" value="<?php echo e($start_date); ?>" class="border rounded px-2 py-1">
            <button class="px-3 py-1 bg-gray-100 border rounded">Go</button>
            <a class="px-3 py-1 border rounded" href="index.php?page=work_logs&filter_type=weekly&week_start=<?php echo e($next); ?>">Next week</a>
          </form>
        <?php else: ?>
          <!-- Custom Date Range -->
          <form method="get" class="flex items-center gap-2 text-sm">
            <input type="hidden" name="page" value="work_logs">
            <input type="hidden" name="filter_type" value="custom">
            <label>From</label>
            <input name="from_date" type="date" value="<?php echo e($from_date); ?>" class="border rounded px-2 py-1" required>
            <label>To</label>
            <input name="to_date" type="date" value="<?php echo e($to_date); ?>" class="border rounded px-2 py-1" required>
            <button class="px-3 py-1 bg-gray-100 border rounded">Apply</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php foreach ($dates as $dt): $dtObj=new DateTime($dt); $day=$dtObj->format('D'); $totalWarpsDay=0; foreach ($workers as $w) { $wid=(int)$w['id']; $entry=$logsMap[$dt][$wid] ?? null; $totalWarpsDay += $entry['warps'] ?? 0; } ?>
      <div class="border rounded mb-4">
        <div class="px-3 py-2 bg-gray-50 border-b flex items-center justify-between">
          <div class="font-medium"><?php echo e($dt . ' (' . $day . ')'); ?></div>
          <div class="text-sm text-gray-600">Total Warps: <span class="font-semibold"><?php echo (int)$totalWarpsDay; ?></span></div>
        </div>
        <div class="p-3 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600">
                <th class="py-1">Worker</th>
                <th class="py-1 text-right">Warps</th>
                <th class="py-1 text-right">Avg Rate</th>
                <th class="py-1 text-right">Bought</th>
                <th class="py-1 text-right">Amount</th>
                <th class="py-1 text-right">Net</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($workers as $w): $wid=(int)$w['id']; $entry=$logsMap[$dt][$wid] ?? null; $warps=$entry['warps'] ?? 0; $amount=$entry['amount'] ?? 0.0; $rate=$entry['rate'] ?? 0.0; $lastId=$entry['id'] ?? 0; $bought=$advMap[$dt][$wid] ?? 0.0; $net=$amount-$bought; $key=$dt.'_'.$wid; ?>
                <tr class="border-t">
                  <td class="py-1"><?php echo e($w['name']); ?></td>
                  <td class="py-1 text-right">
                    <?php if ($warps>0 && $lastId>0): ?>
                      <div id="warp_view_<?php echo e($key); ?>" class="inline-flex items-center gap-2">
                        <span><?php echo (int)$warps; ?></span>
                        <button type="button" class="px-2 py-1 bg-blue-600 text-white rounded" onclick="toggleWarpEdit('<?php echo e($key); ?>', true)">Edit</button>
                      </div>
                      <form id="warp_form_<?php echo e($key); ?>" method="post" class="hidden inline-flex items-center gap-2" onsubmit="return confirm('Save changes?');">
                        <input type="hidden" name="action" value="worklog_update_total">
                        <input type="hidden" name="worker_id" value="<?php echo (int)$wid; ?>">
                        <input type="hidden" name="work_date" value="<?php echo e($dt); ?>">
                        <input type="hidden" name="week_start" value="<?php echo e($start_date); ?>">
                        <input type="hidden" name="rate_per_warp" value="<?php echo number_format((float)$rate,2,'.',''); ?>">
                        <input name="warps_count" type="number" min="0" value="<?php echo (int)$warps; ?>" class="border rounded px-2 py-1 w-20">
                        <div class="flex items-center gap-2">
                          <button class="px-2 py-1 bg-blue-600 text-white rounded">Save</button>
                          <button type="button" class="px-2 py-1 border rounded" onclick="toggleWarpEdit('<?php echo e($key); ?>', false)">Cancel</button>
                        </div>
                      </form>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="py-1 text-right"><?php echo $warps>0 ? ('₹ '.number_format($rate,2)) : '-'; ?></td>
                  <td class="py-1 text-right"><?php echo $bought>0 ? ('<span class="text-red-600 font-medium">₹ '.number_format($bought,2).'</span>') : '-'; ?></td>
                  <td class="py-1 text-right"><?php echo $warps>0 ? ('₹ '.number_format($amount,2)) : '-'; ?></td>
                  <td class="py-1 text-right"><?php echo ($warps>0 || $bought>0) ? ('₹ '.number_format($net,2)) : '-'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    function toggleWarpEdit(key, editing){
      var v = document.getElementById('warp_view_' + key);
      var f = document.getElementById('warp_form_' + key);
      if(!v || !f) return;
      if (editing){
        v.classList.add('hidden');
        f.classList.remove('hidden');
        var inp = f.querySelector('input[name="warps_count"]');
        if (inp) { try { inp.focus(); inp.select(); } catch(e){} }
      } else {
        f.classList.add('hidden');
        v.classList.remove('hidden');
      }
    }
  </script>

  <div class="bg-white p-4 rounded shadow mt-6">
    <h2 class="font-semibold mb-3"><?php echo $filter_type === 'custom' ? 'Custom Range Summary' : 'Weekly Summary (Mon–Sat)'; ?>: <?php echo e($start_date); ?> to <?php echo e($end_date); ?></h2>
    <?php
      // Aggregate weekly totals per worker
      $weeklyTotals = [];
      foreach ($workers as $w) {
        $weeklyTotals[(int)$w['id']] = ['name'=>$w['name'], 'warps'=>0, 'amount'=>0.0, 'bought'=>0.0];
      }
      foreach ($dates as $dt) {
        if (isset($logsMap[$dt])) {
          foreach ($logsMap[$dt] as $wid=>$entry) {
            if (isset($weeklyTotals[$wid])) {
              $weeklyTotals[$wid]['warps'] += (int)$entry['warps'];
              $weeklyTotals[$wid]['amount'] += (float)$entry['amount'];
            }
          }
        }
        if (isset($advMap[$dt])) {
          foreach ($advMap[$dt] as $wid=>$adv) {
            if (isset($weeklyTotals[$wid])) {
              $weeklyTotals[$wid]['bought'] += (float)$adv; // remaining (amount - paid) already
            }
          }
        }
      }
      $gtWarps=0; $gtAmt=0.0; $gtBought=0.0; $gtNet=0.0;
    ?>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Worker</th>
            <th class="py-2 text-right">Total Warps</th>
            <th class="py-2 text-right">Total Amount</th>
            <th class="py-2 text-right">Bought</th>
            <th class="py-2 text-right">Net</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($weeklyTotals as $wid=>$t): $net = $t['amount'] - $t['bought']; $gtWarps += $t['warps']; $gtAmt += $t['amount']; $gtBought += $t['bought']; $gtNet += $net; ?>
            <tr class="border-t">
              <td class="py-2"><?php echo e($t['name']); ?></td>
              <td class="py-2 text-right"><?php echo (int)$t['warps']; ?></td>
              <td class="py-2 text-right">₹ <?php echo number_format($t['amount'], 2); ?></td>
              <td class="py-2 text-right"><?php echo $t['bought']>0 ? ('<span class="text-red-600 font-medium">₹ '.number_format($t['bought'], 2).'</span>') : '₹ '.number_format($t['bought'], 2); ?></td>
              <td class="py-2 text-right">₹ <?php echo number_format($net, 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="border-t font-semibold">
            <td class="py-2">Total</td>
            <td class="py-2 text-right"><?php echo (int)$gtWarps; ?></td>
            <td class="py-2 text-right">₹ <?php echo number_format($gtAmt, 2); ?></td>
            <td class="py-2 text-right">₹ <?php echo number_format($gtBought, 2); ?></td>
            <td class="py-2 text-right">₹ <?php echo number_format($gtNet, 2); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="bg-white p-4 rounded shadow">
    <h2 class="font-semibold mb-3">Worker Advances</h2>
    <form method="post" class="grid md:grid-cols-5 gap-3 mb-4">
      <input type="hidden" name="action" value="advance_add">
      <div>
        <label class="block text-sm text-gray-700 mb-1">Worker</label>
        <select name="worker_id" class="w-full border rounded px-3 py-2" required>
          <option value="">Select worker</option>
          <?php foreach ($workers as $w): ?>
            <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Date</label>
        <input name="advance_date" type="date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Amount (₹)</label>
        <input name="amount" type="number" step="0.01" min="0.01" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm text-gray-700 mb-1">Note</label>
        <input name="note" class="w-full border rounded px-3 py-2" placeholder="optional">
      </div>
      <div class="flex items-end">
        <button class="bg-amber-600 text-white px-4 py-2 rounded w-full">Add Advance</button>
      </div>
    </form>

    <h3 class="font-semibold mb-2 text-sm text-gray-700">Unsettled Advances</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-2">Date</th>
            <th class="py-2">Worker</th>
            <th class="py-2">Amount</th>
            <th class="py-2">Paid</th>
            <th class="py-2">Remaining</th>
            <th class="py-2">Note</th>
            <th class="py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $stmt = $pdo->prepare('SELECT a.id, a.advance_date, a.amount, a.paid_amount, a.note, w.name as worker_name
                                 FROM advances a JOIN workers w ON w.id=a.worker_id
                                 WHERE a.settled=0 ORDER BY a.advance_date DESC, a.id DESC');
          $stmt->execute();
          foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row): $day=(new DateTime($row['advance_date']))->format('D'); $remaining = max(0, (float)$row['amount'] - (float)$row['paid_amount']); ?>
            <tr class="border-t">
              <td class="py-2"><?php echo e($row['advance_date'] . ' (' . $day . ')'); ?></td>
              <td class="py-2"><?php echo e($row['worker_name']); ?></td>
              <td class="py-2">₹ <?php echo number_format((float)$row['amount'], 2); ?></td>
              <td class="py-2">₹ <?php echo number_format((float)$row['paid_amount'], 2); ?></td>
              <td class="py-2 font-medium">₹ <?php echo number_format($remaining, 2); ?></td>
              <td class="py-2"><?php echo e($row['note']); ?></td>
              <td class="py-2">
                <div class="flex items-center gap-3 flex-wrap">
                  <form method="post" class="flex items-center gap-2" onsubmit="return confirm('Update this advance?');">
                    <input type="hidden" name="action" value="advance_update">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <input id="amt_<?php echo (int)$row['id']; ?>" name="amount" type="number" step="0.01" min="0.01" value="<?php echo e($row['amount']); ?>" class="border rounded px-2 py-1 w-28" disabled>
                    <input id="note_<?php echo (int)$row['id']; ?>" name="note" value="<?php echo e($row['note']); ?>" class="border rounded px-2 py-1 w-40" placeholder="note" disabled>
                    <button id="save_<?php echo (int)$row['id']; ?>" class="px-3 py-1 bg-blue-600 text-white rounded hidden">Save</button>
                  </form>
                  <button class="px-3 py-1 bg-blue-600 text-white rounded" onclick="enableEdit(<?php echo (int)$row['id']; ?>)">Edit</button>
                  <form method="post" class="flex items-center gap-2" onsubmit="return confirm('Settle this amount?');">
                    <input type="hidden" name="action" value="advance_settle">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <input name="settle_amount" type="number" step="0.01" min="0.01" max="<?php echo $remaining; ?>" class="border rounded px-2 py-1 w-28" placeholder="₹ to settle">
                    <button class="px-3 py-1 bg-emerald-600 text-white rounded">Settle</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
      function enableEdit(id){
        var a=document.getElementById('amt_'+id);
        var n=document.getElementById('note_'+id);
        var s=document.getElementById('save_'+id);
        if(a&&n&&s){ a.disabled=false; n.disabled=false; s.classList.remove('hidden'); a.focus(); }
      }
    </script>
  </div>

  <!-- Weekly warps pivot removed to declutter; covered in weekly view above -->
</section>
</main>
</body>
</html>
