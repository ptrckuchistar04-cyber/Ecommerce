<?php
require_once __DIR__ . '/../db.php';

/* ============ AUTH ============ */
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function isAdmin(): bool    { return (($_SESSION['user_role'] ?? '') === 'administrator'); }
function currentUserId(): ?int { return $_SESSION['user_id'] ?? null; }

function requireLogin(string $redirect = 'login.php'): void {
    if (!isLoggedIn()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$redirect}?return={$back}");
        exit;
    }
}
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) { http_response_code(403); die('Forbidden'); }
}

/* ============ CSRF ============ */
function csrfToken(): string { return $_SESSION['csrf_token'] ?? ''; }
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
function csrfCheck(): void {
    $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $t)) {
        http_response_code(419);
        die('Invalid CSRF token. Please refresh and try again.');
    }
}

/* ============ HELPERS ============ */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string     { return '₱' . number_format((float)$n, 2); }
function hashPassword(string $p): string { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
function verifyPassword(string $p, string $h): bool { return password_verify($p, $h); }
function generateOrderNumber(): string { return 'OTL-' . strtoupper(bin2hex(random_bytes(6))); }

/* ============ SCHEMA DETECTION (memoized — runs once per request) ============ */
function hasPropertyExtraFields(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try {
        $r = (bool) db()->query("SHOW COLUMNS FROM property_details LIKE 'is_mortgaged'")->fetch();
    } catch (Exception $e) { $r = false; }
    return $r;
}

function hasVehicleExtraFields(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try {
        $r = (bool) db()->query("SHOW COLUMNS FROM vehicle_details LIKE 'color'")->fetch();
    } catch (Exception $e) { $r = false; }
    return $r;
}

function hasInquiryDetailFields(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try {
        $r = (bool) db()->query("SHOW COLUMNS FROM sell_inquiries LIKE 'property_type'")->fetch();
    } catch (Exception $e) { $r = false; }
    return $r;
}

/* ============ LISTINGS ============ */
function getListings(?string $type = null, string $search = '', int $limit = 100): array {
    $extraProp = '';
    $extraVeh  = '';
    if (hasPropertyExtraFields()) {
        $extraProp = ', pd.is_mortgaged, pd.monthly_amortization, pd.mortgage_bank,
                       pd.furnishing, pd.parking_slots, pd.floors, pd.lot_area';
    }
    if (hasVehicleExtraFields()) {
        $extraVeh = ', vd.color, vd.engine_type, vd.`condition`';
    }

    $sql = "SELECT l.id, l.type, l.title, l.description, l.price, l.reservation_fee,
                   l.main_image, l.status, l.is_promo, l.is_bundle,
                   pd.square_meters, pd.bedrooms, pd.bathrooms, pd.property_type, pd.location
                   {$extraProp}
                   , vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.fuel_type
                   {$extraVeh}
            FROM listings l
            LEFT JOIN property_details pd ON l.id = pd.listing_id AND l.type = 'property'
            LEFT JOIN vehicle_details  vd ON l.id = vd.listing_id AND l.type = 'vehicle'
            WHERE l.status = 'available'";
    $p = [];
    if ($type && in_array($type, ['property','vehicle'], true)) {
        $sql .= " AND l.type = :t"; $p[':t'] = $type;
    }
    if ($search !== '') {
        $sql .= " AND (l.title LIKE :s OR l.description LIKE :s2)";
        $p[':s']  = "%{$search}%";
        $p[':s2'] = "%{$search}%";
    }
    $sql .= " ORDER BY l.is_promo DESC, l.created_at DESC LIMIT :lim";

    $stmt = db()->prepare($sql);
    foreach ($p as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getPromoListings(int $limit = 6): array {
    $stmt = db()->prepare(
       "SELECT l.id, l.type, l.title, l.price, l.reservation_fee, l.main_image,
               pd.location, vd.make, vd.model, vd.year
        FROM listings l
        LEFT JOIN property_details pd ON l.id = pd.listing_id
        LEFT JOIN vehicle_details  vd ON l.id = vd.listing_id
        WHERE l.status='available' AND l.is_promo=1
        ORDER BY l.created_at DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getListing(int $id): ?array {
    $extraProp = '';
    $extraVeh  = '';
    if (hasPropertyExtraFields()) {
        $extraProp = ', pd.lot_area, pd.floors,
                       pd.is_mortgaged, pd.monthly_amortization, pd.mortgage_bank,
                       pd.furnishing, pd.parking_slots';
    }
    if (hasVehicleExtraFields()) {
        $extraVeh = ', vd.color, vd.engine_type, vd.`condition`, vd.plate_number';
    }

    $sql = "SELECT l.*,
               pd.property_type, pd.square_meters, pd.bedrooms, pd.bathrooms,
               pd.year_built, pd.location
               {$extraProp}
               , vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.fuel_type,
               vd.modifications, vd.vin
               {$extraVeh}
        FROM listings l
        LEFT JOIN property_details pd ON l.id = pd.listing_id
        LEFT JOIN vehicle_details  vd ON l.id = vd.listing_id
        WHERE l.id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getListingAmenities(int $id): array {
    $stmt = db()->prepare("SELECT name FROM amenities WHERE listing_id = :id ORDER BY id");
    $stmt->execute([':id' => $id]);
    return array_column($stmt->fetchAll(), 'name');
}

function getListingImages(int $id): array {
    $stmt = db()->prepare("SELECT url FROM images WHERE listing_id = :id ORDER BY sort_order, id");
    $stmt->execute([':id' => $id]);
    return array_column($stmt->fetchAll(), 'url');
}

/* ============ SELL INQUIRY → LISTING CREATION ============ */
function createListingFromInquiry(int $inquiryId, ?int $adminId = null): ?int {
    $stmt = db()->prepare("SELECT * FROM sell_inquiries WHERE id = ?");
    $stmt->execute([$inquiryId]);
    $inq = $stmt->fetch();
    if (!$inq) return null;

    // Use the approving admin's id; fall back to any administrator, never a blind "1".
    if ($adminId === null) $adminId = currentUserId();
    if ($adminId === null) {
        try {
            $adminId = (int) db()->query(
                "SELECT id FROM users WHERE role='administrator' ORDER BY id LIMIT 1"
            )->fetchColumn();
        } catch (Exception $e) { $adminId = null; }
    }
    if (!$adminId) {
        error_log('createListingFromInquiry: no admin id available');
        return null;
    }

    try {
        db()->beginTransaction();

        // 1. Create the listing
        $price = (float)$inq['asking_price'];
        $reserveFee = min($price * 0.01, 50000);
        if ($reserveFee < 1000) $reserveFee = 1000;

        // Record the seller (the inquiry's owner) when the schema supports it,
        // so they can later pay a promo fee on their own approved listing.
        if (hasPromoFields()) {
            $lStmt = db()->prepare(
                "INSERT INTO listings (admin_id, seller_id, type, title, description, price, reservation_fee, main_image, status, is_promo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', 0)"
            );
            $lStmt->execute([
                $adminId,
                (int)$inq['user_id'],
                $inq['item_type'],
                $inq['title'],
                $inq['description'],
                $price,
                $reserveFee,
                $inq['main_image'],
            ]);
        } else {
            $lStmt = db()->prepare(
                "INSERT INTO listings (admin_id, type, title, description, price, reservation_fee, main_image, status, is_promo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'available', 0)"
            );
            $lStmt->execute([
                $adminId,
                $inq['item_type'],
                $inq['title'],
                $inq['description'],
                $price,
                $reserveFee,
                $inq['main_image'],
            ]);
        }
        $lid = (int)db()->lastInsertId();

        // 2. Create property or vehicle details
        if ($inq['item_type'] === 'property') {
            // Check if new columns exist
            $hasExtra = hasPropertyExtraFields();
            if ($hasExtra) {
                $dStmt = db()->prepare(
                    "INSERT INTO property_details
                     (listing_id, property_type, square_meters, lot_area, bedrooms, bathrooms, floors,
                      year_built, location, is_mortgaged, monthly_amortization, mortgage_bank,
                      furnishing, parking_slots)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $dStmt->execute([
                    $lid,
                    $inq['property_type'] ?? 'house',
                    (float)($inq['square_meters'] ?? 0),
                    (float)($inq['lot_area'] ?? 0),
                    (int)($inq['bedrooms'] ?? 0),
                    (int)($inq['bathrooms'] ?? 0),
                    (int)($inq['floors'] ?? 1),
                    !empty($inq['year_built']) ? (int)$inq['year_built'] : null,
                    $inq['location'] ?? '',
                    (int)($inq['is_mortgaged'] ?? 0),
                    !empty($inq['monthly_amortization']) ? (float)$inq['monthly_amortization'] : null,
                    $inq['mortgage_bank'] ?? null,
                    $inq['furnishing'] ?? null,
                    (int)($inq['parking_slots'] ?? 0),
                ]);
            } else {
                // Fallback: original columns only
                $dStmt = db()->prepare(
                    "INSERT INTO property_details
                     (listing_id, property_type, square_meters, bedrooms, bathrooms, year_built, location)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $dStmt->execute([
                    $lid,
                    $inq['property_type'] ?? 'house',
                    (float)($inq['square_meters'] ?? 0),
                    (int)($inq['bedrooms'] ?? 0),
                    (int)($inq['bathrooms'] ?? 0),
                    !empty($inq['year_built']) ? (int)$inq['year_built'] : null,
                    $inq['location'] ?? '',
                ]);
            }
        } else {
            $hasExtra = hasVehicleExtraFields();
            if ($hasExtra) {
                $dStmt = db()->prepare(
                    "INSERT INTO vehicle_details
                     (listing_id, make, model, year, mileage, transmission, fuel_type,
                      modifications, vin, color, engine_type, `condition`, plate_number)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $dStmt->execute([
                    $lid,
                    $inq['make'] ?? '',
                    $inq['model'] ?? '',
                    (int)($inq['vehicle_year'] ?? date('Y')),
                    (int)($inq['mileage'] ?? 0),
                    $inq['transmission'] ?? 'automatic',
                    $inq['fuel_type'] ?? null,
                    $inq['modifications'] ?? null,
                    $inq['vin'] ?? null,
                    $inq['color'] ?? null,
                    $inq['engine_type'] ?? null,
                    $inq['vehicle_condition'] ?? 'used',
                    $inq['plate_number'] ?? null,
                ]);
            } else {
                $dStmt = db()->prepare(
                    "INSERT INTO vehicle_details
                     (listing_id, make, model, year, mileage, transmission, fuel_type, modifications, vin)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $dStmt->execute([
                    $lid,
                    $inq['make'] ?? '',
                    $inq['model'] ?? '',
                    (int)($inq['vehicle_year'] ?? date('Y')),
                    (int)($inq['mileage'] ?? 0),
                    $inq['transmission'] ?? 'automatic',
                    $inq['fuel_type'] ?? null,
                    $inq['modifications'] ?? null,
                    $inq['vin'] ?? null,
                ]);
            }
        }

        // 3. Move inquiry images to listing (only if inquiry_images table exists)
        try {
            $imgStmt = db()->prepare("SELECT url FROM inquiry_images WHERE inquiry_id = ? ORDER BY sort_order, id");
            $imgStmt->execute([$inquiryId]);
            $inqImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($inqImages as $idx => $url) {
                db()->prepare("INSERT INTO images (listing_id, url, sort_order) VALUES (?,?,?)")
                    ->execute([$lid, $url, $idx]);
            }
            if ($inq['main_image']) {
                $checkImg = db()->prepare("SELECT COUNT(*) FROM images WHERE listing_id = ? AND url = ?");
                $checkImg->execute([$lid, $inq['main_image']]);
                if ($checkImg->fetchColumn() == 0) {
                    db()->prepare("INSERT INTO images (listing_id, url, sort_order) VALUES (?,?,0)")
                        ->execute([$lid, $inq['main_image']]);
                }
            }
        } catch (Exception $e) {
            // inquiry_images table might not exist yet - that's ok
        }

        db()->commit();
        return $lid;
    } catch (Exception $e) {
        db()->rollBack();
        error_log('createListingFromInquiry error: ' . $e->getMessage());
        return null;
    }
}

/* ============ CART ============ */
function getCartItems(int $uid): array {
    $stmt = db()->prepare(
       "SELECT rc.id AS cart_id, l.id, l.type, l.title, l.price, l.reservation_fee, l.main_image,
               pd.square_meters, pd.bedrooms, vd.make, vd.model, vd.year
        FROM reservation_carts rc
        JOIN listings l ON rc.listing_id = l.id
        LEFT JOIN property_details pd ON l.id = pd.listing_id
        LEFT JOIN vehicle_details vd  ON l.id = vd.listing_id
        WHERE rc.user_id = :u ORDER BY rc.added_at DESC");
    $stmt->execute([':u' => $uid]);
    return $stmt->fetchAll();
}

/* ============ COMPARE (session-backed) ============ */
function getCompareList(): array { return $_SESSION['compare_list'] ?? []; }
function addCompare(int $id): bool {
    $_SESSION['compare_list'] = $_SESSION['compare_list'] ?? [];
    if (in_array($id, $_SESSION['compare_list'], true)) return false;
    if (count($_SESSION['compare_list']) >= MAX_COMPARE_ITEMS) return false;
    $_SESSION['compare_list'][] = $id;
    return true;
}
function removeCompare(int $id): void {
    if (!isset($_SESSION['compare_list'])) return;
    $_SESSION['compare_list'] = array_values(array_diff($_SESSION['compare_list'], [$id]));
}
function clearCompare(): void { $_SESSION['compare_list'] = []; }

function getCompareItems(): array {
    $ids = getCompareList();
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $extraProp = '';
    $extraVeh  = '';
    if (hasPropertyExtraFields()) {
        $extraProp = ', pd.is_mortgaged, pd.monthly_amortization, pd.furnishing';
    }
    if (hasVehicleExtraFields()) {
        $extraVeh = ', vd.color, vd.`condition`';
    }

    $stmt = db()->prepare(
       "SELECT l.*,
               pd.property_type, pd.square_meters, pd.bedrooms, pd.bathrooms, pd.location
               {$extraProp}
               , vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.modifications
               {$extraVeh}
        FROM listings l
        LEFT JOIN property_details pd ON l.id = pd.listing_id
        LEFT JOIN vehicle_details  vd ON l.id = vd.listing_id
        WHERE l.id IN ($ph)");
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

/* ============ JSON response ============ */
function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ============ XENDIT HELPERS ============ */

/** Fetch a single invoice from Xendit by id. Returns array or null. */
function xenditGetInvoice(string $invoiceId): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init(XENDIT_API_BASE . '/v2/invoices/' . urlencode($invoiceId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode(XENDIT_SECRET_KEY . ':')],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) return null;
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
}

/**
 * Mark a transaction paid and move its listings to "reserved". Idempotent.
 * Returns true if the order is paid (now or already), false on error.
 */
function settleTransactionPaid(int $txId): bool {
    try {
        db()->beginTransaction();
        $stmt = db()->prepare("SELECT id, user_id, status FROM transactions WHERE id=? FOR UPDATE");
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();
        if (!$tx) { db()->rollBack(); return false; }
        if (in_array($tx['status'], ['paid','completed'], true)) { db()->commit(); return true; }

        db()->prepare("UPDATE transactions SET status='paid', updated_at=NOW() WHERE id=?")->execute([$txId]);
        db()->prepare(
            "UPDATE listings l JOIN transaction_items ti ON l.id=ti.listing_id
             SET l.status='reserved', l.updated_at=NOW()
             WHERE ti.transaction_id=?")->execute([$txId]);
        if (!empty($tx['user_id'])) {
            db()->prepare("DELETE FROM reservation_carts WHERE user_id=?")->execute([$tx['user_id']]);
        }
        db()->commit();
        return true;
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('settleTransactionPaid error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Reconcile a pending order with Xendit (used on the return page so payments
 * confirm even without a public webhook URL). Returns the up-to-date status.
 */
function syncTransactionWithXendit(array $order): string {
    $status = $order['status'] ?? 'pending';
    if ($status !== 'pending' || empty($order['xendit_invoice_id'])) return $status;

    $inv = xenditGetInvoice($order['xendit_invoice_id']);
    if (!$inv) return $status;

    $remote = strtoupper($inv['status'] ?? '');
    if ($remote === 'PAID' || $remote === 'SETTLED') {
        settleTransactionPaid((int)$order['id']);
        try {
            db()->prepare("INSERT INTO payment_logs (transaction_id, invoice_id, status, payload) VALUES (?,?,?,?)")
                ->execute([(int)$order['id'], $order['xendit_invoice_id'], 'PAID_VIA_RETURN', json_encode($inv)]);
        } catch (Throwable $e) {}
        return 'paid';
    }
    if (in_array($remote, ['EXPIRED','FAILED'], true)) {
        $newStatus = $remote === 'EXPIRED' ? 'expired' : 'cancelled';
        try {
            db()->prepare("UPDATE transactions SET status=?, updated_at=NOW() WHERE id=?")
                ->execute([$newStatus, (int)$order['id']]);
        } catch (Throwable $e) {}
        return $newStatus;
    }
    return $status;
}

/* ============ SCHEMA DETECTION for v4 (promo/seller features) ============ */
function hasPromoFields(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try { $r = (bool) db()->query("SHOW COLUMNS FROM listings LIKE 'promo_status'")->fetch(); }
    catch (Exception $e) { $r = false; }
    return $r;
}
function hasAdminNotifications(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try { $r = (bool) db()->query("SHOW TABLES LIKE 'admin_notifications'")->fetch(); }
    catch (Exception $e) { $r = false; }
    return $r;
}

/* ============ ADMIN NOTIFICATIONS ============ */
function notifyAdmin(string $type, string $message, ?string $link = null): void {
    if (!hasAdminNotifications()) { error_log("Admin notify ($type): $message"); return; }
    try {
        db()->prepare("INSERT INTO admin_notifications (type, message, link) VALUES (?,?,?)")
            ->execute([$type, $message, $link]);
    } catch (Exception $e) { error_log('notifyAdmin: ' . $e->getMessage()); }
}
function unreadAdminNotificationCount(): int {
    if (!hasAdminNotifications()) return 0;
    try { return (int) db()->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetchColumn(); }
    catch (Exception $e) { return 0; }
}
function getAdminNotifications(int $limit = 30): array {
    if (!hasAdminNotifications()) return [];
    try {
        $stmt = db()->prepare("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

/* ============ PROMO FEE ============ */
// Flat promo fee for a featured listing (demo). Tweak as you like.
function promoFeeFor(array $listing): float {
    return 500.00;
}

/**
 * Reconcile a promo payment with Xendit. On payment: mark the promo_payment 'paid',
 * set the listing to pending_payment→paid-but-not-yet-featured, and notify the admin.
 * Returns the up-to-date promo_payment status.
 */
function syncPromoWithXendit(array $promo): string {
    $status = $promo['status'] ?? 'pending';
    if ($status !== 'pending' || empty($promo['xendit_invoice_id'])) return $status;

    $inv = xenditGetInvoice($promo['xendit_invoice_id']);
    if (!$inv) return $status;
    $remote = strtoupper($inv['status'] ?? '');

    if ($remote === 'PAID' || $remote === 'SETTLED') {
        try {
            db()->beginTransaction();
            // settle promo payment (idempotent)
            $chk = db()->prepare("SELECT status FROM promo_payments WHERE id=? FOR UPDATE");
            $chk->execute([(int)$promo['id']]);
            if (in_array($chk->fetchColumn(), ['paid'], true)) { db()->commit(); return 'paid'; }

            db()->prepare("UPDATE promo_payments SET status='paid', updated_at=NOW() WHERE id=?")
                ->execute([(int)$promo['id']]);
            // Listing: fee paid, awaiting admin activation (promo_status='paid' but is_promo stays 0
            // until admin approves; if you want auto-feature, set is_promo=1 here instead).
            db()->prepare("UPDATE listings SET promo_status='paid', updated_at=NOW() WHERE id=?")
                ->execute([(int)$promo['listing_id']]);

            // Look up details for a nice notification.
            $tStmt = db()->prepare("SELECT title FROM listings WHERE id=?");
            $tStmt->execute([(int)$promo['listing_id']]);
            $title = $tStmt->fetchColumn() ?: ('listing #' . (int)$promo['listing_id']);

            $sStmt = db()->prepare("SELECT full_name FROM users WHERE id=?");
            $sStmt->execute([(int)$promo['seller_id']]);
            $seller = $sStmt->fetchColumn() ?: 'A seller';

            db()->commit();

            notifyAdmin(
                'promo_payment',
                "{$seller} paid the promo fee for \"{$title}\". Review and activate the promo.",
                'admin.php?tab=listings'
            );
            return 'paid';
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('syncPromoWithXendit: ' . $e->getMessage());
            return $status;
        }
    }
    if (in_array($remote, ['EXPIRED','FAILED'], true)) {
        $new = $remote === 'EXPIRED' ? 'expired' : 'cancelled';
        try {
            db()->prepare("UPDATE promo_payments SET status=?, updated_at=NOW() WHERE id=?")
                ->execute([$new, (int)$promo['id']]);
            db()->prepare("UPDATE listings SET promo_status='none' WHERE id=? AND promo_status='pending_payment'")
                ->execute([(int)$promo['listing_id']]);
        } catch (Throwable $e) {}
        return $new;
    }
    return $status;
}

/* ============ SVG placeholder (no external file needed) ============ */
function placeholderImg(string $type = 'property'): string {
    $icon  = $type === 'vehicle' ? '🚗' : '🏠';
    $bg    = $type === 'vehicle' ? '%23FF8C00' : '%23191970';
    return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400'>"
         . "<rect width='100%25' height='100%25' fill='{$bg}'/>"
         . "<text x='50%25' y='50%25' font-size='140' text-anchor='middle' dy='.35em'>{$icon}</text></svg>";
}
function listingImg(?string $url, string $type): string {
    return $url ?: placeholderImg($type);
}
