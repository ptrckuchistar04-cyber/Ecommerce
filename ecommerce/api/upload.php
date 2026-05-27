<?php
/**
 * Admin-only image upload for listings.
 *   POST  multipart/form-data
 *         listing_id, image (file), as_main (0|1)
 * Accepts JPG/PNG/WEBP up to 5 MB.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error'=>'POST required'], 405);
csrfCheck();

$lid     = (int)($_POST['listing_id'] ?? 0);
$asMain  = !empty($_POST['as_main']);
if ($lid <= 0 || empty($_FILES['image'])) jsonOut(['error'=>'Missing fields'], 400);

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) jsonOut(['error'=>'Upload error: '.$file['error']], 400);
if ($file['size'] > 5 * 1024 * 1024) jsonOut(['error'=>'Max file size 5 MB'], 400);

$mime = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
if (!isset($allowed[$mime])) jsonOut(['error'=>'Only JPG / PNG / WEBP allowed'], 400);

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
$name = 'lst-' . $lid . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
$dest = UPLOAD_DIR . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) jsonOut(['error'=>'Move failed'], 500);

// Relative URL to be saved
$url = 'uploads/products/' . $name;

try {
    db()->prepare("INSERT INTO images (listing_id, url) VALUES (?,?)")->execute([$lid, $url]);
    if ($asMain) {
        db()->prepare("UPDATE listings SET main_image=? WHERE id=?")->execute([$url, $lid]);
    }
    jsonOut(['success'=>true, 'url'=>$url]);
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonOut(['error'=>'DB error'], 500);
}
