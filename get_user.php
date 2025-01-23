<?php
require 'config.php';

if (isset($_GET['rfid'])) {
    $rfid = $_GET['rfid'];

    try {
        $stmt = $conn->prepare('SELECT * FROM Uzivatele WHERE rfid = :rfid');
        $stmt->bindParam(':rfid', $rfid);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode(['username' => $user['username'], 'password' => $user['password']]);
        } else {
            echo json_encode(['error' => 'Uživatel nenalezen.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Chyba při načítání uživatele: ' . $e->getMessage()]);
    }
}
?>
