<?php
require 'config.php'; // Připojení k databázi

$tableName = 'Uzivatele'; // Název tabulky
$schemaName = 'dbo'; // Schéma tabulky (např. 'dbo')

try {
    // Dotaz pro získání informací o sloupcích
    $query = "SELECT COLUMN_NAME 
              FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_NAME = :tableName AND TABLE_SCHEMA = :schemaName";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':tableName', $tableName);
    $stmt->bindValue(':schemaName', $schemaName);
    $stmt->execute();

    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Zobrazení sloupců
    if ($columns) {
        echo "<h3>Seznam sloupců tabulky $schemaName.$tableName:</h3>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>" . htmlspecialchars($column) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Žádné sloupce nebyly nalezeny nebo tabulka neexistuje.</p>";
    }
} catch (PDOException $e) {
    echo "Chyba při načítání sloupců: " . $e->getMessage();
}
?>