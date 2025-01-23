<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $conn->prepare('SELECT * FROM Uzivatele WHERE username = :username');
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username']; 
                $_SESSION['department'] = $user['Oddeleni']; 
                header('Location: index.php');
                exit;
            } else {
                $error = "Nesprávné uživatelské jméno nebo heslo.";
            }
        } catch (PDOException $e) {
            // Zobrazit podrobnìjší chybovou zprávu pro diagnostiku
            $error = "Chyba pøi pøihlašování: " . $e->getMessage();
        }
    } else {
        $error = "Vyplòte prosím všechny pole.";
    }
}
?>


<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pøihlášení</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.10.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
        
        body {
            background: linear-gradient(135deg, 
                #f6b729 0%, 
                #f6b729 23%, 
                #b57b49 23%, 
                #b57b49 47%, 
                #81c3d7 47%, 
                #81c3d7 71%, 
                #7cb227 71%, 
                #7cb227 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #ffffffbd;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .login-title {
            margin-bottom: 20px;
        }
        .login-btn {
            background-color: #009688;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-btn:hover {
            background-color: #303f9f;
        }
        .login-btn .icon-text {
            display: flex;
            align-items: center;
        }
        .login-btn .icon-text .icon {
            margin-right: 8px;
            font-size: 1.2rem;
        }
        .form-outline {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="text-center login-title">Pøihlášení</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST" id="loginForm">
            <div class="form-outline">
                <input type="text" id="username" name="username" class="form-control" required>
                <label class="form-label" for="username">Uživatelské jméno</label>
            </div>
            <div class="form-outline">
                <input type="password" id="password" name="password" class="form-control" required>
                <label class="form-label" for="password">Heslo</label>
            </div>
            <button type="submit" class="btn btn-primary btn-block login-btn">
                <div class="icon-text">
                    <i class="fas fa-sign-in-alt icon"></i>
                    <span>Pøihlásit se</span>
                </div>
            </button>
        </form>
    </div>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.10.2/mdb.min.js"></script>
    <script type="text/javascript">
        document.addEventListener('keydown', function(event) {
            if (event.key === '1' || event.keyCode === 49 || event.keyCode === 97 || event.code === 'Numpad1') {
                event.preventDefault(); 
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
