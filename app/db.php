<?php
// Simple PDO SQLite connection and schema bootstrap
class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            $dbDir = __DIR__ . '/../data';
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $dbPath = $dbDir . '/app.db';
            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::initSchema();
        }
        return self::$pdo;
    }

    private static function initSchema() {
        $sql = [
            // companies
            'CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )',
            // yarn types
            'CREATE TABLE IF NOT EXISTS yarn_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                UNIQUE(company_id, name)
            )',
            // stocks per yarn type (current balance)
            'CREATE TABLE IF NOT EXISTS stocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                yarn_type_id INTEGER NOT NULL,
                weight_kg REAL NOT NULL DEFAULT 0,
                UNIQUE(company_id, yarn_type_id)
            )',
            // workers
            'CREATE TABLE IF NOT EXISTS workers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                UNIQUE(company_id, name)
            )',
            // work logs
            'CREATE TABLE IF NOT EXISTS work_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                worker_id INTEGER NOT NULL,
                work_date TEXT NOT NULL,
                warps_count INTEGER NOT NULL,
                rate_per_warp REAL NOT NULL,
                amount REAL NOT NULL
            )'
        ];
        foreach ($sql as $q) {
            self::$pdo->exec($q);
        }

        // Lightweight migration: ensure bags_count column exists on stocks
        try {
            self::$pdo->exec('ALTER TABLE stocks ADD COLUMN bags_count INTEGER NOT NULL DEFAULT 0');
        } catch (Exception $e) {
            // ignore if exists
        }

        // Ensure advances table exists
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS advances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
            worker_id INTEGER NOT NULL,
            advance_date TEXT NOT NULL,
            amount REAL NOT NULL,
            note TEXT,
            settled INTEGER NOT NULL DEFAULT 0
        )');
        // Migration: add paid_amount to advances if missing
        try {
            self::$pdo->exec('ALTER TABLE advances ADD COLUMN paid_amount REAL NOT NULL DEFAULT 0');
        } catch (Exception $e) {
            // ignore if exists
        }
    }
}

function get_company() {
    $pdo = DB::conn();
    $id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $stmt = $pdo->query('SELECT * FROM companies ORDER BY id LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) {
        $_SESSION['company_id'] = (int)$row['id'];
    }
    return $row;
}

function list_companies() {
    $pdo = DB::conn();
    $stmt = $pdo->query('SELECT * FROM companies ORDER BY name');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function select_company($id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT id FROM companies WHERE id=?');
    $stmt->execute([(int)$id]);
    if ($stmt->fetchColumn()) {
        $_SESSION['company_id'] = (int)$id;
        return true;
    }
    return false;
}

function create_company($name) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('INSERT INTO companies(name) VALUES(:name)');
    $stmt->execute([':name' => trim($name)]);
    $_SESSION['company_id'] = (int)$pdo->lastInsertId();
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
