<?php
session_start();

// Already logged in? redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $safe_user = mysqli_real_escape_string($conn, $username);
        $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$safe_user'");

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login | Vyomark Digital</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen bg-blue-950 flex items-center justify-center px-4">
  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-10">
      <div class="inline-flex items-center gap-3 mb-4">
        <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
          <span class="text-white font-bold text-2xl">V</span>
        </div>
        <span class="font-bold text-2xl text-white">Vyomark <span class="text-blue-400">Digital</span></span>
      </div>
      <p class="text-blue-300 text-sm">Admin Panel — Authorized Access Only</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-[32px] p-10 shadow-2xl shadow-blue-900/50">
      <h1 class="text-2xl font-bold text-blue-950 mb-2">Welcome back</h1>
      <p class="text-gray-500 text-sm mb-8">Sign in to access the admin dashboard.</p>

      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-5 py-4 rounded-2xl text-sm mb-6 flex items-center gap-3">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div class="space-y-2">
          <label class="text-xs font-bold text-blue-950 uppercase tracking-widest">Username</label>
          <input
            type="text"
            name="username"
            required
            placeholder="Enter your username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            class="w-full px-5 py-4 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-yellow-400 focus:bg-white transition-all text-blue-950"
          />
        </div>
        <div class="space-y-2">
          <label class="text-xs font-bold text-blue-950 uppercase tracking-widest">Password</label>
          <input
            type="password"
            name="password"
            required
            placeholder="Enter your password"
            class="w-full px-5 py-4 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-yellow-400 focus:bg-white transition-all text-blue-950"
          />
        </div>
        <button
          type="submit"
          class="w-full bg-yellow-400 hover:bg-yellow-500 text-blue-950 font-bold py-4 rounded-2xl transition-all transform hover:scale-[1.02] shadow-lg shadow-yellow-100 mt-2"
        >
          Sign In →
        </button>
      </form>
    </div>

    <p class="text-center text-blue-400 text-xs mt-8">© 2026 Vyomark Digital Solutions</p>
  </div>
</body>
</html>