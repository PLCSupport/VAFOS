<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['barcode'])) {
    header("Location: http://srv-vafos.vafo.local/vafosapp/index.php");
    exit();
}

$barcode = htmlspecialchars($_GET['barcode']);
$username = $_SESSION['username'];

// Připojení k databázi zahrnutím souboru config.php
include '../config.php';

// Získání startovního času (měření latence serveru)
$startTime = microtime(true);

// SQL dotaz pro načtení dat na základě zadaného čárového kódu
$sql = "SELECT 
            StockUnitCode, StockUnitSSCC, StockUnitName, StockUnitTypeName, StockUnitExternalID, StockUnitStatus, 
            StockUnitStatusName, CatalogueItemID, CatalogueItemName, Quantity, MUID, 
            SkladName, SkladoveMistoName, StockUnitProcessStatus
        FROM 
            StockUnitDetailView
        WHERE 
            StockUnitCode = :barcode1 OR StockUnitSSCC = :barcode2";

// Příprava a provedení dotazu
$stmt = $conn->prepare($sql);
$stmt->bindParam(':barcode1', $barcode);
$stmt->bindParam(':barcode2', $barcode);
$stmt->execute();

// Načtení výsledků
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kontrola, zda byly nalezeny nějaké výsledky
if ($results && count($results) > 0) {
    // Získáme StockUnitCode a StockUnitSSCC, pokud existují
    $stockUnitCode = !empty($results[0]['StockUnitCode']) ? $results[0]['StockUnitCode'] : NULL;
    $stockUnitSSCC = !empty($results[0]['StockUnitSSCC']) ? $results[0]['StockUnitSSCC'] : NULL;
    
    // Pokud je zadané nové skladové místo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newLocation'])) {
        $newLocation = trim(htmlspecialchars($_POST['newLocation']));
        // Přijetí dat z klienta
        $signalStrength = isset($_POST['signalStrength']) ? $_POST['signalStrength'] : 'unknown';
        $latency = isset($_POST['latency']) ? $_POST['latency'] : 'unknown';
        $processTime = isset($_POST['processTime']) ? $_POST['processTime'] : 'unknown';

        // SQL dotaz pro ověření, zda skladové místo existuje
        $locationCheckSql = "SELECT id FROM VAFOS_EXT_ZDI.dbo.PrehledSklady WHERE LOWER(SkladoveMistoNazev) = LOWER(:newLocation)";
        $locationStmt = $conn->prepare($locationCheckSql);
        $locationStmt->bindParam(':newLocation', $newLocation);
        $locationStmt->execute();
        $locationResults = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

        // Pokud skladové místo existuje, provedeme interní přesun
        if ($locationResults && count($locationResults) > 0) {
            $skladoveMistoID = $locationResults[0]['id'];

            // Volání procedury s dynamickým nastavením parametrů
            $updateSql = "EXEC VAFOS_EXT_ZDI.dbo.InternalTransferStockUnitSubmit 
                          @StockUnitCode = :stockUnitCode, 
                          @StockUnitSSCC = :stockUnitSSCC, 
                          @SkladoveMistoID = :skladoveMistoID, 
                          @SkladoveMistoName = :newLocation, 
                          @Uzivatel = :username";

            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':stockUnitCode', $stockUnitCode, PDO::PARAM_STR);
            $updateStmt->bindParam(':stockUnitSSCC', $stockUnitSSCC, PDO::PARAM_STR);
            $updateStmt->bindParam(':skladoveMistoID', $skladoveMistoID, PDO::PARAM_INT);
            $updateStmt->bindParam(':newLocation', $newLocation, PDO::PARAM_STR);
            $updateStmt->bindParam(':username', $username, PDO::PARAM_STR);

            try {
                $updateStmt->execute();

                // Získání koncového času (měření latence serveru)
                $endTime = microtime(true);
                $latencyInSeconds = $endTime - $startTime;
                $latencyInMilliseconds = round($latencyInSeconds * 1000); // Převod na milisekundy

                // Přidáno: Přijetí typu připojení z klienta
                $connectionType = isset($_POST['connectionType']) ? $_POST['connectionType'] : 'unknown';
                $username = $_SESSION['username']; // Přímý přístup k session těsně před zápisem do logu
                $logData = date('Y-m-d H:i:s') . " | Username: $username | StockUnitCode: $stockUnitCode | StockUnitSSCC: $stockUnitSSCC | Location: $newLocation | SignalStrength: $signalStrength Mbps | ProcessTime: $processTime ms (client) | Server Latency: $latencyInMilliseconds ms\n";
                file_put_contents('logfile.log', $logData, FILE_APPEND);

                // Kontrola, zda bylo místo opravdu změněno
                $checkSql = "SELECT SkladoveMistoName FROM StockUnitDetailView 
                             WHERE (StockUnitCode = :stockUnitCode OR StockUnitSSCC = :stockUnitSSCC) 
                             AND SkladoveMistoName = :newLocation";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bindParam(':stockUnitCode', $stockUnitCode, PDO::PARAM_STR);
                $checkStmt->bindParam(':stockUnitSSCC', $stockUnitSSCC, PDO::PARAM_STR);
                $checkStmt->bindParam(':newLocation', $newLocation, PDO::PARAM_STR);
                $checkStmt->execute();
                $checkResults = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($checkResults && count($checkResults) > 0) {
                    $response = ['success' => true, 'message' => "" . ($stockUnitCode ?? $stockUnitSSCC) . " PŘESUNUTA"];
                } else {
                    $response = ['success' => false, 'message' => "" . ($stockUnitCode ?? $stockUnitSSCC) . " NELZE PŘESUNOUT"];
                }
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => "CHYBA PŘI PŘESUNU " . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => "NENALEZENO."];
        }

        // Odeslání JSON odpovědi
        header('Content-Type: application/json');
        echo json_encode($response);
        exit(); // Ujisti se, že skript končí po odeslání odpovědi
    }
} else {
    // Pokud nebyly nalezeny žádné výsledky
    echo "Žádné výsledky pro zadaný barcode.";
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
  <link rel="stylesheet" href="http://srv-vafos.vafo.local/vafosapp/css/detail_sklad_jednotka.css">
</head>
<body>
  
  <?php include '../../vafosapp/topbar.php'; ?>

  <div class="container">
    <hr style="border: none; border-top: 0px solid #ccc; margin: 10px 0;">

    <!-- Vstupní pole a přepínač pro automatický režim -->
    <div class="input-group mb-3">
    <input type="text" class="form-control" id="barcodeInput" placeholder="Zadejte kód" inputmode="none" autofocus>
      <label class="switch">
        <input type="checkbox" id="modeSwitch" checked>
        <span class="slider"></span>
      </label>
    </div>

    <!-- Výsledky dotazu -->
    <?php if (count($results) > 0): ?>
        <?php foreach ($results as $row): ?>
            <div class="result-row">
                <div class="result-label">SJ:</div>
                <div class="result-data"><?php echo htmlspecialchars($row['StockUnitCode']); ?></div>
            </div>
            
            <div class="result-row">
                <div class="result-label sscc-label">SSCC:</div>
                <div class="result-data sscc-data"><?php echo htmlspecialchars($row['StockUnitSSCC']); ?></div>
            </div>

            <div class="item-row">
              <div class="item-data">
                  <div class="result-label">Položka:</div>
                  <div class="result-data"><?php echo htmlspecialchars($row['CatalogueItemID']); ?></div>
              </div>
              <div class="item-name"><?php echo htmlspecialchars($row['CatalogueItemName']); ?></div>
          </div>

            <div class="result-row">
                <div class="result-label">Množství:</div>
                <div class="result-data">
                    <span class="result-data-inline"><?php echo htmlspecialchars($row['Quantity']); ?></span>
                    <span class="result-data-inline"><?php echo htmlspecialchars($row['MUID']); ?></span>
                </div>
            </div>
            <div class="result-row">
                <div class="result-label">Stav:</div>
                <div class="result-data">
                    <span class="result-data-inline"><?php 
                $status = htmlspecialchars($row['StockUnitProcessStatus']);
                switch ($status) {
                    case 'P':
                        echo "P - Potvrzen";
                        break;
                    case 'R':
                        echo "R - Rezervace";
                        break;
                    case 'N':
                        echo "N - Na cestě";
                        break;
                    default:
                        echo "Neznámý stav";
                        break;
                }
            ?>
        </span>
    </div>
</div>

            <div class="result-row">
                <div class="result-label sklad-label">Sklad:</div>
                <div class="result-data sklad-data">
                    <!--<span class="result-data-inline"><?php echo htmlspecialchars($row['SkladName']); ?></span>-->
                    <span class="result-data-inline"><?php echo htmlspecialchars($row['SkladoveMistoName']); ?></span>
                </div>
            </div>
            
        <?php endforeach; ?>
    <?php else: ?>
    <p>Žádné výsledky pro zadaný SJ nebo SSCC.</p>
    <?php endif; ?>

    <!-- Zobrazení úspěšné nebo chybové zprávy s fajkami a křížky nad alertem, zarovnáno na střed -->
    <div id="output-container" class="d-flex justify-content-center align-items-center" style="display: none; margin-top: 20px; position: relative;">
    <i id="output-icon" class="" style="font-size: 50px; margin-right: 10px;"></i>
    <div id="output" class="alert" role="alert" style="font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;"></div>
</div>
  </div>
  </div>

  <script src="http://srv-vafos.vafo.local/vafosapp/js/jquery-3.5.1.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/popper.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/bootstrap.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/app.js"></script>
  <script>
    $(document).ready(function() {
        let currentStockUnit = null;
        let locationChanged = false;
        let startTime = Date.now();  // Přidáno: Začátek měření času

        if (navigator.connection) {
        let signalStrength = navigator.connection.downlink; // Získání síly signálu

            document.getElementById("barcodeInput").addEventListener("input", function() {
                const barcodeValue = this.value.trim();
                const outputContainer = document.getElementById('output-container');
                const outputIcon = document.getElementById('output-icon');
                const output = document.getElementById('output');

                output.style.display = 'none';

                if (barcodeValue.length > 0) {
                    document.getElementById("barcodeInput").disabled = true;

                    setTimeout(function() {
                        document.getElementById("barcodeInput").disabled = false;
                        document.getElementById("barcodeInput").focus();
                    }, 5000);

                    let endTime = Date.now();  // Přidáno: Konec měření času
                    let processTime = endTime - startTime; // Přidáno: Výpočet času procesu

                    // Podmínky pro různé typy kódů (přesměrování)
                    if (/^PRI-\d{4}-\d{5}-ZDI$/.test(barcodeValue)) {
                        window.location.href = 'detail_sklad_doklad.php?barcode=' + barcodeValue;
                        return;
                    }

                    else if (/^SJ/.test(barcodeValue)) {
                        if (currentStockUnit !== barcodeValue) {
                            locationChanged = false;
                            currentStockUnit = barcodeValue;
                        }
                        window.location.href = 'detail_sklad_jednotka.php?stockunit=' + barcodeValue;
                        return;
                    }

                    else if (/^00/.test(barcodeValue)) {
                        if (currentStockUnit !== barcodeValue) {
                            locationChanged = false;
                            currentStockUnit = barcodeValue;
                        }
                        window.location.href = 'detail_sklad_jednotka.php?stockunitsscc=' + barcodeValue;
                        return;
                    }

                    else {
                        if (locationChanged) {
                            window.location.href = 'detail_sklad_misto.php?barcode=' + barcodeValue;
                        } else {
                            $.post(window.location.href, { 
                    newLocation: barcodeValue, 
                    signalStrength: signalStrength,  // Odeslání síly signálu
                    processTime: processTime,         // Odeslání času procesu
                            }, function(response) {
                                output.style.display = 'flex';

                                if (response.success) {
                                    output.classList.add('alert-success');
                                    output.classList.remove('alert-danger');
                                    outputIcon.className = 'fas fa-check';
                                    outputIcon.style.color = '#28a745';
                                    output.textContent = response.message;

                                    updateSkladoveMisto();
                                    document.getElementById("barcodeInput").value = '';
                                    locationChanged = true;

                                } else {
                                    output.classList.add('alert-danger');
                                    output.classList.remove('alert-success');
                                    outputIcon.className = 'fas fa-times';
                                    outputIcon.style.color = '#dc3545';
                                    output.textContent = response.message;
                                }

                                setTimeout(function() {
                                    document.getElementById("barcodeInput").disabled = false;
                                    document.getElementById("barcodeInput").focus();
                                }, 200);
                            }, 'json');
                        }
                    }
                } else {
                    output.style.display = 'block';
                    output.classList.add('alert-danger');
                    output.classList.remove('alert-success');
                    outputIcon.className = 'fas fa-times';
                    outputIcon.style.color = '#dc3545';
                    output.textContent = "Prosím, zadejte kód nebo skladové místo.";
                }
            });
        }
    });

    function updateSkladoveMisto() {
        $.get(window.location.href, function(data) {
            const skladoveMisto = $(data).find('.sklad-data').text();
            $('.sklad-data').text(skladoveMisto);

            // Aktualizace pouze Stav potvrzení
        const status = $(data).find('.status-confirmation').text();
        $('.status-confirmation').text(status);
        });
    }
</script>


</body>
</html>
