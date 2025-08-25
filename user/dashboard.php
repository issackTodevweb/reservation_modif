<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Accès non autorisé. Veuillez vous connecter en tant qu'utilisateur.";
    header("Location: ../index.php");
    exit();
}

// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $pdo = getConnection();
} catch (PDOException $e) {
    die("Erreur : Impossible de se connecter à la base de données : " . $e->getMessage());
}

// Récupérer les statistiques pour l'utilisateur
$user_id = $_SESSION['user_id'];

// Déterminer la période pour le rapport (par défaut : aujourd'hui)
$report_period = isset($_GET['report_period']) ? $_GET['report_period'] : 'daily';
$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d');
$report_month = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
$report_year = isset($_GET['report_year']) ? $_GET['report_year'] : date('Y');

// Nombre total de colis
try {
    $sql_colis = "SELECT COUNT(*) as total_colis, COALESCE(SUM(package_fee), 0) as total_package_fee 
                  FROM colis WHERE user_id = ?";
    $stmt_colis = $pdo->prepare($sql_colis);
    $stmt_colis->execute([$user_id]);
    $colis_data = $stmt_colis->fetch(PDO::FETCH_ASSOC);
    $total_colis = $colis_data['total_colis'];
    $total_package_fee = $colis_data['total_package_fee'];
} catch (PDOException $e) {
    die("Erreur lors de la récupération des colis : " . $e->getMessage());
}

// Nombre total de réservations et totaux financiers
try {
    $sql_reservations = "SELECT COUNT(*) as total_reservations, 
                                COALESCE(SUM(r.total_amount), 0) as total_amount,
                                COALESCE(SUM(f.tariff), 0) as total_tariff,
                                COALESCE(SUM(f.tax), 0) as total_tax,
                                COALESCE(SUM(f.port_fee), 0) as total_port_fee,
                                COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as total_mvola,
                                COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as total_cash,
                                COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as total_unpaid
                         FROM reservations r
                         JOIN trips t ON r.trip_id = t.id
                         LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                         AND (CASE 
                              WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                              ELSE 'Standard'
                              END) = f.trip_type
                         WHERE r.user_id = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
    $stmt_reservations = $pdo->prepare($sql_reservations);
    $stmt_reservations->execute([$user_id]);
    $reservations_data = $stmt_reservations->fetch(PDO::FETCH_ASSOC);
    $total_reservations = $reservations_data['total_reservations'];
    $total_tariff = $reservations_data['total_tariff'];
    $total_tax = $reservations_data['total_tax'];
    $total_port_fee = $reservations_data['total_port_fee'];
    $total_mvola = $reservations_data['total_mvola'];
    $total_cash = $reservations_data['total_cash'];
    $total_unpaid = $reservations_data['total_unpaid'];
} catch (PDOException $e) {
    die("Erreur lors de la récupération des réservations : " . $e->getMessage());
}

// Rapport périodique : Réservations selon la période sélectionnée
try {
    if ($report_period === 'daily') {
        $sql_periodic_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                                      FROM reservations r
                                      JOIN trips t ON r.trip_id = t.id
                                      LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                      AND (CASE 
                                           WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                           ELSE 'Standard'
                                           END) = f.trip_type
                                      WHERE r.user_id = ? AND DATE(r.created_at) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_reservations = $pdo->prepare($sql_periodic_reservations);
        $stmt_periodic_reservations->execute([$user_id, $report_date]);
        $report_title = "Réservations effectuées le " . date('d/m/Y', strtotime($report_date));
    } elseif ($report_period === 'monthly') {
        $sql_periodic_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                                      FROM reservations r
                                      JOIN trips t ON r.trip_id = t.id
                                      LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                      AND (CASE 
                                           WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                           ELSE 'Standard'
                                           END) = f.trip_type
                                      WHERE r.user_id = ? AND DATE_FORMAT(r.created_at, '%Y-%m') = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_reservations = $pdo->prepare($sql_periodic_reservations);
        $stmt_periodic_reservations->execute([$user_id, $report_month]);
        $report_title = "Réservations effectuées en " . date('m/Y', strtotime($report_month));
    } else { // yearly
        $sql_periodic_reservations = "SELECT r.passenger_name, f.tariff, f.port_fee, f.tax, r.total_amount, r.payment_status
                                      FROM reservations r
                                      JOIN trips t ON r.trip_id = t.id
                                      LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                      AND (CASE 
                                           WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                           ELSE 'Standard'
                                           END) = f.trip_type
                                      WHERE r.user_id = ? AND YEAR(r.created_at) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_reservations = $pdo->prepare($sql_periodic_reservations);
        $stmt_periodic_reservations->execute([$user_id, $report_year]);
        $report_title = "Réservations effectuées en " . $report_year;
    }
    $periodic_reservations = $stmt_periodic_reservations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des réservations périodiques : " . $e->getMessage());
}

// Rapport périodique : Colis selon la période sélectionnée
try {
    if ($report_period === 'daily') {
        $sql_periodic_colis = "SELECT sender_name, package_reference, package_fee
                               FROM colis 
                               WHERE user_id = ? AND DATE(created_at) = ?";
        $stmt_periodic_colis = $pdo->prepare($sql_periodic_colis);
        $stmt_periodic_colis->execute([$user_id, $report_date]);
    } elseif ($report_period === 'monthly') {
        $sql_periodic_colis = "SELECT sender_name, package_reference, package_fee
                               FROM colis 
                               WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $stmt_periodic_colis = $pdo->prepare($sql_periodic_colis);
        $stmt_periodic_colis->execute([$user_id, $report_month]);
    } else { // yearly
        $sql_periodic_colis = "SELECT sender_name, package_reference, package_fee
                               FROM colis 
                               WHERE user_id = ? AND YEAR(created_at) = ?";
        $stmt_periodic_colis = $pdo->prepare($sql_periodic_colis);
        $stmt_periodic_colis->execute([$user_id, $report_year]);
    }
    $periodic_colis = $stmt_periodic_colis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des colis périodiques : " . $e->getMessage());
}

// Totaux périodiques pour le tableau et le graphique
try {
    if ($report_period === 'daily') {
        $sql_periodic_totals = "SELECT COALESCE(SUM(f.tariff), 0) as periodic_tariff,
                                       COALESCE(SUM(f.tax), 0) as periodic_tax,
                                       COALESCE(SUM(f.port_fee), 0) as periodic_port_fee,
                                       COALESCE(SUM(r.total_amount), 0) as periodic_total,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as periodic_mvola,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as periodic_cash,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as periodic_unpaid
                                FROM reservations r
                                JOIN trips t ON r.trip_id = t.id
                                LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                AND (CASE 
                                     WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                     ELSE 'Standard'
                                     END) = f.trip_type
                                WHERE r.user_id = ? AND DATE(r.created_at) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_totals = $pdo->prepare($sql_periodic_totals);
        $stmt_periodic_totals->execute([$user_id, $report_date]);
    } elseif ($report_period === 'monthly') {
        $sql_periodic_totals = "SELECT COALESCE(SUM(f.tariff), 0) as periodic_tariff,
                                       COALESCE(SUM(f.tax), 0) as periodic_tax,
                                       COALESCE(SUM(f.port_fee), 0) as periodic_port_fee,
                                       COALESCE(SUM(r.total_amount), 0) as periodic_total,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as periodic_mvola,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as periodic_cash,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as periodic_unpaid
                                FROM reservations r
                                JOIN trips t ON r.trip_id = t.id
                                LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                AND (CASE 
                                     WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                     ELSE 'Standard'
                                     END) = f.trip_type
                                WHERE r.user_id = ? AND DATE_FORMAT(r.created_at, '%Y-%m') = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_totals = $pdo->prepare($sql_periodic_totals);
        $stmt_periodic_totals->execute([$user_id, $report_month]);
    } else { // yearly
        $sql_periodic_totals = "SELECT COALESCE(SUM(f.tariff), 0) as periodic_tariff,
                                       COALESCE(SUM(f.tax), 0) as periodic_tax,
                                       COALESCE(SUM(f.port_fee), 0) as periodic_port_fee,
                                       COALESCE(SUM(r.total_amount), 0) as periodic_total,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Mvola' THEN r.total_amount ELSE 0 END), 0) as periodic_mvola,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Espèce' THEN r.total_amount ELSE 0 END), 0) as periodic_cash,
                                       COALESCE(SUM(CASE WHEN r.payment_status = 'Non payé' THEN r.total_amount ELSE 0 END), 0) as periodic_unpaid
                                FROM reservations r
                                JOIN trips t ON r.trip_id = t.id
                                LEFT JOIN finances f ON r.passenger_type = f.passenger_type 
                                AND (CASE 
                                     WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                     ELSE 'Standard'
                                     END) = f.trip_type
                                WHERE r.user_id = ? AND YEAR(r.created_at) = ? AND r.payment_status != 'Annulé' AND r.payment_status != ''";
        $stmt_periodic_totals = $pdo->prepare($sql_periodic_totals);
        $stmt_periodic_totals->execute([$user_id, $report_year]);
    }
    $periodic_totals = $stmt_periodic_totals->fetch(PDO::FETCH_ASSOC);

    $sql_periodic_colis_totals = "SELECT COALESCE(SUM(package_fee), 0) as periodic_package_fee
                                  FROM colis 
                                  WHERE user_id = ? AND " . ($report_period === 'daily' ? "DATE(created_at) = ?" : ($report_period === 'monthly' ? "DATE_FORMAT(created_at, '%Y-%m') = ?" : "YEAR(created_at) = ?"));
    $stmt_periodic_colis_totals = $pdo->prepare($sql_periodic_colis_totals);
    $stmt_periodic_colis_totals->execute([$user_id, $report_period === 'daily' ? $report_date : ($report_period === 'monthly' ? $report_month : $report_year)]);
    $periodic_colis_totals = $stmt_periodic_colis_totals->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des totaux périodiques : " . $e->getMessage());
}

// Préparer les données pour l'exportation PDF
$report_data = [
    'title' => $report_title,
    'totals' => $periodic_totals,
    'colis_totals' => $periodic_colis_totals,
    'reservations' => $periodic_reservations,
    'colis' => $periodic_colis
];

$pdo = null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Utilisateur - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/user_dashboard.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Ajout des CDN pour jsPDF et autoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.3/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Tableau de bord</span></a>
            <a href="reservations.php"><i class="fas fa-book"></i><span>Réservations</span></a>
            <a href="list_reservations.php"><i class="fas fa-list"></i><span>Liste des Réservations</span></a>
            <a href="info_passagers.php"><i class="fas fa-users"></i><span>Infos Passagers</span></a>
            <a href="colis.php"><i class="fas fa-box"></i><span>Colis</span></a>
 
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>Consultez vos statistiques et gérez vos réservations et colis.</p>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <h2><?php echo $total_colis; ?></h2>
                    <p>Colis Enregistrés</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-book"></i>
                    <h2><?php echo $total_reservations; ?></h2>
                    <p>Réservations Effectuées</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill"></i>
                    <h2><?php echo number_format($total_tariff, 2); ?> KMF</h2>
                    <p>Total des Tarifs</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percent"></i>
                    <h2><?php echo number_format($total_tax, 2); ?> KMF</h2>
                    <p>Total des Taxes</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-anchor"></i>
                    <h2><?php echo number_format($total_port_fee, 2); ?> KMF</h2>
                    <p>Total des Frais Portuaires</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shipping-fast"></i>
                    <h2><?php echo number_format($total_package_fee, 2); ?> KMF</h2>
                    <p>Total des Frais de Colis</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h2><?php echo number_format($total_mvola, 2); ?> KMF</h2>
                    <p>Total Mvola</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <h2><?php echo number_format($total_cash, 2); ?> KMF</h2>
                    <p>Total Espèces</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-exclamation-circle"></i>
                    <h2><?php echo number_format($total_unpaid, 2); ?> KMF</h2>
                    <p>Total Non Payé</p>
                </div>
            </div>

            <!-- Rapport Périodique -->
            <div class="daily-report">
                <h2><?php echo htmlspecialchars($report_title); ?></h2>
                <form method="GET" class="date-filter">
                    <label for="report_period">Période : </label>
                    <select name="report_period" id="report_period" onchange="toggleDateInputs()">
                        <option value="daily" <?php echo $report_period === 'daily' ? 'selected' : ''; ?>>Journalier</option>
                        <option value="monthly" <?php echo $report_period === 'monthly' ? 'selected' : ''; ?>>Mensuel</option>
                        <option value="yearly" <?php echo $report_period === 'yearly' ? 'selected' : ''; ?>>Annuel</option>
                    </select>
                    <div id="daily-input" style="display: <?php echo $report_period === 'daily' ? 'inline-block' : 'none'; ?>;">
                        <label for="report_date">Date : </label>
                        <input type="date" id="report_date" name="report_date" value="<?php echo $report_date; ?>">
                    </div>
                    <div id="monthly-input" style="display: <?php echo $report_period === 'monthly' ? 'inline-block' : 'none'; ?>;">
                        <label for="report_month">Mois : </label>
                        <input type="month" id="report_month" name="report_month" value="<?php echo $report_month; ?>">
                    </div>
                    <div id="yearly-input" style="display: <?php echo $report_period === 'yearly' ? 'inline-block' : 'none'; ?>;">
                        <label for="report_year">Année : </label>
                        <input type="number" id="report_year" name="report_year" value="<?php echo $report_year; ?>" min="2000" max="<?php echo date('Y'); ?>">
                    </div>
                    <button type="submit">Filtrer</button>
                </form>
                <button onclick="exportToPDF()" class="export-btn"><i class="fas fa-file-pdf"></i> Exporter en PDF</button>

                <!-- Totaux financiers périodiques -->
                <h3>Totaux Financiers</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Total Tarifs (KMF)</th>
                            <th>Total Taxes (KMF)</th>
                            <th>Total Frais Portuaires (KMF)</th>
                            <th>Total Mvola (KMF)</th>
                            <th>Total Espèces (KMF)</th>
                            <th>Total Non Payé (KMF)</th>
                            <th>Total Réservations (KMF)</th>
                            <th>Total Frais de Colis (KMF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo number_format($periodic_totals['periodic_tariff'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_tax'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_port_fee'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_mvola'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_cash'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_unpaid'], 2); ?></td>
                            <td><?php echo number_format($periodic_totals['periodic_total'], 2); ?></td>
                            <td><?php echo number_format($periodic_colis_totals['periodic_package_fee'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Graphique -->
                <div class="chart-container">
                    <canvas id="periodicChart"></canvas>
                </div>

                <h3>Réservations</h3>
                <?php if (!empty($periodic_reservations)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Passager</th>
                                <th>Tarif (KMF)</th>
                                <th>Frais Portuaires (KMF)</th>
                                <th>Taxes (KMF)</th>
                                <th>Type de Paiement</th>
                                <th>Total (KMF)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodic_reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['passenger_name']); ?></td>
                                    <td><?php echo number_format($reservation['tariff'], 2); ?></td>
                                    <td><?php echo number_format($reservation['port_fee'], 2); ?></td>
                                    <td><?php echo number_format($reservation['tax'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['payment_status']); ?></td>
                                    <td><?php echo number_format($reservation['total_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune réservation trouvée pour cette période.</p>
                <?php endif; ?>

                <h3>Colis</h3>
                <?php if (!empty($periodic_colis)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Expéditeur</th>
                                <th>Référence</th>
                                <th>Frais de Port (KMF)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodic_colis as $colis): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($colis['sender_name']); ?></td>
                                    <td><?php echo htmlspecialchars($colis['package_reference']); ?></td>
                                    <td><?php echo number_format($colis['package_fee'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun colis trouvé pour cette période.</p>
                <?php endif; ?>
            </div>
        </main>
    <script>
        // Données pour le graphique
        const chartData = {
            labels: ['Tarifs', 'Taxes', 'Frais Portuaires', 'Mvola', 'Espèces', 'Non Payé', 'Réservations', 'Frais de Colis'],
            datasets: [{
                label: 'Totaux Périodiques (KMF)',
                data: [
                    <?php echo $periodic_totals['periodic_tariff']; ?>,
                    <?php echo $periodic_totals['periodic_tax']; ?>,
                    <?php echo $periodic_totals['periodic_port_fee']; ?>,
                    <?php echo $periodic_totals['periodic_mvola']; ?>,
                    <?php echo $periodic_totals['periodic_cash']; ?>,
                    <?php echo $periodic_totals['periodic_unpaid']; ?>,
                    <?php echo $periodic_totals['periodic_total']; ?>,
                    <?php echo $periodic_colis_totals['periodic_package_fee']; ?>
                ],
                backgroundColor: [
                    'rgba(30, 64, 175, 0.6)', // #1e40af
                    'rgba(249, 115, 22, 0.6)', // #f97316
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)', // Mvola
                    'rgba(255, 206, 86, 0.6)', // Espèces
                    'rgba(255, 99, 132, 0.6)', // Non payé
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 159, 64, 0.6)'
                ],
                borderColor: [
                    'rgba(30, 64, 175, 1)',
                    'rgba(249, 115, 22, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        };

        // Données pour l'exportation PDF
        const reportData = <?php echo json_encode($report_data); ?>;
    </script>
    <script src="../styles/js/user_dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>