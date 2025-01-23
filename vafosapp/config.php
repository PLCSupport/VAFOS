<?php
$serverName = "SRV-VAFOS\VAFOS";
$database = "VAFOS";
$usernameDB = "barsys"; // Přejmenováno na usernameDB, aby nedošlo ke konfliktu
$password = "AdminBarsys";

try {
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database", $usernameDB, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Chyba při připojování k databázi: " . $e->getMessage());
}
?>