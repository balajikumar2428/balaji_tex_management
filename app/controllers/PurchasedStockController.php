<?php

class PurchasedStockController {
    public static function add($pdo, $company) {
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
        $date_purchased = trim($_POST['date_purchased'] ?? date('Y-m-d'));
        $bag_count = (int)($_POST['bag_count'] ?? 0);
        $weight_per_bag = (float)($_POST['weight_per_bag'] ?? 0);
        
        if ($supplier_name === '' || $yarn_type_id <= 0 || $bag_count <= 0 || $weight_per_bag <= 0) {
            throw new Exception('All fields must be filled correctly with values greater than 0');
        }
        
        $stmt = $pdo->prepare('INSERT INTO purchased_stocks (company_id, yarn_type_id, supplier_name, date_purchased, bag_count, weight_per_bag) VALUES(?, ?, ?, ?, ?, ?)');
        $stmt->execute([$company['id'], $yarn_type_id, $supplier_name, $date_purchased, $bag_count, $weight_per_bag]);
        
        $_SESSION['flash_error'] = 'Purchased stock added successfully';
        header('Location: index.php?page=purchased_stocks');
        exit;
    }

    public static function delete($pdo, $company) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            delete_purchased_stock($company['id'], $id);
            $_SESSION['flash_error'] = 'Purchased stock record deleted';
        }
        header('Location: index.php?page=purchased_stocks');
        exit;
    }
}
