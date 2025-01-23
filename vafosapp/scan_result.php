<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['barcode'])) {
    header("Location: index.php");
    exit();
}

$barcode = htmlspecialchars($_GET['barcode']);

// Připojení k databázi zahrnutím souboru config.php
include 'config.php';

$paletaNenalezena = false;

try {
    // Správný SQL dotaz
    $sql = "SELECT * FROM StockUnit WHERE StockUnitCode = ? OR StockUnitSSCC = ?";
    $stmt = $conn->prepare($sql);
    
    // Vázání parametrů
    $stmt->execute([$barcode, $barcode]);

    // Získání výsledku
    if ($stmt->rowCount() == 0) {
        // Pokud barcode není nalezen, nastavíme příznak pro zobrazení zprávy
        $paletaNenalezena = true;
    } else {
        // Pokud barcode je nalezen, získáme data
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $sj = $row['StockUnitCode'];
        $sscc = $row['StockUnitSSCC'];
    }
} catch (PDOException $e) {
    die("Chyba při dotazu na databázi: " . $e->getMessage());
}

if ($paletaNenalezena) {
    // Pokud paleta nebyla nalezena, zobrazí se chybová zpráva a zůstane na index.php
    header("Location: index.php?error=Paleta s čárovým kódem " . urlencode($barcode) . " nebyla nalezena.");
    exit();
}
?>


<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VAFOS - Výsledek skenování</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../vafosapptst/css/styles.css">
  <style>
    .highlight {
        display: flex;
        justify-content: center; /* Vycentruje vše horizontálně */
        text-align: center; /* Vycentrování obsahu na střed */
    }
    .highlight .container {
        display: flex;
        flex-direction: column; /* Uspořádá prvky pod sebe */
        justify-content: center; /* Vycentruje obsah vertikálně */
        align-items: center; /* Vycentruje vše horizontálně */
    }
    .highlight .row {
        display: flex;
        justify-content: space-between; /* Mezera mezi label a hodnotou */
        width: 300px; /* Šířka kontejneru pro zarovnání vlevo a vpravo */
        margin-bottom: 0px; /* Zmenší mezery mezi řádky SJ a SSCC */
    }
    .highlight .label {
        text-align: left; /* Zarovná label vlevo */
        padding-left: 10px;
        font-weight: bold;
    }
    .highlight .value {
        text-align: right; /* Zarovná hodnoty vpravo */
        flex-grow: 1; /* Zabere zbývající místo, aby byla hodnota vpravo */
    }
    .buttons-container {
        text-align: center; /* Zajistí, že tlačítka budou ve středu stránky */
        margin-top: 20px; /* Nastaví odsazení nad tlačítky */
    }
    .buttons-container button {
        margin-bottom: 8px; /* Zmenší mezery mezi tlačítky */
        padding: 10px; /* Zmenší vnitřní okraje tlačítek, pokud je potřeba */
    }

    :root {
        --base-font-size: 14px; /* Zvětšeno na 16px */
    }

    body {
        font-size: var(--base-font-size);
    }

    .result-row {
        display: flex;
        justify-content: space-between; /* Přidá mezery mezi label vlevo a hodnotu vpravo */
        align-items: center;
        margin-bottom: 4px;
        width: 290px; /* Nastavení šířky pro rovnoměrné rozložení */
    }

    /* Styl pro SJ label */
    .sj-label {
        font-weight: bold;
        color: #1f4a77; /* Barva labelu pro SJ */
        font-size: 1.0em;
    }

    /* Styl pro data SJ */
    .sj-data {
        font-weight: bold;
        color: #000;
        background-color: #81c3d791;
        padding: 3px;
        border-radius: 5px;
        text-align: right;
        width: 64%; /* Nastaví, aby data zabírala celou šířku dostupného prostoru */
        line-height: 1.2;
        font-size: 1.0em; /* Výchozí velikost písma pro SJ */
    }

    /* Styl pro SSCC label */
    .sscc-label {
        font-weight: bold;
        color: #1f4a77; /* Speciální barva pro label SSCC */
        font-size: 1.3em;
    }

    /* Styl pro data SSCC */
    .sscc-data {
        font-weight: bold;
        color: #000;
        background-color: #81c3d791; /* Speciální barva pro SSCC */
        padding: 3px;
        border-radius: 5px;
        text-align: right;
        width: 67%; /* Nastaví, aby data zabírala celou šířku dostupného prostoru */
        line-height: 1.2;
        font-size: 1.3em; /* Zvýšení velikosti dat pro SSCC */
    }

  </style>
</head>
<body>
  <?php include 'topbar.php'; ?>
  
  <div class="container" style="margin-top: 10px;">
    <div class="highlight">
      <div class="container">
        <div class="result-row">
          <div class="result-label sj-label">SJ:</div>
          <div class="sj-data"><?php echo htmlspecialchars($sj); ?></div>
        </div>
        <div class="result-row">
          <div class="result-label sscc-label">SSCC:</div>
          <div class="sscc-data"><?php echo htmlspecialchars($sscc); ?></div>
        </div>
      </div>
    </div>

    <div class="buttons-container">
      <button type="button" class="btn btn-primary btn-block" onclick="handleButtonClick(1)">
        <div class="icon-text">
          <span class="icon">1</span>Detail SJ
        </div>
      </button>
      <button type="button" class="btn btn-secondary btn-block" onclick="handleButtonClick(2)">
        <div class="icon-text">
          <span class="icon">2</span>Příjem
        </div>
      </button>
      <button type="button" class="btn btn-success btn-block" onclick="handleButtonClick(3)">
        <div class="icon-text">
          <span class="icon">3</span>Výdej
        </div>
      </button>
      <button type="button" class="btn btn-info btn-block" onclick="handleButtonClick(4)">
        <div class="icon-text">
          <span class="icon">4</span>Interní přesun
        </div>
      </button>
      <button type="button" class="btn btn-warning btn-block" onclick="handleButtonClick(5)">
        <div class="icon-text">
          <span class="icon">5</span>Zadržení
        </div>
      </button>
      <button type="button" class="btn btn-danger btn-block" onclick="handleButtonClick(6)">
        <div class="icon-text">
          <span class="icon">6</span>Odpis
        </div>
      </button>
      <button type="button" class="btn btn-dark btn-block" onclick="handleButtonClick(7)">
        <div class="icon-text">
          <span class="icon">7</span>Inventura
        </div>
      </button>
      <button type="button" class="btn btn-light btn-block" onclick="handleButtonClick(8)">
        <div class="icon-text">
          <span class="icon">8</span>Zpět
        </div>
      </button>
    </div>
  </div>
  
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    function goBack() {
      <?php 
      unset($_SESSION['barcode']); // Smazání barcode ze session 
      ?>
      window.location.href = 'index.php'; // Přesměrování na index.php
    }

    function handleButtonClick(buttonNumber) {
      // Logika pro zpracování kliknutí na tlačítko podle čísla
      console.log("Button " + buttonNumber + " clicked");
      switch(buttonNumber) {
        case 1:
          // Přesměrování na soubor detailsj.php
          window.location.href = '../vafosapp/pages/detailsj.php?barcode=<?php echo htmlspecialchars($barcode); ?>';
          break;
        case 2:
          // Přesměrování na soubor prijem.php
          window.location.href = '../vafosapp/pages/prijem.php?barcode=<?php echo htmlspecialchars($barcode); ?>';
          break;
        case 4:
          // Přesměrování na soubor internipresun.php
          window.location.href = '../vafosapp/pages/internipresun.php?barcode=<?php echo htmlspecialchars($barcode); ?>';
          break;
        case 5:
          // Přesměrování na soubor zadrzeni.php
          window.location.href = '../vafosapp/pages/zadrzeni.php?barcode=<?php echo htmlspecialchars($barcode); ?>';
          break;
        case 8:
          // Logika pro Zpět
          goBack();
          break;
        default:
      }
    }

    document.addEventListener('keydown', function(event) {
      const key = event.key;
      if (key >= '1' && key <= '8') {
        handleButtonClick(parseInt(key));
      }
    });
  </script>
</body>
</html>
