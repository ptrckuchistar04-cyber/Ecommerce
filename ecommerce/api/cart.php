<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cart.php'); exit;
}

// Auth check (do this BEFORE csrf so an unauthenticated POST goes to login)
if (!isLoggedIn()) {
    $_SESSION['flash_error'] = 'Please log in to reserve items.';
    header('Location: ../login.php?return=' . urlencode('listing.php'));
    exit;
}
if (isAdmin()) {
    $_SESSION['flash_error'] = 'Admins cannot reserve items.';
    header('Location: ../admin.php'); exit;
}

// CSRF — show friendly redirect instead of die()
$t = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrfToken(), $t)) {
    $_SESSION['flash_error'] = 'Your session expired. Please try again.';
    header('Location: ../listing.php'); exit;
}

$action = $_GET['action'] ?? '';
$uid    = currentUserId();

if ($action === 'add') {
    $lid = (int)($_POST['listing_id'] ?? 0);
    if ($lid <= 0) {
        $_SESSION['flash_error'] = 'Missing listing.';
        header('Location: ../listing.php'); exit;
    }
    try {
        $stmt = db()->prepare("SELECT id, status, title FROM listings WHERE id=?");
        $stmt->execute([$lid]);
        $l = $stmt->fetch();
        if (!$l) {
            $_SESSION['flash_error'] = 'Listing not found.';
            header('Location: ../listing.php'); exit;
        }
        if ($l['status'] !== 'available') {
            $_SESSION['flash_error'] = 'That listing is no longer available (' . $l['status'] . ').';
            header('Location: ../listing.php'); exit;
        }
        $ins = db()->prepare("INSERT IGNORE INTO reservation_carts (user_id, listing_id) VALUES (?,?)");
        $ins->execute([$uid, $lid]);
        $_SESSION['flash_ok'] = $ins->rowCount()
            ? '"' . $l['title'] . '" added to your reservation cart!'
            : '"' . $l['title'] . '" is already in your cart.';
    } catch (Exception $e) {
        error_log('Cart add error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Could not add item. Error: ' . $e->getMessage();
    }
    header('Location: ../cart.php'); exit;
}

if ($action === 'remove') {
    $cid = (int)($_POST['cart_id'] ?? 0);
    if ($cid > 0) {
        try {
            $stmt = db()->prepare("DELETE FROM reservation_carts WHERE id=? AND user_id=?");
            $stmt->execute([$cid, $uid]);
            $_SESSION['flash_ok'] = 'Item removed.';
        } catch (Exception $e) {
            error_log('Cart remove: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Could not remove item.';
        }
    }
    header('Location: ../cart.php'); exit;
}

header('Location: ../cart.php');
exit;
