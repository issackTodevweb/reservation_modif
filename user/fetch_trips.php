<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $departure_date = isset($_GET['departure_date']) ? trim($_GET['departure_date']) : '';

    error_log("fetch_trips: Date reçue = " . $departure_date);

    if (!$departure_date) {
        error_log("fetch_trips: Erreur - Date de départ non spécifiée");
        echo json_encode(['error' => 'Date de départ non spécifiée']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, departure_port, arrival_port, departure_date, departure_time FROM trips WHERE departure_date = ? AND type = 'aller' ORDER BY departure_time");
    $stmt->execute([$departure_date]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("fetch_trips: Nombre de trajets trouvés = " . count($trips));

    if (empty($trips)) {
        echo json_encode(['error' => 'Aucun trajet disponible pour cette date']);
    } else {
        echo json_encode($trips);
    }
} catch (PDOException $e) {
    error_log("fetch_trips: Erreur PDO = " . $e->getMessage());
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
$pdo = null;
?>