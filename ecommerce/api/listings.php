<?php
/**
 * JSON listings API.
 *   GET /api/listings.php                        → all available
 *   GET /api/listings.php?type=property|vehicle  → filtered
 *   GET /api/listings.php?id=123                 → single listing + amenities
 *   GET /api/listings.php?search=keyword         → search
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $l = getListing($id);
    if (!$l) jsonOut(['error' => 'Not found'], 404);
    $l['amenities'] = getListingAmenities($id);
    jsonOut($l);
}

$type   = $_GET['type']   ?? null;
$search = trim($_GET['search'] ?? '');
$limit  = min(200, (int)($_GET['limit'] ?? 60));
jsonOut(['listings' => getListings($type, $search, $limit)]);
