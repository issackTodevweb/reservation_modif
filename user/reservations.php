<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Accès non autorisé. Veuillez vous connecter en tant qu'utilisateur.";
    header("Location: ../index.php");
    exit();
}

$pdo = getConnection();
$alert = '';

// Récupérer la prochaine référence disponible
$reference_stmt = $pdo->prepare("
    SELECT r.id, r.reference_number 
    FROM reference r 
    WHERE r.status = 'available' 
    AND NOT EXISTS (
        SELECT 1 FROM reservations res WHERE res.reference_id = r.id
    ) 
    ORDER BY r.reference_number ASC LIMIT 1
");
$reference_stmt->execute();
$reference = $reference_stmt->fetch(PDO::FETCH_ASSOC);
$next_reference_id = $reference ? $reference['id'] : null;
$next_reference_number = $reference ? $reference['reference_number'] : 'Aucune référence disponible';

// Initialiser les variables pour le ticket
$next_ticket_id = null;
$next_ticket_number = 'Sélectionnez un type de ticket';

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
    $passenger_name = trim($_POST['passenger_name']);
    $phone_number = trim($_POST['phone_number']);
    $nationality = trim($_POST['nationality']);
    $passport_cin = trim($_POST['passport_cin']);
    $passenger_type = $_POST['passenger_type'];
    $with_baby = $_POST['with_baby'];
    $departure_date = $_POST['departure_date'];
    $trip_id = (int)$_POST['trip_id'];
    $reference_id = (int)$_POST['reference_id'];
    $ticket_id = (int)$_POST['ticket_id'];
    $ticket_type = $_POST['ticket_type'];
    $seat_number = trim($_POST['seat_number']);
    $payment_status = $_POST['payment_status'];
    $modification = trim($_POST['modification']);
    $penalty = trim($_POST['penalty']);
    $total_amount = floatval($_POST['total_amount']); // Récupérer le montant total du formulaire

    // Déterminer le type de trajet
    $trip_stmt = $pdo->prepare("SELECT departure_port, arrival_port FROM trips WHERE id = ?");
    $trip_stmt->execute([$trip_id]);
    $trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trip) {
        $alert = '<div class="alert alert-error">Erreur : Trajet invalide.</div>';
    } else {
        // Définir le type de trajet : 'Hors Standard' pour ANJ-GCO ou GCO-ANJ, sinon 'Standard'
        $is_hors_standard = (
            ($trip['departure_port'] === 'ANJ' && $trip['arrival_port'] === 'GCO') ||
            ($trip['departure_port'] === 'GCO' && $trip['arrival_port'] === 'ANJ')
        );
        $trip_type = $is_hors_standard ? 'Hors Standard' : 'Standard';

        // Vérifier le montant total dans la table finances
        $finance_stmt = $pdo->prepare("SELECT tariff, port_fee, tax FROM finances WHERE passenger_type = ? AND trip_type = ?");
        $finance_stmt->execute([$passenger_type, $trip_type]);
        $finance = $finance_stmt->fetch(PDO::FETCH_ASSOC);
        $calculated_total_amount = $finance ? ($finance['tariff'] + $finance['port_fee'] + $finance['tax']) : 0;

        // Validation
        $errors = [];
        if (empty($passenger_name) || empty($phone_number) || empty($nationality) || empty($passport_cin) || empty($seat_number) || empty($ticket_type)) {
            $errors[] = "Tous les champs obligatoires doivent être remplis.";
        }
        if (!preg_match('/^\+?\d{10,15}$/', $phone_number)) {
            $errors[] = "Numéro de téléphone invalide.";
        }
        if (!preg_match('/^[A-Za-z0-9]{5,20}$/', $passport_cin)) {
            $errors[] = "Numéro de passeport ou CIN invalide (5 à 20 caractères alphanumériques).";
        }
        if (!$reference_id) {
            $errors[] = "Aucune référence disponible.";
        } else {
            $ref_check_stmt = $pdo->prepare("SELECT id FROM reservations WHERE reference_id = ?");
            $ref_check_stmt->execute([$reference_id]);
            if ($ref_check_stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "La référence sélectionnée est déjà utilisée.";
            }
        }
        if (!$ticket_id) {
            $errors[] = "Aucun ticket disponible.";
        } else {
            $ticket_check_stmt = $pdo->prepare("SELECT id FROM reservations WHERE ticket_id = ?");
            $ticket_check_stmt->execute([$ticket_id]);
            if ($ticket_check_stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Le ticket sélectionné est déjà utilisé.";
            }
        }
        if (!in_array($ticket_type, ['GM', 'MG', 'GA', 'AM', 'MA'])) {
            $errors[] = "Type de ticket invalide.";
        }
        $seat_stmt = $pdo->prepare("SELECT status FROM seats WHERE trip_id = ? AND seat_number = ?");
        $seat_stmt->execute([$trip_id, $seat_number]);
        $seat = $seat_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seat || $seat['status'] !== 'free') {
            $errors[] = "La place sélectionnée n'est pas disponible.";
        }
        $ticket_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND status = 'available'");
        $ticket_stmt->execute([$ticket_id]);
        if (!$ticket_stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Le ticket sélectionné n'est pas disponible.";
        }
        if ($total_amount <= 0 || $total_amount != $calculated_total_amount) {
            $errors[] = "Montant total invalide. Veuillez vérifier les paramètres de réservation.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Insérer la réservation
                $stmt = $pdo->prepare("
                    INSERT INTO reservations (
                        user_id, passenger_name, phone_number, nationality, passport_cin, 
                        passenger_type, with_baby, departure_date, trip_id, reference_id, 
                        ticket_id, ticket_type, seat_number, payment_status, total_amount, 
                        modification, penalty
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $passenger_name,
                    $phone_number,
                    $nationality,
                    $passport_cin,
                    $passenger_type,
                    $with_baby,
                    $departure_date,
                    $trip_id,
                    $reference_id,
                    $ticket_id,
                    $ticket_type,
                    $seat_number,
                    $payment_status,
                    $total_amount,
                    $modification,
                    $penalty
                ]);

                // Mettre à jour le statut de la référence
                $pdo->prepare("UPDATE reference SET status = 'reserved' WHERE id = ?")->execute([$reference_id]);

                // Mettre à jour le statut du ticket
                $pdo->prepare("UPDATE tickets SET status = 'reserved' WHERE id = ?")->execute([$ticket_id]);

                // Mettre à jour le statut de la place
                $pdo->prepare("UPDATE seats SET status = 'occupied' WHERE trip_id = ? AND seat_number = ?")->execute([$trip_id, $seat_number]);

                // Journalisation
                $reservation_id = $pdo->lastInsertId();
                $stmt_log = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, action_details, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_log->execute([
                    $_SESSION['user_id'],
                    'Created reservation',
                    "Created reservation ID $reservation_id for passenger $passenger_name (Ticket: $ticket_type, Seat: $seat_number, Passport/CIN: $passport_cin, Total: $total_amount KMF)"
                ]);

                $pdo->commit();
                $alert = '<div class="alert alert-success">Réservation enregistrée avec succès ! Montant total : ' . number_format($total_amount, 2) . ' KMF</div>';

                // Rafraîchir la référence
                $reference_stmt->execute();
                $reference = $reference_stmt->fetch(PDO::FETCH_ASSOC);
                $next_reference_id = $reference ? $reference['id'] : null;
                $next_reference_number = $reference ? $reference['reference_number'] : 'Aucune référence disponible';

                // Réinitialiser le ticket
                $next_ticket_id = null;
                $next_ticket_number = 'Sélectionnez un type de ticket';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $alert = '<div class="alert alert-error">Erreur lors de l\'enregistrement : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $alert = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/user_reservations.css?v=<?php echo time(); ?>">
</head>
<body>
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-book"></i><span>Tableau de bord</span></a>
            <a href="reservations.php" class="active"><i class="fas fa-book"></i><span>Réservations</span></a>
            <a href="list_reservations.php"><i class="fas fa-list"></i> Liste des Réservations</a>
            <a href="info_passagers.php"><i class="fas fa-users"></i> Infos Passagers</a>
            <a href="colis.php"><i class="fas fa-box"></i> Colis</a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Faire une Réservation</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Formulaire de Réservation</h2>
                <form method="POST" id="reservation-form">
                    <input type="hidden" name="submit_reservation" value="1">
                    <input type="hidden" name="reference_id" value="<?php echo $next_reference_id; ?>">
                    <input type="hidden" name="ticket_id" id="ticket_id" value="<?php echo $next_ticket_id; ?>">
                    <input type="hidden" name="seat_number" id="seat_number">
                    <input type="hidden" name="total_amount" id="form_total_amount"> <!-- Champ caché pour total_amount -->
                    <div class="form-group">
                        <label for="passenger_name">Nom du Passager</label>
                        <input type="text" name="passenger_name" id="passenger_name" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Numéro de Téléphone</label>
                        <input type="text" name="phone_number" id="phone_number" placeholder="+2691234567" required>
                    </div>
                    <div class="form-group">
                        <label for="nationality">Nationalité</label>
                        <input type="text" name="nationality" id="nationality" required>
                    </div>
                    <div class="form-group">
                        <label for="passport_cin">Numéro de Passeport ou CIN</label>
                        <input type="text" name="passport_cin" id="passport_cin" placeholder="Ex: A123456 ou 123456789" required>
                    </div>
                    <div class="form-group">
                        <label for="passenger_type">Type de Passager</label>
                        <select name="passenger_type" id="passenger_type" required onchange="updateAmount()">
                            <option value="">-- Sélectionner --</option>
                            <option value="Comorien Adulte">Comorien Adulte</option>
                            <option value="Comorien Enfant">Comorien Enfant</option>
                            <option value="Étranger Adulte">Étranger Adulte</option>
                            <option value="Étranger Enfant">Étranger Enfant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="with_baby">Avec Bébé ?</label>
                        <select name="with_baby" id="with_baby" required>
                            <option value="Non">Non</option>
                            <option value="Oui">Oui</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ticket_type">Type de Ticket</label>
                        <select name="ticket_type" id="ticket_type" required onchange="updateTicketNumber(); updateAmount();">
                            <option value="">-- Sélectionner --</option>
                            <option value="GM">GM</option>
                            <option value="MG">MG</option>
                            <option value="GA">GA</option>
                            <option value="AM">AM</option>
                            <option value="MA">MA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="departure_date">Date de Départ</label>
                        <input type="date" name="departure_date" id="departure_date" required onchange="fetchTrips()">
                    </div>
                    <div class="form-group">
                        <label for="trip_id">Trajet</label>
                        <select name="trip_id" id="trip_id" required onchange="updateAmount()">
                            <option value="">-- Sélectionner une date d'abord --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modification">Modification</label>
                        <input type="text" name="modification" id="modification" placeholder="Entrez les détails de modification">
                    </div>
                    <div class="form-group">
                        <label for="penalty">Pénalité</label>
                        <input type="text" name="penalty" id="penalty" placeholder="Entrez les détails de pénalité">
                    </div>
                    <div class="form-group">
                        <label for="reference_number">Référence</label>
                        <input type="text" name="reference_number" id="reference_number" value="<?php echo htmlspecialchars($next_reference_number); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="ticket_number">Numéro de Ticket</label>
                        <input type="text" name="ticket_number" id="ticket_number" value="<?php echo htmlspecialchars($next_ticket_number); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="selected_seat">Place Sélectionnée</label>
                        <input type="text" id="selected_seat" readonly placeholder="Cliquez sur 'Voir les places disponibles'">
                        <button type="button" id="show_seats" class="btn" onclick="showAvailableSeats()">Voir les places disponibles</button>
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Statut de Paiement</label>
                        <select name="payment_status" id="payment_status" required>
                            <option value="Non payé">Non payé</option>
                            <option value="Mvola">Mvola</option>
                            <option value="Espèce">Espèce</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="total_amount">Montant à Payer (KMF)</label>
                        <input type="text" id="total_amount" readonly value="0.00">
                    </div>
                    <button type="submit" class="btn">Réserver</button>
                </form>
            </div>

            <!-- Modale pour la disposition des places -->
            <div id="seats_modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Disposition des places</h2>
                    <div id="boat_layout"></div>
                    <div class="seat-legend">
                        <div class="legend-item"><span class="seat free"></span> Libre</div>
                        <div class="legend-item"><span class="seat occupied"></span> Occupé</div>
                        <div class="legend-item"><span class="seat crew"></span> Équipage</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="../styles/js/user_reservations.js?v=<?php echo time(); ?>"></script>
</body>
</html>