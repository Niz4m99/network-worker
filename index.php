<?php
session_start();

$host = 'localhost'; $db = 'db_network'; $user = 'root'; $pass = ''; $charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];

try { $pdo = new PDO($dsn, $user, $pass, $options); } catch (\PDOException $e) { die("DB Error"); }

$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('admin', 'teknisi') DEFAULT 'teknisi')");
$checkAdmin = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch();
if(!$checkAdmin) { $hash = password_hash('admin123', PASSWORD_DEFAULT); $pdo->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$hash', 'admin')"); }

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $stmt->execute([trim($_POST['username'])]); $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role'];
        header("Location: index.php"); exit;
    } else { $loginError = 'Username atau Password salah!'; }
}

if (!isset($_SESSION['user_id'])) {
?>
    <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login - J2H GROUP</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-900 flex items-center justify-center h-screen bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3N2Zz4=')]">
        <div class="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-sm border border-slate-100 transform transition-all hover:scale-105">
            <div class="text-center mb-8"><div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 text-3xl mb-4">📊</div><h1 class="text-2xl font-black text-slate-800">J2H <span class="text-blue-600">GROUP</span></h1><p class="text-xs text-slate-500 font-bold tracking-widest uppercase mt-1">Monitoring System</p></div>
            <?php if($loginError): ?><div class="bg-rose-50 text-rose-600 text-xs font-bold p-3 rounded-xl mb-4 text-center border border-rose-100"><?= $loginError ?></div><?php endif; ?>
            <form method="POST" class="space-y-5">
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1.5">Username</label><input type="text" name="username" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-blue-500 outline-none transition" required autofocus></div>
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1.5">Password</label><input type="password" name="password" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-blue-500 outline-none transition" required></div>
                <button type="submit" name="login" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl text-sm font-black tracking-wide shadow-lg shadow-blue-600/30 transition-all">MASUK</button>
            </form>
        </div>
    </body></html>
<?php exit; }

$isAdmin = ($_SESSION['role'] === 'admin');

if ($isAdmin) {
    if (isset($_POST['add_device'])) {
        $api_port = !empty($_POST['api_port']) ? intval($_POST['api_port']) : 8728;
        $stmt = $pdo->prepare("INSERT INTO devices (name, ip_address, parent_id, api_user, api_password, api_port) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([trim($_POST['name']), trim($_POST['ip_address']), !empty($_POST['parent_id']) ? $_POST['parent_id'] : null, $_POST['api_user'], $_POST['api_password'], $api_port]);
        header("Location: index.php?page=management"); exit;
    }
    if (isset($_POST['edit_device'])) {
        $api_port = !empty($_POST['api_port']) ? intval($_POST['api_port']) : 8728;
        if(!empty($_POST['api_password'])) {
            $stmt = $pdo->prepare("UPDATE devices SET name=?, ip_address=?, parent_id=?, api_user=?, api_password=?, api_port=? WHERE id=?");
            $stmt->execute([trim($_POST['name']), trim($_POST['ip_address']), !empty($_POST['parent_id']) ? $_POST['parent_id'] : null, $_POST['api_user'], $_POST['api_password'], $api_port, $_POST['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE devices SET name=?, ip_address=?, parent_id=?, api_user=?, api_port=? WHERE id=?");
            $stmt->execute([trim($_POST['name']), trim($_POST['ip_address']), !empty($_POST['parent_id']) ? $_POST['parent_id'] : null, $_POST['api_user'], $api_port, $_POST['id']]);
        }
        header("Location: index.php?page=management"); exit;
    }
    if (isset($_GET['delete'])) {
        $pdo->prepare("UPDATE devices SET parent_id = NULL WHERE parent_id = ?")->execute([$_GET['delete']]);
        $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$_GET['delete']]);
        header("Location: index.php?page=management"); exit;
    }
    if (isset($_POST['add_contact'])) {
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone_number']);
        if(strpos($phone, '0') === 0) $phone = '62' . substr($phone, 1);
        if(strpos($phone, '@c.us') === false) $phone .= '@c.us';
        $pdo->prepare("INSERT INTO wa_contacts (name, phone_number) VALUES (?, ?)")->execute([trim($_POST['name']), $phone]);
        header("Location: index.php?page=contacts"); exit;
    }
    if (isset($_GET['del_contact'])) { $pdo->prepare("DELETE FROM wa_contacts WHERE id = ?")->execute([$_GET['del_contact']]); header("Location: index.php?page=contacts"); exit; }
    
    if (isset($_POST['add_user'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")->execute([trim($_POST['username']), $hash, $_POST['role']]);
        header("Location: index.php?page=users"); exit;
    }
    if (isset($_GET['del_user'])) {
        if($_GET['del_user'] != $_SESSION['user_id']) { $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['del_user']]); }
        header("Location: index.php?page=users"); exit;
    }
}

if (isset($_GET['get_status'])) {
    $stmt = $pdo->query('SELECT id, name, ip_address, parent_id, status, last_checked, system_uptime, cpu_load, free_ram, pppoe_active, pppoe_list, api_user, api_port FROM devices');
    echo json_encode($stmt->fetchAll()); exit;
}

$page = $_GET['page'] ?? 'topology';
if (!$isAdmin && in_array($page, ['add', 'edit', 'contacts', 'users'])) { $page = 'topology'; }

$dropdownDevices = $pdo->query('SELECT id, name FROM devices ORDER BY name ASC')->fetchAll();
$editData = null; if ($page === 'edit' && isset($_GET['id'])) { $stmt = $pdo->prepare('SELECT * FROM devices WHERE id = ?'); $stmt->execute([$_GET['id']]); $editData = $stmt->fetch(); }
$contactsData = []; if ($page === 'contacts') { $contactsData = $pdo->query('SELECT * FROM wa_contacts ORDER BY id DESC')->fetchAll(); }
$usersData = []; if ($page === 'users') { $usersData = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll(); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>J2H GROUP - Monitoring System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>#network-map { width: 100%; height: calc(100vh - 4rem); background-color: #ffffff; background-image: linear-gradient(to right, #e2e8f0 1px, transparent 1px), linear-gradient(to bottom, #e2e8f0 1px, transparent 1px); background-size: 24px 24px; }</style>
</head>
<body class="bg-slate-50 font-sans antialiased h-screen flex overflow-hidden relative">

    <audio id="alertSound" loop><source src="alarm.mp3" type="audio/mpeg"></audio>
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-30 hidden md:hidden transition-opacity duration-300"></div>

    <div id="pppoeModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-100 rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[85vh] animate-[fadeIn_0.2s_ease-out]">
            <div class="p-5 bg-slate-900 text-white flex justify-between items-center shrink-0">
                <div><h3 id="modalTitle" class="font-bold text-lg tracking-wide">User PPPoE</h3><p class="text-[11px] text-slate-400 mt-0.5">Live Connection</p></div>
                <button onclick="closePppoeModal()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 flex items-center justify-center text-sm transition">✕</button>
            </div>
            <div class="p-4 bg-white border-b border-slate-200 shrink-0">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">🔍</span>
                    <input type="text" id="pppoeModalSearch" onkeyup="filterModalPppoeUsers()" placeholder="Cari nama client atau IP Address..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-indigo-500 outline-none transition">
                </div>
            </div>
            <div id="modalBody" class="p-4 overflow-y-auto space-y-3 text-sm text-slate-700 flex-1 custom-scrollbar"></div>
        </div>
    </div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900 text-slate-300 flex flex-col shadow-xl z-40 transform -translate-x-full md:translate-x-0 md:relative transition-transform duration-300">
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800 bg-slate-950 shrink-0">
            <div class="flex items-center gap-3"><svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 13h18M3 7h18M3 19h18"></path></svg><span class="text-xl font-black tracking-wider text-white">J2H <span class="text-blue-500">GROUP</span></span></div>
            <button id="close-sidebar-btn" class="md:hidden text-slate-400 text-xl">✕</button>
        </div>
        
        <div class="p-5 bg-slate-800/50 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full <?= $isAdmin ? 'bg-amber-500' : 'bg-blue-500' ?> text-white flex items-center justify-center font-bold text-lg"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <div class="flex-1 overflow-hidden">
                    <h4 class="text-sm font-bold text-white truncate"><?= htmlspecialchars($_SESSION['username']) ?></h4>
                    <span class="text-[10px] uppercase tracking-wider font-bold <?= $isAdmin ? 'text-amber-400' : 'text-blue-400' ?>"><?= $_SESSION['role'] ?></span>
                </div>
            </div>
        </div>

        <nav class="p-4 space-y-1 overflow-y-auto flex-1 custom-scrollbar">
            <div class="text-[10px] font-bold tracking-widest text-slate-500 uppercase px-4 py-2">Monitoring</div>
            <a href="?page=topology" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'topology' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 hover:text-white' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V16zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V16z"></path></svg> Topologi Visual</a>
            <a href="?page=management" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'management' || $page === 'edit' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 hover:text-white' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg> Device List</a>
            <a href="?page=pppoe" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'pppoe' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-900/40' : 'hover:bg-slate-800 hover:text-white text-indigo-400' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg> PPPoE Users</a>
            
            <?php if($isAdmin): ?>
            <div class="text-[10px] font-bold tracking-widest text-slate-500 uppercase px-4 py-2 mt-4">Administrator</div>
            <a href="?page=add" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'add' ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' ?>">➕ Tambah Device</a>
            <a href="?page=contacts" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'contacts' ? 'bg-emerald-600 text-white' : 'hover:bg-slate-800 hover:text-white text-emerald-400' ?>">💬 WhatsApp Notif</a>
            <a href="?page=users" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition <?= $page === 'users' ? 'bg-amber-600 text-white' : 'hover:bg-slate-800 hover:text-white text-amber-400' ?>">🔐 User Manager</a>
            <?php endif; ?>
        </nav>

        <div class="p-4 border-t border-slate-800 shrink-0">
            <a href="?logout=true" onclick="return confirm('Yakin ingin keluar?')" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-rose-600/10 text-rose-500 hover:bg-rose-600 hover:text-white font-bold text-sm transition">LOGOUT 🚪</a>
        </div>
    </aside>

    <nav class="md:hidden fixed bottom-0 w-full bg-white border-t border-slate-200 flex justify-around items-center h-16 z-50 px-1 shadow-lg">
        <a href="?page=topology" class="flex flex-col items-center gap-1 py-1.5 <?= $page === 'topology' ? 'text-blue-600' : 'text-slate-400' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V16zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V16z"></path></svg><span class="text-[9px] font-bold">Topologi</span></a>
        <a href="?page=management" class="flex flex-col items-center gap-1 py-1.5 <?= $page === 'management' || $page === 'edit' ? 'text-blue-600' : 'text-slate-400' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg><span class="text-[9px] font-bold">List</span></a>
        <a href="?page=pppoe" class="flex flex-col items-center gap-1 py-1.5 <?= $page === 'pppoe' ? 'text-indigo-600' : 'text-slate-400' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg><span class="text-[9px] font-bold">PPPoE</span></a>
        <?php if($isAdmin): ?>
        <a href="?page=add" class="flex flex-col items-center gap-1 py-1.5 <?= $page === 'add' ? 'text-slate-800' : 'text-slate-400' ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"></path></svg><span class="text-[9px] font-bold">Add</span></a>
        <?php endif; ?>
        <a href="?logout=true" onclick="return confirm('Keluar?')" class="flex flex-col items-center gap-1 py-1.5 text-rose-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg><span class="text-[9px] font-bold">Logout</span></a>
    </nav>

    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="h-16 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-4 md:px-8 text-white z-10 shadow-md">
            <div class="flex items-center gap-3">
                <button id="open-sidebar-btn" class="md:hidden p-1.5 rounded-lg bg-slate-800 text-slate-200"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg></button>
                <h2 class="text-sm md:text-lg font-bold tracking-wide uppercase text-slate-200">
                    <?= $page === 'topology' ? 'TOPOLOGI VISUAL' : ($page === 'management' ? 'DEVICE MANAGEMENT' : ($page === 'contacts' ? 'WA NOTIFIKASI' : ($page === 'pppoe' ? 'DAFTAR PPPOE AKTIF' : ($page === 'users' ? 'USER MANAGER' : 'FORM PERANGKAT')))) ?>
                </h2>
                <span id="downCounter" class="hidden bg-rose-600 text-white px-2 py-0.5 rounded-full text-[10px] md:text-xs font-black animate-pulse">0 DOWN</span>
            </div>
            <div class="flex items-center gap-2 md:gap-4">
                <button id="toggleAudioBtn" class="bg-slate-800 text-slate-300 px-2.5 py-1.5 md:px-4 md:py-2 rounded-xl text-[11px] md:text-xs font-bold border border-slate-700"><span id="audioIcon">🔕</span> <span id="audioText" class="hidden sm:inline">Alarm Mati</span></button>
                <div id="loading-indicator" class="hidden"><span class="flex h-2.5 w-2.5 relative"><span class="animate-ping absolute h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative rounded-full h-2.5 w-2.5 bg-emerald-500"></span></span></div>
            </div>
        </header>

        <main class="flex-1 relative overflow-auto pb-20 md:pb-0">
            
            <?php if ($page === 'topology'): ?>
                <div id="network-map"></div>
                <div class="absolute bottom-24 left-4 md:bottom-6 md:left-6 bg-slate-900/90 text-white px-3 py-2 rounded-xl text-[10px] md:text-xs font-bold flex gap-3 shadow-xl border border-slate-700 backdrop-blur-sm z-10">
                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#00ff66]"></span> Online</div>
                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Offline</div>
                </div>

            <?php elseif ($page === 'pppoe'): ?>
                <div class="p-4 md:p-8 max-w-7xl mx-auto">
                    <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 md:p-6 mb-6 flex flex-col md:flex-row md:items-center gap-4 shadow-sm justify-between">
                        <div class="flex items-start gap-4"><div class="text-3xl">👥</div><div><h4 class="font-bold text-indigo-800 text-sm md:text-base">Monitoring User PPPoE Real-Time</h4><p class="text-xs md:text-sm text-indigo-700 mt-1">Data ditarik otomatis dari seluruh router MikroTik yang terdaftar.</p></div></div>
                        <div class="w-full md:w-80 mt-2 md:mt-0 relative"><span class="absolute inset-y-0 left-0 flex items-center pl-4 text-indigo-400">🔍</span><input type="text" id="globalPppoeSearch" onkeyup="renderPppoeCards()" placeholder="Cari client atau IP..." class="w-full pl-10 pr-4 py-3 bg-white border border-indigo-200 rounded-xl text-sm font-medium focus:border-indigo-500 outline-none transition shadow-sm"></div>
                    </div>
                    <div id="pppoe-cards-container" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6"></div>
                </div>

            <?php elseif ($page === 'management'): ?>
                <div class="p-4 md:p-8 max-w-7xl mx-auto">
                    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                        <div class="p-4 md:p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <h3 class="font-bold text-slate-700 text-base md:text-lg">Daftar Router & Switch</h3>
                            <?php if($isAdmin): ?><a href="?page=add" class="hidden sm:inline-block bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-xl text-xs font-bold shadow-sm">+ Tambah Baru</a><?php endif; ?>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-100 text-slate-600 text-[11px] md:text-xs uppercase font-bold border-b">
                                    <tr><th class="p-3 md:p-4">Nama Alat</th><th class="p-3 md:p-4 hidden sm:table-cell">IP Address</th><th class="p-3 md:p-4 text-center">Status</th><th class="p-3 md:p-4 hidden md:table-cell">Metrik</th><th class="p-3 md:p-4 text-center">PPPoE</th><?php if($isAdmin): ?><th class="p-3 md:p-4 text-center">Aksi</th><?php endif; ?></tr>
                                </thead>
                                <tbody id="device-table-body" class="text-xs md:text-sm text-slate-700"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($isAdmin && ($page === 'add' || $page === 'edit')): ?>
                <div class="p-4 md:p-8 max-w-2xl mx-auto">
                    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 md:p-8">
                        <h3 class="font-bold text-slate-800 text-lg mb-6 border-b border-slate-100 pb-4"><?= $page === 'edit' ? '📝 Edit Konfigurasi' : '🚀 Tambah Perangkat Baru' ?></h3>
                        <form method="POST" action="index.php" class="space-y-5">
                            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div><label class="block text-xs font-bold text-slate-500 mb-1">Nama Perangkat</label><input type="text" name="name" value="<?= $editData['name'] ?? '' ?>" placeholder="e.g. CORE-ROUTER" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" required></div>
                                <div><label class="block text-xs font-bold text-slate-500 mb-1">IP & Port (SNMP)</label><input type="text" name="ip_address" value="<?= $editData['ip_address'] ?? '' ?>" placeholder="192.168.1.1:161" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" required></div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Induk Topologi (Parent Node)</label>
                                <select name="parent_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50">
                                    <option value="">-- Kosong (Root Node) --</option>
                                    <?php foreach ($dropdownDevices as $dd): if ($editData && $editData['id'] == $dd['id']) continue; ?>
                                        <option value="<?= $dd['id'] ?>" <?= ($editData && $editData['parent_id'] == $dd['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dd['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="p-5 bg-indigo-50 border border-indigo-100 rounded-2xl mt-4">
                                <h4 class="font-bold text-indigo-800 text-sm mb-3">Opsi API MikroTik (Untuk Data PPPoE)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div><label class="block text-[10px] font-bold text-indigo-500 mb-1">Port API</label><input type="number" name="api_port" value="<?= $editData['api_port'] ?? '8728' ?>" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white" required></div>
                                    <div><label class="block text-[10px] font-bold text-indigo-500 mb-1">User API</label><input type="text" name="api_user" value="<?= $editData['api_user'] ?? '' ?>" placeholder="Kosong = Bukan Mikrotik" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white"></div>
                                    <div><label class="block text-[10px] font-bold text-indigo-500 mb-1">Pass API</label><input type="password" name="api_password" placeholder="<?= $page === 'edit' ? '(Isi jika ingin diubah)' : '' ?>" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white"></div>
                                </div>
                            </div>
                            <div class="pt-4 flex gap-3"><button type="submit" name="<?= $page === 'edit' ? 'edit_device' : 'add_device' ?>" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl text-sm font-bold shadow-md transition">Simpan Perangkat</button></div>
                        </form>
                    </div>
                </div>

            <?php elseif ($isAdmin && $page === 'contacts'): ?>
                <div class="p-4 md:p-8 max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 self-start">
                        <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3">📞 Tambah Penerima WA</h3>
                        <form method="POST" class="space-y-3">
                            <input type="text" name="name" placeholder="Nama / Divisi" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" required>
                            <input type="text" name="phone_number" placeholder="Contoh: 08123456789" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" required>
                            <button type="submit" name="add_contact" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl text-sm font-bold shadow-md transition mt-2">Daftarkan Nomor</button>
                        </form>
                    </div>
                    <div class="md:col-span-2 bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
                        <div class="p-5 border-b border-slate-100 bg-slate-50"><h3 class="font-bold text-slate-700">Daftar Teknisi Tujuan Notifikasi</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left"><thead class="bg-slate-100 text-xs text-slate-500 uppercase"><tr><th class="p-4">Nama</th><th class="p-4">Format Bot (C.US)</th><th class="p-4 text-center">Aksi</th></tr></thead>
                                <tbody class="text-sm">
                                    <?php if(empty($contactsData)): ?><tr><td colspan="3" class="p-8 text-center text-slate-400">Belum ada nomor yang didaftarkan.</td></tr><?php endif; ?>
                                    <?php foreach($contactsData as $ct): ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($ct['name']) ?></td><td class="p-4 text-emerald-600 font-mono text-xs"><?= $ct['phone_number'] ?></td>
                                        <td class="p-4 text-center"><a href="?del_contact=<?= $ct['id'] ?>" onclick="return confirm('Hapus nomor ini?')" class="bg-rose-100 hover:bg-rose-500 text-rose-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">Hapus</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($isAdmin && $page === 'users'): ?>
                <div class="p-4 md:p-8 max-w-5xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-3xl shadow-xl border border-slate-200 p-6 self-start">
                        <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3">👤 Buat Akun Baru</h3>
                        <form method="POST" action="index.php" class="space-y-3">
                            <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Username Login</label><input type="text" name="username" class="w-full px-4 py-2 border rounded-xl text-sm" required></div>
                            <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Password Baru</label><input type="password" name="password" class="w-full px-4 py-2 border rounded-xl text-sm" required></div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 mb-1">Role (Hak Akses)</label>
                                <select name="role" class="w-full px-4 py-2 border rounded-xl text-sm bg-slate-50"><option value="teknisi">Teknisi (Melihat Saja)</option><option value="admin">Administrator (Penuh)</option></select>
                            </div>
                            <button type="submit" name="add_user" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-xl text-sm font-bold shadow-md transition mt-2">Buat Akun</button>
                        </form>
                    </div>
                    <div class="md:col-span-2 bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden">
                        <div class="p-5 border-b border-slate-100 bg-slate-50"><h3 class="font-bold text-slate-700">Daftar Akses Sistem</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left"><thead class="bg-slate-100 text-xs text-slate-500 uppercase"><tr><th class="p-4">Username</th><th class="p-4 text-center">Tipe Role</th><th class="p-4 text-center">Aksi</th></tr></thead>
                                <tbody class="text-sm">
                                    <?php foreach($usersData as $usr): ?>
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="p-4 font-bold text-slate-700"><?= htmlspecialchars($usr['username']) ?> <?php if($usr['id'] == $_SESSION['user_id']) echo '<span class="text-[9px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full ml-1">You</span>'; ?></td>
                                        <td class="p-4 text-center"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?= $usr['role'] == 'admin' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' ?>"><?= $usr['role'] ?></span></td>
                                        <td class="p-4 text-center">
                                            <?php if($usr['id'] != $_SESSION['user_id']): ?>
                                            <a href="?del_user=<?= $usr['id'] ?>" onclick="return confirm('Hapus akun ini?')" class="bg-rose-100 hover:bg-rose-500 text-rose-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition">Cabut Akses</a>
                                            <?php else: ?> <span class="text-xs text-slate-300">-</span> <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        window.allDevicesData = []; let currentModalUsers = [];

        function showPppoeModal(deviceName, encodedList) {
            document.getElementById('modalTitle').innerText = `User PPPoE - ${deviceName}`;
            document.getElementById('pppoeModalSearch').value = ''; 
            try {
                const listStr = decodeURIComponent(encodedList);
                if (!listStr || listStr.trim() === "" || listStr === "null") { currentModalUsers = []; } else { currentModalUsers = JSON.parse(listStr); }
            } catch(e) { currentModalUsers = []; }
            renderModalList(currentModalUsers); document.getElementById('pppoeModal').classList.remove('hidden');
        }

        function renderModalList(users) {
            const body = document.getElementById('modalBody'); body.innerHTML = '';
            if (users.length === 0) { body.innerHTML = '<div class="text-center text-slate-400 py-10 font-medium">Tidak ada data client ditemukan.</div>'; return; }
            
            users.forEach((u, index) => {
                body.innerHTML += `
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center bg-white p-4 rounded-2xl border border-slate-200 shadow-sm gap-2 hover:border-indigo-200 transition">
                        <div class="flex items-start gap-3">
                            <span class="text-slate-400 text-sm font-bold w-5 pt-0.5">${index + 1}.</span>
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-800 text-sm flex items-center gap-2 uppercase tracking-wide"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> ${u.name}</span>
                                <span class="text-xs text-blue-500 font-mono mt-1 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>${u.ip}</span>
                            </div>
                        </div>
                        <div class="flex flex-col sm:text-right gap-1.5 ml-8 sm:ml-0">
                            <span class="text-xs font-bold text-slate-700 flex items-center sm:justify-end gap-1.5">⏱️ ${u.uptime}</span>
                            <span class="text-[10px] text-slate-500 font-mono font-bold bg-slate-50 px-2.5 py-1 rounded border border-slate-100 flex items-center sm:justify-end gap-1.5">⇅ ${u.txrx}</span>
                        </div>
                    </div>`;
            });
        }

        function filterModalPppoeUsers() { const query = document.getElementById('pppoeModalSearch').value.toLowerCase(); const filtered = currentModalUsers.filter(u => u.name.toLowerCase().includes(query) || u.ip.toLowerCase().includes(query)); renderModalList(filtered); }
        function closePppoeModal() { document.getElementById('pppoeModal').classList.add('hidden'); }

        function renderPppoeCards() {
            const container = document.getElementById('pppoe-cards-container'); if (!container) return;
            const searchInput = document.getElementById('globalPppoeSearch'); const query = searchInput ? searchInput.value.toLowerCase() : '';
            let pppoeCardsHTML = ''; let totalMatchCards = 0;

            window.allDevicesData.forEach(device => {
                if (device.api_user && device.api_user !== '') {
                    let userTagsHtml = '<div class="text-xs text-slate-400 py-4 w-full text-center">Belum ada user online</div>';
                    let hasMatchingUser = false; 

                    if (device.pppoe_list && device.pppoe_list.trim() !== '' && device.pppoe_list !== 'null') {
                        try {
                            let usersArray = JSON.parse(device.pppoe_list);
                            if (query !== '') { usersArray = usersArray.filter(u => u.name.toLowerCase().includes(query) || u.ip.toLowerCase().includes(query)); }
                            if (usersArray.length > 0) {
                                hasMatchingUser = true;
                                userTagsHtml = usersArray.map(u => `
                                    <div class="bg-indigo-50/50 border border-indigo-100 p-3 rounded-xl w-full flex justify-between items-center mb-2 hover:bg-white transition shadow-sm">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-indigo-900 text-xs flex items-center gap-1.5 uppercase"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ${u.name}</span>
                                            <span class="text-[10px] text-indigo-500/80 font-mono mt-1 flex items-center gap-1">🌐 ${u.ip}</span>
                                        </div>
                                        <div class="flex flex-col text-right">
                                            <span class="text-[10px] font-bold text-indigo-700">⏱️ ${u.uptime}</span>
                                            <span class="text-[9px] text-indigo-500 font-mono font-bold bg-white px-1.5 py-0.5 rounded border border-indigo-100 mt-1 shadow-sm">⇅ ${u.txrx}</span>
                                        </div>
                                    </div>`).join('');
                            } else if (query !== '') { userTagsHtml = '<div class="text-xs text-rose-400 py-4 w-full text-center font-bold">Tidak ada kecocokan di router ini</div>'; }
                        } catch(e) {}
                    }

                    if (query !== '' && !hasMatchingUser) return; 

                    totalMatchCards++;
                    pppoeCardsHTML += `
                    <div class="bg-white rounded-3xl shadow-lg border border-slate-100 p-5 flex flex-col h-full animate-[fadeIn_0.3s_ease-out]">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-4">
                            <div><h3 class="font-bold text-slate-800 text-sm">${device.name}</h3><div class="text-[10px] text-slate-400 font-mono mt-0.5">${device.ip_address}</div></div>
                            <span class="bg-indigo-600 text-white text-[11px] font-black px-3 py-1.5 rounded-xl shadow-sm">${device.pppoe_active === '-' ? '0 User' : device.pppoe_active}</span>
                        </div>
                        <div class="flex flex-col overflow-y-auto max-h-72 pr-1 custom-scrollbar">${userTagsHtml}</div>
                    </div>`;
                }
            });

            if (totalMatchCards === 0) { container.innerHTML = `<div class="col-span-full text-center text-slate-500 py-12 bg-white rounded-3xl border border-slate-200 shadow-sm"><div class="text-4xl mb-3">📡</div><p class="font-bold text-sm">${query === '' ? 'Tidak ada data PPPoE aktif.' : 'Tidak ada hasil pencarian untuk "' + query + '"'}</p></div>`; } 
            else { container.innerHTML = pppoeCardsHTML; }
        }

        const sidebar = document.getElementById('sidebar'); const overlay = document.getElementById('sidebar-overlay');
        const openBtn = document.getElementById('open-sidebar-btn'); const closeBtn = document.getElementById('close-sidebar-btn');
        if (openBtn) openBtn.addEventListener('click', () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); });
        function closeMobileSidebar() { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); }
        if (closeBtn) closeBtn.addEventListener('click', closeMobileSidebar); if (overlay) overlay.addEventListener('click', closeMobileSidebar);

        const audioEl = document.getElementById('alertSound'); const toggleBtn = document.getElementById('toggleAudioBtn');
        const audioIcon = document.getElementById('audioIcon'); const audioText = document.getElementById('audioText');
        let isAudioAllowed = false; let isAlarmPlaying = false;
        toggleBtn.addEventListener('click', () => {
            isAudioAllowed = !isAudioAllowed;
            if (isAudioAllowed) { toggleBtn.classList.replace('bg-slate-800', 'bg-emerald-950'); toggleBtn.classList.replace('text-slate-300', 'text-emerald-400'); audioIcon.innerText = '🔔'; audioText.innerText = 'Alarm Active'; } 
            else { toggleBtn.classList.replace('bg-emerald-950', 'bg-slate-800'); toggleBtn.classList.replace('text-emerald-400', 'text-slate-300'); audioIcon.innerText = '🔕'; audioText.innerText = 'Alarm Muted'; if (isAlarmPlaying) { audioEl.pause(); audioEl.currentTime = 0; isAlarmPlaying = false; } }
        });

        const isTopologyPage = <?= $page === 'topology' ? 'true' : 'false' ?>;
        const isMobileDevice = window.innerWidth < 768; 
        let nodes, edges, network; let currentEdgesData = [];

        if (isTopologyPage) {
            nodes = new vis.DataSet(); edges = new vis.DataSet();
            network = new vis.Network(document.getElementById('network-map'), { nodes, edges }, {
                physics: { enabled: true, barnesHut: { gravitationalConstant: isMobileDevice ? -1800 : -3500, centralGravity: isMobileDevice ? 0.3 : 0.15, springLength: isMobileDevice ? 90 : 160 } },
                nodes: { shape: 'box', margin: isMobileDevice ? 6 : 10, font: { color: '#000000', size: isMobileDevice ? 10 : 12, face: 'Tahoma', bold: true }, shadow: { enabled: true, color: 'rgba(0,0,0,0.1)', size: 3 }, borderWidth: 1.5 },
                edges: { width: 1.5, color: { color: '#94a3b8' }, arrows: { to: { enabled: true, scaleFactor: 0.4, type: 'arrow' } }, smooth: false }
            });
        }

        async function refreshData() {
            document.getElementById('loading-indicator').classList.remove('hidden');
            try {
                const response = await fetch('index.php?get_status=1');
                window.allDevicesData = await response.json();
                const data = window.allDevicesData;
                
                let tableHTML = ''; let newEdges = []; let totalDown = 0; currentEdgesData = [];

                data.forEach((device) => {
                    const isUp = device.status === 'UP'; if (!isUp) totalDown++;
                    const cpuLabel = device.cpu_load && device.cpu_load !== '-' ? `\nCPU: ${device.cpu_load}` : '';
                    const uptimeLabel = device.system_uptime && device.system_uptime !== '-' ? `\nUptime: ${device.system_uptime}` : '';
                    const pppoeLabel = device.pppoe_active && device.pppoe_active !== '-' ? `\nPPPoE: ${device.pppoe_active}` : '';
                    const nodeBg = isUp ? '#00ff66' : '#f87171'; const nodeBorder = isUp ? '#00cc52' : '#dc2626';

                    if (isTopologyPage) {
                        nodes.update({ id: device.id, label: isMobileDevice ? `${device.name}${cpuLabel}${pppoeLabel}` : `${device.name}\nIP: ${device.ip_address}${cpuLabel}${uptimeLabel}${pppoeLabel}`, color: { background: nodeBg, border: nodeBorder } });
                        if (device.parent_id) { newEdges.push({ id: `edge-${device.parent_id}-${device.id}`, from: device.parent_id, to: device.id }); currentEdgesData.push({ from: device.parent_id, to: device.id, isUp: isUp }); }
                    }

                    if (document.getElementById('device-table-body')) {
                        let encodedList = encodeURIComponent(device.pppoe_list || '');
                        let pppoeHtml = device.pppoe_active && device.pppoe_active !== '-' ? `<button onclick="showPppoeModal('${device.name}', '${encodedList}')" class="bg-indigo-100 hover:bg-indigo-600 hover:text-white text-indigo-800 px-3 py-1.5 rounded-xl font-bold text-[11px] transition">👥 ${device.pppoe_active}</button>` : `<span class="text-slate-400 text-xs">-</span>`;
                        let actionHtml = isAdmin ? `<td class="p-3 md:p-4 text-center"><div class="flex justify-center gap-1"><a href="?page=edit&id=${device.id}" class="bg-amber-500 hover:bg-amber-600 text-white px-2 py-1.5 rounded-lg text-[10px] font-bold shadow-sm transition">Edit</a><a href="?delete=${device.id}" onclick="return confirm('Yakin hapus?')" class="bg-rose-500 hover:bg-rose-600 text-white px-2 py-1.5 rounded-lg text-[10px] font-bold shadow-sm transition">Hapus</a></div></td>` : '';

                        tableHTML += `<tr class="border-b hover:bg-slate-50 transition"><td class="p-3 md:p-4 font-bold text-slate-800">${device.name}<div class="sm:hidden font-mono text-[10px] text-slate-500 mt-1">${device.ip_address}</div></td><td class="p-3 md:p-4 font-mono text-xs text-slate-600 hidden sm:table-cell">${device.ip_address}</td><td class="p-3 md:p-4 text-center"><span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase ${isUp ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700'}">${device.status}</span></td><td class="p-3 md:p-4 text-[11px] md:text-xs hidden md:table-cell"><div class="text-slate-600">⏱️ ${device.system_uptime}</div><div class="text-amber-700 font-bold mt-0.5">⚙️ CPU: ${device.cpu_load}</div></td><td class="p-3 md:p-4 text-center">${pppoeHtml}</td>${actionHtml}</tr>`;
                    }
                });

                if (isTopologyPage) { edges.clear(); edges.add(newEdges); } else if (document.getElementById('device-table-body')) { document.getElementById('device-table-body').innerHTML = tableHTML; }
                
                renderPppoeCards();

                const counterEl = document.getElementById('downCounter');
                if (totalDown > 0) { counterEl.innerText = `${totalDown} DOWN`; counterEl.classList.remove('hidden'); if (isAudioAllowed && !isAlarmPlaying) { audioEl.play().catch(e => {}); isAlarmPlaying = true; } } 
                else { counterEl.classList.add('hidden'); if (isAlarmPlaying) { audioEl.pause(); audioEl.currentTime = 0; isAlarmPlaying = false; } }
            } catch (error) {} finally { setTimeout(() => document.getElementById('loading-indicator').classList.add('hidden'), 500); }
        }

        let animationProgress = 0;
        if (isTopologyPage) {
            network.on("afterDrawing", function (ctx) {
                currentEdgesData.forEach(edgeData => {
                    const positions = network.getPositions([edgeData.from, edgeData.to]);
                    const p1 = positions[edgeData.from]; const p2 = positions[edgeData.to];
                    if (p1 && p2) {
                        const x = p1.x + (p2.x - p1.x) * animationProgress; const y = p1.y + (p2.y - p1.y) * animationProgress;
                        ctx.beginPath(); ctx.arc(x, y, isMobileDevice ? 3.5 : 5, 0, 2 * Math.PI, false);
                        ctx.fillStyle = edgeData.isUp ? '#00cc52' : '#ef4444'; ctx.fill();
                        ctx.shadowColor = edgeData.isUp ? '#00ff66' : '#f87171'; ctx.shadowBlur = isMobileDevice ? 5 : 10; ctx.fill(); ctx.shadowBlur = 0;
                    }
                });
            });
            function animateTraffic() { animationProgress += isMobileDevice ? 0.008 : 0.005; if (animationProgress > 1) animationProgress = 0; network.redraw(); requestAnimationFrame(animateTraffic); }
            animateTraffic();
        }

        setInterval(refreshData, 5000); refreshData();
    </script>
    <style>.custom-scrollbar::-webkit-scrollbar{width:6px}.custom-scrollbar::-webkit-scrollbar-track{background:#f8fafc;border-radius:10px}.custom-scrollbar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}.custom-scrollbar::-webkit-scrollbar-thumb:hover{background:#94a3b8}</style>
</body>
</html>