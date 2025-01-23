<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'config.php';

// Seznam dostupných databází
$databases = [
    "VAFOS_CICENICE", "VAFOS_EXT_CB", "VAFOS_EXT_DSV", "VAFOS_EXT_PRE", "VAFOS_EXT_RUD", 
    "VAFOS_EXT_ZDI", "VAFOS_CHELCICE", "VAFOS_CHOTOVINY", "VAFOS_CHRASTANY", "VAFOS_RATMIROV"
];

// Načtení databáze a hledaného výrazu z GET požadavku
$selectedDatabase = $_GET['database'] ?? $databases[0];
$searchTerm = $_GET['search'] ?? '';

// Aktualizace údajů při odeslání formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['database'], $_POST['rfid'], $_POST['username'], $_POST['Jmeno'], $_POST['password'])) {
    $dbName = $_POST['database'];
    $rfid = $_POST['rfid'];
    $username = $_POST['username'];
    $jmeno = $_POST['Jmeno'];
    $password = $_POST['password'];
    
    // Připojení k vybrané databázi a aktualizace hodnot
    $conn->query("USE " . $dbName);
    $stmt = $conn->prepare("UPDATE uzivatele SET Jmeno = :jmeno, password = :password, rfid = :rfid WHERE username = :username");
    $stmt->bindParam(':jmeno', $jmeno, PDO::PARAM_STR);
    $stmt->bindParam(':password', $password, PDO::PARAM_STR);
    $stmt->bindParam(':rfid', $rfid, PDO::PARAM_STR);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    
    // Zpráva o úspěchu
    echo "<div class='alert alert-success'>Údaje byly úspěšně aktualizovány.</div>";
}

// Funkce pro načtení dat z vybrané databáze s filtrováním
function fetchData($dbName, $conn, $searchTerm) {
    $conn->query("USE " . $dbName);
    $sql = "SELECT Jmeno, username, password, rfid FROM uzivatele";
    if ($searchTerm) {
        $sql .= " WHERE Jmeno LIKE ? OR username LIKE ?";
        $stmt = $conn->prepare($sql);
        $searchTerm = '%' . $searchTerm . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $conn->query($sql);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Načtení údajů pro zobrazení v tabulce
$data = fetchData($selectedDatabase, $conn, $searchTerm);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa RFID</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">
    <style>
        /* Vlastní styl pro tabulku */
        .custom-table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .custom-table thead {
            background-color: #343a40;
            color: #fff;
        }
        .custom-table thead th {
            padding: 12px;
        }
        .custom-table tbody tr {
            transition: background-color 0.3s ease;
        }
        .custom-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .custom-table td {
            padding: 10px;
            vertical-align: middle;
        }
        .custom-table td input {
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .btn-primary, .btn-success {
            border-radius: 20px;
        }
        .container {
            max-width: 1000px;
        }
    </style>
    <script>
        function enableEditing(rowId) {
            const inputs = document.querySelectorAll(`#row-${rowId} input`);
            inputs.forEach(input => input.removeAttribute('readonly'));

            const editButton = document.querySelector(`#edit-btn-${rowId}`);
            editButton.innerText = 'Uložit';
            editButton.classList.remove('btn-primary');
            editButton.classList.add('btn-success');
            editButton.setAttribute('onclick', `submitForm(${rowId})`);
        }

        function submitForm(rowId) {
            document.querySelector(`#form-${rowId}`).submit();
        }
    </script>
</head>
<body>
<div class="container my-5">
    <h2 class="text-center mb-4">Správa RFID <i class="bi bi-card-checklist"></i></h2>

    <!-- Výběr databáze a vyhledávání -->
    <form method="GET" class="form-row align-items-center mb-4">
        <div class="col-md-4">
            <label for="database" class="sr-only">Vyberte lokalitu:</label>
            <select name="database" id="database" class="form-control" onchange="this.form.submit()">
                <?php foreach ($databases as $db): ?>
                    <option value="<?= $db ?>" <?= $selectedDatabase === $db ? 'selected' : '' ?>><?= $db ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label for="search" class="sr-only">Vyhledat</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="🔍 Hledat uživatele..." value="<?= htmlspecialchars($searchTerm) ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary btn-block"><i class="bi bi-search"></i> Vyhledat</button>
        </div>
    </form>

    <!-- Zobrazení tabulky s daty -->
    <div class="table-responsive">
        <table class="table custom-table">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>RFID</th>
                    <th class="text-center">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                    <tr id="row-<?= $index ?>">
                        <form method="POST" id="form-<?= $index ?>">
                            <input type="hidden" name="database" value="<?= htmlspecialchars($selectedDatabase) ?>">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($row['username']) ?>">

                            <td><input type="text" name="Jmeno" value="<?= htmlspecialchars($row['Jmeno']) ?>" class="form-control" readonly></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><input type="text" name="password" value="<?= htmlspecialchars($row['password']) ?>" class="form-control" readonly></td>
                            <td><input type="text" name="rfid" value="<?= htmlspecialchars($row['rfid']) ?>" class="form-control" style="width: 200px;" readonly></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-primary btn-sm" id="edit-btn-<?= $index ?>" onclick="enableEditing(<?= $index ?>)">Upravit</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
