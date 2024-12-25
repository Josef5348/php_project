<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if (!empty($username) && !empty($_POST['password'])) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        if ($stmt->execute([$username, $password])) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="register_style.css">

    <title>Register</title>
</head>
<body>
<h1>Register</h1>
<form method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register</button>
</form>
<?php if (isset($error)) echo "<p>$error</p>"; ?>
<a href="login.php">Already have an account? Login</a>
</body>
</html>
