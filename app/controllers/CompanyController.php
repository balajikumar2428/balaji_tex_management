<?php

class CompanyController {
    public static function create($pdo) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') throw new Exception('Company name required');
        create_company($name);
        header('Location: index.php');
        exit;
    }

    public static function select($pdo) {
        $id = (int)($_POST['company_id'] ?? 0);
        if (!select_company($id)) throw new Exception('Invalid company');
        header('Location: index.php');
        exit;
    }
}
