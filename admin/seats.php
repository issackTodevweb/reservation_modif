<?php
session_start();
require_once '../config/database.php';

$pdo = getConnection();
$alert = '';
$selected_trip_id = isset($_POST['trip_id']) && is_numeric($_POST['trip_id']) ? (int)$_POST['trip_id'] : (isset($_GET['trip_id']) && is_numeric($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0);

// Récupérer les places pour le trajet sélectionné
$seats = [];
if ($selected_trip_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT seat_number, status FROM seats WHERE trip_id = ? ORDER BY seat_number");
        $stmt->execute([$selected_trip_id]);
        $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucune place n'est trouvée, initialiser les 29 places avec statut 'free'
        if (empty($seats)) {
            $seat_numbers = [
                '1A', '1B', '1C', '2A', '2B', '2C', '2D',
                '3A', '3B', '3C', '3D', '3E', '3F',
                '4A', '4B', '4C', '4D', '4E', '4F',
                '5A', '5B', '5C', '5D', '5E', '5F',
                '6A', '6B', '6C', '6D'
            ];
            $stmt_seat = $pdo->prepare("INSERT INTO seats (trip_id, seat_number, status) VALUES (?, ?, 'free')");
            foreach ($seat_numbers as $seat) {
                $stmt_seat->execute([$selected_trip_id, $seat]);
            }
            // Récupérer les places après insertion
            $stmt->execute([$selected_trip_id]);
            $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $alert = '<div class="alert alert-success">Places initialisées pour ce trajet.</div>';
        }
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la récupération ou initialisation des places : ' . $e->getMessage() . '</div>';
    }
}

// Si la requête est AJAX, retourner uniquement le HTML de la disposition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_id']) && !isset($_POST['update_seat'])) {
    if (!empty($seats)) {
        ?>
        <div class="boat-layout">
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('1A', array_column($seats, 'seat_number'))]) ? $seats[array_search('1A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1A">1A</div>
                    <div class="seat <?php echo isset($seats[array_search('1B', array_column($seats, 'seat_number'))]) ? $seats[array_search('1B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1B">1B</div>
                </div>
                <div class="aisle"></div>
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('1C', array_column($seats, 'seat_number'))]) ? $seats[array_search('1C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1C">1C</div>
                </div>
            </div>
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('2A', array_column($seats, 'seat_number'))]) ? $seats[array_search('2A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2A">2A</div>
                    <div class="seat <?php echo isset($seats[array_search('2B', array_column($seats, 'seat_number'))]) ? $seats[array_search('2B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2B">2B</div>
                </div>
                <div class="aisle"></div>
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('2C', array_column($seats, 'seat_number'))]) ? $seats[array_search('2C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2C">2C</div>
                    <div class="seat <?php echo isset($seats[array_search('2D', array_column($seats, 'seat_number'))]) ? $seats[array_search('2D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2D">2D</div>
                </div>
            </div>
            <div class="aisle vertical"></div>
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('3A', array_column($seats, 'seat_number'))]) ? $seats[array_search('3A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3A">3A</div>
                    <div class="seat <?php echo isset($seats[array_search('3B', array_column($seats, 'seat_number'))]) ? $seats[array_search('3B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3B">3B</div>
                    <div class="seat <?php echo isset($seats[array_search('3C', array_column($seats, 'seat_number'))]) ? $seats[array_search('3C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3C">3C</div>
                </div>
                <div class="aisle"></div>
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('3D', array_column($seats, 'seat_number'))]) ? $seats[array_search('3D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3D">3D</div>
                    <div class="seat <?php echo isset($seats[array_search('3E', array_column($seats, 'seat_number'))]) ? $seats[array_search('3E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3E">3E</div>
                    <div class="seat <?php echo isset($seats[array_search('3F', array_column($seats, 'seat_number'))]) ? $seats[array_search('3F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3F">3F</div>
                </div>
            </div>
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('4A', array_column($seats, 'seat_number'))]) ? $seats[array_search('4A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4A">4A</div>
                    <div class="seat <?php echo isset($seats[array_search('4B', array_column($seats, 'seat_number'))]) ? $seats[array_search('4B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4B">4B</div>
                    <div class="seat <?php echo isset($seats[array_search('4C', array_column($seats, 'seat_number'))]) ? $seats[array_search('4C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4C">4C</div>
                </div>
                <div class="aisle"></div>
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('4D', array_column($seats, 'seat_number'))]) ? $seats[array_search('4D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4D">4D</div>
                    <div class="seat <?php echo isset($seats[array_search('4E', array_column($seats, 'seat_number'))]) ? $seats[array_search('4E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4E">4E</div>
                    <div class="seat <?php echo isset($seats[array_search('4F', array_column($seats, 'seat_number'))]) ? $seats[array_search('4F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4F">4F</div>
                </div>
            </div>
            <div class="aisle vertical"></div>
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('5A', array_column($seats, 'seat_number'))]) ? $seats[array_search('5A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5A">5A</div>
                    <div class="seat <?php echo isset($seats[array_search('5B', array_column($seats, 'seat_number'))]) ? $seats[array_search('5B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5B">5B</div>
                    <div class="seat <?php echo isset($seats[array_search('5C', array_column($seats, 'seat_number'))]) ? $seats[array_search('5C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5C">5C</div>
                </div>
                <div class="aisle"></div>
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('5D', array_column($seats, 'seat_number'))]) ? $seats[array_search('5D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5D">5D</div>
                    <div class="seat <?php echo isset($seats[array_search('5E', array_column($seats, 'seat_number'))]) ? $seats[array_search('5E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5E">5E</div>
                    <div class="seat <?php echo isset($seats[array_search('5F', array_column($seats, 'seat_number'))]) ? $seats[array_search('5F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5F">5F</div>
                </div>
            </div>
            <div class="aisle vertical"></div>
            <div class="seat-row">
                <div class="seat-block">
                    <div class="seat <?php echo isset($seats[array_search('6A', array_column($seats, 'seat_number'))]) ? $seats[array_search('6A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6A">6A</div>
                    <div class="seat <?php echo isset($seats[array_search('6B', array_column($seats, 'seat_number'))]) ? $seats[array_search('6B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6B">6B</div>
                    <div class="seat <?php echo isset($seats[array_search('6C', array_column($seats, 'seat_number'))]) ? $seats[array_search('6C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6C">6C</div>
                    <div class="seat <?php echo isset($seats[array_search('6D', array_column($seats, 'seat_number'))]) ? $seats[array_search('6D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6D">6D</div>
                </div>
            </div>
        </div>
        <?php
        exit;
    }
}

// Vérifier l'accès admin uniquement pour l'affichage de la page complète
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Récupérer tous les trajets pour le sélecteur (pour admin)
$stmt = $pdo->query("SELECT id, departure_port, arrival_port, departure_date, departure_time FROM trips ORDER BY departure_date DESC, departure_time DESC");
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mettre à jour le statut d’une place via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seat'])) {
    $trip_id = $_POST['trip_id'];
    $seat_number = $_POST['seat_number'];
    $current_status = $_POST['current_status'];

    // Définir l’ordre des statuts : free → occupied → crew → free
    $status_order = ['free' => 'occupied', 'occupied' => 'crew', 'crew' => 'free'];
    $new_status = $status_order[$current_status];

    try {
        $stmt = $pdo->prepare("UPDATE seats SET status = ? WHERE trip_id = ? AND seat_number = ?");
        $stmt->execute([$new_status, $trip_id, $seat_number]);
        echo json_encode(['success' => true, 'new_status' => $new_status]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des places - Adore Comores Express</title>
    <link rel="stylesheet" href="../styles/css/admin_seats.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
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
            <a href="seats.php" class="active"><i class="fas fa-chair"></i> Gestion des Places</a>
            <a href="reservations.php"><i class="fas fa-book"></i> Réservations</a>
            <a href="tickets.php"><i class="fas fa-ticket"></i><span>Gestion des Tickets</span></a>
            <a href="references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>

            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des places</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Sélectionner un trajet</h2>
                <form method="GET">
                    <div class="form-group">
                        <label for="trip_id">Trajet</label>
                        <select name="trip_id" id="trip_id" onchange="this.form.submit()" required>
                            <option value="">-- Sélectionner un trajet --</option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?php echo $trip['id']; ?>" <?php echo $selected_trip_id == $trip['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($trip['departure_port'] . ' → ' . $trip['arrival_port'] . ' (' . $trip['departure_date'] . ' ' . $trip['departure_time'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <?php if ($selected_trip_id > 0): ?>
                <?php if (!empty($seats)): ?>
                    <div class="seats-container">
                        <h2>Disposition des places</h2>
                        <div class="boat-layout">
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('1A', array_column($seats, 'seat_number'))]) ? $seats[array_search('1A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1A">1A</div>
                                    <div class="seat <?php echo isset($seats[array_search('1B', array_column($seats, 'seat_number'))]) ? $seats[array_search('1B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1B">1B</div>
                                </div>
                                <div class="aisle"></div>
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('1C', array_column($seats, 'seat_number'))]) ? $seats[array_search('1C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="1C">1C</div>
                                </div>
                            </div>
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('2A', array_column($seats, 'seat_number'))]) ? $seats[array_search('2A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2A">2A</div>
                                    <div class="seat <?php echo isset($seats[array_search('2B', array_column($seats, 'seat_number'))]) ? $seats[array_search('2B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2B">2B</div>
                                </div>
                                <div class="aisle"></div>
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('2C', array_column($seats, 'seat_number'))]) ? $seats[array_search('2C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2C">2C</div>
                                    <div class="seat <?php echo isset($seats[array_search('2D', array_column($seats, 'seat_number'))]) ? $seats[array_search('2D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="2D">2D</div>
                                </div>
                            </div>
                            <div class="aisle vertical"></div>
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('3A', array_column($seats, 'seat_number'))]) ? $seats[array_search('3A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3A">3A</div>
                                    <div class="seat <?php echo isset($seats[array_search('3B', array_column($seats, 'seat_number'))]) ? $seats[array_search('3B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3B">3B</div>
                                    <div class="seat <?php echo isset($seats[array_search('3C', array_column($seats, 'seat_number'))]) ? $seats[array_search('3C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3C">3C</div>
                                </div>
                                <div class="aisle"></div>
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('3D', array_column($seats, 'seat_number'))]) ? $seats[array_search('3D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3D">3D</div>
                                    <div class="seat <?php echo isset($seats[array_search('3E', array_column($seats, 'seat_number'))]) ? $seats[array_search('3E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3E">3E</div>
                                    <div class="seat <?php echo isset($seats[array_search('3F', array_column($seats, 'seat_number'))]) ? $seats[array_search('3F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="3F">3F</div>
                                </div>
                            </div>
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('4A', array_column($seats, 'seat_number'))]) ? $seats[array_search('4A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4A">4A</div>
                                    <div class="seat <?php echo isset($seats[array_search('4B', array_column($seats, 'seat_number'))]) ? $seats[array_search('4B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4B">4B</div>
                                    <div class="seat <?php echo isset($seats[array_search('4C', array_column($seats, 'seat_number'))]) ? $seats[array_search('4C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4C">4C</div>
                                </div>
                                <div class="aisle"></div>
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('4D', array_column($seats, 'seat_number'))]) ? $seats[array_search('4D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4D">4D</div>
                                    <div class="seat <?php echo isset($seats[array_search('4E', array_column($seats, 'seat_number'))]) ? $seats[array_search('4E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4E">4E</div>
                                    <div class="seat <?php echo isset($seats[array_search('4F', array_column($seats, 'seat_number'))]) ? $seats[array_search('4F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="4F">4F</div>
                                </div>
                            </div>
                            <div class="aisle vertical"></div>
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('5A', array_column($seats, 'seat_number'))]) ? $seats[array_search('5A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5A">5A</div>
                                    <div class="seat <?php echo isset($seats[array_search('5B', array_column($seats, 'seat_number'))]) ? $seats[array_search('5B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5B">5B</div>
                                    <div class="seat <?php echo isset($seats[array_search('5C', array_column($seats, 'seat_number'))]) ? $seats[array_search('5C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5C">5C</div>
                                </div>
                                <div class="aisle"></div>
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('5D', array_column($seats, 'seat_number'))]) ? $seats[array_search('5D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5D">5D</div>
                                    <div class="seat <?php echo isset($seats[array_search('5E', array_column($seats, 'seat_number'))]) ? $seats[array_search('5E', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5E">5E</div>
                                    <div class="seat <?php echo isset($seats[array_search('5F', array_column($seats, 'seat_number'))]) ? $seats[array_search('5F', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="5F">5F</div>
                                </div>
                            </div>
                            <div class="aisle vertical"></div>
                            <div class="seat-row">
                                <div class="seat-block">
                                    <div class="seat <?php echo isset($seats[array_search('6A', array_column($seats, 'seat_number'))]) ? $seats[array_search('6A', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6A">6A</div>
                                    <div class="seat <?php echo isset($seats[array_search('6B', array_column($seats, 'seat_number'))]) ? $seats[array_search('6B', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6B">6B</div>
                                    <div class="seat <?php echo isset($seats[array_search('6C', array_column($seats, 'seat_number'))]) ? $seats[array_search('6C', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6C">6C</div>
                                    <div class="seat <?php echo isset($seats[array_search('6D', array_column($seats, 'seat_number'))]) ? $seats[array_search('6D', array_column($seats, 'seat_number'))]['status'] : 'free'; ?>" data-trip-id="<?php echo $selected_trip_id; ?>" data-seat-number="6D">6D</div>
                                </div>
                            </div>
                        </div>
                        <div class="seat-legend">
                            <div class="legend-item"><span class="seat free"></span> Libre</div>
                            <div class="legend-item"><span class="seat occupied"></span> Occupé</div>
                            <div class="legend-item"><span class="seat crew"></span> Équipage</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">Aucune place trouvée pour ce trajet. Veuillez réessayer ou contacter l'administrateur.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="../styles/js/admin_seats.js?v=<?php echo time(); ?>"></script>
</body>
</html>