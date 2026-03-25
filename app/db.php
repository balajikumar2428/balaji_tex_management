<?php
// MySQL PDO connection for Docker
class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            // Use environment variables for Docker, fallback to defaults
            $host = $_ENV['DB_HOST'] ?? 'localhost'; // Default for XAMPP
            $dbname = $_ENV['DB_NAME'] ?? 'balaji_tex';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            $port = $_ENV['DB_PORT'] ?? '3306';
            
            try {
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                self::$pdo = new PDO($dsn, $username, $password);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}

function get_company() {
    $pdo = DB::conn();
    $id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, company_name as name, created_date as created_at FROM companies WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $stmt = $pdo->query('SELECT id, company_name as name, created_date as created_at FROM companies ORDER BY id LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row) {
        $_SESSION['company_id'] = (int)$row['id'];
    }
    return $row;
}

function list_companies() {
    $pdo = DB::conn();
    $stmt = $pdo->query('SELECT id, company_name as name, created_date as created_at FROM companies ORDER BY company_name');
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
    $stmt = $pdo->prepare('INSERT INTO companies(company_name, created_date) VALUES(:name, CURDATE())');
    $stmt->execute([':name' => trim($name)]);
    $_SESSION['company_id'] = (int)$pdo->lastInsertId();
}

// Worker functions for existing table structure
function list_workers($company_id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT * FROM workers');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function create_worker($company_id, $name) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('INSERT INTO workers(name) VALUES(?)');
    $stmt->execute([trim($name)]);
    return $pdo->lastInsertId();
}

function delete_worker($company_id, $id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('DELETE FROM workers WHERE id=?');
    $stmt->execute([$id]);
}

// Stock functions for existing table structure
function list_stocks($company_id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT * FROM stocks ORDER BY date DESC');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_stock($company_id, $cotton_type, $bag_weight, $total_bags) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('INSERT INTO stocks(cotton_type, bag_weight, total_bags, date) VALUES(?, ?, ?, CURDATE())');
    $stmt->execute([$cotton_type, $bag_weight, $total_bags]);
    return $pdo->lastInsertId();
}

// Purchased Stocks structure
function list_purchased_stocks($company_id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT p.*, yt.name as yarn_name
                          FROM purchased_stocks p 
                          LEFT JOIN yarn_types yt ON p.yarn_type_id = yt.id 
                          WHERE p.company_id=? 
                          ORDER BY p.date_purchased DESC, p.id DESC');
    $stmt->execute([$company_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function delete_purchased_stock($company_id, $id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('DELETE FROM purchased_stocks WHERE company_id=? AND id=?');
    $stmt->execute([$company_id, $id]);
}

// Summary Metrics
function get_yarn_production_summary($company_id) {
    $pdo = DB::conn();
    
    // Get purchased totals per yarn type
    // Group by yarn_type_id to get total kg bought
    $purchased_query = "SELECT yarn_type_id, SUM(total_weight) as purchased_kg 
                        FROM purchased_stocks 
                        WHERE company_id = ? 
                        GROUP BY yarn_type_id";
    $stmt_purchased = $pdo->prepare($purchased_query);
    $stmt_purchased->execute([$company_id]);
    $purchased_data = $stmt_purchased->fetchAll(PDO::FETCH_ASSOC);
    
    // Get finished (production) totals per yarn type
    // Note: bag_weight holds total weight for chippams (with 1 total_bags), 
    // and for bags we generally calculate total by bag_weight * total_bags (but the DB tracks raw numbers).
    // Let's accurately sum up based on stock type if necessary or just rely on total weight logic
    // Actually bag_weight IS the total weight for chippams, but for bags we inserted bag_weight=50, total_bags=X
    // Therefore effective weight = sum(bag_weight * total_bags)
    $finished_query = "SELECT s.yarn_type_id, 
                              SUM(s.total_bags * s.bag_weight) as finished_kg 
                       FROM stocks s 
                       JOIN yarn_types yt ON s.yarn_type_id = yt.id
                       WHERE yt.company_id = ? 
                       GROUP BY s.yarn_type_id";
    $stmt_finished = $pdo->prepare($finished_query);
    $stmt_finished->execute([$company_id]);
    $finished_data = $stmt_finished->fetchAll(PDO::FETCH_ASSOC);
    
    // Get Yarn Types
    $yarn_types = list_yarn_types($company_id);
    
    $summary = [];
    foreach ($yarn_types as $yt) {
        $summary[$yt['id']] = [
            'yarn_name' => $yt['name'],
            'purchased_kg' => 0.0,
            'finished_kg' => 0.0,
            'pending_kg' => 0.0
        ];
    }
    
    // Merge Purchased
    foreach ($purchased_data as $row) {
        if (isset($summary[$row['yarn_type_id']])) {
            $summary[$row['yarn_type_id']]['purchased_kg'] += (float)$row['purchased_kg'];
        }
    }
    
    // Merge Finished
    foreach ($finished_data as $row) {
        if (isset($summary[$row['yarn_type_id']])) {
            $summary[$row['yarn_type_id']]['finished_kg'] += (float)$row['finished_kg'];
        }
    }
    
    // Calculate Pending
    foreach ($summary as $id => $data) {
        $summary[$id]['pending_kg'] = max(0, $data['purchased_kg'] - $data['finished_kg']);
    }
    
    // Filter out yarn types with no activity in either purchase or production to keep UI clean
    $active_summary = array_filter($summary, function($data) {
        return $data['purchased_kg'] > 0 || $data['finished_kg'] > 0;
    });
    
    return $active_summary;
}

// Yarn types functions
function list_yarn_types($company_id) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT * FROM yarn_types WHERE company_id=? ORDER BY name');
    $stmt->execute([$company_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function create_yarn_type($company_id, $name) {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('INSERT INTO yarn_types(company_id, name) VALUES(?, ?)');
    $stmt->execute([$company_id, trim($name)]);
    return $pdo->lastInsertId();
}

// Work logs functions
function list_work_logs($company_id, $worker_id = null, $start_date = null, $end_date = null) {
    $pdo = DB::conn();
    $sql = 'SELECT wl.*, w.name as worker_name FROM work_logs wl LEFT JOIN workers w ON wl.worker_id = w.id WHERE wl.company_id=?';
    $params = [$company_id];
    
    if ($worker_id) {
        $sql .= ' AND wl.worker_id=?';
        $params[] = $worker_id;
    }
    if ($start_date) {
        $sql .= ' AND wl.work_date >= ?';
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql .= ' AND wl.work_date <= ?';
        $params[] = $end_date;
    }
    
    $sql .= ' ORDER BY wl.work_date DESC, wl.id DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function create_work_log($company_id, $worker_id, $work_date, $warps_count, $rate_per_warp) {
    $pdo = DB::conn();
    $amount = $warps_count * $rate_per_warp;
    $stmt = $pdo->prepare('INSERT INTO work_logs(company_id, worker_id, work_date, warps_count, rate_per_warp, amount) VALUES(?, ?, ?, ?, ?, ?)');
    $stmt->execute([$company_id, $worker_id, $work_date, $warps_count, $rate_per_warp, $amount]);
    return $pdo->lastInsertId();
}

// Advances functions
function list_advances($company_id, $worker_id = null) {
    $pdo = DB::conn();
    $sql = 'SELECT a.*, w.name as worker_name FROM advances a LEFT JOIN workers w ON a.worker_id = w.id WHERE a.company_id=?';
    $params = [$company_id];
    
    if ($worker_id) {
        $sql .= ' AND a.worker_id=?';
        $params[] = $worker_id;
    }
    
    $sql .= ' ORDER BY a.advance_date DESC, a.id DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function create_advance($company_id, $worker_id, $advance_date, $amount, $note = '') {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('INSERT INTO advances(company_id, worker_id, advance_date, amount, note) VALUES(?, ?, ?, ?, ?)');
    $stmt->execute([$company_id, $worker_id, $advance_date, $amount, $note]);
    return $pdo->lastInsertId();
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
