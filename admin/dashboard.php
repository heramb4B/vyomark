<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$msg = '';
$msg_type = '';
$active_tab = $_GET['tab'] ?? 'contacts';

// ── REFRESH: reload current tab with fresh data ──
if (isset($_GET['refresh'])) {
    $refresh_tab = in_array($_GET['refresh'], ['contacts', 'admins', 'subscriptions']) ? $_GET['refresh'] : 'contacts';
    header('Location: dashboard.php?tab=' . $refresh_tab . '&msg=refreshed');
    exit();
}

// ── CONTACTS: delete single ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM contacts WHERE id = $id");
    header('Location: dashboard.php?tab=contacts&msg=contact_deleted');
    exit();
}

// ── CONTACTS: delete all ──
if (isset($_POST['delete_all'])) {
    mysqli_query($conn, "TRUNCATE TABLE contacts");
    header('Location: dashboard.php?tab=contacts&msg=contacts_cleared');
    exit();
}

// ── ADMINS: add new admin ──
if (isset($_POST['add_admin'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($new_username) || empty($new_password) || empty($confirm_password)) {
        $msg = 'All fields are required.';
        $msg_type = 'error';
        $active_tab = 'admins';
    } elseif (strlen($new_username) < 3) {
        $msg = 'Username must be at least 3 characters.';
        $msg_type = 'error';
        $active_tab = 'admins';
    } elseif (strlen($new_password) < 6) {
        $msg = 'Password must be at least 6 characters.';
        $msg_type = 'error';
        $active_tab = 'admins';
    } elseif ($new_password !== $confirm_password) {
        $msg = 'Passwords do not match.';
        $msg_type = 'error';
        $active_tab = 'admins';
    } else {
        $safe_user = mysqli_real_escape_string($conn, $new_username);
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe_user'");
        if (mysqli_num_rows($check) > 0) {
            $msg = 'Username already exists. Choose a different one.';
            $msg_type = 'error';
            $active_tab = 'admins';
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            mysqli_query($conn, "INSERT INTO users (username, password) VALUES ('$safe_user', '$hashed')");
            header('Location: dashboard.php?tab=admins&msg=admin_added');
            exit();
        }
    }
}

// ── ADMINS: delete admin ──
if (isset($_GET['delete_admin']) && is_numeric($_GET['delete_admin'])) {
    $del_id = (int)$_GET['delete_admin'];
    // Prevent deleting the protected "admin" account
    $target = mysqli_query($conn, "SELECT id, username FROM users WHERE id = $del_id");
    $target_row = mysqli_fetch_assoc($target);
    if ($target_row && $target_row['username'] === 'admin') {
        header('Location: dashboard.php?tab=admins&msg=cant_delete_superadmin');
        exit();
    }
    // Prevent deleting yourself
    $self = mysqli_query($conn, "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $_SESSION['admin_username']) . "'");
    $self_row = mysqli_fetch_assoc($self);
    if ($self_row && $self_row['id'] == $del_id) {
        header('Location: dashboard.php?tab=admins&msg=cant_delete_self');
        exit();
    }
    mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
    header('Location: dashboard.php?tab=admins&msg=admin_deleted');
    exit();
}

// ── SUBSCRIPTIONS: delete single ──
if (isset($_GET['delete_sub']) && is_numeric($_GET['delete_sub'])) {
    $sub_id = (int)$_GET['delete_sub'];
    mysqli_query($conn, "DELETE FROM subscriptions WHERE id = $sub_id");
    header('Location: dashboard.php?tab=subscriptions&msg=sub_deleted');
    exit();
}

// ── SUBSCRIPTIONS: delete all ──
if (isset($_POST['delete_all_subs'])) {
    mysqli_query($conn, "TRUNCATE TABLE subscriptions");
    header('Location: dashboard.php?tab=subscriptions&msg=subs_cleared');
    exit();
}

// ── Load contacts ──
$search = trim($_GET['search'] ?? '');
$where = '';
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where = "WHERE name LIKE '%$s%' OR email LIKE '%$s%' OR phone LIKE '%$s%' OR message LIKE '%$s%'";
}
$per_page = 10;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$total_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM contacts $where");
$total_row    = mysqli_fetch_assoc($total_result);
$total        = (int)$total_row['cnt'];
$total_pages  = ceil($total / $per_page);

$contacts_result = mysqli_query($conn, "SELECT * FROM contacts $where ORDER BY submitted_at DESC LIMIT $per_page OFFSET $offset");
$contacts = [];
while ($row = mysqli_fetch_assoc($contacts_result)) { $contacts[] = $row; }

// ── Load admins ──
$admins_result = mysqli_query($conn, "SELECT id, username, created_at FROM users ORDER BY created_at ASC");
$admins = [];
while ($row = mysqli_fetch_assoc($admins_result)) { $admins[] = $row; }

// ── Load subscriptions ──
$subs_result = mysqli_query($conn, "SELECT * FROM subscriptions ORDER BY subscribed_at DESC");
$subscriptions = [];
while ($row = mysqli_fetch_assoc($subs_result)) { $subscriptions[] = $row; }
$total_subs = count($subscriptions);

// ── Stats ──
$today_res   = mysqli_query($conn, "SELECT COUNT(*) as c FROM contacts WHERE DATE(submitted_at) = CURDATE()");
$today_count = mysqli_fetch_assoc($today_res)['c'];
$week_res    = mysqli_query($conn, "SELECT COUNT(*) as c FROM contacts WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$week_count  = mysqli_fetch_assoc($week_res)['c'];

// ── Site Visits ──
$visits_total_res   = mysqli_query($conn, "SELECT COUNT(*) as c FROM site_visits");
$visits_total       = $visits_total_res ? (int)mysqli_fetch_assoc($visits_total_res)['c'] : 0;
$visits_today_res   = mysqli_query($conn, "SELECT COUNT(*) as c FROM site_visits WHERE DATE(visited_at) = CURDATE()");
$visits_today       = $visits_today_res ? (int)mysqli_fetch_assoc($visits_today_res)['c'] : 0;
$visits_week_res    = mysqli_query($conn, "SELECT COUNT(*) as c FROM site_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$visits_week        = $visits_week_res ? (int)mysqli_fetch_assoc($visits_week_res)['c'] : 0;

$current_username = htmlspecialchars($_SESSION['admin_username']);

// URL msg override
if (isset($_GET['msg'])) {
    $get_msg = $_GET['msg'];
    if ($get_msg === 'contact_deleted') { $msg = 'Contact entry deleted.'; $msg_type = 'success'; }
    elseif ($get_msg === 'contacts_cleared') { $msg = 'All contact entries cleared.'; $msg_type = 'success'; }
    elseif ($get_msg === 'admin_added') { $msg = 'New admin added successfully!'; $msg_type = 'success'; }
    elseif ($get_msg === 'admin_deleted') { $msg = 'Admin deleted successfully.'; $msg_type = 'success'; }
    elseif ($get_msg === 'cant_delete_self') { $msg = 'You cannot delete your own account.'; $msg_type = 'error'; }
    elseif ($get_msg === 'cant_delete_superadmin') { $msg = 'The "admin" account is protected and cannot be deleted.'; $msg_type = 'error'; }
    elseif ($get_msg === 'sub_deleted') { $msg = 'Subscriber deleted.'; $msg_type = 'success'; }
    elseif ($get_msg === 'subs_cleared') { $msg = 'All subscribers cleared.'; $msg_type = 'success'; }
    elseif ($get_msg === 'refreshed') { $msg = 'Dashboard refreshed with latest data.'; $msg_type = 'success'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | Vyomark Digital</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .toast { animation: slideIn 0.4s ease, fadeOut 0.5s ease 4s forwards; }
    @keyframes slideIn { from { transform: translateY(-16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes fadeOut  { from { opacity: 1; } to { opacity: 0; pointer-events: none; } }
    .tab-btn.active { background: #172554; color: #fff; }
    .tab-btn { transition: all 0.2s; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    input[type="password"]::-ms-reveal { display: none; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

  <!-- Navbar -->
  <nav class="bg-blue-950 text-white px-6 py-4 flex items-center justify-between shadow-xl sticky top-0 z-50">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center">
        <span class="font-bold text-lg">V</span>
      </div>
      <span class="font-bold text-lg">Vyomark <span class="text-blue-400">Admin</span></span>
    </div>
    <div class="flex items-center gap-6">
      <span class="text-blue-300 text-sm hidden sm:block">👋 Hello, <strong class="text-white"><?= $current_username ?></strong></span>
      <a href="dashboard.php?refresh=<?= urlencode($active_tab) ?>&tab=<?= urlencode($active_tab) ?>"
        class="bg-blue-800 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-full text-sm transition-all flex items-center gap-2">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Refresh
      </a>
      <a href="../index.html" class="bg-yellow-400 hover:bg-yellow-500 text-blue-950 font-bold px-5 py-2 rounded-full text-sm transition-all">Logout</a>
    </div>
  </nav>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- Toast -->
    <?php if ($msg): ?>
      <div class="toast mb-6 px-6 py-4 rounded-2xl font-medium text-sm flex items-center gap-3
        <?= $msg_type === 'error' ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
        <?php if ($msg_type === 'error'): ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php else: ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <?php endif; ?>
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-5 mb-8">
      <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Total Submissions</p>
        <p class="text-4xl font-extrabold text-blue-950"><?= $total ?></p>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Today</p>
        <p class="text-4xl font-extrabold text-blue-600"><?= $today_count ?></p>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Last 7 Days</p>
        <p class="text-4xl font-extrabold text-yellow-500"><?= $week_count ?></p>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Total Admins</p>
        <p class="text-4xl font-extrabold text-green-600"><?= count($admins) ?></p>
      </div>
      <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Site Visits</p>
        <p class="text-4xl font-extrabold text-purple-600"><?= number_format($visits_total) ?></p>
        <p class="text-xs text-gray-400 mt-1">
          <span class="text-purple-500 font-semibold"><?= $visits_today ?></span> today &nbsp;·&nbsp;
          <span class="text-purple-500 font-semibold"><?= $visits_week ?></span> this week
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 bg-white border border-gray-100 rounded-2xl p-1.5 shadow-sm w-fit">
      <button onclick="switchTab('contacts')" id="tab-contacts"
        class="tab-btn <?= $active_tab === 'contacts' ? 'active' : 'text-gray-500 hover:bg-gray-100' ?> px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Contact Submissions
      </button>
      <button onclick="switchTab('admins')" id="tab-admins"
        class="tab-btn <?= $active_tab === 'admins' ? 'active' : 'text-gray-500 hover:bg-gray-100' ?> px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Manage Admins
      </button>
      <button onclick="switchTab('subscriptions')" id="tab-subscriptions"
        class="tab-btn <?= $active_tab === 'subscriptions' ? 'active' : 'text-gray-500 hover:bg-gray-100' ?> px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
        Subscriptions
        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-bold"><?= $total_subs ?></span>
      </button>
    </div>

    <!-- ══════════ CONTACTS TAB ══════════ -->
    <div id="content-contacts" class="tab-content <?= $active_tab === 'contacts' ? 'active' : '' ?>">

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-extrabold text-blue-950">Contact Submissions
          <span class="ml-2 text-sm font-semibold text-gray-400">(<?= $total ?> total)</span>
        </h2>
        <div class="flex items-center gap-3 flex-wrap">
          <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="contacts"/>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..."
              class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-yellow-400 w-44"/>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm hover:bg-blue-700 transition-all">Search</button>
            <?php if ($search): ?>
              <a href="dashboard.php?tab=contacts" class="bg-gray-100 text-gray-600 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-200 transition-all">Clear</a>
            <?php endif; ?>
          </form>
          <?php if ($total > 0): ?>
          <form method="POST" onsubmit="return confirm('Delete ALL contact entries? This cannot be undone.')">
            <button type="submit" name="delete_all" class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all">
              Clear All
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($contacts)): ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-20 text-center">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <h3 class="text-lg font-bold text-blue-950 mb-2"><?= $search ? 'No results found' : 'No submissions yet' ?></h3>
          <p class="text-gray-400 text-sm"><?= $search ? 'Try a different search term.' : 'Contact form submissions will appear here.' ?></p>
        </div>
      <?php else: ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-blue-950 text-white text-left">
                  <th class="px-6 py-4 font-semibold">#</th>
                  <th class="px-6 py-4 font-semibold">Name</th>
                  <th class="px-6 py-4 font-semibold">Email</th>
                  <th class="px-6 py-4 font-semibold">Phone</th>
                  <th class="px-6 py-4 font-semibold">Message</th>
                  <th class="px-6 py-4 font-semibold">Date</th>
                  <th class="px-6 py-4 font-semibold text-center">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($contacts as $i => $c): ?>
                <tr class="hover:bg-blue-50/30 transition-colors">
                  <td class="px-6 py-4 text-gray-400 font-medium"><?= $offset + $i + 1 ?></td>
                  <td class="px-6 py-4 font-semibold text-blue-950"><?= htmlspecialchars($c['name']) ?></td>
                  <td class="px-6 py-4 text-gray-600"><a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="hover:text-blue-600 transition-colors"><?= htmlspecialchars($c['email']) ?></a></td>
                  <td class="px-6 py-4 text-gray-600"><a href="tel:<?= htmlspecialchars($c['phone']) ?>" class="hover:text-blue-600 transition-colors"><?= htmlspecialchars($c['phone']) ?></a></td>
                  <td class="px-6 py-4 text-gray-500 max-w-xs">
                    <span title="<?= htmlspecialchars($c['message']) ?>">
                      <?= htmlspecialchars(strlen($c['message']) > 60 ? substr($c['message'], 0, 60) . '...' : $c['message']) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-gray-400 whitespace-nowrap"><?= date('d M Y, h:i A', strtotime($c['submitted_at'])) ?></td>
                  <td class="px-6 py-4 text-center">
                    <a href="dashboard.php?delete=<?= $c['id'] ?>&tab=contacts<?= $search ? '&search='.urlencode($search) : '' ?>"
                      onclick="return confirm('Delete this entry?')"
                      class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Delete</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($total_pages > 1): ?>
          <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-400">Page <?= $page_num ?> of <?= $total_pages ?></p>
            <div class="flex gap-2">
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?tab=contacts&page=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                  class="w-9 h-9 flex items-center justify-center rounded-lg text-sm font-semibold transition-all
                  <?= $p === $page_num ? 'bg-blue-950 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100' ?>"><?= $p ?></a>
              <?php endfor; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ══════════ ADMINS TAB ══════════ -->
    <div id="content-admins" class="tab-content <?= $active_tab === 'admins' ? 'active' : '' ?>">

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Add Admin Form -->
        <div>
          <h2 class="text-xl font-extrabold text-blue-950 mb-6">Add New Admin</h2>
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8">
            <form method="POST" class="space-y-5">
              <div class="space-y-2">
                <label class="text-xs font-bold text-blue-950 uppercase tracking-widest">Username</label>
                <input type="text" name="new_username" required placeholder="e.g. john_admin" minlength="3"
                  value="<?= $active_tab === 'admins' ? htmlspecialchars($_POST['new_username'] ?? '') : '' ?>"
                  class="w-full px-5 py-4 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-yellow-400 focus:bg-white transition-all text-blue-950 text-sm"/>
              </div>
              <div class="space-y-2">
                <label class="text-xs font-bold text-blue-950 uppercase tracking-widest">Password</label>
                <div class="relative">
                  <input type="password" name="new_password" id="new_password" required placeholder="Min. 6 characters" minlength="6"
                    class="w-full px-5 py-4 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-yellow-400 focus:bg-white transition-all text-blue-950 text-sm pr-12"/>
                  <button type="button" onclick="togglePass('new_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
              </div>
              <div class="space-y-2">
                <label class="text-xs font-bold text-blue-950 uppercase tracking-widest">Confirm Password</label>
                <div class="relative">
                  <input type="password" name="confirm_password" id="confirm_password" required placeholder="Re-enter password" minlength="6"
                    class="w-full px-5 py-4 bg-gray-50 rounded-2xl outline-none focus:ring-2 focus:ring-yellow-400 focus:bg-white transition-all text-blue-950 text-sm pr-12"/>
                  <button type="button" onclick="togglePass('confirm_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
              </div>
              <button type="submit" name="add_admin"
                class="w-full bg-yellow-400 hover:bg-yellow-500 text-blue-950 font-bold py-4 rounded-2xl transition-all transform hover:scale-[1.02] shadow-lg shadow-yellow-100 flex items-center justify-center gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Admin
              </button>
            </form>
          </div>
        </div>

        <!-- Existing Admins List -->
        <div>
          <h2 class="text-xl font-extrabold text-blue-950 mb-6">Existing Admins <span class="text-sm font-semibold text-gray-400">(<?= count($admins) ?>)</span></h2>
          <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <?php if (empty($admins)): ?>
              <div class="p-10 text-center text-gray-400 text-sm">No admins found.</div>
            <?php else: ?>
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-blue-950 text-white text-left">
                    <th class="px-6 py-4 font-semibold">#</th>
                    <th class="px-6 py-4 font-semibold">Username</th>
                    <th class="px-6 py-4 font-semibold">Created</th>
                    <th class="px-6 py-4 font-semibold text-center">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <?php foreach ($admins as $i => $admin): ?>
                  <tr class="hover:bg-blue-50/30 transition-colors">
                    <td class="px-6 py-4 text-gray-400 font-medium"><?= $i + 1 ?></td>
                    <td class="px-6 py-4 font-semibold text-blue-950 flex items-center gap-2">
                      <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs uppercase">
                        <?= substr($admin['username'], 0, 1) ?>
                      </div>
                      <?= htmlspecialchars($admin['username']) ?>
                      <?php if ($admin['username'] === $_SESSION['admin_username']): ?>
                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-semibold">You</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-gray-400 whitespace-nowrap"><?= date('d M Y', strtotime($admin['created_at'])) ?></td>
                    <td class="px-6 py-4 text-center">
                      <?php if ($admin['username'] === 'admin'): ?>
                        <span class="inline-flex items-center gap-1 text-gray-400 bg-gray-100 px-3 py-1.5 rounded-lg text-xs font-semibold cursor-not-allowed" title="This account is protected and cannot be deleted">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                          Protected
                        </span>
                      <?php elseif ($admin['username'] !== $_SESSION['admin_username']): ?>
                        <a href="dashboard.php?delete_admin=<?= $admin['id'] ?>&tab=admins"
                          onclick="return confirm('Delete admin \'<?= htmlspecialchars($admin['username']) ?>\'? They will lose access immediately.')"
                          class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Delete</a>
                      <?php else: ?>
                        <span class="text-gray-300 text-xs font-semibold px-4 py-1.5">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- ══════════ SUBSCRIPTIONS TAB ══════════ -->
    <div id="content-subscriptions" class="tab-content <?= $active_tab === 'subscriptions' ? 'active' : '' ?>">

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-extrabold text-blue-950">Newsletter Subscriptions
          <span class="ml-2 text-sm font-semibold text-gray-400">(<?= $total_subs ?> total)</span>
        </h2>
        <?php if ($total_subs > 0): ?>
        <form method="POST" onsubmit="return confirm('Delete ALL subscribers? This cannot be undone.')">
          <button type="submit" name="delete_all_subs" class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-600 hover:text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-all">
            Clear All
          </button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (empty($subscriptions)): ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-20 text-center">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <h3 class="text-lg font-bold text-blue-950 mb-2">No subscribers yet</h3>
          <p class="text-gray-400 text-sm">Email subscriptions from the website footer will appear here.</p>
        </div>
      <?php else: ?>
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-blue-950 text-white text-left">
                  <th class="px-6 py-4 font-semibold">#</th>
                  <th class="px-6 py-4 font-semibold">Email Address</th>
                  <th class="px-6 py-4 font-semibold">Subscribed On</th>
                  <th class="px-6 py-4 font-semibold text-center">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <?php foreach ($subscriptions as $i => $sub): ?>
                <tr class="hover:bg-blue-50/30 transition-colors">
                  <td class="px-6 py-4 text-gray-400 font-medium"><?= $i + 1 ?></td>
                  <td class="px-6 py-4 font-semibold text-blue-950">
                    <a href="mailto:<?= htmlspecialchars($sub['email']) ?>" class="hover:text-blue-600 transition-colors flex items-center gap-2">
                      <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                        <?= strtoupper(substr($sub['email'], 0, 1)) ?>
                      </div>
                      <?= htmlspecialchars($sub['email']) ?>
                    </a>
                  </td>
                  <td class="px-6 py-4 text-gray-400 whitespace-nowrap"><?= date('d M Y, h:i A', strtotime($sub['subscribed_at'])) ?></td>
                  <td class="px-6 py-4 text-center">
                    <a href="dashboard.php?delete_sub=<?= $sub['id'] ?>&tab=subscriptions"
                      onclick="return confirm('Remove this subscriber?')"
                      class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition-all">Delete</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <script>
    function switchTab(tab) {
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.classList.add('text-gray-500');
      });
      document.getElementById('content-' + tab).classList.add('active');
      const btn = document.getElementById('tab-' + tab);
      btn.classList.add('active');
      btn.classList.remove('text-gray-500');
    }

    function togglePass(fieldId, btn) {
      const input = document.getElementById(fieldId);
      if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
      } else {
        input.type = 'password';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      }
    }
  </script>
</body>
</html>