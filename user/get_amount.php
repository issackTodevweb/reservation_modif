<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$pdo = getConnection();
$response = ['total_amount' => 0.00];

try {
    $passenger_type = isset($_POST['passenger_type']) ? trim($_POST['passenger_type']) : '';
    $trip_id = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;
    $ticket_type = isset($_POST['ticket_type']) ? trim($_POST['ticket_type']) : '';

    error_log("get_amount: passenger_type=$passenger_type, trip_id=$trip_id, ticket_type=$ticket_type");

    // Validation des paramètres
    if (!$passenger_type || !$trip_id || !$ticket_type) {
        $response['error'] = 'Paramètres manquants.';
        error_log("get_amount: Erreur - Paramètres manquants.");
        echo json_encode($response);
        exit;
    }

    // Déterminer le type de trajet
    $trip_stmt = $pdo->prepare("SELECT departure_port, arrival_port FROM trips WHERE id = ?");
    $trip_stmt->execute([$trip_id]);
    $trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $response['error'] = 'Trajet invalide.';
        error_log("get_amount: Erreur - Trajet invalide (trip_id=$trip_id).");
        echo json_encode($response);
        exit;
    }

    // Définir le type de trajet : 'Hors Standard' pour ANJ-GCO ou GCO-ANJ, sinon 'Standard'
    $is_hors_standard = (
        ($trip['departure_port'] === 'ANJ' && $trip['arrival_port'] === 'GCO') ||
        ($trip['departure_port'] === 'GCO' && $trip['arrival_port'] === 'ANJ')
    );
    $trip_type = $is_hors_standard ? 'Hors Standard' : 'Standard';
    error_log("get_amount: trip_type=$trip_type");

    // Récupérer les tarifs depuis la table finances
    $finance_stmt = $pdo->prepare("SELECT tariff, port_fee, tax FROM finances WHERE passenger_type = ? AND trip_type = ?");
    $finance_stmt->execute([$passenger_type, $trip_type]);
    $finance = $finance_stmt->fetch(PDO::FETCH_ASSOC);

    if ($finance) {
        $response['total_amount'] = $finance['tariff'] + $finance['port_fee'] + $finance['tax'];
        error_log("get_amount: Montant calculé = {$response['total_amount']} (tariff={$finance['tariff']}, port_fee={$finance['port_fee']}, tax={$finance['tax']})");
    } else {
        $response['error'] = 'Aucun tarif trouvé pour cette combinaison.';
        error_log("get_amount: Erreur - Aucun tarif trouvé pour passenger_type=$passenger_type, trip_type=$trip_type");
    }
} catch (PDOException $e) {
    $response['error'] = 'Erreur lors du calcul du montant : ' . $e->getMessage();
    error_log("get_amount: Erreur PDO - " . $e->getMessage());
}

echo json_encode($response);
$pdo = null;
?>