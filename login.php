<?php
session_start();

// Nëse është tashmë i identifikuar, dërgoje direkt në dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard/indexadmin.php");
    exit();
}

require_once './database/db.php'; 

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT admin_id, password FROM admins WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['logged_in'] = true;

                header("Location: dashboard/indexadmin.php");
                exit;
            } else {
                $error = "Përdoruesi ose fjalëkalimi është gabim.";
            }
        } catch (\PDOException $e) {
            $error = "Gabim në databazë: " . $e->getMessage();
        }
    } else {
        $error = "Ju lutem plotësoni të gjitha fushat.";
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ChatBot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            background-color: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 0.6rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .form-control {
            border-radius: 6px;
            padding: 0.65rem 0.75rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15);
        }
        .brand-title {
            font-weight: 700;
            color: #212529;
            letter-spacing: -0.5px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <h2 class="brand-title mb-1">Mirëseerdhët</h2>
        <p class="text-muted small">Identifikohuni për të hyrë në ChatBot Admin Dashboard</p>
    </div>

    <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2 text-center small border-0 mb-3" role="alert">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Shkruani përdoruesin" required autocomplete="username">
        </div>
        
        <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Fjalëkalimi</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mb-3">Kyçuni</button>
        
        <div class="text-center mt-3">
            <p class="small text-muted mb-0">
                Nuk keni një llogari? <a href="register.php" class="text-decoration-none fw-medium">Regjistrohuni këtu</a>
            </p>
        </div>
    </form>
</div>

</body>
</html>