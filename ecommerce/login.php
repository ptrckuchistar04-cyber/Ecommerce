<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$err = '';
$return = $_GET['return'] ?? ($_POST['return'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $err = 'Please fill in all fields.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
        } catch (Exception $e) {
            error_log('Login DB: ' . $e->getMessage());
            $u = null;
            $err = 'Database error. Please try again.';
        }

        if (!$err) {
            if ($u && verifyPassword($pass, $u['password_hash'])) {
                // Regenerate session ID but KEEP the data (including csrf_token)
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$u['id'];
                $_SESSION['user_name'] = $u['full_name'];
                $_SESSION['user_role'] = $u['role'];

                // Determine destination
                $dest = '';
                if ($return) {
                    // Only allow same-site relative paths
                    $clean = ltrim($return, '/');
                    // strip leading "ecommerce/" if present
                    $clean = preg_replace('#^ecommerce/#', '', $clean);
                    if (preg_match('#^[\w\-./?&=]+$#', $clean) && !str_starts_with($clean, '//')) {
                        $dest = $clean;
                    }
                }
                if (!$dest) {
                    $dest = ($u['role']==='administrator') ? 'admin.php' : 'index.php';
                }
                header('Location: ' . $dest);
                exit;
            } else {
                $err = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = 'Sign In — On The Line';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-6 py-16">
  <div class="bg-white rounded-3xl shadow-deep p-8">
    <h1 class="font-display text-3xl font-bold text-navy">Welcome back</h1>
    <p class="text-ink/60 mt-1">Sign in to manage your reservations.</p>

    <?php if ($err): ?><div class="mt-5 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?= e($err) ?></div><?php endif; ?>

    <form method="POST" class="mt-6 space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="return" value="<?= e($return) ?>">

      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Email</label>
        <input type="email" name="email" required autofocus class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-semibold text-navy mb-1">Password</label>
        <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-orange focus:outline-none">
      </div>

      <button class="btn btn-primary w-full justify-center">Sign In</button>
    </form>

    <p class="mt-6 text-center text-sm text-ink/70">
      No account? <a href="register.php" class="text-orange font-semibold hover:underline">Create one</a>
    </p>
    <div class="mt-4 p-3 bg-slate-50 rounded-xl text-xs text-ink/60 space-y-1">
      <p class="font-semibold text-navy">Demo accounts (after running database.sql):</p>
      <p>👤 Customer: <code class="bg-white px-1 rounded">customer@example.com</code> / <code class="bg-white px-1 rounded">customer123</code></p>
      <p>🛡 Admin: <code class="bg-white px-1 rounded">admin@ontheline.com</code> / <code class="bg-white px-1 rounded">admin123</code></p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
