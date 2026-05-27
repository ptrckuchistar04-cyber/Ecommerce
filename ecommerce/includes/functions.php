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

/* ============ LISTINGS ============ */
function getListings(?string $type = null, string $search = '', int $limit = 100): array {
    $sql = "SELECT l.id, l.type, l.title, l.description, l.price, l.reservation_fee,
                   l.main_image, l.status, l.is_promo, l.is_bundle,
                   pd.square_meters, pd.bedrooms, pd.bathrooms, pd.property_type, pd.location,
                   vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.fuel_type
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
    $stmt = db()->prepare(
       "SELECT l.*,
               pd.property_type, pd.square_meters, pd.bedrooms, pd.bathrooms, pd.year_built, pd.location,
               vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.fuel_type, vd.modifications, vd.vin
        FROM listings l
        LEFT JOIN property_details pd ON l.id = pd.listing_id
        LEFT JOIN vehicle_details  vd ON l.id = vd.listing_id
        WHERE l.id = :id");
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getListingAmenities(int $id): array {
    $stmt = db()->prepare("SELECT name FROM amenities WHERE listing_id = :id ORDER BY id");
    $stmt->execute([':id' => $id]);
    return array_column($stmt->fetchAll(), 'name');
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
    $stmt = db()->prepare(
       "SELECT l.*,
               pd.property_type, pd.square_meters, pd.bedrooms, pd.bathrooms, pd.location,
               vd.make, vd.model, vd.year, vd.mileage, vd.transmission, vd.modifications
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
