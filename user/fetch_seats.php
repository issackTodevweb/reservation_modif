<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

    if (!$trip_id) {
        echo json_encode([]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT seat_number, status FROM seats WHERE trip_id = ? ORDER BY seat_number");
    $stmt->execute([$trip_id]);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Simuler une disposition (par exemple, 4 sièges par rangée, 2 blocs séparés par une allée)
    $rows = [];
    $seats_per_row = 4;
    $current_row = ['seats' => []];
    $seat_count = 0;

    foreach ($seats as $seat) {
        $current_row['seats'][] = $seat;
        $seat_count++;
        if ($seat_count % $seats_per_row === 0) {
            $current_row['aisle'] = true;
            $rows[] = $current_row;
            $current_row = ['seats' => []];
        }
    }
    if (!empty($current_row['seats'])) {
        $rows[] = $current_row;
    }

    echo json_encode(['rows' => $rows]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
$pdo = null;
?>