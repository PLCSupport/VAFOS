<?php
session_start();

// Kontrola, zda jsou v session nastaveny hodnoty 'username' a 'department'
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}

// Výpis obsahu celé session
//echo '<pre>';
//print_r($_SESSION);
//echo '</pre>';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VAFOS</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../vafosapp/css/styles.css">
  <link rel="stylesheet" href="../vafosapp/css/index.css">
  <!-- Přidání Font Awesome pro ikony -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  
</head>
<body>
  <?php include 'topbar.php'; ?>

  <div class="container">
    <br>
    <!--<label for="barcodeInput">Kód skladové jednotky:</label>-->
    <input type="text" class="form-control mb-3" id="barcodeInput" placeholder="Zadejte kód">
    
    <button type="button" class="btn btn-primary btn-custom btn-icon mb-3" id="confirmButton">
      <span class="icon">1</span> Potvrdit
    </button>

    <div class="d-flex align-items-center mb-3">
      <label for="modeSwitch" class="mr-2">Automatický režim</label>
      <label class="switch">
        <input type="checkbox" id="modeSwitch" checked>
        <span class="slider"></span>
      </label>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger text-center mt-4" role="alert">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div id="output" class="alert alert-success" role="alert" style="display: none;"></div>

    <!-- Animace ikony čárového kódu s červenou linkou -->
    <div class="barcode-container">
        <div class="scan-line"></div>
        <i class="fas fa-barcode barcode-icon"></i>
        <i class="fas fa-barcode barcode-icon"></i>
    </div>
    <br>
    <div id="alertContainer"></div>

    <!--<a href="logout.php" class="btn btn-danger btn-custom btn-icon" id="logoutButton">
      <span class="icon">2</span> Odhlásit
    </a>-->
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="http://srv-vafos.vafo.local/vafosapp/js/app.js"></script>

  <script>
    // Nastavení kurzoru na vstupní pole při načtení stránky
    document.getElementById("barcodeInput").focus();
  </script>
</body>
</html>
