<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

switch ($action) {
    case 'add':
        if (!$id) jsonOut(['success'=>false,'message'=>'Missing id'], 400);
        // verify listing exists
        $stmt = db()->prepare("SELECT id FROM listings WHERE id=? AND status='available'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) jsonOut(['success'=>false,'message'=>'Listing not found'], 404);

        if (in_array($id, getCompareList(), true)) {
            jsonOut(['success'=>false,'message'=>'Already in comparison','count'=>count(getCompareList())]);
        }
        if (count(getCompareList()) >= MAX_COMPARE_ITEMS) {
            jsonOut(['success'=>false,'message'=>'Maximum '.MAX_COMPARE_ITEMS.' items allowed','count'=>count(getCompareList())]);
        }
        addCompare($id);
        jsonOut(['success'=>true,'message'=>'Added','count'=>count(getCompareList())]);

    case 'remove':
        removeCompare($id);
        jsonOut(['success'=>true,'count'=>count(getCompareList())]);

    case 'clear':
        clearCompare();
        jsonOut(['success'=>true,'count'=>0]);

    case 'count':
    default:
        jsonOut(['count'=>count(getCompareList())]);
}
