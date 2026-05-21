<?php
session_start();
include './database/db.php';

$fail = "";
$login = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_user = trim($_POST['email']); 
    $password = $_POST['password'];   
    
    try {
        $sql = "SELECT * FROM admins WHERE username = :username OR email = :email";
        $stmt = $pdo->prepare($sql);
    
        $stmt->execute([
            ':username' => $email_or_user,
            ':email'    => $email_or_user
        ]);
        
        $row = $stmt->fetch();
        
        if ($row) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['admin_id'] = $row['admin_id'];
                $_SESSION['admin_name'] = $row['username'];

                header("Location: ./dashboard/index.php");
                exit(); 
            } else {
                $fail = "Invalid password";
            }
        } else {
             $fail = "User not found";
        }
    } catch (\PDOException $e) {
        $fail = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>
    <form method="post" id="login-form">
        <h1>Login</h1>
        <?php if(!empty($fail)): ?>
            <p style="color: red; font-size: 14px; text-align: center;"><?php echo $fail; ?></p>
        <?php endif; ?>
        <input type="text" name="email" id="email" placeholder="Name or Email">
        <input type="password" name="password" id="password" placeholder="Password">
        <button id="submit">Login</button>
    </form>
</body>
</html>