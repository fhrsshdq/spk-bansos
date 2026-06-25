<?php
session_start();
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $admins = [];
    if(file_exists('admins.json')) {
        $admins = json_decode(file_get_contents('admins.json'), true) ?: [];
    }
    
    $loggedIn = false;
    foreach($admins as $admin) {
        if($admin['username'] === $username && $admin['password'] === $password) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = $admin['role'];
            $loggedIn = true;
            break;
        }
    }
    
    if($loggedIn) {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SPK Bansos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { display: flex; min-height: 100vh; margin: 0; }
        
        /* Split layout */
        .left-panel {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .right-panel {
            flex: 1;
            background-color: #cc0000;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .right-panel { display: none; }
            .left-panel { padding: 20px; }
            .login-box { padding: 20px; width: 100%; max-width: 100%; }
        }

        /* Left Panel Content */
        .login-box {
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .login-box h2 {
            font-size: 28px;
            color: #111827;
            margin-bottom: 5px;
        }
        .login-box p.subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .form-control:focus {
            border-color: #cc0000;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #cc0000;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 15px;
        }
        .btn-login:hover {
            background-color: #a30000;
        }
        
        .forgot-password {
            display: inline-block;
            color: #111827;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .alert {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Bottom Links */
        .bottom-links {
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
            text-align: center;
        }
        .bottom-links a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 13px;
            margin: 0 10px;
        }
        .bottom-links a:hover {
            text-decoration: underline;
        }

        /* Right Panel Content */
        .right-panel h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .right-panel h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .right-panel p {
            font-size: 14px;
            line-height: 1.6;
            max-width: 500px;
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <div class="left-panel">
        <div class="login-box">
            <h2>Login</h2>
            <p class="subtitle">SPK Bansos</p>
            
            <?php if($error): ?>
            <div class="alert"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <a href="#" class="forgot-password">Forgot Your Password?</a>
        </div>
        
    </div>
    
    <div class="right-panel">
        <h1>SPK Bansos</h1>
        <h4>Sistem Pendukung Keputusan Kelayakan Penerima Bantuan Sosial</h4>
        <p>Aplikasi ini mempermudah proses seleksi penerima bantuan sosial menggunakan metode Simple Additive Weighting (SAW). Kami membantu memastikan penyaluran bantuan tepat sasaran, objektif, dan transparan.</p>
    </div>

</body>
</html>
