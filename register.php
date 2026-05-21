<?php
session_start();
include '/database/db.php';

$fail = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $fail = "Passwords do not match!";
    } else {
        try {
            $check_sql = "SELECT * FROM admins WHERE username = :username OR email = :email";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([
                ':username' => $username,
                ':email'    => $email
            ]);
            if ($check_stmt->fetch()) {
                $fail = "Username or Email is already registered.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $insert_sql = "INSERT INTO admins (username, email, password) VALUES (:username, :email, :password)";
                $insert_stmt = $pdo->prepare($insert_sql);
                
                $result = $insert_stmt->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':password' => $hashed_password
                ]);
                
                if ($result) {
                    $success = "Registration successful! You can now log in.";
                } else {
                    $fail = "Registration failed. Please try again.";
                }
            }
        } catch (\PDOException $e) {
            $fail = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register</title>
    <link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>
    <form method="post" id="login-form">
        <h1>Register</h1>
        
        <!-- Error Alerts -->
        <?php if(!empty($fail)): ?>
            <p style="color: red; font-size: 14px; text-align: center;"><?php echo $fail; ?></p>
        <?php endif; ?>

        <!-- Success Alerts -->
        <?php if(!empty($success)): ?>
            <p style="color: green; font-size: 14px; text-align: center;"><?php echo $success; ?></p>
        <?php endif; ?>
        
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        
        <button id="submit" type="submit">Register</button>
        
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            Already have an account? <a href="login.php" style="color: #007bff; text-decoration: none;">Login here</a>
        </p>
    </form>
</body>
</html>