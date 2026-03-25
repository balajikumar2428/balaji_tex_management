<?php

class WorkerController {
    public static function add($pdo, $company) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new Exception('Worker name required');
        $stmt = $pdo->prepare('INSERT INTO workers(name) VALUES(?)');
        $stmt->execute([$name]);
        header('Location: index.php?page=workers');
        exit;
    }

    public static function delete($pdo, $company) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM workers WHERE id=?');
        $stmt->execute([$id]);
        header('Location: index.php?page=workers');
        exit;
    }
}
