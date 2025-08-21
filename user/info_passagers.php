<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Accès non autorisé. Veuillez vous connecter en tant qu'utilisateur.";
    header("Location: /index.php");
    exit();
}

// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $pdo = getConnection();
} catch (PDOException $e) {
    die("Erreur : Impossible de se connecter à la base de données. Vérifiez config/database.php : " . $e->getMessage());
}

// Ajouter un membre d'équipage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_crew'])) {
    $crew_name = trim($_POST['crew_name']);
    $departure_date = $_POST['departure_date'];

    if (!empty($crew_name) && !empty($departure_date)) {
        try {
            $pdo->beginTransaction();

            // Insérer le membre d'équipage dans crew_members
            $stmt = $pdo->prepare("INSERT INTO crew_members (name, role, status) VALUES (?, 'crew', 'available')");
            $stmt->execute([$crew_name]);
            $crew_member_id = $pdo->lastInsertId();

            // Trouver le trip_id correspondant à la departure_date
            $stmt = $pdo->prepare("SELECT id FROM trips WHERE departure_date = ? AND type = 'aller' LIMIT 1");
            $stmt->execute([$departure_date]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($trip) {
                // Associer le membre d'équipage au trajet dans trip_crew
                $stmt = $pdo->prepare("INSERT INTO trip_crew (trip_id, crew_member_id) VALUES (?, ?)");
                $stmt->execute([$trip['id'], $crew_member_id]);
                $_SESSION['success'] = "Membre d'équipage ajouté avec succès.";
            } else {
                throw new Exception("Aucun trajet trouvé pour la date spécifiée.");
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur lors de l'ajout du membre d'équipage : " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Le nom du membre d'équipage et la date de départ sont requis.";
    }
    header("Location: info_passagers.php" . (!empty($departure_date) ? "?date_filter=" . urlencode($departure_date) : ""));
    exit();
}

// Modifier un membre d'équipage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_crew'])) {
    $crew_id = (int)$_POST['crew_id'];
    $crew_name = trim($_POST['crew_name']);
    $filter_date = $_POST['filter_date'] ?? $_GET['date_filter'] ?? date('Y-m-d');

    if (!empty($crew_name) && !empty($crew_id)) {
        try {
            $stmt = $pdo->prepare("UPDATE crew_members SET name = ? WHERE id = ?");
            $stmt->execute([$crew_name, $crew_id]);
            $_SESSION['success'] = "Membre d'équipage modifié avec succès.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la modification du membre d'équipage : " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Le nom du membre d'équipage est requis.";
    }
    header("Location: info_passagers.php?date_filter=" . urlencode($filter_date));
    exit();
}

// Supprimer un membre d'équipage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_crew'])) {
    $crew_id = (int)$_POST['crew_id'];
    $filter_date = $_POST['filter_date'] ?? $_GET['date_filter'] ?? date('Y-m-d');

    if (!empty($crew_id)) {
        try {
            $pdo->beginTransaction();
            // Supprimer les assignations dans trip_crew
            $stmt = $pdo->prepare("DELETE FROM trip_crew WHERE crew_member_id = ?");
            $stmt->execute([$crew_id]);
            // Supprimer le membre d'équipage
            $stmt = $pdo->prepare("DELETE FROM crew_members WHERE id = ?");
            $stmt->execute([$crew_id]);
            $pdo->commit();
            $_SESSION['success'] = "Membre d'équipage supprimé avec succès.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur lors de la suppression du membre d'équipage : " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "ID du membre d'équipage invalide.";
    }
    header("Location: info_passagers.php?date_filter=" . urlencode($filter_date));
    exit();
}

// Gestion de la recherche et du filtrage
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
$ticket_type_filter = isset($_GET['ticket_type']) ? trim($_GET['ticket_type']) : '';
$where_clause = '';
$params = [$_SESSION['user_id']];
if (!empty($search_query)) {
    $where_clause .= " AND (r.passenger_name LIKE ? OR r.phone_number LIKE ? OR ref.reference_number LIKE ? OR r.passport_cin LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
if (!empty($date_filter)) {
    $where_clause .= " AND r.departure_date = ?";
    $params[] = $date_filter;
}
if (!empty($ticket_type_filter) && in_array($ticket_type_filter, ['GM', 'MG', 'GA', 'AM', 'MA'])) {
    $where_clause .= " AND r.ticket_type = ?";
    $params[] = $ticket_type_filter;
}

// Récupérer les informations des passagers
try {
    $sql = "SELECT r.passenger_name, r.phone_number, r.nationality, r.passport_cin, r.with_baby, r.departure_date, 
                   t.departure_port, t.arrival_port, t.departure_time, ref.reference_number, r.ticket_type
            FROM reservations r
            JOIN trips t ON r.trip_id = t.id
            JOIN reference ref ON r.reference_id = ref.id
            WHERE r.user_id = ? $where_clause
            ORDER BY r.departure_date DESC, t.departure_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $passagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des passagers : " . $e->getMessage());
    die("Erreur lors de la récupération des passagers : " . htmlspecialchars($e->getMessage()));
}

// Récupérer la liste des membres d'équipage pour le modal et la liste
try {
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.name AS crew_name 
        FROM crew_members cm
        JOIN trip_crew tc ON cm.id = tc.crew_member_id
        JOIN trips t ON tc.trip_id = t.id
        WHERE t.departure_date = ? 
        ORDER BY cm.name
    ");
    $stmt->execute([$date_filter ?: date('Y-m-d')]);
    $crew_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de la liste des membres d'équipage : " . $e->getMessage());
    die("Erreur lors de la récupération de la liste des membres d'équipage : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informations des Passagers - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/user_info_passagers.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
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
            <a href="list_reservations.php"><i class="fas fa-list"></i> Liste des Réservations</a>
            <a href="info_passagers.php" class="active"><i class="fas fa-users"></i> Infos Passagers</a>
            <a href="colis.php"><i class="fas fa-box"></i> Colis</a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Se Déconnecter</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Informations des Passagers</h1>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Section des passagers -->
            <div class="passenger-section">
                <div class="search-sort-container">
                    <form method="GET" action="info_passagers.php" class="search-container">
                        <input type="text" name="search" placeholder="Rechercher par passager, téléphone, référence, passeport..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="date" name="date_filter" value="<?php echo htmlspecialchars($date_filter); ?>">
                        <select name="ticket_type">
                            <option value="">Tous les types de tickets</option>
                            <option value="GM" <?php echo $ticket_type_filter === 'GM' ? 'selected' : ''; ?>>GM</option>
                            <option value="MG" <?php echo $ticket_type_filter === 'MG' ? 'selected' : ''; ?>>MG</option>
                            <option value="GA" <?php echo $ticket_type_filter === 'GA' ? 'selected' : ''; ?>>GA</option>
                            <option value="AM" <?php echo $ticket_type_filter === 'AM' ? 'selected' : ''; ?>>AM</option>
                            <option value="MA" <?php echo $ticket_type_filter === 'MA' ? 'selected' : ''; ?>>MA</option>
                        </select>
                        <button type="submit" class="btn"><i class="fas fa-search"></i> Rechercher</button>
                        <?php if (!empty($search_query) || !empty($date_filter) || !empty($ticket_type_filter)): ?>
                            <a href="info_passagers.php" class="btn reset-btn">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>
                <h2>Liste des Passagers - <?php echo date('d/m/Y', strtotime($date_filter ?: date('Y-m-d'))); ?></h2>
                <button class="export-pdf-btn" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> Exporter en PDF</button>
                <?php if (!empty($passagers)): ?>
                    <table id="passengers-table">
                        <thead>
                            <tr>
                                <th>Nom du Passager</th>
                                <th>Téléphone</th>
                                <th>Nationalité</th>
                                <th>Passeport/CIN</th>
                                <th>Avec Bébé</th>
                                <th>Trajet</th>
                                <th>Référence</th>
                                <th>Type de Ticket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passagers as $passager): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($passager['passenger_name']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['nationality']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['passport_cin']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['with_baby']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['departure_port'] . ' → ' . $passager['arrival_port'] . ' (' . date('d/m/Y H:i', strtotime($passager['departure_date'] . ' ' . $passager['departure_time'])) . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($passager['reference_number']); ?></td>
                                    <td><?php echo htmlspecialchars($passager['ticket_type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun passager trouvé pour cette date.</p>
                <?php endif; ?>
            </div>

            <!-- Section de l'équipage -->
            <div class="crew-section">
                <h2>Gestion de l'Équipage - <?php echo date('d/m/Y', strtotime($date_filter ?: date('Y-m-d'))); ?></h2>
                <form method="POST" class="crew-form">
                    <label for="crew_name">Ajouter un membre d'équipage :</label>
                    <input type="text" id="crew_name" name="crew_name" placeholder="Nom du membre d'équipage" required>
                    <input type="date" id="crew_departure_date" name="departure_date" value="<?php echo htmlspecialchars($date_filter ?: date('Y-m-d')); ?>" required>
                    <button type="submit" name="add_crew">Ajouter</button>
                </form>
                <button class="manage-crew-btn" onclick="openCrewModal()">Gérer l'équipage</button>
                <div class="crew-list">
                    <h3>Membres actuels :</h3>
                    <?php if (!empty($crew_list)): ?>
                        <ul>
                            <?php foreach ($crew_list as $crew): ?>
                                <li><?php echo htmlspecialchars($crew['crew_name']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Aucun membre d'équipage pour cette date.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal pour gérer les membres d'équipage -->
            <div id="crewModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeCrewModal()">&times;</span>
                    <h2>Gérer les membres d'équipage - <?php echo date('d/m/Y', strtotime($date_filter ?: date('Y-m-d'))); ?></h2>
                    <?php if (!empty($crew_list)): ?>
                        <?php foreach ($crew_list as $crew): ?>
                            <div class="crew-item">
                                <form method="POST">
                                    <input type="hidden" name="crew_id" value="<?php echo $crew['id']; ?>">
                                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date_filter ?: date('Y-m-d')); ?>">
                                    <input type="text" name="crew_name" value="<?php echo htmlspecialchars($crew['crew_name']); ?>" required>
                                    <button type="submit" name="edit_crew" class="edit-btn">Modifier</button>
                                    <button type="submit" name="delete_crew" class="delete-btn">Supprimer</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucun membre d'équipage pour cette date.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="../styles/js/user_info_passagers.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php $pdo = null; ?>