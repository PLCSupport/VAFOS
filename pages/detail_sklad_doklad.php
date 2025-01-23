<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['dbName']) || empty($_SESSION['dbName'])) {
    die("Chyba: Název databáze (dbName) není nastaven v session.");
}

$dbName = $_SESSION['dbName'];

// Kontrola, zda je v URL nový čárový kód
if (isset($_GET['barcode']) && !empty($_GET['barcode'])) {
    // Uložení nového čárového kódu do session
    $_SESSION['barcode'] = htmlspecialchars($_GET['barcode']);
}

if (!isset($_SESSION['barcode'])) {
    // Pokud není čárový kód v session, přesměrujeme uživatele zpět na index
    header("Location: http://srv-vafos.vafo.local/vafosapp/index.php");
    exit();
}

$barcode = $_SESSION['barcode'];

// Připojení k databázi zahrnutím souboru config.php
include '../config.php';

// Přepnutí na konkrétní databázi
$conn->exec("USE " . $dbName);

// Získání údajů o příjemci na základě barcode
function getReceiptData($conn, $barcode) {
    $countSql = "SELECT 
                    COUNT(*) AS totalItems,
                    SUM(CASE WHEN StockTransactionProcessStatus = 1 THEN 1 ELSE 0 END) AS confirmedItems,
                    MAX(StockReceiptCode) AS StockReceiptCode
                 FROM StockTransactionView
                 WHERE StockReceiptCode = :barcode";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bindParam(':barcode', $barcode);
    $countStmt->execute();
    return $countStmt->fetch(PDO::FETCH_ASSOC);
}

$countResult = getReceiptData($conn, $barcode);

// Zpracování vstupního kódu (StockUnitCode nebo StockUnitSSCC)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['stockUnitCode']) || isset($_POST['stockUnitSSCC']))) {
    $stockUnitCode = isset($_POST['stockUnitCode']) ? $_POST['stockUnitCode'] : null;
    $stockUnitSSCC = isset($_POST['stockUnitSSCC']) ? $_POST['stockUnitSSCC'] : null;

    $sql = "SELECT StockTransactionProcessStatus, StockTransactionCode, StockUnitCode, StockUnitSSCC, CatalogueItemName, Muid, StockTransactionQuantity, SkladName, SkladoveMistoName, StockReceiptCode, StockTransactionDateView, CatalogueItemID, StockUnitProcessStatus
    FROM StockTransactionView 
    WHERE (StockUnitCode = :stockUnitCode OR StockUnitSSCC = :stockUnitSSCC)
    AND StockReceiptCode = :barcode
    ORDER BY StockTransactionCreateDateView DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':stockUnitCode', $stockUnitCode);
    $stmt->bindParam(':stockUnitSSCC', $stockUnitSSCC);
    $stmt->bindParam(':barcode', $barcode);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if ($result['StockTransactionProcessStatus'] == 0) {
            // Potvrzení (změna statusu na 1) pro konkrétní StockUnitCode nebo StockUnitSSCC
            $stockTransactionCode = $result['StockTransactionCode'];
            $execSql = "EXEC " . $dbName . ".dbo.stockTransactionSubmit :stockTransactionCode, 1";
            $execStmt = $conn->prepare($execSql);
            $execStmt->bindParam(':stockTransactionCode', $stockTransactionCode);
            $execStmt->execute();

            // Aktualizace údajů o potvrzených položkách
            $countResult = getReceiptData($conn, $barcode);

            echo json_encode([
                'status' => 'confirmed',
                'data' => $result,
                'confirmed' => $countResult['confirmedItems'],
                'total' => $countResult['totalItems']
            ]);
        } else {
            // V případě "Již bylo potvrzeno", přidáme do výsledku data
            echo json_encode([
                'status' => 'already_confirmed',
                'data' => $result
            ]);
        }
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VAFOS - Výsledek skenování</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="http://srv-vafos.vafo.local/vafosapp/css/themes/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="http://srv-vafos.vafo.local/vafosapp/css/styles.css">
  <link rel="stylesheet" href="http://srv-vafos.vafo.local/vafosapp/css/detail_sklad_doklad.css">
  
  
</head>
<body>
  
  <?php include '../../vafosapp/topbar.php'; ?>

  <div class="container">
    <hr style="border: none; border-top: 0px solid #ccc; margin: 10px 0;"> <!-- Vložený tenký řádek -->

    <!-- Vstupní pole a přepínač pro automatický režim -->
    <div class="input-group mb-3">
      <input type="text" class="form-control" id="barcodeInput" placeholder="Zadejte kód">
      <label class="switch">
        <input type="checkbox" id="modeSwitch" checked>
        <span class="slider"></span>
      </label>
    </div>

    <!-- Zobrazení StockReceiptCode a potvrzených/total položek pod vstupním polem -->
    <div id="barcodeResult" class="form-group">
      <div class="result-row">
          <div class="result-label">Doklad:</div>
          <div class="result-data doklad-data"><?php echo $countResult['StockReceiptCode']; ?></div>
      </div>
      <div class="result-row">
          <div class="result-label">Potvrzeno:</div>
          <div class="result-data centered"><?php echo $countResult['confirmedItems']; ?> / <?php echo $countResult['totalItems']; ?></div>
      </div>
    </div>

    <!-- Rámeček kolem výsledků od "SJ" po "Sklad" -->
    <div id="result" class="result-container"></div> <!-- Místo pro zobrazení výsledků potvrzeného StockUnitCode -->

    <!-- Místo pro alerty -->
    <div id="alerts" class="mt-3"></div> <!-- Zobrazení alertů pod formulářem -->

  </div>

  <script src="http://srv-vafos.vafo.local/vafosapp/js/jquery-3.5.1.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/popper.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/bootstrap.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/app.js"></script>
  <script>
    $(document).ready(function() {
        // Zaměření kurzoru na vstupní pole při načtení stránky
        $("#barcodeInput").focus();

        // Funkce pro zpracování StockUnitCode, StockReceiptCode nebo SkladoveMistoNazev
        $('#barcodeInput').on('input', function() {
            var inputCode = $(this).val();

            // Kontrola, zda uživatel zadává StockReceiptCode (barcode)
            if (inputCode === '<?php echo $barcode; ?>') {
                $('#barcodeInput').val('').focus(); // Vymaže vstupní pole a focus zpět
            } else if (inputCode.length > 0) {
                // Kontrola pro StockReceiptCode s prefixem PRI nebo VYD
                if (inputCode.startsWith("PRI") || inputCode.startsWith("VYD")) {
                    window.location.href = '?barcode=' + inputCode;
                    return; // Přerušení funkce, aby neproběhlo zpracování alertů
                } 
                // Kontrola pro SkladoveMistoNazev s prefixem A, B, C, D, E, R, O
                else if (/^[ABCDEOR]/.test(inputCode)) {
                    window.location.href = '?sklad=' + inputCode;
                    return; // Přerušení funkce, aby neproběhlo zpracování alertů
                } else {
                    // AJAX volání pro zpracování čárového kódu
                    $.ajax({
                        url: '', // Odesílá na stejný soubor
                        type: 'POST',
                        data: {
                            stockUnitCode: inputCode,
                            stockUnitSSCC: inputCode
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            $('#result').removeClass('result-success result-warning result-danger');

                            if (result.status === 'confirmed') {
                                const data = result.data;
                                $('#result').html(`
                                    <div class="result-row">
                                        <div class="result-label">SJ:</div>
                                        <div class="result-data">${data.StockUnitCode}</div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label sscc-label">SSCC:</div>
                                        <div class="result-data sscc-data">${data.StockUnitSSCC}</div>
                                    </div>
                                    <div class="item-row">
                                      <div class="item-data">
                                          <div class="result-label">Položka:</div>
                                          <div class="result-data">${data.CatalogueItemID}</div>
                                      </div>
                                      <div class="item-name">${data.CatalogueItemName}</div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label">Množství:</div>
                                        <div class="result-data">
                                            <span class="result-data-inline">${data.StockTransactionQuantity}</span>
                                            <span class="result-data-inline">${data.Muid}</span>
                                        </div>
                                    </div>
                                    <div class="result-row" id="statusRow">
                                        <div class="result-label">Stav:</div>
                                        <div class="result-data">
                                            <span class="result-data-inline status-text">
                                                ${data.StockUnitProcessStatus === 'P' ? 'P - Potvrzeno' :
                                                  data.StockUnitProcessStatus === 'R' ? 'R - Rezervace' :
                                                  data.StockUnitProcessStatus === 'N' ? 'N - Na cestě' :
                                                  'Neznámý stav'}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label sklad-label">Sklad:</div>
                                        <div class="result-data sklad-data">
                                            <span class="result-data-inline">${data.SkladoveMistoName}</span>
                                        </div>
                                    </div>
                                `);
                                
                                // Aktualizace confirmed / total po úspěšném potvrzení
                            $('#barcodeResult .centered').text(`${result.confirmed} / ${result.total}`);

                                // Přidání zeleného rámečku kolem výsledků od SJ po Sklad
                                $('#result').addClass('result-success');

                                // Zobrazení alertu potvrzení
                                $('#alerts').html(`
                                    <div class="d-flex justify-content-center align-items-center" style="margin-top: 19px; position: relative;">
                                        <i class="fas fa-check" style="font-size: 50px; color: #28a745; margin-right: 10px;"></i>
                                        <div class="alert alert-success" style="display: inline-block; font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;">
                                            ${data.StockUnitCode} POTVRZENA.
                                        </div>
                                    </div>
                                `);

                                // Aktualizace stavu přímo v DOMu
                                $('#statusRow .status-text').text('P - Potvrzeno'); // Změna stavu na "Potvrzeno"
                                console.log('Stav aktualizován na P - Potvrzeno');
                            } else if (result.status === 'already_confirmed') {
                                const data = result.data;
                                $('#alerts').html(`
                                    <div class="d-flex justify-content-center align-items-center" style="margin-top: 19px; position: relative;">
                                        <i class="fas fa-times" style="font-size: 50px; color: #f0ad4e; margin-right: 10px;"></i>
                                        <div class="alert alert-warning" style="display: inline-block; font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;">
                                            ${data.StockUnitCode} JIŽ POTVRZENÁ.
                                        </div>
                                    </div>
                                `);

                                // Zobrazení dat o potvrzené paletě
                                $('#result').html(`
                                    <div class="result-row">
                                        <div class="result-label">SJ:</div>
                                        <div class="result-data">${data.StockUnitCode}</div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label sscc-label">SSCC:</div>
                                        <div class="result-data sscc-data">${data.StockUnitSSCC}</div>
                                    </div>
                                    <div class="item-row">
                                      <div class="item-data">
                                          <div class="result-label">Položka:</div>
                                          <div class="result-data">${data.CatalogueItemID}</div>
                                      </div>
                                      <div class="item-name">${data.CatalogueItemName}</div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label">Množství:</div>
                                        <div class="result-data">
                                            <span class="result-data-inline">${data.StockTransactionQuantity}</span>
                                            <span class="result-data-inline">${data.Muid}</span>
                                        </div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label">Stav:</div>
                                        <div class="result-data">
                                            <span class="result-data-inline">
                                                ${data.StockUnitProcessStatus === 'P' ? 'P - Potvrzeno' :
                                                  data.StockUnitProcessStatus === 'R' ? 'R - Rezervace' :
                                                  data.StockUnitProcessStatus === 'N' ? 'N - Na cestě' :
                                                  'Neznámý stav'}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="result-row">
                                        <div class="result-label sklad-label">Sklad:</div>
                                        <div class="result-data sklad-data">
                                            <span class="result-data-inline">${data.SkladoveMistoName}</span>
                                        </div>
                                    </div>
                                `);

                                // Přidání žlutého rámečku kolem výsledků od SJ po Sklad
                                $('#result').addClass('result-warning');

                            } else if (result.status === 'not_found') {
                                $('#alerts').html(`
                                    <div class="d-flex justify-content-center align-items-center" style="margin-top: 19px; position: relative;">
                                        <i class="fas fa-times" style="font-size: 50px; color: #dc3545; margin-right: 10px;"></i>
                                        <div class="alert alert-danger" style="display: inline-block; font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;">
                                            ${inputCode} NENALEZENO
                                        </div>
                                    </div>
                                `);

                                // Vymazání formuláře při alertu "Paleta není na příjemce"
                                $('#result').html('');
                            }

                            // Vymaže vstupní pole a zaměří opět focus
                            $('#barcodeInput').val('').focus();
                        }
                    });
                }
            }
        });
    });
</script>
