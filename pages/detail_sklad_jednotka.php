<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['dbName']) || empty($_SESSION['dbName'])) {
    die("Chyba: Název databáze (dbName) není nastaven v session.");
}

$dbName = $_SESSION['dbName'];

if (!isset($_GET['barcode'])) {
    header("Location: https://vafos.vafo.cz/vafosapppro/index.php");
    exit();
}

$barcode = htmlspecialchars($_GET['barcode']);
$username = $_SESSION['username'];

// Připojení k databázi zahrnutím souboru config.php
include '../config.php';

// Přiřazení LocationID na základě session
$locationID = $_SESSION['department'];

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
            (StockUnitCode = :barcode1 OR StockUnitSSCC = :barcode2)
            AND LocationID = :locationID";

// Příprava a provedení dotazu
$stmt = $conn->prepare($sql);
$stmt->bindParam(':barcode1', $barcode);
$stmt->bindParam(':barcode2', $barcode);
$stmt->bindParam(':locationID', $locationID);
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
        $signalStrength = isset($_POST['signalStrength']) ? $_POST['signalStrength'] : 'unknown';
        $processTime = isset($_POST['processTime']) ? $_POST['processTime'] : 'unknown';

        $locationCheckSql = "SELECT id FROM " . $dbName . ".dbo.PrehledSklady WHERE LOWER(SkladoveMistoNazev) = LOWER(:newLocation)";
        $locationStmt = $conn->prepare($locationCheckSql);
        $locationStmt->bindParam(':newLocation', $newLocation);
        $locationStmt->execute();
        $locationResults = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($locationResults && count($locationResults) > 0) {
            $skladoveMistoID = $locationResults[0]['id'];

            $updateSql = "EXEC " . $dbName . ".dbo.InternalTransferStockUnitSubmit 
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
                $response = ['success' => true, 'message' => "$stockUnitCode přesunuto na $newLocation"];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => "Chyba při přesunu: " . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => "Skladové místo nenalezeno."];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newQuantity'])) {
        $newQuantity = trim(htmlspecialchars($_POST['newQuantity']));
        $note = 'inventura'; // Pevně nastavená hodnota pro Note
    
        // Zachovat původní skladové místo z databázového výsledku
        $skladoveMistoID = !empty($results[0]['SkladoveMistoID']) ? $results[0]['SkladoveMistoID'] : NULL;
        $skladoveMistoName = !empty($results[0]['SkladoveMistoName']) ? $results[0]['SkladoveMistoName'] : NULL;
    
        $submitSql = "EXEC " . $dbName . ".dbo.StockUnitStockTakeSubmit 
                      @StockUnitCode = :stockUnitCode,
                      @StockUnitSSCC = :stockUnitSSCC,
                      @SkladoveMistoID = :skladoveMistoID,
                      @SkladoveMistoName = :skladoveMistoName,
                      @Uzivatel = :username,
                      @Quantity = :quantity,
                      @Note = :note";
    
        $submitStmt = $conn->prepare($submitSql);
        $submitStmt->bindParam(':stockUnitCode', $stockUnitCode, PDO::PARAM_STR);
        $submitStmt->bindParam(':stockUnitSSCC', $stockUnitSSCC, PDO::PARAM_STR);
        $submitStmt->bindParam(':skladoveMistoID', $skladoveMistoID, PDO::PARAM_INT);
        $submitStmt->bindParam(':skladoveMistoName', $skladoveMistoName, PDO::PARAM_STR);
        $submitStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $submitStmt->bindParam(':quantity', $newQuantity, PDO::PARAM_STR);
        $submitStmt->bindParam(':note', $note, PDO::PARAM_STR);
    
        try {
            $submitStmt->execute();
    
            // Měření latence
            $endTime = microtime(true);
            $latencyInMilliseconds = round(($endTime - $startTime) * 1000);
    
            // Logování
            $logData = date('Y-m-d H:i:s') . " | Uživatelské jméno: $username | SJ: $stockUnitCode | SSCC: $stockUnitSSCC | Množství: $newQuantity | Poznámka: $note | Skladové místo: $skladoveMistoName | Latence: $latencyInMilliseconds ms\n";
            file_put_contents('logfile.log', $logData, FILE_APPEND);
    
            $response = ['success' => true, 'message' => "Úspěšně aktualizováno."];
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => "Chyba při aktualizaci množství: " . $e->getMessage()];
        }
    
        // Odeslat JSON odpověď
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
} else {

   // Hláška, pokud jednotka není nalezena v dané lokaci
   $response = [
    'success' => false,
    'message' => "Jednotka s čárovým kódem '$barcode' není nalezena v lokaci s ID '$locationID'."
];
}
?>




<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VAFOS - Výsledek skenování</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://vafos.vafo.cz/vafosapppro/css/themes/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://vafos.vafo.cz/vafosapppro/css/styles.css">
  <link rel="stylesheet" href="https://vafos.vafo.cz/vafosapppro/css/detail_sklad_jednotka.css">
</head>
<body>
  
  <?php include '../../vafosapppro/topbar.php'; ?>

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

          <div class="result-row custom-quantity-row" id="quantityContainer">
    <div class="result-label custom-quantity-label">Množství:</div>
    <div class="result-data custom-quantity-data" style="background-color: #81c3d791; border-radius: 5px; padding: 10px;">
        <span id="quantityLabel" class="result-data-inline" data-status="<?php echo htmlspecialchars($row['StockUnitProcessStatus']); ?>">
            <?php echo intval($row['Quantity']); // Odebere desetinná místa ?>
        </span>
        <span class="result-data-inline"><?php echo htmlspecialchars($row['MUID']); ?></span>
    </div>
</div>

<!-- Modální okno -->
<div class="modal fade" id="quantityModal" tabindex="-1" role="dialog" aria-labelledby="quantityModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quantityModalLabel">Úprava množství</h5>
                <!--<button type="button" class="close" data-dismiss="modal" aria-label="Zavřít">
                    <span aria-hidden="true">&times;</span>
                </button>-->
            </div>
            <div class="modal-body">
            <div class="result-row">
                <div class="result-label">SJ:</div>
                <div class="result-data"><?php echo htmlspecialchars($row['StockUnitCode']); ?></div>
            </div>
                <div class="result-row">
                <div class="result-label sscc-label">SSCC:</div>
                <div class="result-data sscc-data"><?php echo htmlspecialchars($row['StockUnitSSCC']); ?></div>
            </div>
                <div class="result-row">
                <div class="result-label custom-quantity-label">Množství:</div>
                    <div class="result-data result-data-mnozstvi" id="modalCurrentQuantity">
                    <?php echo number_format((float)$row['Quantity'], 0, '.', ''); ?> ks
                    </div>
                </div>
                <form id="updateQuantityForm" method="post">
                    <!-- Štítek a pole Nové množství vedle sebe -->
                    <div class="result-row">
                        <label for="newQuantity" class="result-label quantity-label">Nové množství:</label>
                        <input 
                            type="tel" 
                            class="custom-input" 
                            id="newQuantity" 
                            name="newQuantity" 
                            inputmode="decimal" 
                            pattern="^[0-9]+(\.[0-9]{1,3})?$" 
                            value="" 
                            required>
                    </div>
                    <div class="result-row">
                        <div class="result-label">Rozdíl:</div>
                        <div class="result-data result-data-rozdil" id="modalDifference">0</div>
                    </div>
                    <!-- Notifikace pod vstupním polem -->
                    <div id="modalNotification" class="alert d-none align-items-center mt-3" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span id="notificationText"></span>
                    </div>
                </form>
            </div>
            <!-- Upravená sekce tlačítek -->
            <div class="modal-footer modal-footer-inline">
    <button type="button" class="btn btn-danger" data-dismiss="modal">Zrušit</button>
    <button type="submit" form="updateQuantityForm" class="btn btn-success">Potvrdit</button>
</div>
        </div>
    </div>
</div>



<div class="result-row">
    <div class="result-label">Stav:</div>
    <div class="result-data" id="statusLabel" data-status="<?php echo htmlspecialchars($row['StockUnitProcessStatus']); ?>">
        <span class="result-data-inline">
            <?php 
                $status = htmlspecialchars($row['StockUnitProcessStatus']);
                switch ($status) {
                    case 'P':
                        echo "P - Potvrzeno";
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
        <div id="output-container" class="d-flex justify-content-center align-items-center" style="display: flex; margin-top: 20px; position: relative;">
        <i id="output-icon" class="fas fa-times" style="font-size: 50px; margin-right: 10px; color: #dc3545;"></i>
        <div id="output" class="alert alert-danger" role="alert" style="font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;">
            '<?php echo htmlspecialchars($barcode); ?>' není v lokaci '<?php echo htmlspecialchars($locationID); ?>'.
        </div>
    </div>
    <?php endif; ?>

    <!-- Zobrazení úspěšné nebo chybové zprávy s fajkami a křížky nad alertem, zarovnáno na střed -->
    <div id="output-container" class="d-flex justify-content-center align-items-center" style="display: none; margin-top: 20px; position: relative;">
    <i id="output-icon" class="" style="font-size: 50px; margin-right: 10px;"></i>
    <div id="output" class="alert" role="alert" style="font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;"></div>
</div>
  </div>
  </div>

  <script src="https://vafos.vafo.cz/vafosapppro/js/jquery-3.5.1.min.js"></script>
  <script src="https://vafos.vafo.cz/vafosapppro/js/popper.min.js"></script>
  <script src="https://vafos.vafo.cz/vafosapppro/js/bootstrap.min.js"></script>
  <script src="https://vafos.vafo.cz/vafosapppro/js/app.js"></script>
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
                    }, 500);

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

    



    function formatQuantity(quantity) {
    return parseFloat(quantity).toFixed(0); // Vždy zobrazí 3 desetinná místa
}

// Aktualizace dat v modálním okně při jeho otevření
$('#quantityModal').on('show.bs.modal', function() {
    const quantityLabel = document.getElementById('quantityLabel');
    const modalQuantityInput = document.getElementById('newQuantity');
    const modalCurrentQuantity = document.getElementById('modalCurrentQuantity');
    const modalNotification = document.getElementById('modalNotification'); // Notifikace v modálním okně
    const modalDifference = document.getElementById('modalDifference'); // Pole pro rozdíl

    // Vymazání notifikace
    if (modalNotification) {
        modalNotification.classList.add('d-none'); // Skrytí notifikace
        modalNotification.textContent = ''; // Vymazání textu notifikace
    }

    // Resetování rozdílu na 0.000
    if (modalDifference) {
        modalDifference.textContent = "0"; // Nastavení rozdílu na 0.000
    }

    // Resetování pole "Zadejte nové množství" na 0.000
    if (modalQuantityInput) {
        modalQuantityInput.value = ""; // Nastavení výchozí hodnoty na 0.000
    }

    // Nastavení aktuálního množství v modálním okně
    if (quantityLabel && modalCurrentQuantity) {
        const currentQuantity = quantityLabel.textContent.replace('', '').trim();
        modalCurrentQuantity.textContent = formatQuantity(currentQuantity);
    }
});

// Odeslání formuláře pro aktualizaci množství
document.getElementById('updateQuantityForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const formData = new FormData(this);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const modalNotification = document.getElementById('modalNotification');
        if (!modalNotification) {
            console.error("Element #modalNotification nebyl nalezen.");
            return;
        }

        if (data.success) {
            // Zobrazení notifikace
            modalNotification.classList.remove('d-none', 'alert-danger');
            modalNotification.classList.add('alert-success');
            modalNotification.textContent = data.message;

            // Aktualizace hodnot
            const quantityLabel = document.getElementById('quantityLabel');
            if (quantityLabel) {
                const newQuantity = formData.get('newQuantity');
                quantityLabel.textContent = formatQuantity(newQuantity) + "";
            }

            const modalCurrentQuantity = document.getElementById('modalCurrentQuantity');
            if (modalCurrentQuantity) {
                modalCurrentQuantity.textContent = formatQuantity(formData.get('newQuantity'));
            }

            // Zavření modálního okna po 3 vteřinách
            setTimeout(() => {
                $('#quantityModal').modal('hide');
            }, 1000);
        } else {
            // Zobrazení chybové zprávy
            modalNotification.classList.remove('d-none', 'alert-success');
            modalNotification.classList.add('alert-danger');
            modalNotification.textContent = data.message;
        }
    })
    .catch(error => {
        console.error("Chyba při odesílání dat:", error);

        const modalNotification = document.getElementById('modalNotification');
        if (modalNotification) {
            modalNotification.classList.remove('d-none', 'alert-success');
            modalNotification.classList.add('alert-danger');
            modalNotification.textContent = "Chyba při odesílání dat.";
        }
    });
});

document.getElementById('newQuantity').addEventListener('input', function () {
    const modalCurrentQuantity = parseFloat(document.getElementById('modalCurrentQuantity').textContent.trim());
    const newQuantity = parseFloat(this.value);

    const modalDifference = document.getElementById('modalDifference');
    if (!isNaN(modalCurrentQuantity) && !isNaN(newQuantity)) {
        const difference = newQuantity - modalCurrentQuantity;
        modalDifference.textContent = difference.toFixed(0); // Vždy zobrazí 3 desetinná místa
    } else {
        modalDifference.textContent = "0"; // Defaultní hodnota
    }
});

// Nastavení autofocusu po zavření modálního okna
$('#quantityModal').on('hidden.bs.modal', function() {
    const barcodeInput = document.getElementById('barcodeInput'); // Hlavní vstupní pole
    if (barcodeInput) {
        barcodeInput.focus(); // Nastavení focusu
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const quantityLabel = document.getElementById("quantityLabel");
    const statusLabel = document.getElementById("statusLabel");
    const barcodeInput = document.getElementById("barcodeInput"); // Vstupní pole

    if (quantityLabel && statusLabel && barcodeInput) {
        quantityLabel.addEventListener("click", function (event) {
            const status = statusLabel.getAttribute("data-status").trim(); // Získání hodnoty stavu

            if (status !== "P") {
                console.log("Stav není P. Modální okno se nezobrazí.");
                event.stopPropagation(); // Zastaví další propagaci události
                event.preventDefault(); // Zamezí výchozímu chování
                
                // Nastavení fokus na vstupní pole
                barcodeInput.focus();
                return; // Ukončí funkci
            }

            console.log("Stav je P. Otevírám modální okno.");
            $('#quantityModal').modal('show'); // Zobrazí modální okno
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    $('#quantityModal').on('shown.bs.modal', function () {
        const newQuantityInput = document.getElementById('newQuantity');
        if (newQuantityInput) {
            newQuantityInput.focus(); // Zaostření na pole
            newQuantityInput.setAttribute('readonly', true); // Nastaví pouze pro čtení, zabrání klávesnici
            setTimeout(() => {
                newQuantityInput.removeAttribute('readonly'); // Odstraní readonly po krátké prodlevě
            }, 1000); // Prodleva 200ms, aby klávesnice nebyla spuštěna
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const quantityLabel = document.getElementById("quantityLabel");
    const quantityContainer = document.querySelector(".custom-quantity-data");

    if (quantityLabel && quantityContainer) {
        const status = quantityLabel.getAttribute("data-status");

        if (status === "P") {
            quantityContainer.style.border = "3px solid #ffc107";
            quantityContainer.style.padding = "2px";
            quantityContainer.style.borderRadius = "5px";
        } else {
            quantityContainer.style.border = "none"; // Pokud není P, odstraníme rámeček
        }
    }
});
</script>

</body>
</html>
