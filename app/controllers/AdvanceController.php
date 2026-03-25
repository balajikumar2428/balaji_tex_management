<?php

class AdvanceController {
    public static function add($pdo, $company) {
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

    public static function settle($pdo, $company) {
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

    public static function update($pdo, $company) {
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
}
