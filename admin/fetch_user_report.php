<?php
session_start();

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $pdo = getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données']);
    exit();
}

// Vérifier les paramètres POST
if (!isset($_POST['user_id']) || !isset($_POST['report_period'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit();
}

$user_id = $_POST['user_id'];
$report_period = $_POST['report_period'];
$report_date = isset($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d');
$report_month = isset($_POST['report_month']) ? $_POST['report_month'] : date('Y-m');
$report_year = isset($_POST['report_year']) ? $_POST['report_year'] : date('Y');

// Déterminer le titre du rapport
if ($report_period === 'daily') {
    $report_title = "Rapport Journalier - " . date('d/m/Y', strtotime($report_date));
} elseif ($report_period === 'monthly') {
    $report_title = "Rapport Mensuel - " . date('m/Y', strtotime($report_month));
} else {
    $report_title = "Rapport Annuel - " . $report_year;
}

// Récupérer le nom d'utilisateur
try {
    $sql_username = "SELECT username FROM users WHERE id = ?";
    $stmt_username = $pdo->prepare($sql_username);
    $stmt_username->execute([$user_id]);
    $user = $stmt_username->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Utilisateur non trouvé']);
        exit();
    }
    $username = $user['username'];
    $report_title .= " - $username";
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération de l\'utilisateur']);
    exit();
}

// Récupérer les réservations
try {
    if ($report_period === 'daily') {
        $sql_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                             FROM reservations r
                             JOIN trips t ON r.trip_id = t.id
                             LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                             AND (CASE 
                                  WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                  ELSE 'Standard'
                                  END) = f.trip_type
                             WHERE r.user_id = ? AND r.departure_date = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_reservations = $pdo->prepare($sql_reservations);
        $stmt_reservations->execute([$user_id, $report_date]);
    } elseif ($report_period === 'monthly') {
        $sql_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                             FROM reservations r
                             JOIN trips t ON r.trip_id = t.id
                             LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                             AND (CASE 
                                  WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                  ELSE 'Standard'
                                  END) = f.trip_type
                             WHERE r.user_id = ? AND DATE_FORMAT(r.departure_date, '%Y-%m') = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_reservations = $pdo->prepare($sql_reservations);
        $stmt_reservations->execute([$user_id, $report_month]);
    } else { // yearly
        $sql_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                             FROM reservations r
                             JOIN trips t ON r.trip_id = t.id
                             LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                             AND (CASE 
                                  WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                  ELSE 'Standard'
                                  END) = f.trip_type
                             WHERE r.user_id = ? AND YEAR(r.departure_date) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_reservations = $pdo->prepare($sql_reservations);
        $stmt_reservations->execute([$user_id, $report_year]);
    }
    $reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des réservations']);
    exit();
}

// Récupérer les colis
try {
    if ($report_period === 'daily') {
        $sql_colis = "SELECT sender_name, package_reference, package_fee
                      FROM colis 
                      WHERE user_id = ? AND DATE(created_at) = ?";
        $stmt_colis = $pdo->prepare($sql_colis);
        $stmt_colis->execute([$user_id, $report_date]);
    } elseif ($report_period === 'monthly') {
        $sql_colis = "SELECT sender_name, package_reference, package_fee
                      FROM colis 
                      WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $stmt_colis = $pdo->prepare($sql_colis);
        $stmt_colis->execute([$user_id, $report_month]);
    } else { // yearly
        $sql_colis = "SELECT sender_name, package_reference, package_fee
                      FROM colis 
                      WHERE user_id = ? AND YEAR(created_at) = ?";
        $stmt_colis = $pdo->prepare($sql_colis);
        $stmt_colis->execute([$user_id, $report_year]);
    }
    $colis = $stmt_colis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des colis']);
    exit();
}

// Récupérer les totaux
try {
    if ($report_period === 'daily') {
        $sql_totals = "SELECT COALESCE(SUM(f.tariff), 0) as user_tariff,
                              COALESCE(SUM(f.tax), 0) as user_tax,
                              COALESCE(SUM(f.port_fee), 0) as user_port_fee,
                              COALESCE(SUM(r.total_amount), 0) as user_total,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as user_mvola,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as user_cash,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as user_unpaid
                       FROM reservations r
                       JOIN trips t ON r.trip_id = t.id
                       LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                       AND (CASE 
                            WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                            ELSE 'Standard'
                            END) = f.trip_type
                       WHERE r.user_id = ? AND r.departure_date = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_totals = $pdo->prepare($sql_totals);
        $stmt_totals->execute([$user_id, $report_date]);
    } elseif ($report_period === 'monthly') {
        $sql_totals = "SELECT COALESCE(SUM(f.tariff), 0) as user_tariff,
                              COALESCE(SUM(f.tax), 0) as user_tax,
                              COALESCE(SUM(f.port_fee), 0) as user_port_fee,
                              COALESCE(SUM(r.total_amount), 0) as user_total,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as user_mvola,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as user_cash,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as user_unpaid
                       FROM reservations r
                       JOIN trips t ON r.trip_id = t.id
                       LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                       AND (CASE 
                            WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                            ELSE 'Standard'
                            END) = f.trip_type
                       WHERE r.user_id = ? AND DATE_FORMAT(r.departure_date, '%Y-%m') = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_totals = $pdo->prepare($sql_totals);
        $stmt_totals->execute([$user_id, $report_month]);
    } else { // yearly
        $sql_totals = "SELECT COALESCE(SUM(f.tariff), 0) as user_tariff,
                              COALESCE(SUM(f.tax), 0) as user_tax,
                              COALESCE(SUM(f.port_fee), 0) as user_port_fee,
                              COALESCE(SUM(r.total_amount), 0) as user_total,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as user_mvola,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as user_cash,
                              COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as user_unpaid
                       FROM reservations r
                       JOIN trips t ON r.trip_id = t.id
                       LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                       AND (CASE 
                            WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                            ELSE 'Standard'
                            END) = f.trip_type
                       WHERE r.user_id = ? AND YEAR(r.departure_date) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_totals = $pdo->prepare($sql_totals);
        $stmt_totals->execute([$user_id, $report_year]);
    }
    $totals = $stmt_totals->fetch(PDO::FETCH_ASSOC);

    $sql_colis_totals = "SELECT COALESCE(SUM(package_fee), 0) as user_package_fee
                         FROM colis 
                         WHERE user_id = ? AND " . ($report_period === 'daily' ? "DATE(created_at) = ?" : ($report_period === 'monthly' ? "DATE_FORMAT(created_at, '%Y-%m') = ?" : "YEAR(created_at) = ?"));
    $stmt_colis_totals = $pdo->prepare($sql_colis_totals);
    $stmt_colis_totals->execute([$user_id, $report_period === 'daily' ? $report_date : ($report_period === 'monthly' ? $report_month : $report_year)]);
    $colis_totals = $stmt_colis_totals->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des totaux']);
    exit();
}

// Retourner les données au format JSON
echo json_encode([
    'title' => $report_title,
    'reservations' => $reservations,
    'colis' => $colis,
    'totals' => $totals,
    'colis_totals' => $colis_totals
]);

$pdo = null;
?>