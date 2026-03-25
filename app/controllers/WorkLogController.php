<?php

class WorkLogController {
    public static function add($pdo, $company) {
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

    public static function update($pdo, $company) {
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

    public static function updateTotal($pdo, $company) {
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

    public static function delete($pdo, $company) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM work_logs WHERE company_id=? AND id=?')->execute([$company['id'], $id]);
        header('Location: index.php?page=work_logs');
        exit;
    }
}
