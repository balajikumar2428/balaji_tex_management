<?php

class StockController {
    public static function add($pdo, $company) {
        $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
        $stock_type = trim($_POST['stock_type'] ?? '');
        $stock_notes = trim($_POST['stock_notes'] ?? '');
        
        if ($yarn_type_id <= 0 || $stock_type === '') {
            throw new Exception('All fields are required');
        }
        
        // Get yarn type name
        $stmt = $pdo->prepare('SELECT name FROM yarn_types WHERE id=?');
        $stmt->execute([$yarn_type_id]);
        $yarn_name = $stmt->fetchColumn();
        
        if ($stock_type === 'chippam') {
            $total_chippam_weight_raw = trim($_POST['total_chippam_weight'] ?? '');
            $total_chippam_number_raw = trim($_POST['total_chippam_number'] ?? '');
            $stock_date = trim($_POST['stock_date'] ?? date('Y-m-d'));
            
            if ($total_chippam_weight_raw === '') throw new Exception('Total chippam weight is required');
            if ($total_chippam_number_raw === '') throw new Exception('Total chippam number is required');
            
            // Parse multiple values separated by comma
            $weight_values = array_map('floatval', array_map('trim', explode(',', $total_chippam_weight_raw)));
            $number_values = array_map('intval', array_map('trim', explode(',', $total_chippam_number_raw)));
            
            // Validate all values
            foreach ($weight_values as $weight) {
                if ($weight <= 0) throw new Exception('All chippam weights must be greater than 0');
            }
            foreach ($number_values as $number) {
                if ($number <= 0) throw new Exception('All chippam numbers must be greater than 0');
            }
            
            // Handle mismatched counts by using totals or averages
            if (count($weight_values) !== count($number_values)) {
                // If counts don't match, check if we have multiple weights and single quantity
                if (count($weight_values) > 1 && count($number_values) === 1) {
                    // Multiple weights, single quantity - use sum of weights
                    $total_chippam_weight = array_sum($weight_values);
                    $total_chippam_number = array_sum($number_values);
                } elseif (count($weight_values) === 1 && count($number_values) > 1) {
                    // Single weight, multiple quantities - multiply weight by total quantity
                    $total_chippam_weight = $weight_values[0] * array_sum($number_values);
                    $total_chippam_number = array_sum($number_values);
                } else {
                    // Other mismatched cases - use average weight
                    $avg_weight = array_sum($weight_values) / count($weight_values);
                    $total_chippam_weight = $avg_weight * array_sum($number_values);
                    $total_chippam_number = array_sum($number_values);
                }
            }
            
            // Create separate entries for chippam stocks (each entry = 1 bag)
            if (count($weight_values) !== count($number_values)) {
                // Handle mismatched counts
                if (count($weight_values) > 1 && count($number_values) === 1) {
                    // Multiple weights, single quantity - create separate entries for each weight (each = 1 bag)
                    foreach ($weight_values as $weight) {
                        $stmt = $pdo->prepare('INSERT INTO stocks(yarn_type_id, cotton_type, bag_weight, total_bags, stock_type, date, notes) VALUES(?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$yarn_type_id, $yarn_name, $weight, 1, 'chippam', $stock_date, $stock_notes]); // 1 bag per entry
                    }
                } elseif (count($weight_values) === 1 && count($number_values) > 1) {
                    // Single weight, multiple quantities - create separate entries for each quantity (each = 1 bag)
                    foreach ($number_values as $number) {
                        $stmt = $pdo->prepare('INSERT INTO stocks(yarn_type_id, cotton_type, bag_weight, total_bags, stock_type, date, notes) VALUES(?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$yarn_type_id, $yarn_name, $weight_values[0], 1, 'chippam', $stock_date, $stock_notes]); // 1 bag per entry
                    }
                } else {
                    // Other mismatched cases - create one entry with totals
                    $avg_weight = array_sum($weight_values) / count($weight_values);
                    $total_chippam_weight = $avg_weight * array_sum($number_values);
                    $total_chippam_number = array_sum($number_values);
                    $stmt = $pdo->prepare('INSERT INTO stocks(yarn_type_id, cotton_type, bag_weight, total_bags, stock_type, date, notes) VALUES(?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$yarn_type_id, $yarn_name, $total_chippam_weight, $total_chippam_number, 'chippam', $stock_date, $stock_notes]);
                }
            } else {
                // Matching counts - create separate entries for each weight/quantity pair (each = 1 bag)
                foreach ($weight_values as $index => $weight) {
                    $stmt = $pdo->prepare('INSERT INTO stocks(yarn_type_id, cotton_type, bag_weight, total_bags, stock_type, date, notes) VALUES(?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$yarn_type_id, $yarn_name, $weight, 1, 'chippam', $stock_date, $stock_notes]); // 1 bag per entry
                }
            }
            $_SESSION['flash_error'] = 'Chippam stock added successfully';
            
        } elseif ($stock_type === 'bag') {
            $total_bags_raw = trim($_POST['total_bags'] ?? '');
            $stock_date = trim($_POST['stock_date'] ?? date('Y-m-d'));
            
            if ($total_bags_raw === '') {
                throw new Exception('Total bags is required');
            }
            
            // Parse multiple values separated by comma
            $bag_values = array_map('intval', array_map('trim', explode(',', $total_bags_raw)));
            
            // Validate all values
            foreach ($bag_values as $bags) {
                if ($bags <= 0) throw new Exception('All bag quantities must be greater than 0');
            }
            
            // Calculate totals
            $total_bags = array_sum($bag_values);
            
            // Create separate entries for each bag quantity with fixed 50kg per bag
            foreach ($bag_values as $index => $bags) {
                $bag_weight = 50.000; // Fixed 50kg per bag
                $stmt = $pdo->prepare('INSERT INTO stocks(yarn_type_id, cotton_type, bag_weight, total_bags, stock_type, date, notes) VALUES(?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$yarn_type_id, $yarn_name, $bag_weight, $bags, 'bag', $stock_date, $stock_notes]);
            }
            
            $_SESSION['flash_error'] = 'Bag stock added successfully';
        }
        
        header('Location: index.php?page=stocks');
        exit;
    }

    public static function sell($pdo, $company) {
        $yarn_type_id = (int)($_POST['yarn_type_id'] ?? 0);
        $stock_type = trim($_POST['stock_type'] ?? ''); // 'bag' or 'chippam'
        $sell_qty = (int)($_POST['sell_bags'] ?? 0);
        $weight_per_unit = (float)($_POST['sell_weight_per_unit'] ?? 0);
        $sell_date = trim($_POST['sell_date'] ?? date('Y-m-d'));
        $sell_notes = trim($_POST['sell_notes'] ?? '');

        if ($yarn_type_id <= 0 || $stock_type === '' || $sell_qty <= 0 || $weight_per_unit <= 0) {
            throw new Exception('All fields are required and must be valid');
        }

        // Ensure sales table exists (Migration)
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            yarn_type_id INT NOT NULL,
            stock_type ENUM('chippam', 'bag') NOT NULL,
            sold_date DATE NOT NULL,
            quantity INT NOT NULL,
            weight_per_unit DECIMAL(10,3) NOT NULL,
            total_weight DECIMAL(10,3) NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // FIFO Deduction Logic
        // Find all available batches for this yarn and type, ordered by date (oldest first)
        $stmt = $pdo->prepare("SELECT * FROM stocks 
                             WHERE yarn_type_id = ? AND stock_type = ? AND (total_bags - sold_bags) > 0 
                             ORDER BY date ASC, id ASC");
        $stmt->execute([$yarn_type_id, $stock_type]);
        $available_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_available = 0;
        foreach ($available_batches as $b) {
            $total_available += ($b['total_bags'] - $b['sold_bags']);
        }

        if ($sell_qty > $total_available) {
            throw new Exception("Not enough stock available. Total available: $total_available");
        }

        $remaining_to_sell = $sell_qty;
        foreach ($available_batches as $batch) {
            if ($remaining_to_sell <= 0) break;

            $batch_available = $batch['total_bags'] - $batch['sold_bags'];
            $sell_from_this_batch = min($remaining_to_sell, $batch_available);
            
            $weight_sold_from_batch = $sell_from_this_batch * $weight_per_unit;
            
            $new_sold_bags = $batch['sold_bags'] + $sell_from_this_batch;
            $new_sold_weight = $batch['sold_weight'] + $weight_sold_from_batch;

            $update_stmt = $pdo->prepare("UPDATE stocks SET sold_bags = ?, sold_weight = ? WHERE id = ?");
            $update_stmt->execute([$new_sold_bags, $new_sold_weight, $batch['id']]);

            $remaining_to_sell -= $sell_from_this_batch;
        }

        // Record the sale
        $total_weight_sold = $sell_qty * $weight_per_unit;
        $insert_sale = $pdo->prepare("INSERT INTO stock_sales (company_id, yarn_type_id, stock_type, sold_date, quantity, weight_per_unit, total_weight, notes) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_sale->execute([$company['id'], $yarn_type_id, $stock_type, $sell_date, $sell_qty, $weight_per_unit, $total_weight_sold, $sell_notes]);

        $_SESSION['flash_error'] = "Sold $sell_qty " . ($stock_type === 'bag' ? 'bags' : 'chippams') . " successfully";
        header('Location: index.php?page=stocks');
        exit;
    }

    public static function deleteAll($pdo, $company) {
        $stmt = $pdo->prepare('DELETE FROM stocks');
        $stmt->execute();
        
        $_SESSION['flash_error'] = 'All stocks have been deleted successfully';
        header('Location: index.php?page=stocks');
        exit;
    }

    public static function delete($pdo, $company) {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        if ($stock_id <= 0) throw new Exception('Invalid stock ID');
        
        $stmt = $pdo->prepare('DELETE FROM stocks WHERE id=?');
        $stmt->execute([$stock_id]);
        $_SESSION['flash_error'] = 'Stock deleted successfully';
        header('Location: index.php?page=stocks');
        exit;
    }
}
