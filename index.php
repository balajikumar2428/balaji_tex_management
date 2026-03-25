<?php
require __DIR__ . '/app/db.php';
session_start();
$hadSessionCompany = isset($_SESSION['company_id']);
$company = get_company();
$page = $_GET['page'] ?? null;

// Require controllers
require_once __DIR__ . '/app/controllers/CompanyController.php';
require_once __DIR__ . '/app/controllers/YarnTypeController.php';
require_once __DIR__ . '/app/controllers/StockController.php';
require_once __DIR__ . '/app/controllers/PurchasedStockController.php';
require_once __DIR__ . '/app/controllers/WorkerController.php';
require_once __DIR__ . '/app/controllers/WorkLogController.php';
require_once __DIR__ . '/app/controllers/AdvanceController.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo = DB::conn();
        
        if ($action === 'company_create') {
            CompanyController::create($pdo);
        } elseif ($action === 'company_select') {
            CompanyController::select($pdo);
        } else {
            // All other actions require a company
            if (!$company) throw new Exception('Company not initialized');
            
            switch ($action) {
                // Yarn type actions
                case 'yarn_type_add': YarnTypeController::add($pdo, $company); break;
                case 'yarn_type_delete': YarnTypeController::delete($pdo, $company); break;
                case 'yarn_type_edit': YarnTypeController::edit($pdo, $company); break;
                
                // Stock management actions
                case 'add_stock': StockController::add($pdo, $company); break;
                case 'sell_stock': StockController::sell($pdo, $company); break;
                case 'delete_all_stocks': StockController::deleteAll($pdo, $company); break;
                case 'delete_stock': StockController::delete($pdo, $company); break;
                
                // Purchased Stocks actions
                case 'purchased_stock_add': PurchasedStockController::add($pdo, $company); break;
                case 'purchased_stock_delete': PurchasedStockController::delete($pdo, $company); break;
                
                // Worker add/delete
                case 'worker_add': WorkerController::add($pdo, $company); break;
                case 'worker_delete': WorkerController::delete($pdo, $company); break;
                
                // Work log actions
                case 'worklog_add': WorkLogController::add($pdo, $company); break;
                case 'worklog_update': WorkLogController::update($pdo, $company); break;
                case 'worklog_update_total': WorkLogController::updateTotal($pdo, $company); break;
                case 'worklog_delete': WorkLogController::delete($pdo, $company); break;
                
                // Advances actions
                case 'advance_add': AdvanceController::add($pdo, $company); break;
                case 'advance_settle': AdvanceController::settle($pdo, $company); break;
                case 'advance_update': AdvanceController::update($pdo, $company); break;
                
                default:
                    if ($action !== '') {
                        throw new Exception('Invalid action');
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}

// Check for direct download pages first (before including layout)
if ($page === 'stock_log') {
    require __DIR__ . '/pages/stock_log.php';
    exit;
}
if ($page === 'stocks_download_new') {
    require __DIR__ . '/pages/stocks_download_new.php';
    exit;
}

// Don't include layout for direct downloads
if (!defined('DIRECT_DOWNLOAD')) {
    require __DIR__ . '/app/layout.php';
}

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
    case 'purchased_stocks':
        require __DIR__ . '/pages/purchased_stocks.php';
        break;
    case 'sales':
        require __DIR__ . '/pages/sales.php';
        break;
    case 'summary':
        require __DIR__ . '/pages/summary.php';
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
