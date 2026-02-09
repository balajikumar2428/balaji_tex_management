<?php
require __DIR__ . '/app/db.php';
session_start();
$hadSessionCompany = isset($_SESSION['company_id']);
$company = get_company();
$page = $_GET['page'] ?? null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo = DB::conn();
        if ($action === 'company_create') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('Company name required');
            create_company($name);
            header('Location: index.php');
            exit;
        }
        if ($action === 'company_select') {
            $id = (int)($_POST['company_id'] ?? 0);
            if (!select_company($id)) throw new Exception('Invalid company');
            header('Location: index.php');
            exit;
        }
        if (!$company) throw new Exception('Company not initialized');

        // Yarn type add/delete
        if ($action === 'yarn_type_add') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('Yarn type name required');
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO yarn_types(company_id, name) VALUES(?, ?)');
            $stmt->execute([$company['id'], $name]);
            // ensure stock row exists
            $ytId = (int)$pdo->lastInsertId();
            if ($ytId === 0) {
                $stmt = $pdo->prepare('SELECT id FROM yarn_types WHERE company_id=? AND name=?');
                $stmt->execute([$company['id'], $name]);
                $ytId = (int)($stmt->fetchColumn() ?: 0);
            }
            if ($ytId) {
                $pdo->prepare('INSERT OR IGNORE INTO stocks(company_id, yarn_type_id, weight_kg) VALUES(?, ?, 0)')
                    ->execute([$company['id'], $ytId]);
            }
            header('Location: index.php?page=yarn_types');
            exit;
        }
        if ($action === 'yarn_type_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM stocks WHERE company_id=? AND yarn_type_id=?')->execute([$company['id'], $id]);
            $pdo->prepare('DELETE FROM yarn_types WHERE company_id=? AND id=?')->execute([$company['id'], $id]);
            header('Location: index.php?page=yarn_types');
            exit;
        }

        // Stocks update (set or adjust)
        if ($action === 'stock_update') {
            $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
            $delta = (float)($_POST['delta'] ?? 0);
            $stmt = $pdo->prepare('UPDATE stocks SET weight_kg = ROUND(weight_kg + ?, 3) WHERE company_id=? AND yarn_type_id=?');
            $stmt->execute([$delta, $company['id'], $yarn_type_id]);
            header('Location: index.php?page=stocks');
            exit;
        }

        // Production entry: add bags from gross weights
        if ($action === 'stock_prod_add') {
            $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
            $input = trim($_POST['bag_weights'] ?? '');
            if ($input === '') throw new Exception('Enter at least one gross weight');
            $WASTAGE = 0.400;
            $parts = preg_split('/[\s,]+/', $input);
            $netSum = 0.0;
            $bagCount = 0;
            foreach ($parts as $p) {
                $w = (float)$p;
                if ($w > $WASTAGE) {
                    $netSum += ($w - $WASTAGE);
                    $bagCount += 1;
                }
            }
            if ($bagCount === 0) throw new Exception('No valid bag weights entered (> 0.400)');
            $stmt = $pdo->prepare('UPDATE stocks SET weight_kg = ROUND(weight_kg + ?, 3), chippam_bags = chippam_bags + ? WHERE company_id=? AND yarn_type_id=?');
            $stmt->execute([$netSum, $bagCount, $company['id'], $yarn_type_id]);
            $_SESSION['flash_error'] = "Added $bagCount chippam bag(s), net ".number_format($netSum,3)." kg";
            header('Location: index.php?page=stocks');
            exit;
        }

        // Production entry: add 50kg bags
        if ($action === 'stock_prod_add_bag') {
            $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
            $bagCount = max(0, (int)($_POST['bag_count'] ?? 0));
            if ($bagCount <= 0) throw new Exception('Enter number of 50kg bags to add');
            $netWeight = $bagCount * (50.0 - 0.400);
            $stmt = $pdo->prepare('UPDATE stocks SET weight_kg = ROUND(weight_kg + ?, 3), bags = bags + ? WHERE company_id=? AND yarn_type_id=?');
            $stmt->execute([$netWeight, $bagCount, $company['id'], $yarn_type_id]);
            $_SESSION['flash_error'] = "Added $bagCount bag(s), net ".number_format($netWeight,3)." kg";
            header('Location: index.php?page=stocks');
            exit;
        }

        // Sales entry: sell chippams (bags) and deduct average net weight
        if ($action === 'stock_sell_bags') {
            $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
            $bagsToSell = max(0, (int)($_POST['bags_to_sell'] ?? 0));
            if ($bagsToSell <= 0) throw new Exception('Enter number of chippams to sell');
            $stmt = $pdo->prepare('SELECT weight_kg, chippam_bags, bags FROM stocks WHERE company_id=? AND yarn_type_id=?');
            $stmt->execute([$company['id'], $yarn_type_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Stock not found');
            $chippamBags = (int)$row['chippam_bags'];
            $regularBags = (int)$row['bags'];
            $totalBags = $chippamBags + $regularBags;
            $weight = (float)$row['weight_kg'];
            if ($bagsToSell > $totalBags) throw new Exception('Not enough bags in stock');
            $avg = $totalBags > 0 ? ($weight / $totalBags) : 0.0;
            $deduct = $bagsToSell * $avg;
            // Deduct proportionally from chippam and regular bags
            $newChippam = max(0, $chippamBags - floor($bagsToSell * $chippamBags / $totalBags));
            $newRegular = max(0, $regularBags - floor($bagsToSell * $regularBags / $totalBags));
            $newWeight = max(0, $weight - $deduct);
            $stmt = $pdo->prepare('UPDATE stocks SET weight_kg = ROUND(?, 3), chippam_bags = ?, bags = ? WHERE company_id=? AND yarn_type_id=?');
            $stmt->execute([$newWeight, $newChippam, $newRegular, $company['id'], $yarn_type_id]);
            $_SESSION['flash_error'] = "Deducted ".number_format($deduct,3)." kg by selling $bagsToSell bag(s)";
            header('Location: index.php?page=stocks');
            exit;
        }

        // Worker add/delete
        if ($action === 'worker_add') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new Exception('Worker name required');
            $pdo->prepare('INSERT OR IGNORE INTO workers(company_id, name) VALUES(?, ?)')->execute([$company['id'], $name]);
            header('Location: index.php?page=workers');
            exit;
        }
        if ($action === 'worker_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM work_logs WHERE company_id=? AND worker_id=?')->execute([$company['id'], $id]);
            $pdo->prepare('DELETE FROM workers WHERE company_id=? AND id=?')->execute([$company['id'], $id]);
            header('Location: index.php?page=workers');
            exit;
        }

        // Work log add/delete
        if ($action === 'worklog_add') {
            $worker_id = (int)($_POST['worker_id'] ?? 0);
            $work_date = $_POST['work_date'] ?? date('Y-m-d');
            $warps_count = (int)($_POST['warps_count'] ?? 0);
            $rate = (float)($_POST['rate_per_warp'] ?? 0);
            $amount = $warps_count * $rate;
            if ($worker_id <= 0 || $warps_count <= 0 || $rate <= 0) throw new Exception('Invalid work log');
            $pdo->prepare('INSERT INTO work_logs(company_id, worker_id, work_date, warps_count, rate_per_warp, amount) VALUES(?,?,?,?,?,?)')
                ->execute([$company['id'], $worker_id, $work_date, $warps_count, $rate, $amount]);
            header('Location: index.php?page=work_logs');
            exit;
        }
        if ($action === 'worklog_update') {
            $id = (int)($_POST['id'] ?? 0);
            $work_date = $_POST['work_date'] ?? date('Y-m-d');
            $warps_count = (int)($_POST['warps_count'] ?? 0);
            $rate = (float)($_POST['rate_per_warp'] ?? 0);
            if ($id <= 0 || $warps_count <= 0 || $rate <= 0) throw new Exception('Invalid update');
            $amount = $warps_count * $rate;
            $stmt = $pdo->prepare('UPDATE work_logs SET work_date=?, warps_count=?, rate_per_warp=?, amount=? WHERE company_id=? AND id=?');
            $stmt->execute([$work_date, $warps_count, $rate, $amount, $company['id'], $id]);
            $_SESSION['flash_error'] = 'Work log updated';
            $redir_week = $_POST['week_start'] ?? '';
            $to = 'index.php?page=work_logs' . ($redir_week ? ('&week_start=' . urlencode($redir_week)) : '');
            header('Location: ' . $to);
            exit;
        }
        if ($action === 'worklog_update_total') {
            // Replace the entire day's total for a worker: delete existing rows for that day and insert one with provided warps and rate
            $worker_id = (int)($_POST['worker_id'] ?? 0);
            $work_date = $_POST['work_date'] ?? date('Y-m-d');
            $warps_count = (int)($_POST['warps_count'] ?? 0);
            $rate = (float)($_POST['rate_per_warp'] ?? 0);
            if ($worker_id <= 0 || $warps_count < 0 || $rate <= 0) throw new Exception('Invalid update');
            $amount = $warps_count * $rate;
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('DELETE FROM work_logs WHERE company_id=? AND worker_id=? AND work_date=?');
                $stmt->execute([$company['id'], $worker_id, $work_date]);
                if ($warps_count > 0) {
                    $stmt = $pdo->prepare('INSERT INTO work_logs(company_id, worker_id, work_date, warps_count, rate_per_warp, amount) VALUES(?,?,?,?,?,?)');
                    $stmt->execute([$company['id'], $worker_id, $work_date, $warps_count, $rate, $amount]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            $_SESSION['flash_error'] = 'Day total updated';
            $redir_week = $_POST['week_start'] ?? '';
            $to = 'index.php?page=work_logs' . ($redir_week ? ('&week_start=' . urlencode($redir_week)) : '');
            header('Location: ' . $to);
            exit;
        }
        if ($action === 'worklog_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM work_logs WHERE company_id=? AND id=?')->execute([$company['id'], $id]);
            header('Location: index.php?page=work_logs');
            exit;
        }

        // Advances: add and settle
        if ($action === 'advance_add') {
            $worker_id = (int)($_POST['worker_id'] ?? 0);
            $advance_date = $_POST['advance_date'] ?? date('Y-m-d');
            $amount = (float)($_POST['amount'] ?? 0);
            $note = trim($_POST['note'] ?? '');
            if ($worker_id <= 0 || $amount <= 0) throw new Exception('Invalid advance');
            $stmt = $pdo->prepare('INSERT INTO advances(company_id, worker_id, advance_date, amount, note, settled) VALUES(?,?,?,?,?,0)');
            $stmt->execute([$company['id'], $worker_id, $advance_date, $amount, $note]);
            header('Location: index.php?page=work_logs');
            exit;
        }
        if ($action === 'advance_settle') {
            $id = (int)($_POST['id'] ?? 0);
            $settle_amount = isset($_POST['settle_amount']) ? (float)$_POST['settle_amount'] : 0.0;
            $stmt = $pdo->prepare('SELECT amount, paid_amount FROM advances WHERE company_id=? AND id=?');
            $stmt->execute([$company['id'], $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Advance not found');
            $amount = (float)$row['amount'];
            $paid = (float)$row['paid_amount'];
            $remaining = max(0.0, $amount - $paid);
            if ($settle_amount <= 0 || $settle_amount > $remaining) {
                $settle_amount = $remaining; // default to remaining
            }
            $new_paid = min($amount, $paid + $settle_amount);
            $settled = ($new_paid >= $amount) ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE advances SET paid_amount=?, settled=? WHERE company_id=? AND id=?');
            $stmt->execute([$new_paid, $settled, $company['id'], $id]);
            $_SESSION['flash_error'] = 'Settled ₹ ' . number_format($settle_amount,2) . ' (remaining ₹ ' . number_format(max(0,$amount-$new_paid),2) . ')';
            header('Location: index.php?page=work_logs');
            exit;
        }

        if ($action === 'advance_update') {
            $id = (int)($_POST['id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $note = trim($_POST['note'] ?? '');
            if ($amount <= 0) throw new Exception('Invalid amount');
            // clamp paid_amount if it exceeds new amount
            $stmt = $pdo->prepare('SELECT paid_amount FROM advances WHERE company_id=? AND id=?');
            $stmt->execute([$company['id'], $id]);
            $paid = (float)($stmt->fetchColumn() ?: 0);
            $new_paid = min($paid, $amount);
            $settled = ($new_paid >= $amount) ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE advances SET amount=?, note=?, paid_amount=?, settled=? WHERE company_id=? AND id=?');
            $stmt->execute([$amount, $note, $new_paid, $settled, $company['id'], $id]);
            $_SESSION['flash_error'] = 'Advance updated';
            header('Location: index.php?page=work_logs');
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

require __DIR__ . '/app/layout.php';

if (!$company) {
    $page = 'onboarding';
}
// If there are companies but this is a fresh session and no explicit page was requested, show Companies page first
if ($company && !$hadSessionCompany && $page === null) {
    $page = 'companies';
}

switch ($page ?? 'dashboard') {
    case 'onboarding':
        require __DIR__ . '/pages/onboarding.php';
        break;
    case 'companies':
        require __DIR__ . '/pages/companies.php';
        break;
    case 'dashboard':
        require __DIR__ . '/pages/dashboard.php';
        break;
    case 'yarn_types':
        require __DIR__ . '/pages/yarn_types.php';
        break;
    case 'stocks':
        require __DIR__ . '/pages/stocks.php';
        break;
    case 'workers':
        require __DIR__ . '/pages/workers.php';
        break;
    case 'work_logs':
        require __DIR__ . '/pages/work_logs.php';
        break;
    default:
        require __DIR__ . '/pages/dashboard.php';
}
