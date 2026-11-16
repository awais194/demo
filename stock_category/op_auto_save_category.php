<?php
include_once("../connection/localhost.php");

// New category from the popup on stock/stock_management.php (stock_category/ajax_save_category.php).
// Answers {category_id, category_name}, or {error} when the name is empty or already used.
header('Content-Type: application/json');

$Name = trim($_POST['new_name'] ?? '');
if ($Name === '')
{
    echo json_encode(['error' => 'Please enter the category name']);
    exit;
}

$Existing = DBQM::fetch_assoc(DBQM::query("SELECT id FROM st_stock_category WHERE name = " . escape($Name)));
if ($Existing)
{
    echo json_encode(['error' => 'Category "' . $Name . '" already exists']);
    exit;
}

$oStockCategory = new StockCategory();
$oStockCategory->initForSave();

$oStockCategory->saveMainTableData([1, $Name]);
$CategoryId = $oStockCategory->getVoucherId();

echo json_encode(empty($CategoryId) ? ['error' => 'Category was not saved. Please try again.'] : ['category_id' => $CategoryId, 'category_name' => $Name]);
