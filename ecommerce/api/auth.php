<?php
/**
 * Lightweight JSON auth API (for any future SPA/mobile integration).
 *   POST action=login    {email, password}
 *   POST action=register {full_name, email, password, birthday, ...}
 *   GET  action=me
 *   POST action=logout
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'me':
        if (!isLoggedIn()) jsonOut(['authenticated' => false]);
        $stmt = db()->prepare("SELECT id, full_name, email, phone, age, role FROM users WHERE id=?");
        $stmt->execute([currentUserId()]);
        jsonOut(['authenticated' => true, 'user' => $stmt->fetch()]);

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error' => 'POST required'], 405);
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!$email || !$pass) jsonOut(['success' => false, 'message' => 'Missing fields'], 400);

        $stmt = db()->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !verifyPassword($pass, $u['password_hash'])) {
            jsonOut(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$u['id'];
        $_SESSION['user_name'] = $u['full_name'];
        $_SESSION['user_role'] = $u['role'];
        jsonOut(['success' => true, 'role' => $u['role']]);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        jsonOut(['success' => true]);

    default:
        jsonOut(['error' => 'Unknown action'], 400);
}
