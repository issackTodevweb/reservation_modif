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
            padding: 10px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1001;
        }
        .ticket {
            width: 100%;
            background: #ffffff;
            border-radius: 6px;
            padding: 10px;
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .logo {
            width: 100%;
            max-width: 150px;
            height: auto;
            margin-bottom: 5px;
        }
        .contact-info {
            font-size: 10px;
            color: #333;
            margin-bottom: 5px;
        }
        .ticket h1 {
            font-size: 16px;
            color: #1e40af;
            margin: 5px 0;
            font-weight: bold;
        }
        .ticket-columns {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin: 5px 0;
        }
        .column-left, .column-right {
            width: 48%;
            text-align: left;
        }
        .column-left p, .column-right p {
            margin: 3px 0;
            font-size: 12px;
            color: #333;
        }
        .column-left p strong, .column-right p strong {
            color: #1e40af;
            font-size: 12px;
        }
        .column-left p .translation, .column-right p .translation {
            font-size: 10px;
            color: #666;
            font-style: italic;
            display: block;
            margin-top: 1px;
        }
        .conditions {
            width: 100%;
            text-align: left;
            font-size: 11px;
            color: #333;
            margin-top: 10px;
        }
        .conditions h3 {
            font-size: 12px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .conditions p {
            margin: 3px 0;
        }
        .conditions ul {
            margin: 3px 0 3px 20px;
            padding: 0;
            list-style-type: disc;
        }
        .conditions ul li {
            margin-bottom: 2px;
        }
        .conditions p .translation, .conditions ul .translation {
            font-size: 10px;
            color: #666;
            font-style: italic;
            display: block;
            margin-top: 1px;
        }
        .print-btn, .pdf-btn {
            cursor: pointer;
            display: inline-block;
            margin: 5px 4px;
            padding: 6px 12px;
            background: #f97316;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 10px;
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
            font-size: 16px;
            color: #333;
            z-index: 1003;
            pointer-events: auto;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            .ticket-modal, .ticket-modal * {
                visibility: visible;
            }
            .ticket-modal {
                display: block;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: auto;
                background: none;
            }
            .ticket-modal-content {
                margin: 0;
                padding: 3mm;
                border: none;
                box-shadow: none;
                width: 190mm;
                max-width: 190mm;
                position: relative;
                top: 0;
                left: 0;
                box-sizing: border-box;
                page-break-after: avoid;
            }
            .ticket {
                border: none;
                box-shadow: none;
                font-size: 12px;
                padding: 3mm;
                width: 100%;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .contact-info {
                font-size: 10px;
                margin-bottom: 2mm;
            }
            .ticket h1 {
                font-size: 16px;
                margin-bottom: 2mm;
            }
            .ticket-columns {
                display: flex;
                justify-content: space-between;
                width: 100%;
                margin: 2mm 0;
            }
            .column-left, .column-right {
                width: 48%;
            }
            .column-left p, .column-right p {
                font-size: 12px;
                margin: 1mm 0;
            }
            .column-left p strong, .column-right p strong {
                font-size: 12px;
            }
            .column-left p .translation, .column-right p .translation {
                font-size: 10px;
                margin-top: 0.5mm;
            }
            .conditions {
                font-size: 11px;
                margin-top: 2mm;
            }
            .conditions h3 {
                font-size: 12px;
                margin-bottom: 2mm;
            }
            .conditions p {
                margin: 1mm 0;
            }
            .conditions ul {
                margin: 1mm 0 1mm 5mm;
            }
            .conditions ul li {
                margin-bottom: 1mm;
            }
            .conditions p .translation, .conditions ul .translation {
                font-size: 10px;
                margin-top: 0.5mm;
            }
            .print-btn, .pdf-btn, .ticket-close {
                display: none;
            }
            @page {
                size: A4;
                margin: 3mm;
            }
        }
        @media (max-width: 600px) {
            .ticket {
                font-size: 11px;
            }
            .contact-info {
                font-size: 9px;
            }
            .ticket h1 {
                font-size: 14px;
            }
            .column-left p, .column-right p {
                font-size: 11px;
            }
            .column-left p strong, .column-right p strong {
                font-size: 11px;
            }
            .column-left p .translation, .column-right p .translation {
                font-size: 9px;
            }
            .conditions {
                font-size: 10px;
            }
            .conditions h3 {
                font-size: 11px;
            }
            .conditions p .translation, .conditions ul .translation {
                font-size: 9px;
            }
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
                        <img src="../images/ACX.png" alt="Adore Comores Express Logo" class="logo">
                        <p class="contact-info">Gde Comores : +269 320 72 13 | Moheli : +269 320 72 18 | Anjouan : +269 320 72 19 | Direction : +269 320 72 23</p>
                        <h1>Billet Ferry Rapide</h1>
                        <div class="ticket-columns">
                            <div class="column-left">
                                <p><strong>Numéro de Billet :</strong> <span id="ticket_number"></span><br><span class="translation">Ticket Number</span></p>
                                <p><strong>Date de Voyage :</strong> <span id="departure_date"></span><br><span class="translation">Travel Date</span></p>
                                <p><strong>Nom du Passager :</strong> <span id="passenger_name"></span><br><span class="translation">Passenger Name</span></p>
                                <p><strong>Route :</strong> <span id="route"></span><br><span class="translation">Route</span></p>
                                <p><strong>Nationalité :</strong> <span id="nationality"></span><br><span class="translation">Nationality</span></p>
                                <p><strong>Téléphone :</strong> <span id="phone_number"></span><br><span class="translation">Phone Number</span></p>
                                <p><strong>Type de Billet :</strong> <span id="ticket_type"></span><br><span class="translation">Ticket Type</span></p>
                                <p><strong>Avec Bébé ? :</strong> <span id="with_baby"></span><br><span class="translation">With Baby?</span></p>
                            </div>
                            <div class="column-right">
                                <p><strong>Enregistrement en Ville :</strong> Moroni, 2h avant départ<br><span class="translation">City Check-in: Moroni, 2 hours before departure</span></p>
                                <p><strong>Enregistrement au Port :</strong> Port de Moroni, 1h avant départ<br><span class="translation">Port Check-in: Moroni Port, 1 hour before departure</span></p>
                                <p><strong>Départ du Bateau :</strong> <span id="departure_time"></span><br><span class="translation">Boat Departure</span></p>
                                <p><strong>Numéro de Reçu :</strong> <span id="reference_number"></span><br><span class="translation">Receipt Number</span></p>
                                <p><strong>Tarif :</strong> <span id="tariff"></span> KMF<br><span class="translation">Fare</span></p>
                                <p><strong>Taxe :</strong> <span id="tax"></span> KMF<br><span class="translation">Tax</span></p>
                                <p><strong>Frais de Port :</strong> <span id="port_fee"></span> KMF<br><span class="translation">Port Fee</span></p>
                                <p><strong>Total à Payer :</strong> <span id="total_amount"></span> KMF<br><span class="translation">Total to Pay</span></p>
                                <p><strong>Modification :</strong> <span id="modification"></span><br><span class="translation">Modification</span></p>
                                <p><strong>Pénalité :</strong> <span id="penalty"></span><br><span class="translation">Penalty</span></p>
                            </div>
                        </div>
                        <div class="conditions">
                            <h3>Conditions Générales de Transport</h3>
                            <p><strong>Billet nominatif :</strong> Non cessible, non transférable sans accord du transporteur (Art. 686).<br>
                            <span class="translation">Non-transferable ticket: Not transferable without carrier’s consent (Art. 686).</span></p>
                            <p><strong>Modifications :</strong></p>
                            <ul>
                                <li>Changement de nom : 10 000 KMF<br><span class="translation">Name change: 10,000 KMF</span></li>
                                <li>Changement date/heure : 5 000 KMF<br><span class="translation">Date/time change: 5,000 KMF</span></li>
                            </ul>
                            <p><strong>Embarquement :</strong> Le passager doit se présenter à l’heure indiquée. Tout retard est considéré comme "No Show", équivalent à une annulation le jour même → 100 % de retenue, aucun remboursement. (Art. 688)<br>
                            <span class="translation">Boarding: Passenger must arrive on time. Any delay is considered a "No Show," equivalent to a same-day cancellation → 100% retained, no refund (Art. 688).</span></p>
                            <p><strong>Annulation par le passager :</strong></p>
                            <ul>
                                <li>Avant 72h : 25 % retenus<br><span class="translation">More than 72h: 25% retained</span></li>
                                <li>Entre 72h et 24h : 50 % retenus<br><span class="translation">Between 72h and 24h: 50% retained</span></li>
                                <li>Moins de 24h ou No Show : 100 % retenus<br><span class="translation">Less than 24h or No Show: 100% retained</span></li>
                            </ul>
                            <p><strong>Objets interdits :</strong> Drogue, armes, briquets, objets inflammables, etc. → Interdiction stricte.<br>
                            <span class="translation">Prohibited items: Drugs, weapons, lighters, flammable items, etc. → Strictly prohibited.</span></p>
                            <p><strong>Objets de valeur :</strong> À déclarer obligatoirement à l’enregistrement. Le transporteur décline toute responsabilité en cas de non-déclaration.<br>
                            <span class="translation">Valuables: Must be declared at check-in. The carrier assumes no liability for undeclared items.</span></p>
                            <p><strong>Bagages :</strong></p>
                            <ul>
                                <li>Franchise incluse : 20 kg en soute + 5 kg en bagage à main par passager.<br>
                                <span class="translation">Included allowance: 20 kg checked luggage + 5 kg carry-on per passenger.</span></li>
                                <li>En cas de perte, une déclaration écrite doit être faite immédiatement auprès du commandant lors du débarquement. Aucune réclamation ne sera acceptée après.<br>
                                <span class="translation">In case of loss, a written declaration must be made immediately to the captain upon disembarkation. No claims accepted afterward.</span></li>
                                <li>Le transporteur pourra, en cas de perte avérée, indemniser jusqu’à un maximum de 100 000 KMF par passager, quelle que soit la nature ou la valeur déclarée du bagage.<br>
                                <span class="translation">In case of confirmed loss, the carrier may compensate up to a maximum of 100,000 KMF per passenger, regardless of the nature or declared value of the luggage.</span></li>
                                <li>Cette indemnisation est soumise à vérification et ne sera accordée qu'en cas de perte réelle confirmée. Toute tentative de fraude ou de fausse déclaration entraînera des poursuites conformément à la loi (Art. 689).<br>
                                <span class="translation">This compensation is subject to verification and will only be granted for confirmed losses. Any attempt at fraud or false declaration will result in legal action (Art. 689).</span></li>
                            </ul>
                            <p><strong>Accès à bord :</strong> Sans billet ou numéro d’embarquement, aucun accès ne sera autorisé.<br>
                            <span class="translation">Boarding: No access allowed without a ticket or boarding number.</span></p>
                            <p><strong>Important ! :</strong> En achetant ce billet, le passager accepte toutes les conditions ci-dessus.<br>
                            <span class="translation">Important: By purchasing this ticket, the passenger accepts all the above conditions.</span></p>
                            <p><strong>Code de la Marine Comorienne – Articles 686, 688, 689</strong></p>
                        </div>
                        <button class="print-btn" onclick="printTicket()">Imprimer</button>
                        <button class="pdf-btn" onclick="exportToPDF()">Exporter en PDF</button>
                    </div>
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
                try {
                    const { jsPDF } = window.jspdf;
                    if (!jsPDF) {
                        console.error('jsPDF n\'est pas chargé');
                        alert('Erreur : La bibliothèque jsPDF n\'est pas disponible. Vérifiez votre connexion ou la source du script.');
                        return;
                    }
                    const doc = new jsPDF({
                        orientation: 'portrait',
                        unit: 'mm',
                        format: 'a4'
                    });
                    const ticketData = {
                        ticket_number: document.getElementById('ticket_number')?.textContent || 'N/A',
                        departure_date: document.getElementById('departure_date')?.textContent || 'N/A',
                        passenger_name: document.getElementById('passenger_name')?.textContent || 'N/A',
                        route: document.getElementById('route')?.textContent || 'N/A',
                        nationality: document.getElementById('nationality')?.textContent || 'N/A',
                        phone_number: document.getElementById('phone_number')?.textContent || 'N/A',
                        ticket_type: document.getElementById('ticket_type')?.textContent || 'N/A',
                        with_baby: document.getElementById('with_baby')?.textContent || 'N/A',
                        checkin_city: 'Moroni, 2h avant départ / City Check-in: Moroni, 2 hours before departure',
                        checkin_port: 'Port de Moroni, 1h avant départ / Port Check-in: Moroni Port, 1 hour before departure',
                        departure_time: document.getElementById('departure_time')?.textContent || 'N/A',
                        reference_number: document.getElementById('reference_number')?.textContent || 'N/A',
                        tariff: (document.getElementById('tariff')?.textContent || '0') + ' KMF',
                        tax: (document.getElementById('tax')?.textContent || '0') + ' KMF',
                        port_fee: (document.getElementById('port_fee')?.textContent || '0') + ' KMF',
                        total_amount: (document.getElementById('total_amount')?.textContent || '0') + ' KMF',
                        modification: document.getElementById('modification')?.textContent || 'Aucune',
                        penalty: document.getElementById('penalty')?.textContent || 'Aucune',
                        conditions: 'Conditions Générales de Transport\n' +
                                    'Billet nominatif : Non cessible, non transférable sans accord du transporteur (Art. 686).\n' +
                                    'Non-transferable ticket: Not transferable without carrier’s consent (Art. 686).\n' +
                                    'Modifications :\n' +
                                    '• Changement de nom : 10 000 KMF\n' +
                                    '  Name change: 10,000 KMF\n' +
                                    '• Changement date/heure : 5 000 KMF\n' +
                                    '  Date/time change: 5,000 KMF\n' +
                                    'Embarquement :\n' +
                                    'Le passager doit se présenter à l’heure indiquée. Tout retard est considéré comme "No Show", \n' + 
                                    'équivalent à une annulation le jour même → 100 % de retenue, aucun remboursement. (Art. 688)\n' +
                                    'Boarding: Passenger must arrive on time. Any delay is considered a "No Show," \n' + 
                                        'equivalent to a same-day cancellation → 100% retained, no refund (Art. 688).\n' +
                                    'Annulation par le passager :\n' +
                                    '• Avant 72h : 25 % retenus\n' +
                                    '  More than 72h: 25% retained\n' +
                                    '• Entre 72h et 24h : 50 % retenus\n' +
                                    '  Between 72h and 24h: 50% retained\n' +
                                    '• Moins de 24h ou No Show : 100 % retenus\n' +
                                    '  Less than 24h or No Show: 100% retained\n' +
                                    'Objets interdits : Drogue, armes, briquets, objets inflammables, etc. → Interdiction stricte.\n' +
                                    'Prohibited items: Drugs, weapons, lighters, flammable items, etc. → Strictly prohibited.\n' +
                                    'Objets de valeur : À déclarer obligatoirement à l’enregistrement. Le transporteur décline toute responsabilité en cas de non-déclaration.\n' +
                                    'Valuables: Must be declared at check-in. The carrier assumes no liability for undeclared items.\n' +
                                    'Bagages :\n' +
                                    '• Franchise incluse : 20 kg en soute + 5 kg en bagage à main par passager.\n' +
                                    '  Included allowance: 20 kg checked luggage + 5 kg carry-on per passenger.\n' +
                                    '• En cas de perte, une déclaration écrite doit être faite immédiatement auprès du commandant lors du débarquement. Aucune réclamation ne sera acceptée après.\n' +
                                    '  In case of loss, a written declaration must be made immediately to the captain upon disembarkation. No claims accepted afterward.\n' +
                                    '• Le transporteur pourra, en cas de perte avérée, indemniser jusqu’à un maximum de 100 000 KMF par passager, quelle que soit la nature ou la valeur déclarée du bagage.\n' +
                                    '  In case of confirmed loss, the carrier may compensate up to a maximum of 100,000 KMF per passenger, regardless of the nature or declared value of the luggage.\n' +
                                    '• Cette indemnisation est soumise à vérification et ne sera accordée qu\'en cas de perte réelle confirmée. Toute tentative de fraude ou de fausse déclaration entraînera des poursuites conformément à la loi (Art. 689).\n' +
                                    '  This compensation is subject to verification and will only be granted for confirmed losses. Any attempt at fraud or false declaration will result in legal action (Art. 689).\n' +
                                    'Accès à bord : Sans billet ou numéro d’embarquement, aucun accès ne sera autorisé.\n' +
                                    'Boarding: No access allowed without a ticket or boarding number.\n' +
                                    'Important ! : En achetant ce billet, le passager accepte toutes les conditions ci-dessus.\n' +
                                    'Important: By purchasing this ticket, the passenger accepts all the above conditions.\n' +
                                    'Code de la Marine Comorienne – Articles 686, 688, 689'
                    };
                    const pageWidth = 210;
                    const margin = 3;
                    const contentWidth = pageWidth - 2 * margin;
                    let y = 10; // Début plus haut pour compenser l'absence du logo
                    doc.setFontSize(9); // Réduction de la taille pour les informations de contact
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Gde Comores : +269 320 72 13 | Moheli : +269 320 72 18 | Anjouan : +269 320 72 19 | Direction : +269 320 72 23', pageWidth / 2, y, { align: 'center' });
                    y += 6;
                    doc.setFontSize(12); // Réduction de 14 à 12 pour le titre
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Billet Ferry Rapide', pageWidth / 2, y, { align: 'center' });
                    y += 8;
                    doc.setFontSize(10); // Réduction de 12 à 10 pour le contenu principal
                    doc.setTextColor(0, 0, 0);
                    const leftColumnX = margin + 10;
                    const rightColumnX = pageWidth / 2 + 10;
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro de Billet: ' + ticketData.ticket_number, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8); // Réduction de 10 à 8 pour la traduction
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Number', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Ville: ' + ticketData.checkin_city.split(' / ')[0], rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('City Check-in', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Date de Voyage: ' + ticketData.departure_date, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Travel Date', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Enreg. Port: ' + ticketData.checkin_port.split(' / ')[0], rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Check-in', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nom du Passager: ' + ticketData.passenger_name, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Passenger Name', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Départ Bateau: ' + ticketData.departure_time, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Boat Departure', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Route: ' + ticketData.route, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Route', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Numéro Reçu: ' + ticketData.reference_number, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Receipt Number', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Nationalité: ' + ticketData.nationality, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Nationality', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Tarif: ' + ticketData.tariff, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Fare', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Téléphone: ' + ticketData.phone_number, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Phone Number', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Taxe: ' + ticketData.tax, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Tax', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Type de Billet: ' + ticketData.ticket_type, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Ticket Type', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Frais Port: ' + ticketData.port_fee, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Port Fee', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Avec Bébé?: ' + ticketData.with_baby, leftColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('With Baby?', leftColumnX, y + 3, { align: 'left' });
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Total: ' + ticketData.total_amount, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Total to Pay', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Modification: ' + ticketData.modification, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Modification', rightColumnX, y + 3, { align: 'left' });
                    y += 8;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Pénalité: ' + ticketData.penalty, rightColumnX, y, { align: 'left' });
                    doc.setFontSize(8);
                    doc.setTextColor(102, 102, 102);
                    doc.setFont('helvetica', 'italic');
                    doc.text('Penalty', rightColumnX, y + 3, { align: 'left' });
                    y += 10;
                    doc.setFontSize(10);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Conditions Générales de Transport', margin, y);
                    y += 5;
                    doc.setFontSize(8); // Réduction de 11 à 9 pour les conditions
                    doc.setFont('helvetica', 'normal');
                    const splitConditions = doc.splitTextToSize(ticketData.conditions, contentWidth);
                    doc.text(splitConditions, margin, y, { align: 'left' });
                    doc.save('ticket_' + ticketData.ticket_number + '.pdf');
                } catch (error) {
                    console.error('Erreur lors de l\'exportation en PDF :', error);
                    alert('Une erreur s\'est produite lors de la génération du PDF. Vérifiez la console pour plus de détails.');
                }
            };
        });
    </script>
</body>
</html>