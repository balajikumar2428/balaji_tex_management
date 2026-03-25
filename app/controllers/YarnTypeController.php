<?php

class YarnTypeController {
    public static function add($pdo, $company) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new Exception('Yarn type name required');
        
        $stmt = $pdo->prepare('SELECT id FROM yarn_types WHERE company_id=? AND name=?');
        $stmt->execute([$company['id'], $name]);
        $existing = $stmt->fetchColumn();
        
        if (!$existing) {
            $stmt = $pdo->prepare('INSERT INTO yarn_types(company_id, name) VALUES(?, ?)');
            $stmt->execute([$company['id'], $name]);
        }
        header('Location: index.php?page=yarn_types');
        exit;
    }

    public static function delete($pdo, $company) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM yarn_types WHERE id=?');
        $stmt->execute([$id]);
        header('Location: index.php?page=yarn_types');
        exit;
    }

    public static function edit($pdo, $company) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id <= 0 || $name === '') throw new Exception('Invalid input');
        
        $stmt = $pdo->prepare('SELECT id FROM yarn_types WHERE company_id=? AND name=? AND id!=?');
        $stmt->execute([$company['id'], $name, $id]);
        $existing = $stmt->fetchColumn();
        
        if (!$existing) {
            $stmt = $pdo->prepare('UPDATE yarn_types SET name=? WHERE id=?');
            $stmt->execute([$name, $id]);
        }
        
        header('Location: index.php?page=yarn_types');
        exit;
    }
}
