<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// --- LOGOUT HANDLING ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- ADMIN MANAGEMENT HANDLING ---
$ADMINS_FILE = 'admins.json';
$adminsData = [];
if (file_exists($ADMINS_FILE)) {
    $adminsData = json_decode(file_get_contents($ADMINS_FILE), true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin']) && isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    $newUsername = trim($_POST['new_admin_username']);
    $newPassword = $_POST['new_admin_password'];
    $newRole = $_POST['new_admin_role'];
    
    // Check if username exists
    $exists = false;
    foreach($adminsData as $a) {
        if($a['username'] === $newUsername) $exists = true;
    }
    
    if(!$exists) {
        $adminsData[] = [
            'username' => $newUsername,
            'password' => $newPassword,
            'role' => $newRole
        ];
        file_put_contents($ADMINS_FILE, json_encode($adminsData, JSON_PRETTY_PRINT));
        header("Location: admin_dashboard.php?view=admin");
        exit;
    } else {
        header("Location: admin_dashboard.php?view=admin");
        exit;
    }
}

if (isset($_GET['delete_admin']) && isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    $del_index = (int)$_GET['delete_admin'];
    if (isset($adminsData[$del_index])) {
        // Prevent deleting oneself
        if($adminsData[$del_index]['username'] !== $_SESSION['username']) {
            unset($adminsData[$del_index]);
            $adminsData = array_values($adminsData);
            file_put_contents($ADMINS_FILE, json_encode($adminsData, JSON_PRETTY_PRINT));
            header("Location: admin_dashboard.php?view=admin");
            exit;
        } else {
            header("Location: admin_dashboard.php?view=admin");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profil']) && isset($_SESSION['username'])) {
    $newUsername = trim($_POST['edit_username']);
    $newPassword = $_POST['edit_password'];
    $currentUsername = $_SESSION['username'];
    
    // Check if new username is already taken by someone else
    $exists = false;
    if ($newUsername !== $currentUsername) {
        foreach($adminsData as $a) {
            if($a['username'] === $newUsername) $exists = true;
        }
    }
    
    if (!$exists) {
        foreach($adminsData as &$a) {
            if($a['username'] === $currentUsername) {
                $a['username'] = $newUsername;
                if(!empty($newPassword)) {
                    $a['password'] = $newPassword;
                }
                break;
            }
        }
        file_put_contents($ADMINS_FILE, json_encode($adminsData, JSON_PRETTY_PRINT));
        $_SESSION['username'] = $newUsername; // Update session
        header("Location: admin_dashboard.php?msg=Profil berhasil diperbarui");
        exit;
    } else {
        header("Location: admin_dashboard.php?msg=Username sudah dipakai orang lain");
        exit;
    }
}


$JSON_FILE = 'data_warga.json';
$KRITERIA_FILE = 'kriteria.json';
$msg = '';
$msg_type = '';

// Load Kriteria
$kriteriaData = [];
if (file_exists($KRITERIA_FILE)) {
    $kriteriaData = json_decode(file_get_contents($KRITERIA_FILE), true) ?: [];
} else {
    // Default fallback
    $kriteriaData = [
        ['kode' => 'C1', 'nama' => 'Penghasilan Bulanan', 'bobot' => 40, 'tipe' => 'COST'],
        ['kode' => 'C2', 'nama' => 'Jumlah Tanggungan', 'bobot' => 40, 'tipe' => 'BENEFIT'],
        ['kode' => 'C3', 'nama' => 'Status Pekerjaan', 'bobot' => 20, 'tipe' => 'BENEFIT']
    ];
    file_put_contents($KRITERIA_FILE, json_encode($kriteriaData, JSON_PRETTY_PRINT));
}

// Read all Warga Data
function readJSON($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function writeJSON($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

$allData = readJSON($JSON_FILE);

// Handle Delete Warga
if (isset($_GET['delete'])) {
    $del_index = (int)$_GET['delete'];
    if (isset($allData[$del_index])) {
        unset($allData[$del_index]);
        $allData = array_values($allData);
        writeJSON($JSON_FILE, $allData);
        header("Location: admin_dashboard.php?view=data&msg=deleted");
        exit;
    }
}

// Handle Add / Edit Warga
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_data'])) {
    $nokk = trim($_POST['nokk']);
    $nama = trim($_POST['nama']);
    $edit_index = $_POST['edit_index'];
    
    $oldScores = [];
    if ($edit_index !== '') {
        $idx = (int)$edit_index;
        if (isset($allData[$idx]['scores'])) {
            $oldScores = $allData[$idx]['scores'];
        }
    }
    
    $scores = [];
    $raw_data = [];
    foreach ($kriteriaData as $k) {
        $kode = $k['kode'];
        $opt = isset($_POST['raw_'.$kode]) ? trim($_POST['raw_'.$kode]) : '';
        $raw_data[$kode] = $opt;
        
        $auto_score = 1;
        $nama_k = strtolower($k['nama']);
        
        if ($opt !== '') {
            if (strpos($nama_k, 'penghasilan') !== false || strpos($nama_k, 'gaji') !== false) {
                if ($opt == '< Rp 500.000') $auto_score = 5;
                elseif ($opt == 'Rp 500.000 - Rp 1.000.000') $auto_score = 4;
                elseif ($opt == 'Rp 1.000.000 - Rp 2.000.000') $auto_score = 3;
                elseif ($opt == 'Rp 2.000.000 - Rp 3.000.000') $auto_score = 2;
                elseif ($opt == '> Rp 3.000.000') $auto_score = 1;
            } elseif (strpos($nama_k, 'tanggungan') !== false || strpos($nama_k, 'anak') !== false) {
                if ($opt == '> 5 Orang') $auto_score = 5;
                elseif ($opt == '4 Orang') $auto_score = 4;
                elseif ($opt == '3 Orang') $auto_score = 3;
                elseif ($opt == '2 Orang') $auto_score = 2;
                elseif ($opt == '1 Orang') $auto_score = 1;
            } elseif (strpos($nama_k, 'dinding') !== false || strpos($nama_k, 'rumah') !== false) {
                if ($opt == 'Lainnya' || $opt == 'Bambu') $auto_score = 5;
                elseif ($opt == 'Kayu / Papan') $auto_score = 4;
                elseif ($opt == 'Semi Permanen') $auto_score = 3;
                elseif ($opt == 'Tembok Permanen') $auto_score = 1;
            } elseif (strpos($nama_k, 'listrik') !== false) {
                if ($opt == 'Tanpa Listrik') $auto_score = 5;
                elseif ($opt == '450 VA') $auto_score = 4;
                elseif ($opt == '900 VA') $auto_score = 3;
                elseif ($opt == '1300 VA') $auto_score = 2;
                elseif ($opt == '2200 VA') $auto_score = 1;
            } elseif (strpos($nama_k, 'pekerjaan') !== false) {
                if ($opt == 'Tidak Bekerja') $auto_score = 5;
                elseif ($opt == 'Buruh Harian') $auto_score = 4;
                elseif ($opt == 'Wiraswasta') $auto_score = 3;
                elseif ($opt == 'Karyawan Swasta') $auto_score = 2;
                elseif ($opt == 'PNS / TNI / Polri') $auto_score = 1;
            } else {
                if ($opt == 'Sangat Baik') $auto_score = 5;
                elseif ($opt == 'Baik') $auto_score = 4;
                elseif ($opt == 'Cukup') $auto_score = 3;
                elseif ($opt == 'Kurang') $auto_score = 2;
                elseif ($opt == 'Sangat Kurang') $auto_score = 1;
            }
        } else {
            $auto_score = isset($oldScores[$kode]) ? $oldScores[$kode] : 1;
        }
        
        $scores[$kode] = $auto_score;
    }
    
    $newRow = [
        'nokk' => $nokk,
        'nama' => $nama,
        'scores' => $scores,
        'raw_data' => $raw_data
    ];

    if ($edit_index !== '') {
        $idx = (int)$edit_index;
        if (isset($allData[$idx])) {
            $allData[$idx] = $newRow;
            writeJSON($JSON_FILE, $allData);
            header("Location: admin_dashboard.php?view=data&msg=updated");
            exit;
        }
    } else {
        $allData[] = $newRow;
        writeJSON($JSON_FILE, $allData);
        header("Location: admin_dashboard.php?view=data&msg=added");
        exit;
    }
}

// Handle Add Kriteria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_kriteria'])) {
    $newKode = trim($_POST['new_kode']);
    $newNama = trim($_POST['new_nama']);
    $newBobot = (float)$_POST['new_bobot'];
    $newTipe = trim($_POST['new_tipe']);
    
    $kriteriaData[] = [
        'kode' => $newKode,
        'nama' => $newNama,
        'bobot' => $newBobot,
        'tipe' => $newTipe
    ];
    file_put_contents($KRITERIA_FILE, json_encode($kriteriaData, JSON_PRETTY_PRINT));
    
    // Auto-add default score 1 for this new criteria to all existing Warga
    foreach ($allData as &$warga) {
        $warga['scores'][$newKode] = 1;
    }
    writeJSON($JSON_FILE, $allData);
    
    header("Location: admin_dashboard.php?view=criteria");
    exit;
}

// Handle Delete Kriteria
if (isset($_GET['delete_kriteria'])) {
    $del_index = (int)$_GET['delete_kriteria'];
    if (isset($kriteriaData[$del_index])) {
        $delKode = $kriteriaData[$del_index]['kode'];
        unset($kriteriaData[$del_index]);
        $kriteriaData = array_values($kriteriaData);
        file_put_contents($KRITERIA_FILE, json_encode($kriteriaData, JSON_PRETTY_PRINT));
        
        // Remove this criteria from all existing Warga
        foreach ($allData as &$warga) {
            unset($warga['scores'][$delKode]);
        }
        writeJSON($JSON_FILE, $allData);
        
        header("Location: admin_dashboard.php?view=criteria");
        exit;
    }
}

// Handle Save All Kriteria Weights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_kriteria'])) {
    foreach ($kriteriaData as &$k) {
        $kode = $k['kode'];
        if (isset($_POST['w_'.$kode])) {
            $k['bobot'] = (float)$_POST['w_'.$kode];
        }
    }
    file_put_contents($KRITERIA_FILE, json_encode($kriteriaData, JSON_PRETTY_PRINT));
    // Respond for ajax or redirect
    header("Location: admin_dashboard.php?view=criteria");
    exit;
}

// Handle Save Mass Assessment (Penilaian)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_penilaian'])) {
    if (isset($_POST['scores']) && is_array($_POST['scores'])) {
        foreach ($allData as $idx => &$warga) {
            if (isset($_POST['scores'][$idx]) && is_array($_POST['scores'][$idx])) {
                foreach ($_POST['scores'][$idx] as $kode => $val) {
                    $warga['scores'][$kode] = (int)$val;
                }
            }
        }
        writeJSON($JSON_FILE, $allData);
    }
    header("Location: admin_dashboard.php?view=penilaian&msg=updated_penilaian");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') { $msg = 'Warga berhasil ditambahkan!'; $msg_type = 'success'; }
    if ($_GET['msg'] === 'updated') { $msg = 'Data warga berhasil diubah!'; $msg_type = 'success'; }
    if ($_GET['msg'] === 'deleted') { $msg = 'Data warga berhasil dihapus!'; $msg_type = 'success'; }
    if ($_GET['msg'] === 'updated_penilaian') { $msg = 'Penilaian berhasil disimpan!'; $msg_type = 'success'; }
}

$initial_view = isset($_GET['view']) && in_array($_GET['view'], ['data', 'criteria', 'penilaian', 'admin']) ? $_GET['view'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Bansos Tepat Sasaran - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .alert { padding: 12px 20px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        #sys-msg {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideDown { from { top: -50px; opacity: 0; } to { top: 20px; opacity: 1; } }
        @keyframes fadeOutToast { to { opacity: 0; top: 10px; } }
        .btn-danger { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; padding: 8px 12px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .btn-danger:hover { background: #fee2e2; }
        .btn-edit { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; padding: 8px 12px; border-radius: 8px; cursor: pointer; transition: 0.2s; margin-right: 5px; }
        .btn-edit:hover { background: #e0f2fe; }
        .custom-dropdown-item:hover { background: #fef2f2; color: var(--primary); font-weight: 500; }
        .custom-dropdown-item:last-child { border-bottom: none; }
        
        .profile-dropdown {
            position: absolute;
            top: 60px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            width: 180px;
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--border-color);
            z-index: 1000;
        }
        .profile-dropdown a {
            padding: 12px 15px;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .profile-dropdown a:hover {
            background: #f8fafc;
            color: var(--primary);
        }
        .user-profile { position: relative; cursor: pointer; }
        
        #nav-admin { color: var(--primary); font-weight: 600; font-size: 16px; }
        #nav-admin i { color: var(--primary); font-size: 18px; }
        .nav-links li.active #nav-admin, .nav-links li.active #nav-admin i { color: white !important; }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand-logo">
            <div>
                <h2>SPK Bansos</h2>
                <p>Metode SAW</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-label">MAIN</div>
            <ul class="nav-links">
                <li class="<?= $initial_view == 'dashboard' ? 'active' : '' ?>"><a href="#" id="nav-dashboard"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li class="<?= $initial_view == 'data' ? 'active' : '' ?>"><a href="#" id="nav-data"><i class="fa-solid fa-users"></i> Data Warga</a></li>
                <li class="<?= $initial_view == 'criteria' ? 'active' : '' ?>"><a href="#" id="nav-criteria"><i class="fa-solid fa-sliders"></i> Kriteria Bansos</a></li>
                <li class="<?= $initial_view == 'penilaian' ? 'active' : '' ?>"><a href="#" id="nav-penilaian"><i class="fa-solid fa-check-to-slot"></i> Penilaian Kelayakan</a></li>
                <li class="<?= $initial_view == 'hasil' ? 'active' : '' ?>"><a href="#" id="nav-hasil"><i class="fa-solid fa-clipboard-check"></i> Daftar Penerima Bansos</a></li>
                <li class="<?= $initial_view == 'panduan' ? 'active' : '' ?>"><a href="#" id="nav-panduan"><i class="fa-solid fa-book"></i> Panduan Sistem</a></li>
            </ul>
        </div>
        
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
        <div class="sidebar-footer" style="padding:15px; padding-top:0;">
            <ul class="nav-links">
                <li class="<?= $initial_view == 'admin' ? 'active' : '' ?>">
                    <a href="#" id="nav-admin">
                        <i class="fa-solid fa-users-cog"></i> Kelola Admin
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
        
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div>
                <button id="sidebar-toggle" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-dark); transition: color 0.3s; display:flex; align-items:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                </button>
            </div>
            
            <div class="topbar-right">
                <div class="user-profile" onclick="toggleProfileDropdown()">
                    <div class="avatar">A</div>
                    <span style="font-size: 14px; font-weight: 500; color: var(--text-dark);">Admin <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left:5px;"></i></span>
                    
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="#" onclick="event.preventDefault(); openEditProfilModal(); toggleProfileDropdown();"><i class="fa-solid fa-user-edit"></i> Edit Profil</a>
                        <a href="admin_dashboard.php?logout=true" style="color: #ef4444; border-top: 1px solid var(--border-color);"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="page-content">
            
            <?php if($msg): ?>
            <div class="alert alert-<?= $msg_type ?>" id="sys-msg">
                <i class="fa-solid <?= $msg_type == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i> 
                <span><?= $msg ?></span>
            </div>
            <script>
                setTimeout(() => {
                    const msgEl = document.getElementById('sys-msg');
                    if(msgEl) {
                        msgEl.style.animation = 'fadeOutToast 0.4s ease forwards';
                        setTimeout(() => msgEl.remove(), 400);
                    }
                }, 2500); // Hilang dalam 2.5 detik
            </script>
            <?php endif; ?>

            <!-- DASHBOARD VIEW -->
            <section id="view-dashboard" class="view-section <?= $initial_view == 'dashboard' ? 'active' : '' ?>">
                

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="stat-info">
                                <h3>Total Warga</h3>
                                <div class="stat-value" id="total-warga">0</div>
                            </div>
                        </div>
                        <div class="stat-card-bottom">
                            <span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 100%</span>
                            <span>Telah terdaftar dalam sistem</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="stat-info">
                                <h3>Kuota Penerima</h3>
                                <div style="display:flex; justify-content:center; align-items:center; gap:5px; margin-top:5px;">
                                    <input type="number" id="input-kuota" value="3" min="1" max="1000" class="form-control" style="width: 80px; padding: 5px; font-weight: bold; font-size: 14px; text-align: center; height: 34px;"> 
                                </div>
                            </div>
                        </div>
                        <div class="stat-card-bottom">
                            <span>Batas warga layak bansos</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="stat-info">
                                <h3>Nominal per Penerima</h3>
                                <div style="display:flex; justify-content:center; align-items:center; gap:5px; margin-top:5px;">
                                    <span style="font-weight: 800; font-size: 14px; color: var(--text-dark);">Rp</span>
                                    <input type="number" id="input-nominal" value="600000" min="0" step="50000" class="form-control" style="width: 110px; padding: 5px; font-weight: bold; font-size: 14px; text-align: center; height: 34px;"> 
                                </div>
                            </div>
                        </div>
                        <div class="stat-card-bottom">
                            <span>Dana bansos tiap keluarga</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div class="stat-info">
                                <h3>Anggaran Total</h3>
                                <div class="stat-value" id="total-anggaran" style="font-size:20px; color: var(--primary);">Rp 0</div>
                            </div>
                        </div>
                        <div class="stat-card-bottom">
                            <span style="font-size:11px; font-weight:600; color:var(--text-muted);"><i class="fa-solid fa-calculator"></i> Kuota × Nominal</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid mb-4">
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Grafik Distribusi Kelayakan</span>
                        </div>
                        <div style="position: relative; width: 100%; height: 300px;">
                            <canvas id="eligibilityChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Aktivitas Terbaru</span>
                            <span id="last-updated" style="font-size: 12px; color: var(--text-muted);"></span>
                        </div>
                        <div class="activity-list" id="activity-list"></div>
                    </div>
                </div>

                <div class="dashboard-grid mb-4">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="card-title">Daftar Prioritas Utama</span>
                            <a href="#" id="toggle-prioritas-btn" style="font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600;" onclick="event.preventDefault(); togglePrioritas();">Lihat Semua</a>
                        </div>
                        <div class="table-container">
                            <table id="top5-table" style="min-width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Nama Warga</th>
                                        <th>Nilai Kelayakan</th>
                                    </tr>
                                </thead>
                                <tbody id="top5-body"><tr><td colspan="2" style="text-align: center;">Memuat data...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><span class="card-title">Distribusi Nilai Akhir</span></div>
                        <div style="position: relative; width: 100%; height: 250px; display:flex; justify-content:center; align-items:center;">
                            <canvas id="distChart"></canvas>
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center;">
                                <div id="dist-total" style="font-size:24px; font-weight:bold; color:var(--text-dark);">0</div>
                                <div style="font-size:12px; color:var(--text-muted);">Total</div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            
            <!-- HASIL SELEKSI VIEW -->
            <section id="view-hasil" class="view-section <?= $initial_view == 'hasil' ? 'active' : '' ?>">
                <div class="card" style="min-height: 500px;">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="card-title">Daftar Kelayakan Penerima Bansos</span>
                            <p style="color:var(--text-muted); font-size:13px; font-weight:normal; margin-top:5px;">Ranking Penerima Bansos Terbaik berdasarkan Metode SAW</p>
                        </div>
                        <div class="btn-group" style="display:flex; gap:10px;">
                            <button id="btn-hitung-saw" class="btn-primary" style="background:var(--primary); padding: 10px 20px; font-weight:bold;"><i class="fa-solid fa-calculator"></i> Hitung SAW</button>
                            <button id="btn-detail-matriks" class="btn-primary" style="background:var(--primary); padding: 10px 20px; font-weight:bold; display:none;" onclick="openMatriksModal()"><i class="fa-solid fa-table"></i> Detail Matriks</button>
                            <button id="btn-print" class="btn-primary" style="background:var(--primary); padding: 10px 20px; font-weight:bold; display:none;"><i class="fa-solid fa-print"></i> Cetak</button>
                        </div>
                    </div>
                    
                    <!-- Placeholder State (Before Calculation) -->
                    <div id="hasil-placeholder" style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:300px; background:#f8fafc; border-radius:12px; border:2px dashed #cbd5e1; margin-bottom:20px;">
                        <h3 style="color:var(--text-dark); margin-bottom:5px;">Belum ada hasil</h3>
                        <p style="color:var(--text-muted); font-size:14px;">Klik "Hitung SAW" di pojok kanan atas untuk mendapatkan ranking kelayakan warga.</p>
                    </div>

                    <!-- Filter Options (Hidden before Calculation) -->
                    <div id="hasil-filters" style="display:none; background:#f1f5f9; padding:15px; border-radius:12px; margin-bottom:20px;">
                        <div style="display:flex; gap:10px; align-items:center;">
                            <div class="search-container" style="flex:1;">
                                <i class="fa-solid fa-search"></i>
                                <input type="text" id="filter-nama" placeholder="Cari nama / NIP warga...">
                            </div>
                            <div style="display:flex; gap:5px;">
                                <button id="btn-filter-all" class="btn-primary" style="background:var(--primary); padding: 8px 15px; font-size:13px;">Semua</button>
                                <button id="btn-filter-lolos" class="btn-primary" style="background:var(--primary); padding: 8px 15px; font-size:13px;">Lolos</button>
                                <button id="btn-filter-cadangan" class="btn-primary" style="background:var(--primary); padding: 8px 15px; font-size:13px;">Menengah</button>
                                <button id="btn-filter-mampu" class="btn-primary" style="background:var(--primary); padding: 8px 15px; font-size:13px;">Mampu</button>
                            </div>
                        </div>
                    </div>

                    <!-- Result Table (Hidden before Calculation) -->
                    <div id="hasil-table-container" class="table-container" style="display:none;">
                        <p style="font-size:13px; font-weight:bold; color:var(--text-dark); margin-bottom:10px;">Daftar Ranking</p>
                        <table id="ranking-table">
                            <thead>
                                <tr>
                                    <th>Nama Warga</th>
                                    <th>Alamat / RT RW</th>
                                    <th>Nilai Akhir SAW</th>
                                    <th>Status Kelayakan</th>
                                </tr>
                            </thead>
                            <tbody id="ranking-body"><tr><td colspan="4" style="text-align: center;">Memuat data...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </section>
            
            <!-- PANDUAN VIEW -->
            <section id="view-panduan" class="view-section <?= $initial_view == 'panduan' ? 'active' : '' ?>">
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px;">
                            <span class="card-title">Rumus Metode SAW</span>
                        </div>
                        <div style="font-size: 14px; color: var(--text-dark); line-height: 1.6;">
                            <p style="margin-bottom:20px; color:var(--text-muted);">SAW (Simple Additive Weighting) menghitung skor akhir tiap alternatif (warga) dengan menjumlahkan nilai ternormalisasi yang sudah dikalikan bobot kriteria.</p>
                            
                            <h4 style="margin-bottom:8px; font-weight:600; color:var(--text-dark);">Normalisasi</h4>
                            <p style="margin-bottom:12px; color:var(--text-muted); font-size:13px;">Untuk setiap alternatif i dan kriteria j, nilai awal r<sub>ij</sub> dinormalisasi menjadi r<sub>ij</sub>.</p>
                            
                            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                                <div style="flex: 1; background:#f8fafc; padding:12px 15px; border-radius:8px; border: 1px solid var(--border-color);">
                                    <h5 style="margin-bottom:8px; font-weight:600; color:var(--primary); font-size:13px;">Benefit</h5>
                                    <code style="color:var(--text-dark); background:none; padding:0; font-size:13px; font-family:monospace;">r<sub>ij</sub> = x<sub>ij</sub> / max(x<sub>ij</sub>)</code>
                                </div>
                                <div style="flex: 1; background:#f8fafc; padding:12px 15px; border-radius:8px; border: 1px solid var(--border-color);">
                                    <h5 style="margin-bottom:8px; font-weight:600; color:var(--primary); font-size:13px;">Cost</h5>
                                    <code style="color:var(--text-dark); background:none; padding:0; font-size:13px; font-family:monospace;">r<sub>ij</sub> = min(x<sub>ij</sub>) / x<sub>ij</sub></code>
                                </div>
                            </div>
                            
                            <h4 style="margin-bottom:8px; font-weight:600; color:var(--text-dark);">Nilai Preferensi</h4>
                            <div style="background:#f8fafc; padding:12px 15px; border-radius:8px; border: 1px solid var(--border-color); margin-bottom:12px;">
                                <code style="color:var(--text-dark); background:none; padding:0; font-size:13px; font-family:monospace;">V<sub>i</sub> = Σ (w<sub>j</sub> * r<sub>ij</sub>)</code>
                            </div>
                            <p style="color:var(--text-muted); font-size:13px; font-style: italic;">Alternatif dengan V<sub>i</sub> terbesar adalah alternatif terbaik.</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px;">
                            <span class="card-title">Cara Kerja Sistem</span>
                        </div>
                        <div style="font-size: 14px; color: var(--text-dark); line-height: 1.6;">
                            <ol style="padding-left: 20px; color:var(--text-muted); margin: 0;">
                                <li style="margin-bottom: 10px;">Admin login ke dashboard.</li>
                                <li style="margin-bottom: 10px;">Admin mengisi data Kriteria (Bobot, tipe benefit/cost).</li>
                                <li style="margin-bottom: 10px;">Admin mengisi identitas Data Warga.</li>
                                <li style="margin-bottom: 10px;">Admin input nilai setiap warga untuk setiap kriteria pada menu Penilaian.</li>
                                <li style="margin-bottom: 10px;">Sistem menghitung normalisasi dan nilai preferensi secara otomatis.</li>
                                <li style="margin-bottom: 10px;">Admin menekan tombol "Hitung SAW" di menu Hasil Seleksi.</li>
                                <li style="margin-bottom: 10px;">Sistem menyimpan hasil dan menampilkan ranking (terbaik di atas).</li>
                                <li>Admin dapat mencetak laporan hasil ranking.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section id="view-data" class="view-section <?= $initial_view == 'data' ? 'active' : '' ?>">
                
                <div class="card" style="min-height: 500px;">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div class="search-container">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="search-data" placeholder="Cari nama / RT / RW...">
                        </div>
                        <button class="btn-primary" onclick="openModal()" style="font-size:13px; padding:8px 15px;"><i class="fa-solid fa-plus"></i> Tambah</button>
                    </div>
                    
                    <div class="table-container">
                        <table id="data-table" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>Nama Warga</th>
                                    <th>RT / RW</th>
                                    <?php foreach($kriteriaData as $k): ?>
                                    <th style="text-align:center;"><?= htmlspecialchars($k['nama']) ?></th>
                                    <?php endforeach; ?>
                                    <th style="text-align:right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($allData) === 0): ?>
                                    <tr><td colspan="<?= 3 + count($kriteriaData) ?>" style="text-align:center;">Tidak ada data.</td></tr>
                                <?php endif; ?>
                                
                                <?php 
                                foreach($allData as $idx => $row): 
                                    $nama = htmlspecialchars($row['nama']);
                                ?>
                                <tr>
                                    <td><strong style="color: var(--text-dark);"><?= $nama ?></strong></td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?= htmlspecialchars($row['nokk']) ?></td>
                                    
                                    <?php foreach($kriteriaData as $k): ?>
                                    <td style="text-align:center;">
                                        <?php 
                                        if(isset($row['raw_data'][$k['kode']]) && trim($row['raw_data'][$k['kode']]) !== '') {
                                            echo htmlspecialchars($row['raw_data'][$k['kode']]);
                                        } else {
                                            echo '<span style="color:#cbd5e1;">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <?php endforeach; ?>
                                    
                                    <td style="text-align:right; white-space:nowrap;">
                                        <?php $raw_data_json = isset($row['raw_data']) ? json_encode($row['raw_data']) : '{}'; ?>
                                        <button class="btn-edit" onclick='editData(<?= $idx ?>, <?= json_encode(htmlspecialchars(addslashes($row['nokk']))) ?>, <?= json_encode(htmlspecialchars(addslashes($row['nama']))) ?>, <?= htmlspecialchars($raw_data_json, ENT_QUOTES, 'UTF-8') ?>)' title="Edit">
                                            <i class="fa-solid fa-pencil"></i>
                                        </button>
                                        <a href="javascript:void(0);" onclick="showDeleteModal('admin_dashboard.php?delete=<?= $idx ?>', 'data <?= htmlspecialchars(addslashes($row['nama'])) ?>')" class="btn-danger" style="text-decoration:none;" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            
            <!-- PENILAIAN VIEW -->
            <section id="view-penilaian" class="view-section <?= $initial_view == 'penilaian' ? 'active' : '' ?>">
                <div class="card" style="min-height: 500px;">
                    <form method="POST" action="admin_dashboard.php" id="form-penilaian">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="card-title">Penilaian Kelayakan Warga</span>
                            <p style="color:var(--text-muted); font-size:13px; font-weight:normal; margin-top:5px;">Isi nilai kriteria secara langsung untuk semua warga. Jangan lupa klik Simpan.</p>
                        </div>
                        <button type="submit" class="btn-primary" style="padding:10px 20px;"><i class="fa-solid fa-save"></i> Simpan Penilaian</button>
                    </div>
                    
                        <input type="hidden" name="save_penilaian" value="1">
                        <div class="table-container" style="overflow-x: auto;">
                            <table style="min-width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="vertical-align: middle;">Nama Warga</th>
                                        <?php foreach($kriteriaData as $k): ?>
                                        <th style="text-align:center; vertical-align: middle;">
                                            <div style="font-weight:600; color:#475569; margin-bottom:8px; font-size:13px;">
                                                <?= htmlspecialchars($k['kode']) ?> - <?= htmlspecialchars($k['nama']) ?>
                                            </div>
                                            <span style="font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; background: <?= strtolower($k['tipe']) == 'benefit' ? '#dcfce7' : '#fee2e2' ?>; color: <?= strtolower($k['tipe']) == 'benefit' ? '#166534' : '#991b1b' ?>; text-transform:uppercase; letter-spacing:0.5px;">
                                                <?= htmlspecialchars($k['tipe']) ?>
                                            </span>
                                        </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($allData) === 0): ?>
                                        <tr><td colspan="<?= 1 + count($kriteriaData) ?>" style="text-align:center;">Tidak ada data. Tambah warga dulu di menu Data Warga.</td></tr>
                                    <?php endif; ?>
                                    
                                    <?php foreach($allData as $idx => $row): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--text-dark); display:block;"><?= htmlspecialchars($row['nama']) ?></strong>
                                            <span style="font-size:11px; color:var(--text-muted);">RT / RW: <?= htmlspecialchars($row['nokk']) ?></span>
                                        </td>
                                        
                                        <?php foreach($kriteriaData as $k): 
                                            $currentVal = isset($row['scores'][$k['kode']]) ? $row['scores'][$k['kode']] : 1;
                                        ?>
                                        <td style="text-align:center; padding:15px 10px;">
                                            <div style="display:inline-block; text-align:left; min-width: 140px;">
                                                <select name="scores[<?= $idx ?>][<?= htmlspecialchars($k['kode']) ?>]" class="form-control" style="width:100%; padding:6px 10px; font-weight:500; color:var(--text-dark);">
                                                    <option value="5" <?= $currentVal == 5 ? 'selected' : '' ?>>Nilai 5 (Sangat Baik)</option>
                                                    <option value="4" <?= $currentVal == 4 ? 'selected' : '' ?>>Nilai 4 (Baik)</option>
                                                    <option value="3" <?= $currentVal == 3 ? 'selected' : '' ?>>Nilai 3 (Cukup)</option>
                                                    <option value="2" <?= $currentVal == 2 ? 'selected' : '' ?>>Nilai 2 (Kurang)</option>
                                                    <option value="1" <?= $currentVal == 1 ? 'selected' : '' ?>>Nilai 1 (Sangat Kurang)</option>
                                                </select>
                                                <?php 
                                                $raw_txt = (isset($row['raw_data'][$k['kode']]) && trim($row['raw_data'][$k['kode']]) !== '') ? $row['raw_data'][$k['kode']] : '-';
                                                ?>
                                                <div style="font-size:11px; color:var(--text-muted); margin-top:8px; line-height:1.4;">
                                                    Data asli: <br>
                                                    <strong style="color:var(--primary);"><?= htmlspecialchars($raw_txt) ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                            <button type="submit" class="btn-primary" style="padding:10px 20px;"><i class="fa-solid fa-save"></i> Simpan Penilaian</button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="view-criteria" class="view-section <?= $initial_view == 'criteria' ? 'active' : '' ?>">
                <div class="card mb-4" style="min-height: 500px;">
                    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 15px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span class="card-title">Pengaturan Kriteria</span>
                            <p style="color:var(--text-muted); font-size:13px; font-weight:normal; margin-top:5px;">Pastikan total bobot masuk akal (akan dinormalisasi saat perhitungan).</p>
                        </div>
                        <button class="btn-primary" onclick="openTambahKriteriaModal()" style="font-size:13px; padding:8px 15px;"><i class="fa-solid fa-plus"></i> Tambah</button>
                    </div>
                    
                    <form method="POST" action="admin_dashboard.php" id="form-save-kriteria">
                        <div class="table-container" style="border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid var(--border-color);">Kode</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid var(--border-color);">Nama Kriteria</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid var(--border-color);">Bobot (%)</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid var(--border-color);">Tipe</th>
                                        <th style="padding: 12px 20px; text-align: right; font-size: 13px; font-weight: 600; color: #64748b; border-bottom: 1px solid var(--border-color);">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($kriteriaData as $idx => $k): ?>
                                    <tr>
                                        <td style="padding: 12px 20px; border-bottom: 1px solid var(--border-color);"><strong><?= htmlspecialchars($k['kode']) ?></strong></td>
                                        <td style="padding: 12px 20px; border-bottom: 1px solid var(--border-color);"><?= htmlspecialchars($k['nama']) ?></td>
                                        <td style="padding: 12px 20px; border-bottom: 1px solid var(--border-color);">
                                            <span id="disp-w-<?= htmlspecialchars($k['kode']) ?>"><?= htmlspecialchars($k['bobot']) ?></span>%
                                            <input type="hidden" name="w_<?= htmlspecialchars($k['kode']) ?>" id="w-<?= htmlspecialchars($k['kode']) ?>" class="weight-input-val" value="<?= htmlspecialchars($k['bobot']) ?>">
                                        </td>
                                        <td style="padding: 12px 20px; border-bottom: 1px solid var(--border-color);">
                                            <?php if($k['tipe'] == 'COST'): ?>
                                                <span style="font-size:11px; font-weight:600; color:var(--danger); background:#fee2e2; padding:4px 10px; border-radius:20px;">COST</span>
                                            <?php else: ?>
                                                <span style="font-size:11px; font-weight:600; color:#059669; background:#d1fae5; padding:4px 10px; border-radius:20px;">BENEFIT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 20px; border-bottom: 1px solid var(--border-color); text-align:right;">
                                            <button type="button" class="btn-edit" style="padding: 6px 10px;" onclick="openKriteriaModal('<?= htmlspecialchars($k['kode']) ?>', '<?= htmlspecialchars($k['nama']) ?>', 'w-<?= htmlspecialchars($k['kode']) ?>')"><i class="fa-solid fa-pencil"></i></button>
                                            <a href="javascript:void(0);" onclick="showDeleteModal('admin_dashboard.php?delete_kriteria=<?= $idx ?>', 'kriteria <?= htmlspecialchars(addslashes($k['kode'])) ?>? (Seluruh kolom ini di data warga akan ikut terhapus)')" class="btn-danger" style="padding: 6px 10px; border-radius: 8px; text-decoration:none;"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <input type="hidden" name="save_all_kriteria" value="1">
                        <button type="submit" id="btn-submit-kriteria" style="display:none;"></button>
                    </form>

                    <div style="display: flex; align-items: center; justify-content: flex-end; padding: 15px 20px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px;">
                        <span style="font-size:14px; font-weight: 600; color:var(--text-dark); margin-right: 15px;">Keseluruhan Bobot Sistem:</span>
                        <div id="total-weight-display" class="total-weight-badge" style="padding: 6px 15px; font-size: 14px; margin: 0;">Total: 100%</div>
                        <span id="weight-warning" class="error-weight" style="padding: 6px 15px; border-radius: 20px; font-size:13px; margin-left: 10px; display:none;"><i class="fa-solid fa-triangle-exclamation"></i> Total harus 100%!</span>
                    </div>
                </div>
            </section>
            
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
            <!-- VIEW: ADMIN -->
            <section id="view-admin" class="view-section <?= $initial_view == 'admin' ? 'active' : '' ?>">
                <div class="header-action" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div>
                        <h2 class="section-title">Kelola Admin</h2>
                        <p class="section-subtitle">Kelola akun administrator yang dapat mengakses panel ini.</p>
                    </div>
                    <button class="btn-primary" onclick="openTambahAdminModal()"><i class="fa-solid fa-plus"></i> Tambah Admin</button>
                </div>
                
                <div class="card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>USERNAME</th>
                                    <th>ROLE</th>
                                    <th style="text-align:right;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($adminsData as $i => $a): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($a['username']) ?></strong> 
                                        <?= $a['username'] === $_SESSION['username'] ? '<span style="font-size:10px; background:#e0e7ff; color:#4f46e5; padding:2px 6px; border-radius:4px; margin-left:5px;">Anda</span>' : '' ?>
                                    </td>
                                    <td>
                                        <span style="font-size:11px; font-weight:600; padding:3px 8px; border-radius:4px; <?= $a['role']==='superadmin' ? 'background:#fef08a; color:#854d0e;' : 'background:#f1f5f9; color:#475569;' ?>">
                                            <?= strtoupper(htmlspecialchars($a['role'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if($a['username'] !== $_SESSION['username']): ?>
                                        <a href="javascript:void(0);" onclick="showDeleteModal('admin_dashboard.php?delete_admin=<?= $i ?>', 'admin ini')" style="color:#ef4444; font-size:13px; font-weight:500; text-decoration:none;">Hapus</a>
                                        <?php else: ?>
                                        <span style="font-size:12px; color:var(--text-light); font-style:italic;">Saat ini</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <div id="print-layout" style="display:none;"><div style="text-align:center; margin-bottom: 20px;"><h2>LAPORAN PENERIMA BANSOS</h2><p>Pemerintah Desa Sukamaju</p><hr></div><div id="print-table-wrapper"></div><div style="margin-top:50px; text-align:right;"><p id="print-date"></p><br><br><br><p><strong>Kepala Desa</strong></p></div></div>

    <!-- MODAL FORM DATA WARGA -->
    <div id="dataModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:500px; margin:20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <span class="card-title" id="form-title"><i class="fa-solid fa-user-plus"></i> Tambah Warga</span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeModal()"></i>
            </div>
            
            <form method="POST" action="admin_dashboard.php" style="max-height: 70vh; overflow-y: auto; padding-right:10px;">
                <input type="hidden" name="edit_index" id="edit_index" value="">
                
                <div class="mb-4" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">RT / RW</label>
                        <input type="text" name="nokk" id="form_nokk" required placeholder="Contoh: RT 01 / RW 02" class="form-control">
                    </div>
                    <div style="flex: 2;">
                        <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Nama Lengkap</label>
                        <input type="text" name="nama" id="form_nama" required placeholder="Contoh: Ahmad Subarjo" class="form-control">
                    </div>
                </div>
                
                <hr style="border-top:1px dashed var(--border-color); margin:15px 0;">
                <p style="font-size:13px; font-weight:bold; color:var(--text-dark); margin-bottom:15px;">Data Asli (Diisi oleh RT)</p>
                
                <?php foreach($kriteriaData as $k): 
                    $nama_k = strtolower($k['nama']);
                    $options = [];
                    if (strpos($nama_k, 'penghasilan') !== false || strpos($nama_k, 'gaji') !== false) {
                        $options = ['< Rp 500.000', 'Rp 500.000 - Rp 1.000.000', 'Rp 1.000.000 - Rp 2.000.000', 'Rp 2.000.000 - Rp 3.000.000', '> Rp 3.000.000'];
                    } elseif (strpos($nama_k, 'tanggungan') !== false || strpos($nama_k, 'anak') !== false) {
                        $options = ['1 Orang', '2 Orang', '3 Orang', '4 Orang', '> 5 Orang'];
                    } elseif (strpos($nama_k, 'dinding') !== false || strpos($nama_k, 'rumah') !== false) {
                        $options = ['Tembok Permanen', 'Semi Permanen', 'Kayu / Papan', 'Bambu', 'Lainnya'];
                    } elseif (strpos($nama_k, 'listrik') !== false) {
                        $options = ['450 VA', '900 VA', '1300 VA', '2200 VA', 'Tanpa Listrik'];
                    } elseif (strpos($nama_k, 'pekerjaan') !== false) {
                        $options = ['PNS / TNI / Polri', 'Karyawan Swasta', 'Wiraswasta', 'Buruh Harian', 'Tidak Bekerja'];
                    } else {
                        $options = ['Sangat Baik', 'Baik', 'Cukup', 'Kurang', 'Sangat Kurang'];
                    }
                ?>
                <div style="margin-bottom: 12px; position: relative;">
                    <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block; color:var(--text-muted);">
                        <?= htmlspecialchars($k['kode']) ?> - <?= htmlspecialchars($k['nama']) ?>
                    </label>
                    <div style="position: relative;">
                        <input type="text" name="raw_<?= $k['kode'] ?>" id="form_raw_<?= $k['kode'] ?>" class="form-control custom-select-input" placeholder="Pilih dari kategori atau ketik manual..." style="font-size:13px; padding:6px 10px; width:100%;" autocomplete="off" onclick="toggleDropdown('dropdown_<?= $k['kode'] ?>')">
                        <i class="fa-solid fa-chevron-down" style="position:absolute; right:10px; top:12px; color:var(--text-light); pointer-events:none; font-size:10px;"></i>
                        <div id="dropdown_<?= $k['kode'] ?>" class="custom-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid var(--border-color); border-radius:6px; box-shadow:var(--shadow-md); z-index:100; margin-top:4px; max-height:180px; overflow-y:auto;">
                            <?php foreach($options as $opt): ?>
                                <div class="custom-dropdown-item" onclick="selectOption('form_raw_<?= $k['kode'] ?>', '<?= htmlspecialchars(addslashes($opt)) ?>', 'dropdown_<?= $k['kode'] ?>')" style="padding:10px 12px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:var(--text-dark); transition:0.2s;">
                                    <?= htmlspecialchars($opt) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div style="display:flex; gap:10px; margin-top:20px; padding-top:15px; border-top:1px solid var(--border-color);">
                    <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; flex:1; justify-content:center;" onclick="closeModal()">Batal</button>
                    <button type="submit" name="save_data" class="btn-primary" style="flex:2; justify-content:center;"><i class="fa-solid fa-save"></i> Simpan Identitas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT KRITERIA -->
    <div id="kriteriaModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:400px; margin:20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <span class="card-title"><i class="fa-solid fa-sliders"></i> Edit Bobot <span id="kriteria-kode-title"></span></span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeKriteriaModal()"></i>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Nama Kriteria</label>
                <input type="text" id="kriteria-nama-display" class="form-control" disabled style="background:#f1f5f9;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Bobot Kriteria (%)</label>
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="number" id="kriteria-bobot-input" class="form-control" min="0" max="100" style="flex:1;">
                    <span style="font-weight:bold; color:var(--text-muted);">%</span>
                </div>
            </div>

            <input type="hidden" id="kriteria-target-id">

            <div style="display:flex; gap:10px; padding-top:15px; border-top:1px solid var(--border-color);">
                <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; flex:1; justify-content:center;" onclick="closeKriteriaModal()">Batal</button>
                <button type="button" class="btn-primary" style="flex:2; justify-content:center;" onclick="saveKriteriaModal()"><i class="fa-solid fa-save"></i> Terapkan</button>
            </div>
        </div>
    </div>
    <!-- MODAL TAMBAH KRITERIA -->
    <div id="tambahKriteriaModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:400px; margin:20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <span class="card-title"><i class="fa-solid fa-plus"></i> Tambah Kriteria Baru</span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeTambahKriteriaModal()"></i>
            </div>
            
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="add_kriteria" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Kode Kriteria</label>
                    <input type="text" name="new_kode" class="form-control" placeholder="Contoh: C6" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Nama Kriteria</label>
                    <input type="text" name="new_nama" class="form-control" placeholder="Contoh: Kondisi Kendaraan" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Tipe Kriteria</label>
                    <select name="new_tipe" class="form-control" required>
                        <option value="BENEFIT">BENEFIT (Semakin besar makin baik)</option>
                        <option value="COST">COST (Semakin kecil makin baik)</option>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Bobot Awal (%)</label>
                    <input type="number" name="new_bobot" class="form-control" value="0" min="0" max="100" required>
                </div>

                <div style="display:flex; gap:10px; padding-top:15px; border-top:1px solid var(--border-color);">
                    <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; flex:1; justify-content:center;" onclick="closeTambahKriteriaModal()">Batal</button>
                    <button type="submit" class="btn-primary" style="flex:2; justify-content:center;"><i class="fa-solid fa-save"></i> Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETAIL MATRIKS -->
    <div id="matriksModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
        <div class="card" style="width:100%; max-width:900px; margin:auto; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); max-height:90vh; display:flex; flex-direction:column;">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between; flex-shrink:0;">
                <span class="card-title"><i class="fa-solid fa-table"></i> Detail Matriks Perhitungan SAW</span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeMatriksModal()"></i>
            </div>
            
            <div style="overflow-y:auto; padding-right:10px;">
                <h4 style="margin-bottom:10px; color:var(--primary);">1. Matriks Keputusan (X)</h4>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:10px;">Merupakan data nilai asli (mentah) dari setiap alternatif pada masing-masing kriteria.</p>
                <div class="table-container" style="margin-bottom:20px;">
                    <table id="table-matriks-x">
                        <thead>
                            <tr id="thead-matriks-x">
                                <th>Nama Warga</th>
                                <?php foreach($kriteriaData as $k): ?>
                                <th><?= $k['kode'] ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="tbody-matriks-x"></tbody>
                    </table>
                </div>

                <h4 style="margin-bottom:10px; color:var(--primary);">2. Matriks Normalisasi (R)</h4>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:10px;">Hasil normalisasi berdasarkan sifat kriteria (Benefit = max, Cost = min).</p>
                <div class="table-container" style="margin-bottom:20px;">
                    <table id="table-matriks-r">
                        <thead>
                            <tr id="thead-matriks-r">
                                <th>Nama Warga</th>
                                <?php foreach($kriteriaData as $k): ?>
                                <th><?= $k['kode'] ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="tbody-matriks-r"></tbody>
                    </table>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; padding-top:15px; border-top:1px solid var(--border-color); flex-shrink:0;">
                <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; padding:10px 20px;" onclick="closeMatriksModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH ADMIN -->
    <div id="tambahAdminModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:500px; margin:20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <span class="card-title"><i class="fa-solid fa-user-plus"></i> Tambah Admin Baru</span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeTambahAdminModal()"></i>
            </div>
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="add_admin" value="1">
                
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Username</label>
                        <input type="text" name="new_admin_username" class="form-control" placeholder="Username" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Password</label>
                        <input type="password" name="new_admin_password" class="form-control" placeholder="Password" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Role</label>
                        <select name="new_admin_role" class="form-control" required>
                            <option value="admin">Admin Biasa</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; padding-top:15px; border-top:1px solid var(--border-color);">
                    <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; flex:1; justify-content:center;" onclick="closeTambahAdminModal()">Batal</button>
                    <button type="submit" class="btn-primary" style="flex:2; justify-content:center;"><i class="fa-solid fa-user-plus"></i> Tambah Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT PROFIL -->
    <div id="editProfilModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:400px; margin:20px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between;">
                <span class="card-title"><i class="fa-solid fa-user-edit"></i> Edit Profil Saya</span>
                <i class="fa-solid fa-xmark" style="cursor:pointer; color:var(--text-light); font-size:18px;" onclick="closeEditProfilModal()"></i>
            </div>
            
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="edit_profil" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Username Baru</label>
                    <input type="text" name="edit_username" class="form-control" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="font-size:13px; font-weight:600; margin-bottom:5px; display:block;">Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" name="edit_password" class="form-control" placeholder="Tulis password baru...">
                </div>

                <div style="display:flex; gap:10px; padding-top:15px; border-top:1px solid var(--border-color);">
                    <button type="button" class="btn-primary" style="background:#f1f5f9; color:#475569; box-shadow:none; flex:1; justify-content:center;" onclick="closeEditProfilModal()">Batal</button>
                    <button type="submit" class="btn-primary" style="flex:2; justify-content:center;"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js?v=<?= time() ?>"></script>
    <script>
        // Set initial view properly based on PHP redirect
        if ('<?= $initial_view ?>' === 'data') {
            switchView(document.getElementById('view-data'), document.getElementById('nav-data'));
        } else if ('<?= $initial_view ?>' === 'criteria') {
            switchView(document.getElementById('view-criteria'), document.getElementById('nav-criteria'));
        } else if ('<?= $initial_view ?>' === 'penilaian') {
            switchView(document.getElementById('view-penilaian'), document.getElementById('nav-penilaian'));
        } else if ('<?= $initial_view ?>' === 'hasil') {
            switchView(document.getElementById('view-hasil'), document.getElementById('nav-hasil'));
        } else if ('<?= $initial_view ?>' === 'panduan') {
            switchView(document.getElementById('view-panduan'), document.getElementById('nav-panduan'));
        } else if ('<?= $initial_view ?>' === 'admin') {
            switchView(document.getElementById('view-admin'), document.getElementById('nav-admin'));
        }

        // Clean the URL so refreshing goes back to dashboard
        if (window.history.replaceState) {
            window.history.replaceState({}, document.title, "admin_dashboard.php");
        }

        // Modal & Form logic
        function openModal() {
            resetForm();
            document.getElementById('dataModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('dataModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function editData(idx, nokk, nama, raw_data) {
            document.getElementById('form-title').innerHTML = '<i class="fa-solid fa-user-pen"></i> Edit Identitas Warga';
            document.getElementById('edit_index').value = idx;
            document.getElementById('form_nokk').value = nokk;
            document.getElementById('form_nama').value = nama;
            
            // Populate raw_data
            <?php foreach($kriteriaData as $k): ?>
            if (raw_data && raw_data['<?= $k['kode'] ?>']) {
                document.getElementById('form_raw_<?= $k['kode'] ?>').value = raw_data['<?= $k['kode'] ?>'];
            } else {
                document.getElementById('form_raw_<?= $k['kode'] ?>').value = '';
            }
            <?php endforeach; ?>
            
            document.getElementById('dataModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function resetForm() {
            document.getElementById('form-title').innerHTML = '<i class="fa-solid fa-user-plus"></i> Tambah Warga';
            document.getElementById('edit_index').value = '';
            document.getElementById('form_nokk').value = '';
            document.getElementById('form_nama').value = '';
            
            // Reset raw_data
            <?php foreach($kriteriaData as $k): ?>
            document.getElementById('form_raw_<?= $k['kode'] ?>').value = '';
            <?php endforeach; ?>
        }

        function toggleDropdown(id) {
            document.querySelectorAll('.custom-dropdown').forEach(el => {
                if(el.id !== id) el.style.display = 'none';
            });
            const el = document.getElementById(id);
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
        
        function selectOption(inputId, value, dropdownId) {
            document.getElementById(inputId).value = value;
            document.getElementById(dropdownId).style.display = 'none';
        }
        
        document.addEventListener('click', function(e) {
            if(!e.target.matches('.custom-select-input') && !e.target.closest('.custom-dropdown')) {
                document.querySelectorAll('.custom-dropdown').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });

        // Simple local search for the table
        document.getElementById('search-data').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#data-table tbody tr');
            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
        
        // Kriteria Modal logic
        function openTambahKriteriaModal() {
            document.getElementById('tambahKriteriaModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeTambahKriteriaModal() {
            document.getElementById('tambahKriteriaModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openKriteriaModal(kode, nama, targetId) {
            document.getElementById('kriteria-kode-title').textContent = kode;
            document.getElementById('kriteria-nama-display').value = nama;
            document.getElementById('kriteria-target-id').value = targetId;
            
            let currentVal = document.getElementById(targetId).value;
            document.getElementById('kriteria-bobot-input').value = currentVal;
            
            document.getElementById('kriteriaModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeKriteriaModal() {
            document.getElementById('kriteriaModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function saveKriteriaModal() {
            let targetId = document.getElementById('kriteria-target-id').value;
            let newVal = document.getElementById('kriteria-bobot-input').value;
            
            document.getElementById(targetId).value = newVal;
            document.getElementById('disp-' + targetId).textContent = newVal;
            
            // Submitting the background form to update backend json
            document.getElementById('btn-submit-kriteria').click();
            
            closeKriteriaModal();
        }

        function openMatriksModal() {
            document.getElementById('matriksModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeMatriksModal() {
            document.getElementById('matriksModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openTambahAdminModal() {
            document.getElementById('tambahAdminModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeTambahAdminModal() {
            document.getElementById('tambahAdminModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openEditProfilModal() {
            document.getElementById('editProfilModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeEditProfilModal() {
            document.getElementById('editProfilModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        
        function toggleProfileDropdown() {
            const dd = document.getElementById('profileDropdown');
            dd.style.display = dd.style.display === 'flex' ? 'none' : 'flex';
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profile = document.querySelector('.user-profile');
            const dropdown = document.getElementById('profileDropdown');
            
            if (profile && dropdown && !profile.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Delete Confirmation Modal
        let currentDeleteUrl = '';
        function showDeleteModal(url, itemName) {
            currentDeleteUrl = url;
            document.getElementById('delete-item-name').textContent = itemName;
            document.getElementById('customDeleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeDeleteModal() {
            document.getElementById('customDeleteModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        function confirmDelete() {
            if(currentDeleteUrl) {
                window.location.href = currentDeleteUrl;
            }
        }
    </script>

    <!-- Custom Delete Modal -->
    <div id="customDeleteModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); z-index:9999; justify-content:center; align-items:center;">
        <div class="card" style="width:100%; max-width:400px; margin:20px; text-align:center; padding: 30px 20px; border-radius: 16px; animation: modalFadeIn 0.3s ease;">
            <div style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 700; color: var(--text-dark); margin-bottom: 10px;">Konfirmasi Hapus</h3>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 25px; line-height: 1.5;">Yakin ingin menghapus <strong id="delete-item-name" style="color: var(--text-dark);"></strong>? Data yang dihapus tidak dapat dikembalikan.</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn-secondary" onclick="closeDeleteModal()" style="flex: 1; padding: 10px; font-weight: 600; border: 1px solid var(--border-color); background: white; color: var(--text-dark); border-radius: 8px; cursor: pointer;">Batal</button>
                <button type="button" class="btn-danger" onclick="confirmDelete()" style="flex: 1; padding: 10px; font-weight: 600; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer; transition: 0.2s;">Ya, Hapus!</button>
            </div>
        </div>
    </div>
    <style>
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .btn-danger:hover { background: #dc2626 !important; transform: translateY(-1px); }
        .btn-secondary:hover { background: #f8fafc !important; transform: translateY(-1px); }
    </style>
</body>
</html>
