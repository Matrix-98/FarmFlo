<?php
// Minimal, drop-in login page with required role selection including 'farmer'.
// Adjust BASE_URL and include paths to your project if needed.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

/** Backward-compatible password check; upgrades to bcrypt on success */
function ff_check_and_upgrade_password(PDO $pdo, array $u, string $plain): bool {
  $stored = (string)($u['password'] ?? '');
  $ok = false;
  if ($stored !== '' && $stored[0] === '$') { $ok = password_verify($plain, $stored); }
  elseif (preg_match('/^[a-f0-9]{32}$/i', $stored)) { $ok = (md5($plain) === strtolower($stored)); }
  else { $ok = hash_equals($stored, $plain); }
  if ($ok && ($stored === $plain || preg_match('/^[a-f0-9]{32}$/i', $stored))) {
    $new = password_hash($plain, PASSWORD_BCRYPT);
    $u2 = $pdo->prepare('UPDATE users SET password=? WHERE id=?');
    $u2->execute([$new, (int)$u['id']]);
  }
  return $ok;
}

$err = '';
$remember = false;
$next = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim($_POST['identifier'] ?? ''); // username or email
  $password   = $_POST['password'] ?? '';
  $role_sel   = trim($_POST['role'] ?? '');
  $remember   = !empty($_POST['remember']);

  try {
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? OR name = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && ff_check_and_upgrade_password($pdo, $user, $password)) {
      $actual_role = (string)($user['role'] ?? '');
      if (strcasecmp($role_sel, $actual_role) !== 0) {
        $err = 'Selected role does not match your account (' . htmlspecialchars($actual_role) . ').';
      } else {
        $_SESSION['user'] = [
          'id'    => (int)$user['id'],
          'name'  => $user['name'] ?? '',
          'email' => $user['email'] ?? '',
          'role'  => $actual_role,
        ];
        if ($remember) {
          setcookie('ff_id', $identifier, time()+60*60*24*30, '/', '', false, true);
          setcookie('ff_role', $role_sel, time()+60*60*24*30, '/', '', false, true);
        } else {
          setcookie('ff_id','',time()-3600,'/'); setcookie('ff_role','',time()-3600,'/');
        }
        $dest = BASE_URL . '/index.php';
        if ($next) $dest = BASE_URL . '/' . ltrim($next,'/');
        header('Location: '.$dest); exit;
      }
    } else {
      $err = 'Invalid username/email or password.';
    }
  } catch (Throwable $e) {
    $err = 'Login failed.';
  }
}

$prefill_id = $_COOKIE['ff_id'] ?? '';
$prefill_role = $_COOKIE['ff_role'] ?? '';

// Ensure 'farmer' role is supported downstream; DB can remain VARCHAR. If you use ENUM,
// update it to include 'farmer' (see README note).
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — FarmFlo</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <div class="ff-card">
    <h1 class="ff-title">Login</h1>
    <p class="ff-sub">Welcome back! Please login to your account</p>

    <?php if($err): ?><div class="ff-error"><?= $err ?></div><?php endif; ?>

    <form class="ff-form" method="post" action="<?= BASE_URL ?>/pages/login.php<?= $next ? '?next='.urlencode($next) : '' ?>">
      <div class="ff-row ff-with-icon">
        <label>Username or Email</label>
        <span class="ff-icon">👤</span>
        <input class="ff-input" type="text" name="identifier" placeholder="username or email" value="<?= htmlspecialchars($prefill_id) ?>" required>
      </div>

      <div class="ff-row ff-with-icon ff-pwrap">
        <label>Password</label>
        <span class="ff-icon">🔒</span>
        <input class="ff-input" id="pw" type="password" name="password" placeholder="••••••••" required>
        <span class="ff-eye" id="eye" aria-label="Show password" title="Show/Hide">👁</span>
      </div>

      <div class="ff-row">
        <label>Role</label>
        <!-- No 'Any' option; only user roles, including new 'farmer'. -->
        <select class="ff-input ff-select" name="role" required>
          <option value="admin" <?= $prefill_role==='admin'?'selected':''; ?>>Admin</option>
          <option value="logistics_manager" <?= $prefill_role==='logistics_manager'?'selected':''; ?>>Logistics Manager</option>
          <option value="warehouse" <?= $prefill_role==='warehouse'?'selected':''; ?>>Warehouse</option>
          <option value="driver" <?= $prefill_role==='driver'?'selected':''; ?>>Driver</option>
          <option value="customer" <?= $prefill_role==='customer'?'selected':''; ?>>Customer</option>
          <option value="farmer" <?= $prefill_role==='farmer'?'selected':''; ?>>Farmer</option>
        </select>
      </div>

      <div class="ff-inline">
        <label class="ff-check">
          <input type="checkbox" name="remember" <?= $prefill_id ? 'checked' : '' ?>> <span>Remember me</span>
        </label>
        <a class="ff-link" href="<?= BASE_URL ?>/pages/auth/forgot_password.php">Forgot Password?</a>
      </div>

      <div class="ff-actions">
        <button class="ff-btn" type="submit">Login</button>
        <a class="ff-link" href="<?= BASE_URL ?>/pages/auth/request_access.php">Join as Customer</a>
      </div>
    </form>
  </div>

<script>
const pw=document.getElementById('pw');
const eye=document.getElementById('eye');
eye.addEventListener('click',()=>{
  const show = pw.type === 'password';
  pw.type = show ? 'text' : 'password';
  eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});
</script>
</body>
</html>
