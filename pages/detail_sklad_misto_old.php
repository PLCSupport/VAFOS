<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['barcode']) || empty($_GET['barcode'])) {
    error_log("Neplatný nebo prázdný barcode: " . var_export($_GET['barcode'], true));
    header("Location: http://srv-vafos.vafo.local/vafosapp/index.php");
    exit();
}

$barcode = htmlspecialchars($_GET['barcode']);

// Připojení k databázi zahrnutím souboru config.php
include '../config.php';

// Přepnutí na databázi VAFOS_EXT_ZDI
$conn->exec("USE VAFOS_EXT_ZDI");

// SQL dotaz pro získání skladových jednotek na daném místě
$sql = "SELECT 
            StockUnitCode,
            StockUnitSSCC,
            SkladoveMistoName,
            CatalogueItemName,
            CatalogueItemID,
            Quantity,
            MUID,
            StockUnitProcessStatus        
        FROM 
            VAFOS.dbo.StockUnitDetailView
        WHERE 
            SkladoveMistoName = :barcode
        ORDER BY 
            StockUnitCode";

// Příprava a provedení dotazu pro načtení dat
$stmt = $conn->prepare($sql);
$stmt->bindParam(':barcode', $barcode);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL dotaz pro spočítání počtu položek na skladovém místě
$sql_count = "SELECT COUNT(*) AS total_units
              FROM VAFOS.dbo.StockUnitDetailView
              WHERE SkladoveMistoName = :barcode";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bindParam(':barcode', $barcode);
$stmt_count->execute();
$total_units = $stmt_count->fetch(PDO::FETCH_ASSOC)['total_units'];

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
  <link rel="stylesheet" href="http://srv-vafos.vafo.local/vafosapp/css/detail_sklad_misto.css">
  <style>
    .carousel-indicators {
        position: relative; /* Umístíme je pod karusel */
        margin-top: 10px;  /* Odsadíme trochu od karuselu */
    }
    .carousel-indicators li {
        background-color: #f2b32b; /* Modrá barva indikátorů */
    }

    /* Změna barvy šipek */
    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        background-color: #ffffff00; /* Modrá barva šipek */
        border-radius: 50%; /* Kulaté okraje šipek */
        width: 0px; /* Velikost šipek */
        height: 0px;
    }
  </style>
</head>
<body>
  
  <?php include '../../vafosapp/topbar.php'; ?>

  <div class="container">
    <hr style="border: none; border-top: 0px solid #ccc; margin: 10px 0;">

    <div class="input-group mb-3">
      <input type="text" class="form-control" id="barcodeInput" placeholder="Zadejte kód">
      <label class="switch">
        <input type="checkbox" id="modeSwitch" checked>
        <span class="slider"></span>
      </label>
    </div>

    <!-- Zobrazení informací o skladovém místě a počtu jednotek jen tehdy, když nejsou výsledky -->
    <?php if (count($results) == 0): ?>
        <div class="result-row">
            <div class="result-label sklad-label">Sklad:</div>
            <div class="result-data sklad-data">
                <span class="result-data-inline"><?php echo htmlspecialchars($barcode); ?></span>
            </div>
        </div>

        <div class="result-row">
            <div class="result-label">Počet:</div>
            <div class="result-data result-data-počet"><?php echo htmlspecialchars($total_units); ?></div>
        </div>

        <!-- Zobrazení alertu "Prázdné", pokud je počet jednotek 0 -->
        <?php if ($total_units == 0): ?>
            <div class="d-flex justify-content-center align-items-center" style="margin-top: 19px; position: relative;">
    <i class="fas fa-times" style="font-size: 50px; color: #dc3545; margin-right: 10px;"></i>
    <div class="alert alert-danger" style="display: inline-block; font-weight: bold; font-size: 18px; flex-grow: 1; text-align: center;">
        PRÁZDNÉ
    </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Zobrazení výsledků v karuselu, pokud jsou výsledky nalezeny -->
    <?php if (count($results) > 0): ?>
        <div id="carouselExampleControls" class="carousel slide" data-ride="carousel">
          <div class="carousel-inner">
            <?php $first = true; ?>
            <?php foreach ($results as $row): ?>
              <div class="carousel-item <?php echo $first ? 'active' : ''; ?>">
                <div class="result-row">
                    <div class="result-label sklad-label">Sklad:</div>
                    <div class="result-data sklad-data">
                        <span class="result-data-inline"><?php echo htmlspecialchars($row['SkladoveMistoName']); ?></span>
                    </div>
                </div>

                <div class="result-row">
                    <div class="result-label">Počet:</div>
                    <div class="result-data result-data-počet"><?php echo htmlspecialchars($total_units); ?></div>
                </div>

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
              </div>
              <?php $first = false; ?>
            <?php endforeach; ?>
          </div>
          <a class="carousel-control-prev" href="#carouselExampleControls" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Předchozí</span>
          </a>
          <a class="carousel-control-next" href="#carouselExampleControls" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Další</span>
          </a>

          <!-- Karusel indikátory umístěné pod karuselem -->
          <ol class="carousel-indicators">
            <?php for ($i = 0; $i < count($results); $i++): ?>
              <li data-target="#carouselExampleControls" data-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"></li>
            <?php endfor; ?>
          </ol>
        </div>
    <?php endif; ?>
    
    <div id="output-container" class="d-flex justify-content-center align-items-center" style="display: none; margin-top: 19px; position: relative;">
    <i id="output-icon" class="" style="font-size: 50px; margin-right: 10px;"></i>
    <div id="output" class="alert" role="alert" style="font-weight: bold; flex-grow: 1; text-align: center;"></div>
</div>
  </div>

  <script src="http://srv-vafos.vafo.local/vafosapp/js/jquery-3.5.1.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/popper.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/bootstrap.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/app.js"></script>
  <script>
   $(document).ready(function() { 
    // Zaměření kurzoru na vstupní pole při načtení stránky
    $("#barcodeInput").focus();

    // Funkce pro zpracování zadaného kódu
    $('#barcodeInput').on('input', function() {
        var inputCode = $(this).val().trim(); // Zajištění odstranění mezer na začátku a konci

        // Zkontroluj, zda je kód prázdný
        if (inputCode.length === 0) {
            $('#output').hide();  // Skryje alert, pokud není zadán žádný kód
            return;
        }

        // Kontrola, zda zadaný kód odpovídá StockReceiptCode (s prefixem PRI)
        if (/^PRI/.test(inputCode)) {
            // Pokud je zadán StockReceiptCode s prefixem PRI, přesměrujeme na stránku detail_sklad_doklad.php
            window.location.href = 'detail_sklad_doklad.php?barcode=' + encodeURIComponent(inputCode); // Bezpečné přesměrování
            return; // Přerušení funkce, aby neproběhlo další zpracování
        } 
        else if (/^[ABCDEOR]/.test(inputCode)) {
            // Pokud je zadán SkladoveMistoNazev s prefixem A, B, C, D, E, R, O, přesměrujeme stránku
            window.location.href = '?sklad=' + encodeURIComponent(inputCode);
            return; 
        } 
        else if (/^SJ/.test(inputCode)) {
            // Pokud je zadán StockUnitCode s prefixem SJ, přesměrujeme stránku
            window.location.href = '?stockunit=' + encodeURIComponent(inputCode);
            return;
        }
        else if (/^00/.test(inputCode)) {
            // Pokud je zadán StockUnitSSCC s prefixem 00, přesměrujeme stránku
            window.location.href = '?stockunitsscc=' + encodeURIComponent(inputCode);
            return;
        } 
        else {
            // Pokud není zadán žádný ze specifických kódů, zobrazíme alert
            $('#output').html(`Nepodporovaný kód`).show();
        }
    });

    // Funkce pro vrácení focusu do vstupního pole po akci
    function returnFocus() {
        setTimeout(function() {
            document.getElementById("barcodeInput").focus();
        }, 500);
    }

    // Přidání událostí kliknutí pro focus zpět do vstupního pole
    const prevControl = document.querySelector('.carousel-control-prev');
    const nextControl = document.querySelector('.carousel-control-next');

    // Kontrola, zda prvek existuje, než se přidá event listener
    if (prevControl) {
        prevControl.addEventListener('click', returnFocus);
    }

    if (nextControl) {
        nextControl.addEventListener('click', returnFocus);
    }
});
  </script>
</body>
</html>
