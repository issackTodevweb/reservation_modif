
<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$pdo = getConnection();
$alert = '';

// Handle search parameter
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_auto'])) {
        $departure_port = $_POST['departure_port'];
        $arrival_port = $_POST['arrival_port'];
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $mts_time = $_POST['mts_time'];
        $tfs_time = $_POST['tfs_time'];

        if ($departure_port === $arrival_port) {
            $alert = '<div class="alert alert-error">Le port de départ et d\'arrivée ne peuvent pas être identiques.</div>';
        } elseif ($start_date > $end_date) {
            $alert = '<div class="alert alert-error">La date de fin doit être postérieure à la date de début.</div>';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt_trip = $pdo->prepare("INSERT INTO trips (type, departure_port, arrival_port, departure_date, departure_time) VALUES (?, ?, ?, ?, ?)");
                $stmt_seat = $pdo->prepare("INSERT INTO seats (trip_id, seat_number, status) VALUES (?, ?, 'free')");
                $seat_numbers = [
                    '1A', '1B', '1C', '2A', '2B', '2C', '2D',
                    '3A', '3B', '3C', '3D', '3E', '3F',
                    '4A', '4B', '4C', '4D', '4E', '4F',
                    '5A', '5B', '5C', '5D', '5E', '5F',
                    '6A', '6B', '6C', '6D'
                ];

                $current_date = clone $start_date;
                while ($current_date <= $end_date) {
                    $day_of_week = $current_date->format('N');
                    $date_str = $current_date->format('Y-m-d');

                    if (in_array($day_of_week, [1, 4, 6]) && !empty($mts_time)) {
                        $stmt_trip->execute(['aller', $departure_port, $arrival_port, $date_str, $mts_time]);
                        $trip_id = $pdo->lastInsertId();
                        foreach ($seat_numbers as $seat) {
                            $stmt_seat->execute([$trip_id, $seat]);
                        }
                    } elseif (in_array($day_of_week, [2, 5, 7]) && !empty($tfs_time)) {
                        $stmt_trip->execute(['aller', $departure_port, $arrival_port, $date_str, $tfs_time]);
                        $trip_id = $pdo->lastInsertId();
                        foreach ($seat_numbers as $seat) {
                            $stmt_seat->execute([$trip_id, $seat]);
                        }
                    }

                    $current_date->modify('+1 day');
                }
                $pdo->commit();
                $alert = '<div class="alert alert-success">Trajets et places créés automatiquement avec succès !</div>';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $alert = '<div class="alert alert-error">Erreur : ' . $e->getMessage() . '</div>';
            }
        }
    } elseif (isset($_POST['update_trip'])) {
        $id = $_POST['id'];
        $departure_port = $_POST['departure_port'];
        $arrival_port = $_POST['arrival_port'];
        $departure_date = $_POST['departure_date'];
        $departure_time = $_POST['departure_time'];

        if ($departure_port === $arrival_port) {
            $alert = '<div class="alert alert-error">Le port de départ et d\'arrivée ne peuvent pas être identiques.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE trips SET type = ?, departure_port = ?, arrival_port = ?, departure_date = ?, departure_time = ? WHERE id = ?");
                $stmt->execute(['aller', $departure_port, $arrival_port, $departure_date, $departure_time, $id]);
                $alert = '<div class="alert alert-success">Trajet modifié avec succès !</div>';
            } catch (PDOException $e) {
                $alert = '<div class="alert alert-error">Erreur : ' . $e->getMessage() . '</div>';
            }
        }
    } elseif (isset($_POST['delete_trip'])) {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
            $stmt->execute([$id]);
            $alert = '<div class="alert alert-success">Trajet supprimé avec succès !</div>';
        } catch (PDOException $e) {
            $alert = '<div class="alert alert-error">Erreur : ' . $e->getMessage() . '</div>';
        }
    }
}

// Build the query for fetching trips with search filter
$sql = "SELECT * FROM trips WHERE type = 'aller'";
$params = [];
if (!empty($search_query)) {
    $sql .= " AND (departure_date LIKE :search_query OR departure_port LIKE :search_query OR arrival_port LIKE :search_query OR departure_time LIKE :search_query)";
    $params[':search_query'] = '%' . $search_query . '%';
}
$sql .= " ORDER BY departure_date DESC, departure_time DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alert = '<div class="alert alert-error">Erreur lors de la récupération des trajets : ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des trajets - Adore Comores Express</title>
    <link rel="stylesheet" href="../styles/css/admin_trips.css?v=<?php echo time(); ?>">
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
            <a href="trips.php" class="active"><i class="fas fa-ticket-alt"></i> Gestion des Trajets</a>
            <a href="finances.php"><i class="fas fa-money-bill-wave"></i> Finances</a>
            <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="seats.php"><i class="fas fa-chair"></i> Gestion des Places</a>
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
            <h1>Gestion des trajets</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Création automatique de trajets</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="departure_port_auto">Port de départ</label>
                        <select name="departure_port" id="departure_port_auto" required>
                            <option value="MWA">MWA </option>
                            <option value="GCO">GCO </option>
                            <option value="ANJ">ANJ </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="arrival_port_auto">Port d'arrivée</label>
                        <select name="arrival_port" id="arrival_port_auto" required>
                            <option value="MWA">MWA </option>
                            <option value="GCO">GCO </option>
                            <option value="ANJ">ANJ </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_date">Date de début</label>
                        <input type="date" name="start_date" id="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Date de fin</label>
                        <input type="date" name="end_date" id="end_date" required>
                    </div>
                    <div class="form-group">
                        <label for="mts_time">Heure (Lundi, Jeudi, Samedi)</label>
                        <input type="time" name="mts_time" id="mts_time" value="08:00">
                    </div>
                    <div class="form-group">
                        <label for="tfs_time">Heure (Mardi, Vendredi, Dimanche)</label>
                        <input type="time" name="tfs_time" id="tfs_time" value="10:00">
                    </div>
                    <button type="submit" name="create_auto" class="submit-btn">Créer automatiquement</button>
                </form>
            </div>
            <div class="search-container">
                <form method="GET" class="search-form">
                    <div class="form-group search-group">
                        <input type="text" name="search_query" id="search_query" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Rechercher par date, port ou heure (ex: 2025-08-10, MWA, 08:00)">
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="trips-list">
                <h2>Liste des trajets</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Départ</th>
                            <th>Arrivée</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trip['id']); ?></td>
                                <td><?php echo htmlspecialchars($trip['departure_port']); ?></td>
                                <td><?php echo htmlspecialchars($trip['arrival_port']); ?></td>
                                <td><?php echo htmlspecialchars($trip['departure_date']); ?></td>
                                <td><?php echo htmlspecialchars($trip['departure_time']); ?></td>
                                <td>
                                    <button class="action-btn edit" data-id="<?php echo $trip['id']; ?>" data-departure-port="<?php echo $trip['departure_port']; ?>" data-arrival-port="<?php echo $trip['arrival_port']; ?>" data-departure-date="<?php echo $trip['departure_date']; ?>" data-departure-time="<?php echo $trip['departure_time']; ?>"><i class="fas fa-edit"></i> Modifier</button>
                                    <button class="action-btn delete" data-id="<?php echo $trip['id']; ?>"><i class="fas fa-trash"></i> Supprimer</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2>Modifier le trajet</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_departure_port">Port de départ</label>
                    <select name="departure_port" id="edit_departure_port" required>
                        <option value="MWA">MWA </option>
                        <option value="GCO">GCO </option>
                        <option value="ANJ">ANJ </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_arrival_port">Port d'arrivée</label>
                    <select name="arrival_port" id="edit_arrival_port" required>
                        <option value="MWA">MWA</option>
                        <option value="GCO">GCO </option>
                        <option value="ANJ">ANJ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_departure_date">Date de départ</label>
                    <input type="date" name="departure_date" id="edit_departure_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_departure_time">Heure de départ</label>
                    <input type="time" name="departure_time" id="edit_departure_time" required>
                </div>
                <button type="submit" name="update_trip" class="submit-btn">Modifier le trajet</button>
            </form>
        </div>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content delete-modal-content">
            <span class="modal-close">&times;</span>
            <h2>Confirmer la suppression</h2>
            <p>Voulez-vous vraiment supprimer ce trajet ? Cette action est irréversible.</p>
            <form method="POST">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-actions">
                    <button type="submit" name="delete_trip" class="submit-btn delete-btn">Confirmer</button>
                    <button type="button" class="submit-btn cancel-btn modal-close">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../styles/js/admin_trips.js?v=<?php echo time(); ?>"></script>
</body>
</html>
