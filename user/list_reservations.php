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
    die("Erreur : Impossible de se connecter à la base de données. Veuillez contacter l'administrateur.");
}
// Supprimer une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $id = (int)$_POST['delete_reservation'];
    try {
        $pdo->beginTransaction();
        // Récupérer les IDs pour restauration
        $stmt = $pdo->prepare("SELECT reference_id, ticket_id, trip_id, seat_number FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            // Supprimer la réservation
            $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            // Restaurer la référence
            $pdo->prepare("UPDATE reference SET status = 'available' WHERE id = ?")->execute([$reservation['reference_id']]);
            // Restaurer le ticket
            $pdo->prepare("UPDATE tickets SET status = 'available' WHERE id = ?")->execute([$reservation['ticket_id']]);
            // Restaurer la place
            $pdo->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?")
                ->execute([$reservation['trip_id'], $reservation['seat_number']]);
            $pdo->commit();
            $_SESSION['success'] = "Réservation supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Réservation non trouvée ou non autorisée.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    header("Location: list_reservations.php");
    exit();
}
// Annuler une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $id = (int)$_POST['cancel_reservation'];
    try {
        $stmt = $pdo->prepare("SELECT reference_id, ticket_id, trip_id, seat_number FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            $pdo->beginTransaction();
            // Mettre à jour le statut de paiement
            $stmt = $pdo->prepare("UPDATE reservations SET payment_status = 'Annulé' WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            // Restaurer la référence
            $pdo->prepare("UPDATE reference SET status = 'available' WHERE id = ?")->execute([$reservation['reference_id']]);
            // Restaurer le ticket
            $pdo->prepare("UPDATE tickets SET status = 'available' WHERE id = ?")->execute([$reservation['ticket_id']]);
            // Restaurer la place
            $pdo->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?")
                ->execute([$reservation['trip_id'], $reservation['seat_number']]);
            $pdo->commit();
            $_SESSION['success'] = "Réservation annulée avec succès.";
        } else {
            $_SESSION['error'] = "Réservation non trouvée ou non autorisée.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de l'annulation : " . $e->getMessage();
    }
    header("Location: list_reservations.php");
    exit();
}
// Mettre à jour une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $id = (int)$_POST['id'];
    $passenger_name = trim($_POST['passenger_name']);
    $phone_number = trim($_POST['phone_number']);
    $nationality = trim($_POST['nationality']);
    $passenger_type = $_POST['passenger_type'];
    $with_baby = $_POST['with_baby'];
    $departure_date = $_POST['departure_date'];
    $trip_id = (int)$_POST['trip_id'];
    $ticket_type = $_POST['ticket_type'];
    $seat_number = trim($_POST['seat_number']);
    $payment_status = $_POST['payment_status'];
    $modification = trim($_POST['modification']);
    $penalty = trim($_POST['penalty']);
    // Validation
    $errors = [];
    if (empty($passenger_name) || empty($phone_number) || empty($nationality) || empty($seat_number) || empty($ticket_type)) {
        $errors[] = "Tous les champs obligatoires doivent être remplis.";
    }
    if (!preg_match('/^\+?\d{10,15}$/', $phone_number)) {
        $errors[] = "Numéro de téléphone invalide.";
    }
    // Vérifier si la place est disponible (sauf si c'est la même place)
    $seat_stmt = $pdo->prepare("SELECT status, seat_number FROM seats WHERE trip_id = ? AND seat_number = ?");
    $seat_stmt->execute([$trip_id, $seat_number]);
    $seat = $seat_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$seat || ($seat['status'] !== 'free' && $seat['seat_number'] !== $_POST['original_seat_number'])) {
        $errors[] = "La place sélectionnée n'est pas disponible.";
    }
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            // Récupérer l'ancienne réservation pour restaurer
            $stmt = $pdo->prepare("SELECT reference_id, ticket_id, trip_id, seat_number FROM reservations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $old_reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($old_reservation) {
                // Restaurer l'ancienne place
                if ($old_reservation['seat_number'] !== $seat_number) {
                    $pdo->prepare("UPDATE seats SET status = 'free' WHERE trip_id = ? AND seat_number = ?")
                        ->execute([$old_reservation['trip_id'], $old_reservation['seat_number']]);
                }
                $stmt = $pdo->prepare("UPDATE reservations SET passenger_name = ?, phone_number = ?, nationality = ?, passenger_type = ?, with_baby = ?, departure_date = ?, trip_id = ?, ticket_type = ?, seat_number = ?, payment_status = ?, modification = ?, penalty = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$passenger_name, $phone_number, $nationality, $passenger_type, $with_baby, $departure_date, $trip_id, $ticket_type, $seat_number, $payment_status, $modification, $penalty, $id, $_SESSION['user_id']]);
                // Mettre à jour la place
                if ($old_reservation['seat_number'] !== $seat_number) {
                    $pdo->prepare("UPDATE seats SET status = 'occupied' WHERE trip_id = ? AND seat_number = ?")
                        ->execute([$trip_id, $seat_number]);
                }
                $pdo->commit();
                $_SESSION['success'] = "Réservation mise à jour avec succès.";
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = "Réservation non trouvée ou non autorisée.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    header("Location: list_reservations.php");
    exit();
}
// Gestion de la recherche
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [$_SESSION['user_id']];
if (!empty($search_query)) {
    $where_clause = " AND (r.passenger_name LIKE ? OR ref.reference_number LIKE ? OR tk.ticket_number LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
// Récupérer les réservations de l'utilisateur
try {
    $sql = "SELECT r.id, r.passenger_name, r.phone_number, r.nationality, r.passenger_type, r.with_baby, r.departure_date,
                   r.trip_id, r.reference_id, r.ticket_id, r.ticket_type, r.seat_number, r.payment_status, r.total_amount,
                   r.modification, r.penalty,
                   t.departure_port, t.arrival_port, t.departure_time, ref.reference_number, tk.ticket_number,
                   f.tariff, f.port_fee, f.tax
            FROM reservations r
            JOIN trips t ON r.trip_id = t.id
            JOIN reference ref ON r.reference_id = ref.id
            JOIN tickets tk ON r.ticket_id = tk.id
            LEFT JOIN finances f ON r.passenger_type = f.passenger_type AND
                f.trip_type = CASE 
                    WHEN (t.departure_port = 'ANJ' OR t.arrival_port = 'ANJ') THEN 'Hors Standard'
                    ELSE 'Standard'
                END AND f.direction = 'Aller'
            WHERE r.user_id = ? $where_clause
            ORDER BY r.departure_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la récupération des réservations : " . $e->getMessage();
    header("Location: list_reservations.php");
    exit();
}
// Récupérer les trajets pour la modale
$trips_stmt = $pdo->prepare("SELECT id, departure_port, arrival_port, departure_date, departure_time FROM trips ORDER BY departure_date, departure_time");
$trips_stmt->execute();
$trips = $trips_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Réservations - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/user_list_reservations.css?v=<?php echo time(); ?>">
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
            .logo {
                max-width: 40mm;
                height: auto;
                margin-bottom: 2mm;
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
            .logo {
                max-width: 120px;
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
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a>
            <a href="reservations.php"><i class="fas fa-book"></i> Réservations</a>
            <a href="list_reservations.php" class="active"><i class="fas fa-list"></i> Liste des Réservations</a>
            <a href="info_passagers.php"><i class="fas fa-users"></i> Infos Passagers</a>
            <a href="colis.php"><i class="fas fa-box"></i> Colis</a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Se Déconnecter</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="overlay">
            <h1>Liste des Réservations</h1>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <!-- Barre de recherche -->
            <div class="search-container">
                <form method="GET" action="list_reservations.php">
                    <input type="text" name="search" placeholder="Rechercher par passager, référence, ticket..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="btn"><i class="fas fa-search"></i> Rechercher</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="list_reservations.php" class="btn reset-btn">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>
            <!-- Liste des réservations -->
            <div class="reservations-list-container">
                <h2>Vos Réservations</h2>
                <?php if (!empty($reservations)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Passager</th>
                                <th>Téléphone</th>
                                <th>Nationalité</th>
                                <th>Type</th>
                                <th>Avec Bébé</th>
                                <th>Date de Départ</th>
                                <th>Trajet</th>
                                <th>Référence</th>
                                <th>Ticket</th>
                                <th>Type de Ticket</th>
                                <th>Place</th>
                                <th>Paiement</th>
                                <th>Tarif (KMF)</th>
                                <th>Frais Port (KMF)</th>
                                <th>Taxe (KMF)</th>
                                <th>Total (KMF)</th>
                                <th>Modification</th>
                                <th>Pénalité</th>
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
                                    <td><?php echo htmlspecialchars($reservation['departure_date'] . ' ' . $reservation['departure_time']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['departure_port'] . ' → ' . $reservation['arrival_port']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['reference_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['ticket_type']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['seat_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['tariff'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['port_fee'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['tax'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($reservation['total_amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['modification'] ?? 'Aucune'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['penalty'] ?? 'Aucune'); ?></td>
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
                                                data-seat-number="<?php echo htmlspecialchars($reservation['seat_number']); ?>"
                                                data-tariff="<?php echo number_format($reservation['tariff'] ?? 0, 2); ?>"
                                                data-port-fee="<?php echo number_format($reservation['port_fee'] ?? 0, 2); ?>"
                                                data-tax="<?php echo number_format($reservation['tax'] ?? 0, 2); ?>"
                                                data-total-amount="<?php echo number_format($reservation['total_amount'], 2); ?>"
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
                                                data-ticket-type="<?php echo htmlspecialchars($reservation['ticket_type']); ?>"
                                                data-seat-number="<?php echo htmlspecialchars($reservation['seat_number']); ?>"
                                                data-payment-status="<?php echo htmlspecialchars($reservation['payment_status']); ?>"
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
                    <h2>Modifier la Réservation</h2>
                    <form id="editReservationForm" method="POST">
                        <input type="hidden" name="update_reservation" value="1">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="original_seat_number" id="edit_original_seat_number">
                        <input type="hidden" name="seat_number" id="edit_seat_number">
                        <div class="form-group">
                            <label for="edit_passenger_name">Nom du Passager</label>
                            <input type="text" name="passenger_name" id="edit_passenger_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone_number">Numéro de Téléphone</label>
                            <input type="text" name="phone_number" id="edit_phone_number" placeholder="+2691234567" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_nationality">Nationalité</label>
                            <input type="text" name="nationality" id="edit_nationality" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_passenger_type">Type de Passager</label>
                            <select name="passenger_type" id="edit_passenger_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="Comorien Adulte">Comorien Adulte</option>
                                <option value="Comorien Enfant">Comorien Enfant</option>
                                <option value="Étranger Adulte">Étranger Adulte</option>
                                <option value="Étranger Enfant">Étranger Enfant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_with_baby">Avec Bébé ?</label>
                            <select name="with_baby" id="edit_with_baby" required>
                                <option value="Non">Non</option>
                                <option value="Oui">Oui</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_departure_date">Date de Départ</label>
                            <input type="date" name="departure_date" id="edit_departure_date" required onchange="fetchTripsForEdit()">
                        </div>
                        <div class="form-group">
                            <label for="edit_trip_id">Trajet</label>
                            <select name="trip_id" id="edit_trip_id" required>
                                <option value="">-- Sélectionner une date d'abord --</option>
                                <?php foreach ($trips as $trip): ?>
                                    <option value="<?php echo $trip['id']; ?>">
                                        <?php echo htmlspecialchars($trip['departure_port'] . ' → ' . $trip['arrival_port'] . ' (' . $trip['departure_date'] . ' ' . $trip['departure_time'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_ticket_type">Type de Ticket</label>
                            <select name="ticket_type" id="edit_ticket_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="GM">GM</option>
                                <option value="MG">MG</option>
                                <option value="GA">GA</option>
                                <option value="AM">AM</option>
                                <option value="MA">MA</option>
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
                            <label for="edit_selected_seat">Place Sélectionnée</label>
                            <input type="text" id="edit_selected_seat" readonly placeholder="Cliquez sur 'Voir les places disponibles'">
                            <button type="button" id="edit_show_seats" class="btn" onclick="showAvailableSeatsForEdit()">Voir les places disponibles</button>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_status">Statut de Paiement</label>
                            <select name="payment_status" id="edit_payment_status" required>
                                <option value="Non payé">Non payé</option>
                                <option value="Mvola">Mvola</option>
                                <option value="Espèce">Espèce</option>
                            </select>
                        </div>
                        <button type="submit" class="btn update-btn">Mettre à jour</button>
                    </form>
                </div>
            </div>
            <!-- Modale pour la disposition des places -->
            <div id="edit_seats_modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Disposition des places</h2>
                    <div id="edit_boat_layout"></div>
                    <div class="seat-legend">
                        <div class="legend-item"><span class="seat free"></span> Libre</div>
                        <div class="legend-item"><span class="seat occupied"></span> Occupé</div>
                        <div class="legend-item"><span class="seat crew"></span> Équipage</div>
                    </div>
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
                                <p><strong>Enregistrement en Ville :</strong> 2h avant départ<br><span class="translation">City Check-in: 2 hours before departure</span></p>
                                <p><strong>Enregistrement au Port :</strong> 1h avant départ<br><span class="translation">Port Check-in:1 hour before departure</span></p>
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
        </main>
        <script src="../styles/js/user_list_reservations.js?v=<?php echo time(); ?>"></script>
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
                        document.getElementById('edit_original_seat_number').value = button.dataset.seatNumber;
                        document.getElementById('edit_selected_seat').value = button.dataset.seatNumber;
                        document.getElementById('edit_payment_status').value = button.dataset.paymentStatus;
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
                            alert('Erreur : La bibliothèque jsPDF n\'est pas disponible.');
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
                            checkin_city: '2h avant départ / City Check-in: 2 hours before departure',
                            checkin_port: '1h avant départ / Port Check-in: 1 hour before departure',
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
                                        'Embarquement : Le passager doit se présenter à l’heure. Tout retard = "No Show",\n' + 
                                        'annulation le jour même → 100 % retenu, non remboursé (Art. 688).\n' +
                                        'Boarding: Arrive on time. Delay = "No Show,".\n' + 
                                        'same-day cancellation → 100% retained, no refund (Art. 688).\n' +
                                        'Annulation par le passager :\n' +
                                        '• > 72h : 25 % retenus\n' +
                                        '  More than 72h: 25% retained\n' +
                                        '• 72h-24h : 50 % retenus\n' +
                                        '  Between 72h-24h: 50% retained\n' +
                                        '• < 24h/No Show : 100 % retenus\n' +
                                        '  Less than 24h/No Show: 100% retained\n' +
                                        'Objets interdits : Drogue, armes, briquets, inflammables, etc. → Interdiction stricte.\n' +
                                        'Prohibited items: Drugs, weapons, lighters, flammable items, etc. → Strictly prohibited.\n' +
                                        'Objets de valeur : Déclarer à l’enregistrement. Non déclaré = pas de responsabilité du transporteur.\n' +
                                        'Valuables: Declare at check-in. Undeclared = no carrier liability.\n' +
                                        'Bagages :\n' +
                                        '• Franchise : 20 kg soute + 5 kg main par passager.\n' +
                                        '  Allowance: 20 kg checked + 5 kg carry-on per passenger.\n' +
                                        '• Perte : Déclarer immédiatement au commandant à l’arrivée. Pas de réclamation après.\n' +
                                        '  Loss: Declare to captain upon arrival. No claims accepted afterward.\n' +
                                        '• Perte confirmée : Indemnisation max. 100 000 KMF/passager, peu importe la valeur.\n' +
                                        '  Confirmed loss: Max. 100,000 KMF/passenger, regardless of value.\n' +
                                        '• Vérification requise. Fraude = poursuites (Art. 689).\n' +
                                        '  Verification required. Fraud = legal action (Art. 689).\n' +
                                        'Accès à bord : Billet/numéro d’embarquement requis.\n' +
                                        'Boarding: Ticket/boarding number required.\n' +
                                        'Important : L’achat du billet implique l’acceptation des conditions.\n' +
                                        'Important: Ticket purchase implies acceptance of conditions.\n' +
                                        'Code de la Marine Comorienne – Art. 686, 688, 689'
                        };
                        const pageWidth = 210;
                        const margin = 5; // Augmenté pour plus d'espace
                        const contentWidth = pageWidth - 2 * margin;
                        let y = 10;
                        doc.setFontSize(8); // Réduit pour contact-info
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'normal');
                        doc.text('Gde Comores : +269 320 72 13 | Moheli : +269 320 72 18 | Anjouan : +269 320 72 19 | Direction : +269 320 72 23', pageWidth / 2, y, { align: 'center' });
                        y += 5;
                        doc.setFontSize(12);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Billet Ferry Rapide', pageWidth / 2, y, { align: 'center' });
                        y += 6;
                        doc.setFontSize(9); // Réduit pour le contenu principal
                        doc.setTextColor(0, 0, 0);
                        const leftColumnX = margin + 5;
                        const rightColumnX = pageWidth / 2 + 5;
                        doc.setFont('helvetica', 'bold');
                        doc.text('Numéro de Billet: ' + ticketData.ticket_number, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Ticket Number', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Enreg. Ville: ' + ticketData.checkin_city.split(' / ')[0], rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('City Check-in', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Date de Voyage: ' + ticketData.departure_date, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Travel Date', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Enreg. Port: ' + ticketData.checkin_port.split(' / ')[0], rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Port Check-in', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Nom du Passager: ' + ticketData.passenger_name, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Passenger Name', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Départ Bateau: ' + ticketData.departure_time, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Boat Departure', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Route: ' + ticketData.route, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Route', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Numéro Reçu: ' + ticketData.reference_number, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Receipt Number', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Nationalité: ' + ticketData.nationality, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Nationality', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Tarif: ' + ticketData.tariff, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Fare', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Téléphone: ' + ticketData.phone_number, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Phone Number', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Taxe: ' + ticketData.tax, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Tax', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Type de Billet: ' + ticketData.ticket_type, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Ticket Type', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Frais Port: ' + ticketData.port_fee, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Port Fee', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Avec Bébé?: ' + ticketData.with_baby, leftColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('With Baby?', leftColumnX, y + 2, { align: 'left' });
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Total: ' + ticketData.total_amount, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Total to Pay', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Modification: ' + ticketData.modification, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Modification', rightColumnX, y + 2, { align: 'left' });
                        y += 6;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Pénalité: ' + ticketData.penalty, rightColumnX, y, { align: 'left' });
                        doc.setFontSize(7);
                        doc.setTextColor(102, 102, 102);
                        doc.setFont('helvetica', 'italic');
                        doc.text('Penalty', rightColumnX, y + 2, { align: 'left' });
                        y += 8;
                        doc.setFontSize(9);
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Conditions Générales de Transport', margin, y);
                        y += 4;
                        doc.setFontSize(8); // Réduit pour les conditions
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
<?php $pdo = null; ?>