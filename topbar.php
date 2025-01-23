<?php
if (!isset($_SESSION['username']) || !isset($_SESSION['department'])) {
    header("Location: login.php");
    exit();
}
?>
<link rel="stylesheet" href="https://vafos.vafo.cz/vafosapppro/css/styles.css">
<nav class="navbar topbar">
    <span class="navbar-brand">Uživatel: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
    
    <span class="navbar-brand">Lokalita: 
        <strong>
            <?php 
            if (is_array($_SESSION['department'])) {
                // Zobrazí pouze první tři hodnoty
                $departmentsToShow = array_slice($_SESSION['department'], 0, 1);
                echo implode(', ', array_map('htmlspecialchars', $departmentsToShow));

                // Pokud je více než tři hodnoty, přidejte tři tečky
                if (count($_SESSION['department']) > 1) {
                    echo '...';
                }
            } else {
                echo htmlspecialchars($_SESSION['department']);
            }
            ?>
        </strong>
    </span>
</nav>
