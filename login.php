<?php
session_start();
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['rfid']) && !empty($_POST['rfid'])) {
        $rfid = $_POST['rfid'];
    
        try {
            // Kontrola uživatele v databázi VAFOS podle RFID
            $stmt = $conn->prepare('SELECT username, Kodzavodu FROM VAFOS.dbo.Uzivatele WHERE rfid = :rfid');
            $stmt->bindParam(':rfid', $rfid);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($user) {
                // Uživatel nalezen
                $username = $user['username'];
                $kodZavodu = $user['Kodzavodu'];
    
                // Přihlášení
                $_SESSION['username'] = $username;
                $_SESSION['department'] = $kodZavodu;
    
                // Získání dbName na základě Kodzavodu
                $stmt2 = $conn->prepare('SELECT dbName FROM VAFOS.dbo.Ciselnik_Zavod WHERE KodZavodu = :kodZavodu');
                $stmt2->bindParam(':kodZavodu', $kodZavodu);
                $stmt2->execute();
                $dbInfo = $stmt2->fetch(PDO::FETCH_ASSOC);
    
                if ($dbInfo && !empty($dbInfo['dbName'])) {
                    $_SESSION['dbName'] = $dbInfo['dbName'];
                } else {
                    $_SESSION['dbName'] = 'default_db'; // Výchozí hodnota, pokud není dbName nalezeno
                }
    
                echo json_encode(['success' => true]);
                exit;
            } else {
                // RFID nenalezeno v databázi
                $error = 'RFID nenalezeno.';
            }
        } catch (PDOException $e) {
            $error = 'Chyba při zpracování požadavku: ' . $e->getMessage();
        }
    
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    } else if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (!empty($username) && !empty($password)) {
            try {
                // Vyhledání databáze na základě priority
                $sanitizedUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $username); // Ošetření hodnoty

                $query = "SELECT TOP 1 dbName 
                          FROM (
                              SELECT TOP 1 1 AS LoginPriority, Z.dbName 
                              FROM VAFOS.dbo.Uzivatele U WITH (NOLOCK)
                              LEFT JOIN VAFOS.dbo.Ciselnik_Zavod Z WITH (NOLOCK) ON U.Kodzavodu = Z.KodZavodu
                              WHERE U.stav = 0 AND Z.Externi = 0 AND U.username = '$sanitizedUsername'
                              ORDER BY Z.Nazev
                              UNION ALL
                              SELECT TOP 1 2 AS LoginPriority, Z.dbName 
                              FROM VAFOS.dbo.Uzivatele U WITH (NOLOCK)
                              LEFT JOIN VAFOS.dbo.Ciselnik_Zavod Z WITH (NOLOCK) ON U.Kodzavodu = Z.KodZavodu
                              WHERE U.stav = 0 AND Z.Externi = 1 AND U.username = '$sanitizedUsername'
                              ORDER BY Z.Nazev
                          ) AS Combined
                          ORDER BY LoginPriority";

                $stmt = $conn->query($query);
                $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($dbInfo && !empty($dbInfo['dbName'])) {
                    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbInfo['dbName']); // Ošetření názvu databáze

                    // Uložení dbName do session
                    $_SESSION['dbName'] = $dbName;

                    // Kontrola uživatele v příslušné databázi
                    $stmt2 = $conn->prepare("SELECT username, password FROM " . $dbName . ".dbo.Uzivatele WHERE username = :username");
                    $stmt2->bindParam(':username', $username);
                    $stmt2->execute();
                    $user = $stmt2->fetch(PDO::FETCH_ASSOC);

                    if ($user && $password === $user['password']) {
                        // Přihlášení
                        $_SESSION['username'] = $user['username'];

                        // Kodzavodu je k dispozici pouze v hlavní databázi
                        $stmt3 = $conn->prepare('SELECT Kodzavodu FROM VAFOS.dbo.Uzivatele WHERE username = :username');
                        $stmt3->bindParam(':username', $username);
                        $stmt3->execute();
                        $userInfo = $stmt3->fetch(PDO::FETCH_ASSOC);

                        $_SESSION['department'] = $userInfo['Kodzavodu'] ?? 'Neznámý';

                        header('Location: index.php');
                        exit;
                    } else if ($user) {
                        // Nesprávné heslo
                        $error = 'Nesprávné uživatelské jméno nebo heslo.';
                    } else {
                        // Uživatel nenalezen
                        $error = 'Uživatel nenalezen.';
                    }
                } else {
                    $error = 'Databáze pro uživatele nebyla nalezena.';
                }
            } catch (PDOException $e) {
                $error = 'Chyba při přihlašování: ' . $e->getMessage();
            }
        } else {
            $error = 'Vyplňte prosím všechny pole.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení</title>
    <link href="css/alertify.min.css" rel="stylesheet">
    <link href="css/themes/default.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.10.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f6b729, #b57b49, #81c3d7, #7cb227);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }
        .login-container {
            width: 90%;
            max-width: 400px;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 14px rgba(0,0,0,0.1);
        }
        .login-title {
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 0.25rem;
            background: #fff;
            font-size: 16px;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0,123,255,0.5);
        }
        .form-control.no-cursor {
            caret-color: transparent;
        }
        .hidden {
            display: none;
        }
        .btn-primary {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: none;
            background-color: #00BCD3FF;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .alert {
            display: none;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #f44336;
            color: white;
            border-radius: 5px;
        }
        .alert.show {
            display: block;
        }

        /* Styl pro toggle přepínač */
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #81c2ce;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
            
        }

        /* Vycentrování a umístění popisků vedle přepínače */
        .switch-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .switch-label {
            margin: 0 10px;
            font-size: 16px;
            color: #333;
        }
    </style>
</head>
<body>
<div class="login-container">
        <h1 class="login-title">Přihlášení</h1>
        <div class="alert" id="errorAlert" style="display: none;"><?= $error ?></div>
        <form method="POST" action="">
            <div class="form-group">
                <div class="switch-container">
                    <span class="switch-label">Manuál</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="modeSwitch">
                        <span class="slider"></span>
                    </label>
                    <span class="switch-label">RFID</span>
                </div>
            </div>
            <div class="form-group hidden" id="rfidField">
                <label for="rfid">Číselný kód RFID</label>
                <input type="text" name="rfid" id="rfid" class="form-control no-cursor" placeholder="Načíst RFID">
            </div>
            <div class="form-group" id="manualField">
                <label for="username">Uživatelské jméno</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Uživatelské jméno">
            </div>
            <div class="form-group" id="manualFieldPassword">
                <label for="password">Heslo</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Heslo">
            </div>
            <button type="submit" id="submitButton" class="btn-primary">Přihlásit se</button>
        </form>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.10.2/mdb.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modeSwitch = document.getElementById('modeSwitch');
            var rfidField = document.getElementById('rfidField');
            var manualField = document.getElementById('manualField');
            var manualFieldPassword = document.getElementById('manualFieldPassword');
            var submitButton = document.getElementById('submitButton');
            var rfidInput = document.getElementById('rfid');
            var errorAlert = document.getElementById('errorAlert');

            // Výchozí nastavení
            manualField.classList.remove('hidden');
            manualFieldPassword.classList.remove('hidden');
            rfidField.classList.add('hidden');

            modeSwitch.addEventListener('change', function() {
                if (modeSwitch.checked) {
                    // Přepnutí na RFID
                    rfidField.classList.remove('hidden');
                    manualField.classList.add('hidden');
                    manualFieldPassword.classList.add('hidden');
                    submitButton.classList.add('hidden'); // Skrytí tlačítka
                    rfidInput.setAttribute('inputmode', 'none');
                    rfidInput.focus();

                    // Automatická kontrola a přihlášení
                    rfidInput.addEventListener('input', function() {
                        var rfid = this.value;
                        if (rfid) {
                            fetch('login.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: 'rfid=' + encodeURIComponent(rfid)
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    window.location.href = 'index.php'; // Přesměrování po úspěšném přihlášení
                                } else {
                                    showAlert(data.error);
                                    rfidInput.value = ''; // Vyčistit RFID pole po neúspěšném načtení
                                }
                            })
                            .catch(error => {
                                console.error('Chyba:', error);
                                rfidInput.value = ''; // Vyčistit RFID pole po chybě
                            });
                        }
                    });

                } else {
                    // Přepnutí na Manuální zadání
                    rfidField.classList.add('hidden');
                    manualField.classList.remove('hidden');
                    manualFieldPassword.classList.remove('hidden');
                    submitButton.classList.remove('hidden'); // Zobrazení tlačítka
                    rfidInput.removeAttribute('inputmode');
                }
            });

            function showAlert(message) {
                if (errorAlert) {
                    errorAlert.textContent = message;
                    errorAlert.style.display = 'block';
                    setTimeout(() => {
                        errorAlert.style.display = 'none';
                    }, 3000);
            }
        }
            // Zobrazení alertu při načtení stránky, pokud existuje chyba
            if (errorAlert.textContent) {
                showAlert(errorAlert.textContent);
            }
        });
    </script>
</body>
</html>
