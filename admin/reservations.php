<?php
session_start();
// Vérifier si l'utilisateur est connecté et a le rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
    header("Location: ../index.php");
    exit();
}
// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $conn = getConnection();
} catch (PDOException $e) {
    die("Erreur : Impossible de se connecter à la base de données. Vérifiez config/database.php : " . $e->getMessage());
}
// Gestion de la recherche
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
if (!empty($search_query)) {
    $where_clause = " WHERE r.passenger_name LIKE ? OR r.departure_date LIKE ? OR ref.reference_number LIKE ? OR tck.ticket_number LIKE ? OR r.nationality LIKE ? OR r.user_id LIKE ? OR r.modification LIKE ? OR r.penalty LIKE ?";
    $search_param = '%' . $search_query . '%';
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param];
}
// Récupérer la liste des réservations
try {
    $sql = "SELECT r.id, r.passenger_name, r.phone_number, r.nationality, r.passenger_type, r.with_baby, tck.ticket_type,
                   r.departure_date, t.departure_port, t.arrival_port, t.departure_time, ref.reference_number, tck.ticket_number,
                   r.seat_number AS selected_seat, r.payment_status, f.tariff, f.tax, f.port_fee, r.total_amount AS total,
                   u.username AS cashier_name, r.user_id, r.trip_id, r.reference_id, r.ticket_id, r.modification, r.penalty
            FROM reservations r
            LEFT JOIN trips t ON r.trip_id = t.id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN reference ref ON r.reference_id = ref.id
            LEFT JOIN tickets tck ON r.ticket_id = tck.id
            LEFT JOIN finances f ON r.passenger_type = f.passenger_type
                AND f.trip_type = (
                    CASE
                        WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                        ELSE 'Standard'
                    END
                )
            $where_clause
            ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des réservations : " . $e->getMessage());
}
// Récupérer les trajets pour la modale
try {
    $stmt = $conn->query("SELECT id, departure_port, arrival_port, departure_date, type, departure_time FROM trips ORDER BY departure_date");
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des trajets : " . $e->getMessage());
}
// Récupérer les places disponibles pour chaque trajet
try {
    $stmt = $conn->query("SELECT s.id, s.trip_id, s.seat_number, s.status, t.departure_date
                          FROM seats s
                          JOIN trips t ON s.trip_id = t.id
                          WHERE s.status = 'free'
                          ORDER BY t.departure_date, s.seat_number");
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des places : " . $e->getMessage());
}
// Récupérer les utilisateurs pour afficher le nom du caissier dans la modale
try {
    $stmt = $conn->query("SELECT id, username FROM users WHERE role = 'cashier' ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des utilisateurs : " . $e->getMessage());
}
// Ajouter ou mettre à jour une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $id = (int)$_POST['id'];
    $passenger_name = trim($_POST['passenger_name']);
    $phone_number = trim($_POST['phone_number']);
    $nationality = trim($_POST['nationality']);
    $passenger_type = $_POST['passenger_type'];
    $with_baby = $_POST['with_baby'];
    $departure_date = $_POST['departure_date'];
    $trip_id = (int)$_POST['trip_id'];
    $seat_number = $_POST['seat_number'];
    $payment_status = $_POST['payment_status'];
    $modification = trim($_POST['modification']);
    $penalty = trim($_POST['penalty']);
    // Récupérer l'user_id existant de la réservation (non modifiable)
    try {
        $stmt = $conn->prepare("SELECT user_id, seat_number AS original_seat_number FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $reservation['user_id'];
        $original_seat_number = $reservation['original_seat_number'];
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la récupération de l'ID caissier : " . $e->getMessage();
        header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
        exit();
    }
    // Récupérer tariff, tax, port_fee depuis finances
    try {
        $stmt = $conn->prepare("SELECT f.tariff, f.tax, f.port_fee
                               FROM finances f
                               JOIN trips t ON t.id = ?
                               WHERE f.passenger_type = ?
                               AND f.trip_type = (
                                   CASE
                                       WHEN t.departure_port = 'ANJ' AND t.arrival_port = 'MWA' THEN 'ANJ-MWA'
                                       ELSE 'Standard'
                                   END
                               )");
        $stmt->execute([$trip_id, $passenger_type]);
        $finance = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_amount = $finance ? ($finance['tariff'] + $finance['tax'] + $finance['port_fee']) : 0;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la récupération des finances : " . $e->getMessage();
        header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
        exit();
    }
    // Vérifier la disponibilité du siège
    try {
        $stmt = $conn->prepare("SELECT status FROM seats WHERE trip_id = ? AND seat_number = ?");
        $stmt->execute([$trip_id, $seat_number]);
        $seat = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seat || ($seat['status'] !== 'free' && $seat_number !== $original_seat_number)) {
            $_SESSION['error'] = "Le siège sélectionné n'est pas disponible.";
            header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la vérification du siège : " . $e->getMessage();
        header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
        exit();
    }
    // Mettre à jour la réservation
    try {
        $conn->beginTransaction();
        // Restaurer l'ancienne place si différente
        if ($seat_number !== $original_seat_number) {
            $stmt = $conn->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?");
            $stmt->execute([$trip_id, $original_seat_number]);
        }
        $stmt = $conn->prepare("UPDATE reservations SET passenger_name = ?, phone_number = ?, nationality = ?,
                               passenger_type = ?, with_baby = ?, departure_date = ?, trip_id = ?, seat_number = ?,
                               payment_status = ?, total_amount = ?, user_id = ?, modification = ?, penalty = ? WHERE id = ?");
        $stmt->execute([$passenger_name, $phone_number, $nationality, $passenger_type, $with_baby, $departure_date,
                        $trip_id, $seat_number, $payment_status, $total_amount, $user_id, $modification, $penalty, $id]);
        // Mettre à jour le siège
        if ($seat_number !== $original_seat_number) {
            $stmt = $conn->prepare("UPDATE seats SET status = 'occupied' WHERE trip_id = ? AND seat_number = ?");
            $stmt->execute([$trip_id, $seat_number]);
        }
        $conn->commit();
        $_SESSION['success'] = "Réservation mise à jour avec succès.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
    header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
    exit();
}
// Supprimer une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $id = (int)$_POST['delete_reservation'];
    try {
        $stmt = $conn->prepare("SELECT trip_id, seat_number, reference_id, ticket_id FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            $conn->beginTransaction();
            // Supprimer la réservation
            $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            // Restaurer la référence
            $stmt = $conn->prepare("UPDATE reference SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['reference_id']]);
            // Restaurer le ticket
            $stmt = $conn->prepare("UPDATE tickets SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['ticket_id']]);
            // Restaurer la place
            $stmt = $conn->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?");
            $stmt->execute([$reservation['trip_id'], $reservation['seat_number']]);
            $conn->commit();
            $_SESSION['success'] = "Réservation supprimée avec succès.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
    exit();
}
// Annuler une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $id = (int)$_POST['cancel_reservation'];
    try {
        $stmt = $conn->prepare("SELECT trip_id, seat_number, reference_id, ticket_id FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE reservations SET payment_status = 'Annulé' WHERE id = ?");
            $stmt->execute([$id]);
            // Restaurer la référence
            $stmt = $conn->prepare("UPDATE reference SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['reference_id']]);
            // Restaurer le ticket
            $stmt = $conn->prepare("UPDATE tickets SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['ticket_id']]);
            // Restaurer la place
            $stmt = $conn->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?");
            $stmt->execute([$reservation['trip_id'], $reservation['seat_number']]);
            $conn->commit();
            $_SESSION['success'] = "Réservation annulée avec succès.";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de l'annulation : " . $e->getMessage();
    }
    header("Location: reservations.php" . (!empty($search_query) ? "?search=" . urlencode($search_query) : ""));
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réservations - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/admin_reservations.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .ticket-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .ticket-modal-content {
            background: #ffffff;
            margin: 5% auto;
            padding: 5px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }
        .ticket {
            width: 100%;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 6px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto auto auto auto;
            gap: 2px;
            padding: 5px;
            font-family: 'Helvetica', sans-serif;
            font-size: 9px;
        }
        .section {
            padding: 3px;
            border: 1px solid #e5e5e5;
            border-radius: 3px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .section-1a {
            grid-column: 1 / 2;
            grid-row: 1 / 2;
            text-align: center;
        }
        .section-1a img {
            width: 100%;
            height: auto;
            max-height: 40px;
        }
        .section-1b {
            grid-column: 2 / 3;
            grid-row: 1 / 2;
            text-align: center;
            justify-content: center;
        }
        .section-1b h1 {
            font-size: 11px;
            color: #1e40af;
            margin-bottom: 2px;
            font-weight: bold;
        }
        .section-1b p {
            font-size: 9px;
            color: #333;
        }
        .section-2a, .section-2b, .section-3a, .section-3b, .section-4a {
            font-size: 9px;
            padding: 3px;
        }
        .section-2a {
            grid-column: 1 / 2;
            grid-row: 2 / 3;
        }
        .section-2b {
            grid-column: 2 / 3;
            grid-row: 2 / 3;
        }
        .section-3a {
            grid-column: 1 / 2;
            grid-row: 3 / 4;
        }
        .section-3b {
            grid-column: 2 / 3;
            grid-row: 3 / 4;
        }
        .section-4a {
            grid-column: 1 / 3;
            grid-row: 4 / 5;
        }
        .section-4 {
            grid-column: 1 / 3;
            grid-row: 5 / 6;
            text-align: left;
            font-size: 9px;
            color: #333;
            padding: 3px;
            border-top: 1px solid #e0e0e0;
        }
        .section p {
            margin: 0.5px 0;
            color: #333;
        }
        .section p strong {
            color: #1e40af;
            font-size: 9px;
        }
        .section p .translation {
            font-size: 7px;
            color: #666;
            font-style: italic;
            margin-top: 0.3px;
        }
        .print-btn, .pdf-btn {
            cursor: pointer;
            display: inline-block;
            margin: 5px 4px;
            padding: 4px 10px;
            background: #f97316;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 8px;
            z-index: 1002;
            pointer-events: auto;
        }
        .print-btn:hover, .pdf-btn:hover {
            background: #ea580c;
        }
        .ticket-close {
            cursor: pointer;
            position: absolute;
            top: 4px;
            right: 8px;
            font-size: 14px;
            color: #333;
            z-index: 1003;
            pointer-events: auto;
        }
        @media print {
            body, .main-content {
                background: none;
                margin: 0;
                padding: 0;
            }
            .sidebar, .search-container, .reservations-container, .print-btn, .pdf-btn, .ticket-close, .overlay h1 {
                display: none;
            }
            .ticket-modal {
                display: block;
                position: static;
                background: none;
                width: 100%;
                height: auto;
            }
            .ticket-modal-content {
                margin: 3mm;
                padding: 2mm;
                border: none;
                box-shadow: none;
                width: 190mm;
                max-width: 190mm;
                height: auto;
                box-sizing: border-box;
                page-break-after: avoid;
            }
            .ticket {
                border: none;
                box-shadow: none;
                font-size: 9px;
                gap: 1mm;
                padding: 2mm;
                width: 100%;
                box-sizing: border-box;
                transform: scale(0.85);
                transform-origin: top left;
            }
            .section-1a img {
                max-height: 10mm;
            }
            .section-1b h1 {
                font-size: 11px;
                margin-bottom: 1mm;
            }
            .section-1b p {
                font-size: 9px;
            }
            .section-2a, .section-2b, .section-3a, .section-3b, .section-4a {
                font-size: 9px;
                padding: 1mm;
            }
            .section p {
                margin: 0.3mm 0;
            }
            .section p strong {
                font-size: 9px;
            }
            .section p .translation {
                font-size: 7px;
                margin-top: 0.2mm;
            }
            .section-4 {
                font-size: 9px;
                padding: 1mm;
                line-height: 1.1;
            }
            @page {
                size: A4;
                margin: 3mm;
            }
        }
        @media (max-width: 600px) {
            .ticket {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(8, auto);
            }
            .section-1a, .section-1b, .section-2a, .section-2b, .section-3a, .section-3b, .section-4a, .section-4 {
                grid-column: 1 / 2;
            }
            .section-1a { grid-row: 1 / 2; }
            .section-1b { grid-row: 2 / 3; }
            .section-2a { grid-row: 3 / 4; }
            .section-2b { grid-row: 4 / 5; }
            .section-3a { grid-row: 5 / 6; }
            .section-3b { grid-row: 6 / 7; }
            .section-4a { grid-row: 7 / 8; }
            .section-4 { grid-row: 8 / 9; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a>
            <a href="trips.php"><i class="fas fa-ticket-alt"></i> Gestion des Trajets</a>
            <a href="finances.php"><i class="fas fa-money-bill-wave"></i> Finances</a>
            <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="seats.php"><i class="fas fa-chair"></i> Gestion des Places</a>
            <a href="reservations.php" class="active"><i class="fas fa-book"></i> Réservations</a>
            <a href="tickets.php"><i class="fas fa-ticket"></i> Gestion des Tickets</a>
            <a href="references.php"><i class="fas fa-barcode"></i> Références</a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Se Déconnecter</a>
        </nav>
    </aside>
    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des Réservations</h1>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <div class="reservations-container">
                <h2>Liste des Réservations</h2>
                <!-- Barre de recherche -->
                <div class="search-container">
                    <form method="GET" action="reservations.php">
                        <input type="text" name="search" placeholder="Rechercher par nom, date, référence, ticket, ID caissier, modification, pénalité..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn"><i class="fas fa-search"></i> Rechercher</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="reservations.php" class="btn reset-btn">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (!empty($reservations)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom du Passager</th>
                                <th>Téléphone</th>
                                <th>Nationalité</th>
                                <th>Type de Passager</th>
                                <th>Avec Bébé</th>
                                <th>Type de Ticket</th>
                                <th>Date de Départ</th>
                                <th>Trajet</th>
                                <th>Référence</th>
                                <th>Numéro de Ticket</th>
                                <th>Place</th>
                                <th>Statut de Paiement</th>
                                <th>Tarif (KMF)</th>
                                <th>Taxe (KMF)</th>
                                <th>Frais Port (KMF)</th>
                                <th>Total (KMF)</th>
                                <th>Modification</th>
                                <th>Pénalité</th>
                                <th>Caissier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['passenger_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['nationality']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['passenger_type']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['with_baby']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['ticket_type']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['departure_date']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['departure_port'] . ' → ' . $reservation['arrival_port']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['reference_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['selected_seat']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['tariff'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['tax'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['port_fee'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['total'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['modification'] ?? 'Aucune'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['penalty'] ?? 'Aucune'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['cashier_name']); ?></td>
                                    <td>
                                        <button class="btn view-ticket-btn"
                                                data-id="<?php echo $reservation['id']; ?>"
                                                data-passenger-name="<?php echo htmlspecialchars($reservation['passenger_name']); ?>"
                                                data-phone-number="<?php echo htmlspecialchars($reservation['phone_number']); ?>"
                                                data-nationality="<?php echo htmlspecialchars($reservation['nationality']); ?>"
                                                data-passenger-type="<?php echo htmlspecialchars($reservation['passenger_type']); ?>"
                                                data-with-baby="<?php echo htmlspecialchars($reservation['with_baby']); ?>"
                                                data-departure-date="<?php echo htmlspecialchars($reservation['departure_date']); ?>"
                                                data-departure-time="<?php echo htmlspecialchars($reservation['departure_time']); ?>"
                                                data-departure-port="<?php echo htmlspecialchars($reservation['departure_port']); ?>"
                                                data-arrival-port="<?php echo htmlspecialchars($reservation['arrival_port']); ?>"
                                                data-reference-number="<?php echo htmlspecialchars($reservation['reference_number']); ?>"
                                                data-ticket-number="<?php echo htmlspecialchars($reservation['ticket_number']); ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($reservation['ticket_type']); ?>"
                                                data-seat-number="<?php echo htmlspecialchars($reservation['selected_seat']); ?>"
                                                data-tariff="<?php echo number_format($reservation['tariff'] ?? 0, 2); ?>"
                                                data-port-fee="<?php echo number_format($reservation['port_fee'] ?? 0, 2); ?>"
                                                data-tax="<?php echo number_format($reservation['tax'] ?? 0, 2); ?>"
                                                data-total-amount="<?php echo number_format($reservation['total'], 2); ?>"
                                                data-modification="<?php echo htmlspecialchars($reservation['modification'] ?? 'Aucune'); ?>"
                                                data-penalty="<?php echo htmlspecialchars($reservation['penalty'] ?? 'Aucune'); ?>">Voir le Ticket</button>
                                        <button class="btn edit-btn"
                                                data-id="<?php echo $reservation['id']; ?>"
                                                data-passenger-name="<?php echo htmlspecialchars($reservation['passenger_name']); ?>"
                                                data-phone-number="<?php echo htmlspecialchars($reservation['phone_number']); ?>"
                                                data-nationality="<?php echo htmlspecialchars($reservation['nationality']); ?>"
                                                data-passenger-type="<?php echo htmlspecialchars($reservation['passenger_type']); ?>"
                                                data-with-baby="<?php echo htmlspecialchars($reservation['with_baby']); ?>"
                                                data-departure-date="<?php echo htmlspecialchars($reservation['departure_date']); ?>"
                                                data-trip-id="<?php echo $reservation['trip_id']; ?>"
                                                data-seat-number="<?php echo htmlspecialchars($reservation['selected_seat']); ?>"
                                                data-payment-status="<?php echo htmlspecialchars($reservation['payment_status']); ?>"
                                                data-user-id="<?php echo $reservation['user_id']; ?>"
                                                data-cashier-name="<?php echo htmlspecialchars($reservation['cashier_name']); ?>"
                                                data-modification="<?php echo htmlspecialchars($reservation['modification'] ?? ''); ?>"
                                                data-penalty="<?php echo htmlspecialchars($reservation['penalty'] ?? ''); ?>">Modifier</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer cette réservation ?');">
                                            <input type="hidden" name="delete_reservation" value="<?php echo $reservation['id']; ?>">
                                            <button type="submit" class="btn delete-btn">Supprimer</button>
                                        </form>
                                        <?php if ($reservation['payment_status'] !== 'Annulé'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment annuler cette réservation ?');">
                                                <input type="hidden" name="cancel_reservation" value="<?php echo $reservation['id']; ?>">
                                                <button type="submit" class="btn cancel-btn">Annuler</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune réservation trouvée.</p>
                <?php endif; ?>
            </div>
            <!-- Modale pour modification -->
            <div id="editReservationModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <h2>Modifier la réservation</h2>
                    <form id="editReservationForm" method="POST">
                        <input type="hidden" name="update_reservation" value="1">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_passenger_name">Nom du Passager</label>
                            <input type="text" name="passenger_name" id="edit_passenger_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone_number">Téléphone</label>
                            <input type="text" name="phone_number" id="edit_phone_number" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_nationality">Nationalité</label>
                            <input type="text" name="nationality" id="edit_nationality" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_passenger_type">Type de Passager</label>
                            <select name="passenger_type" id="edit_passenger_type" required>
                                <option value="Comorien Adulte">Comorien Adulte</option>
                                <option value="Comorien Enfant">Comorien Enfant</option>
                                <option value="Étranger Adulte">Étranger Adulte</option>
                                <option value="Étranger Enfant">Étranger Enfant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_with_baby">Avec Bébé</label>
                            <select name="with_baby" id="edit_with_baby" required>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_departure_date">Date de Départ</label>
                            <input type="date" name="departure_date" id="edit_departure_date" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_trip_id">Trajet</label>
                            <select name="trip_id" id="edit_trip_id" required>
                                <?php foreach ($trips as $trip): ?>
                                    <option value="<?php echo $trip['id']; ?>">
                                        <?php echo htmlspecialchars($trip['departure_port'] . ' → ' . $trip['arrival_port'] . ' (' . $trip['departure_date'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_seat_number">Place</label>
                            <select name="seat_number" id="edit_seat_number" required>
                                <?php foreach ($seats as $seat): ?>
                                    <option value="<?php echo htmlspecialchars($seat['seat_number']); ?>"
                                            data-trip-id="<?php echo $seat['trip_id']; ?>">
                                        <?php echo htmlspecialchars($seat['seat_number'] . ' (' . $seat['departure_date'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_status">Statut de Paiement</label>
                            <select name="payment_status" id="edit_payment_status" required>
                                <option value="Mvola">Mvola</option>
                                <option value="Espèce">Espèce</option>
                                <option value="Non payé">Non payé</option>
                                <option value="Annulé">Annulé</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_modification">Modification</label>
                            <input type="text" name="modification" id="edit_modification" placeholder="Entrez les détails de modification">
                        </div>
                        <div class="form-group">
                            <label for="edit_penalty">Pénalité</label>
                            <input type="text" name="penalty" id="edit_penalty" placeholder="Entrez les détails de pénalité">
                        </div>
                        <div class="form-group">
                            <label for="edit_user_id">ID d'utilisateur</label>
                            <input type="text" id="edit_user_id" readonly>
                            <input type="hidden" name="user_id" id="edit_user_id_hidden">
                        </div>
                        <button type="submit" class="btn update-btn">Mettre à jour</button>
                    </form>
                </div>
            </div>
            <!-- Modale pour afficher le ticket -->
            <div id="ticketModal" class="ticket-modal">
                <div class="ticket-modal-content">
                    <span class="ticket-close">&times;</span>
                    <div class="ticket">
                        <div class="section section-1a">
                            <img src="../images/ACX.png" alt="Adore Comores Express Logo">
                        </div>
                        <div class="section section-1b">
                            <h1>Billet Ferry Rapide</h1>
                            <p>Fast Ferry Ticket</p>
                        </div>
                        <div class="section section-2a">
                            <p><strong>Numéro de Billet :</strong> <span id="ticket_number"></span><br><span class="translation">Ticket Number</span></p>
                            <p><strong>Date de Voyage :</strong> <span id="departure_date"></span><br><span class="translation">Travel Date</span></p>
                            <p><strong>Nom du Passager :</strong> <span id="passenger_name"></span><br><span class="translation">Passenger Name</span></p>
                            <p><strong>Route :</strong> <span id="route"></span><br><span class="translation">Route</span></p>
                        </div>
                        <div class="section section-2b">
                            <p><strong>Nationalité :</strong> <span id="nationality"></span><br><span class="translation">Nationality</span></p>
                            <p><strong>Téléphone :</strong> <span id="phone_number"></span><br><span class="translation">Phone Number</span></p>
                            <p><strong>Type de Billet :</strong> <span id="ticket_type"></span><br><span class="translation">Ticket Type</span></p>
                            <p><strong>Avec Bébé ? :</strong> <span id="with_baby"></span><br><span class="translation">With Baby?</span></p>
                        </div>
                        <div class="section section-3a">
                            <p><strong>Enregistrement en Ville :</strong> 2h avant départ<br><span class="translation">City Check-in: 2 hours before departure</span></p>
                            <p><strong>Enregistrement au Port :</strong> 1h avant départ<br><span class="translation">Port Check-in: 1 hour before departure</span></p>
                            <p><strong>Départ du Bateau :</strong> <span id="departure_time"></span><br><span class="translation">Boat Departure</span></p>
                        </div>
                        <div class="section section-3b">
                            <p><strong>Numéro de Reçu :</strong> <span id="reference_number"></span><br><span class="translation">Receipt Number</span></p>
                            <p><strong>Tarif :</strong> <span id="tariff"></span> KMF<br><span class="translation">Fare</span></p>
                            <p><strong>Taxe :</strong> <span id="tax"></span> KMF<br><span class="translation">Tax</span></p>
                            <p><strong>Frais de Port :</strong> <span id="port_fee"></span> KMF<br><span class="translation">Port Fee</span></p>
                            <p><strong>Total à Payer :</strong> <span id="total_amount"></span> KMF<br><span class="translation">Total to Pay</span></p>
                        </div>
                        <div class="section section-4a">
                            <p><strong>Modification :</strong> <span id="modification"></span><br><span class="translation">Modification</span></p>
                            <p><strong>Pénalité :</strong> <span id="penalty"></span><br><span class="translation">Penalty</span></p>
                        </div>
                        <div class="section section-4">
                            <p><strong>Conditions Générales / General Conditions:</strong><br>
                            Billet nominatif : Non cessible sans accord du transporteur (Art. 686).<br>
                            <span class="translation">Non-transferable ticket: Not transferable without carrier’s consent.</span><br>
                            <strong>Modifications / Changes:</strong><br>
                            • Nom / Name: 10 000 KMF<br>
                            • Date/heure / Date or time: 5 000 KMF<br>
                            <strong>Embarquement / Boarding:</strong><br>
                            Le passager doit arriver à l’heure. Retard = No Show = Annulation sans remboursement (100 %, Art. 688).<br>
                            <span class="translation">Passenger must arrive on time. Delay = No Show = Cancellation with no refund (100%).</span><br>
                            <strong>Annulation / Cancellation:</strong><br>
                            • Plus de 72h / More than 72h: 25 % retenus<br>
                            • 72h – 24h: 50 % retenus<br>
                            • Moins de 24h ou No Show / Less than 24h or No Show: 100 % retenus<br>
                            <strong>Ports de départ / Departure ports:</strong><br>
                            Mohéli: Hoani – Anjouan: Dodin – Grande Comore: Ouroveni<br>
                            (Transferts inclus: Moroni-Ouroveni, Fomboni-Hoani)<br>
                            <strong>Objets interdits / Prohibited items:</strong> Drogues, armes, briquets, produits inflammables.<br>
                            <span class="translation">Prohibited items: Drugs, weapons, lighters, flammable products.</span><br>
                            <strong>Objets de valeur / Valuables:</strong> Déclaration obligatoire à l’enregistrement. Non déclarés = aucune responsabilité.<br>
                            <span class="translation">Valuables: Mandatory declaration at check-in. Undeclared = no liability.</span><br>
                            <strong>Bagages / Luggage:</strong> En cas de perte, déclaration immédiate au commandant. Aucune réclamation après débarquement.<br>
                            <span class="translation">Luggage: In case of loss, immediate declaration to the captain. No claims after disembarkation.</span><br>
                            <strong>Accès à bord / Boarding:</strong> Interdit sans billet ou numéro d’embarquement.<br>
                            <span class="translation">Boarding: Prohibited without ticket or boarding number.</span><br>
                            <strong>Acceptation / Acceptance:</strong> L’achat du billet vaut acceptation de toutes ces conditions (Art. 686, 688, 689).<br>
                            <span class="translation">Acceptance: Purchase of the ticket implies acceptance of all these conditions (Art. 686, 688, 689).</span>
                            </p>
                        </div>
                    </div>
                    <button class="print-btn" onclick="printTicket()">Imprimer le Ticket</button>
                    <button class="pdf-btn" onclick="exportToPDF()">Exporter en PDF</button>
                </div>
            </div>
        </div>
    </main>
    <script src="../styles/js/admin_reservations.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Script ticket modal chargé');
            const ticketModal = document.getElementById('ticketModal');
            const ticketClose = document.querySelector('.ticket-close');
            const viewTicketButtons = document.querySelectorAll('.view-ticket-btn');
            const editModal = document.getElementById('editReservationModal');
            const editClose = document.querySelector('#editReservationModal .modal-close');
            const editButtons = document.querySelectorAll('.edit-btn');
            viewTicketButtons.forEach(button => {
                button.addEventListener('click', () => {
                    console.log(`Bouton Voir le Ticket cliqué pour la réservation ID: ${button.dataset.id}`);
                    document.getElementById('ticket_number').textContent = button.dataset.ticketNumber;
                    document.getElementById('departure_date').textContent = button.dataset.departureDate;
                    document.getElementById('passenger_name').textContent = button.dataset.passengerName;
                    document.getElementById('route').textContent = `${button.dataset.departurePort} -> ${button.dataset.arrivalPort}`;
                    document.getElementById('nationality').textContent = button.dataset.nationality;
                    document.getElementById('phone_number').textContent = button.dataset.phoneNumber;
                    document.getElementById('ticket_type').textContent = button.dataset.ticketType;
                    document.getElementById('with_baby').textContent = button.dataset.withBaby;
                    document.getElementById('departure_time').textContent = `${button.dataset.departureDate} ${button.dataset.departureTime}`;
                    document.getElementById('reference_number').textContent = button.dataset.referenceNumber;
                    document.getElementById('tariff').textContent = button.dataset.tariff;
                    document.getElementById('tax').textContent = button.dataset.tax;
                    document.getElementById('port_fee').textContent = button.dataset.portFee;
                    document.getElementById('total_amount').textContent = button.dataset.totalAmount;
                    document.getElementById('modification').textContent = button.dataset.modification;
                    document.getElementById('penalty').textContent = button.dataset.penalty;
                    ticketModal.style.display = 'block';
                    console.log('Modale de ticket affichée');
                });
            });
            ticketClose.addEventListener('click', () => {
                ticketModal.style.display = 'none';
                console.log('Modale de ticket fermée');
            });
            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    document.getElementById('edit_id').value = button.dataset.id;
                    document.getElementById('edit_passenger_name').value = button.dataset.passengerName;
                    document.getElementById('edit_phone_number').value = button.dataset.phoneNumber;
                    document.getElementById('edit_nationality').value = button.dataset.nationality;
                    document.getElementById('edit_passenger_type').value = button.dataset.passengerType;
                    document.getElementById('edit_with_baby').value = button.dataset.withBaby;
                    document.getElementById('edit_departure_date').value = button.dataset.departureDate;
                    document.getElementById('edit_trip_id').value = button.dataset.tripId;
                    document.getElementById('edit_seat_number').value = button.dataset.seatNumber;
                    document.getElementById('edit_payment_status').value = button.dataset.paymentStatus;
                    document.getElementById('edit_user_id').value = `${button.dataset.cashierName} (ID: ${button.dataset.userId})`;
                    document.getElementById('edit_user_id_hidden').value = button.dataset.userId;
                    document.getElementById('edit_modification').value = button.dataset.modification;
                    document.getElementById('edit_penalty').value = button.dataset.penalty;
                    editModal.style.display = 'block';
                });
            });
            editClose.addEventListener('click', () => {
                editModal.style.display = 'none';
                console.log('Modale de modification fermée');
            });
            window.addEventListener('click', (event) => {
                if (event.target === ticketModal) {
                    ticketModal.style.display = 'none';
                    console.log('Modale de ticket fermée via clic extérieur');
                }
                if (event.target === editModal) {
                    editModal.style.display = 'none';
                    console.log('Modale de modification fermée via clic extérieur');
                }
            });
            window.printTicket = function() {
                console.log('Bouton Imprimer cliqué');
                window.print();
            };
            window.exportToPDF = function() {
                console.log('Bouton Exporter en PDF cliqué');
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });
                const ticketData = {
                    ticket_number: document.getElementById('ticket_number').textContent,
                    departure_date: document.getElementById('departure_date').textContent,
                    passenger_name: document.getElementById('passenger_name').textContent,
                    route: document.getElementById('route').textContent,
                    nationality: document.getElementById('nationality').textContent,
                    phone_number: document.getElementById('phone_number').textContent,
                    ticket_type: document.getElementById('ticket_type').textContent,
                    with_baby: document.getElementById('with_baby').textContent,
                    checkin_city: '2h avant départ / City Check-in: 2 hours before departure',
                    checkin_port: '1h avant départ / Port Check-in: 1 hour before departure',
                    departure_time: document.getElementById('departure_time').textContent,
                    reference_number: document.getElementById('reference_number').textContent,
                    tariff: document.getElementById('tariff').textContent + ' KMF',
                    tax: document.getElementById('tax').textContent + ' KMF',
                    port_fee: document.getElementById('port_fee').textContent + ' KMF',
                    total_amount: document.getElementById('total_amount').textContent + ' KMF',
                    modification: document.getElementById('modification').textContent,
                    penalty: document.getElementById('penalty').textContent,
                    conditions: 'Billet nominatif : Non cessible sans accord du transporteur (Art. 686).\n' +
                                'Non-transferable ticket: Not transferable without carrier’s consent.\n' +
                                'Modifications / Changes:\n' +
                                '• Nom / Name: 10 000 KMF\n' +
                                '• Date/heure / Date or time: 5 000 KMF\n' +
                                'Embarquement / Boarding:\n' +
                                'Le passager doit arriver à l’heure. Retard = No Show = Annulation sans remboursement (100 %, Art. 688).\n' +
                                'Passenger must arrive on time. Delay = No Show = Cancellation with no refund (100%).\n' +
                                'Annulation / Cancellation:\n' +
                                '• Plus de 72h / More than 72h: 25 % retenus\n' +
                                '• 72h – 24h: 50 % retenus\n' +
                                '• Moins de 24h ou No Show / Less than 24h or No Show: 100 % retenus\n' +
                                'Ports de départ / Departure ports:\n' +
                                'Mohéli: Hoani – Anjouan: Dodin – Grande Comore: Ouroveni\n' +
                                '(Transferts inclus: Moroni-Ouroveni, Fomboni-Hoani)\n' +
                                'Objets interdits / Prohibited items: Drogues, armes, briquets, produits inflammables.\n' +
                                'Prohibited items: Drugs, weapons, lighters, flammable products.\n' +
                                'Objets de valeur / Valuables: Déclaration obligatoire à l’enregistrement. Non déclarés = aucune responsabilité.\n' +
                                'Valuables: Mandatory declaration at check-in. Undeclared = no liability.\n' +
                                'Bagages / Luggage: En cas de perte, déclaration immédiate au commandant. Aucune réclamation après débarquement.\n' +
                                'Luggage: In case of loss, immediate declaration to the captain. No claims after disembarkation.\n' +
                                'Accès à bord / Boarding: Interdit sans billet ou numéro d’embarquement.\n' +
                                'Boarding: Prohibited without ticket or boarding number.\n' +
                                'Acceptation / Acceptance: L’achat du billet vaut acceptation de toutes ces conditions (Art. 686, 688, 689).\n' +
                                'Acceptance: Purchase of the ticket implies acceptance of all these conditions (Art. 686, 688, 689).'
                };
                // Utiliser l'image en base64 pour le logo
                const logoBase64 = 'data:image/png;base64,YOUR_BASE64_STRING_HERE'; // Remplacez par la chaîne base64 réelle de ACX.png
                try {
                    // Définir les marges et la largeur utile
                    const pageWidth = 210;
                    const margin = 5;
                    const contentWidth = pageWidth - 2 * margin;
                    const colWidth = contentWidth / 2 - 2;
                    // Ajouter le logo en haut à gauche (section-1a)
                    doc.addImage(logoBase64, 'PNG', margin, 5, 30, 10);
                    doc.setFontSize(5);
                    doc.text('Logo Adore Comores Express', margin, 17);
                    // Titre à droite du logo (section-1b)
                    let y = 7;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Billet Ferry Rapide', margin + colWidth + 2, y, { align: 'center' });
                    y += 4;
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Fast Ferry Ticket', margin + colWidth + 2, y, { align: 'center' });
                    y += 5;
                    // Section-2a (colonne gauche)
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro de Billet: ' + ticketData.ticket_number, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Number', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Date de Voyage: ' + ticketData.departure_date, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Travel Date', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nom du Passager: ' + ticketData.passenger_name, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Passenger Name', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Route: ' + ticketData.route, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Route', margin, y + 2);
                    y += 5;
                    // Section-2b (colonne droite)
                    let yRight = 16;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nationalité: ' + ticketData.nationality, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Nationality', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Téléphone: ' + ticketData.phone_number, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Phone Number', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Type de Billet: ' + ticketData.ticket_type, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Type', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Avec Bébé?: ' + ticketData.with_baby, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('With Baby?', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    // Section-3a (colonne gauche)
                    y = Math.max(y, yRight);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Ville: ' + ticketData.checkin_city.split(' / ')[0], margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('City Check-in', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Port: ' + ticketData.checkin_port.split(' / ')[0], margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Check-in', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Départ Bateau: ' + ticketData.departure_time, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Boat Departure', margin, y + 2);
                    y += 5;
                    // Section-3b (colonne droite)
                    yRight = Math.max(yRight, y - 15);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro Reçu: ' + ticketData.reference_number, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Receipt Number', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Tarif: ' + ticketData.tariff, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Fare', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Taxe: ' + ticketData.tax, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Tax', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Frais Port: ' + ticketData.port_fee, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Fee', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Total: ' + ticketData.total_amount, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Total to Pay', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    // Section-4a (pleine largeur)
                    y = Math.max(y, yRight);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Modification: ' + ticketData.modification, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Modification', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Pénalité: ' + ticketData.penalty, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Penalty', margin, y + 2);
                    y += 5;
                    // Section-4 (pleine largeur)
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'normal');
                    const splitConditions = doc.splitTextToSize(ticketData.conditions, contentWidth);
                    doc.text(splitConditions, margin, y);
                    // Enregistrer le PDF
                    doc.save('ticket_' + ticketData.ticket_number + '.pdf');
                } catch (e) {
                    console.error('Erreur lors de l\'ajout de l\'image au PDF : ', e);
                    alert('Erreur lors de la génération du PDF. Le logo n\'a pas pu être chargé, mais le PDF est généré sans logo.');
                    // Générer le PDF sans logo
                    const pageWidth = 210;
                    const margin = 5;
                    const contentWidth = pageWidth - 2 * margin;
                    const colWidth = contentWidth / 2 - 2;
                    let y = 5;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Billet Ferry Rapide', margin + colWidth + 2, y, { align: 'center' });
                    y += 4;
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Fast Ferry Ticket', margin + colWidth + 2, y, { align: 'center' });
                    y += 5;
                    // Section-2a
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro de Billet: ' + ticketData.ticket_number, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Number', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Date de Voyage: ' + ticketData.departure_date, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Travel Date', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nom du Passager: ' + ticketData.passenger_name, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Passenger Name', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Route: ' + ticketData.route, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Route', margin, y + 2);
                    y += 5;
                    // Section-2b
                    let yRight = 14;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nationalité: ' + ticketData.nationality, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Nationality', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Téléphone: ' + ticketData.phone_number, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Phone Number', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Type de Billet: ' + ticketData.ticket_type, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Type', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Avec Bébé?: ' + ticketData.with_baby, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('With Baby?', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    // Section-3a
                    y = Math.max(y, yRight);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Ville: ' + ticketData.checkin_city.split(' / ')[0], margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('City Check-in', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Port: ' + ticketData.checkin_port.split(' / ')[0], margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Check-in', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Départ Bateau: ' + ticketData.departure_time, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Boat Departure', margin, y + 2);
                    y += 5;
                    // Section-3b
                    yRight = Math.max(yRight, y - 15);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro Reçu: ' + ticketData.reference_number, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Receipt Number', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Tarif: ' + ticketData.tariff, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Fare', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Taxe: ' + ticketData.tax, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Tax', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Frais Port: ' + ticketData.port_fee, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Fee', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Total: ' + ticketData.total_amount, margin + colWidth + 4, yRight);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Total to Pay', margin + colWidth + 4, yRight + 2);
                    yRight += 5;
                    // Section-4a (pleine largeur)
                    y = Math.max(y, yRight);
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Modification: ' + ticketData.modification, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Modification', margin, y + 2);
                    y += 5;
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Pénalité: ' + ticketData.penalty, margin, y);
                    doc.setFontSize(7);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Penalty', margin, y + 2);
                    y += 10;
                    // Section-4 (pleine largeur)
                    doc.setFontSize(9);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'normal');
                    const splitConditions = doc.splitTextToSize(ticketData.conditions, contentWidth);
                    doc.text(splitConditions, margin, y);
                    // Enregistrer le PDF
                    doc.save('ticket_' + ticketData.ticket_number + '.pdf');
                }
            };
        });
    </script>
</body>
</html>