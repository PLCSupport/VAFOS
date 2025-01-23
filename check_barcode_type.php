<?php
include 'config.php';  // Připojení k databázi
session_start();

if (isset($_POST['barcode'])) {
    $barcode = $_POST['barcode'];

    try {
        // Kontrola, zda je dbName nastaven v session
        if (!isset($_SESSION['dbName']) || empty($_SESSION['dbName'])) {
            throw new Exception("Chyba: Název databáze (dbName) není nastaven v session.");
        }

        $dbName = $_SESSION['dbName'];

        // Přidání diagnostického logu pro debugging
        error_log("Executing procedure in database: " . $dbName . " with barcode: " . $barcode);

        // Příprava a provedení dotazu
        $stmt = $conn->prepare("exec " . $dbName . ".dbo.CheckBarcodeType ?");
        $stmt->bindParam(1, $barcode, PDO::PARAM_STR);
        $stmt->execute();
    
        // Získání výsledku
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Diagnostický log pro kontrolu výsledku
        if ($result) {
            error_log("Fetched result: " . print_r($result, true));
        } else {
            error_log("No result found for barcode: " . $barcode);
        }

        // Kontrola výsledku
        if ($result && isset($result['BarcodeType'])) {
            error_log("Barcode type found: " . $result['BarcodeType']);  // Logování typu čárového kódu
            echo json_encode($result['BarcodeType']);
        } else {
            error_log("No BarcodeType returned or result is empty for barcode: " . $barcode);
            echo json_encode('no_result');
        }
    } catch (PDOException $e) {
        // Výstup chyby do logu
        error_log("SQL error: " . $e->getMessage());
        echo json_encode('SQL error: ' . $e->getMessage());
    } catch (Exception $e) {
        // Výstup chyby do logu
        error_log($e->getMessage());
        echo json_encode($e->getMessage());
    }
} else {
    // Pokud nebyl poskytnut čárový kód
    error_log("No barcode provided in POST request.");
    echo json_encode('no_barcode');
}
?>
