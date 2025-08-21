<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
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
    header("Location: info_passagers.php?filter_date=" . urlencode($departure_date) . "&filter_ticket_type=" . urlencode($_GET['filter_ticket_type'] ?? ''));
    exit();
}

// Modifier un membre d'équipage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_crew'])) {
    $crew_id = (int)$_POST['crew_id'];
    $crew_name = trim($_POST['crew_name']);
    $filter_date = $_POST['filter_date'] ?? $_GET['filter_date'] ?? date('Y-m-d');

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
    header("Location: info_passagers.php?filter_date=" . urlencode($filter_date) . "&filter_ticket_type=" . urlencode($_GET['filter_ticket_type'] ?? ''));
    exit();
}

// Supprimer un membre d'équipage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_crew'])) {
    $crew_id = (int)$_POST['crew_id'];
    $filter_date = $_POST['filter_date'] ?? $_GET['filter_date'] ?? date('Y-m-d');

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
    header("Location: info_passagers.php?filter_date=" . urlencode($filter_date) . "&filter_ticket_type=" . urlencode($_GET['filter_ticket_type'] ?? ''));
    exit();
}

// Paramètres de tri et filtre
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$filter_ticket_type = isset($_GET['filter_ticket_type']) && in_array($_GET['filter_ticket_type'], ['GM', 'MG', 'GA', 'AM', 'MA']) ? $_GET['filter_ticket_type'] : '';
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'departure_date';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
$allowed_columns = ['passenger_name', 'phone_number', 'nationality', 'ticket_number', 'ticket_type', 'with_baby', 'departure_date', 'reference_number'];
$sort_column = in_array($sort_column, $allowed_columns) ? $sort_column : 'departure_date';

// Récupérer les membres d'équipage pour la date filtrée
try {
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.name AS crew_name 
        FROM crew_members cm
        JOIN trip_crew tc ON cm.id = tc.crew_member_id
        JOIN trips t ON tc.trip_id = t.id
        WHERE t.departure_date = ? 
        ORDER BY cm.name
    ");
    $stmt->execute([$filter_date]);
    $crew_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération de la liste des membres d'équipage : " . $e->getMessage());
}

// Construire la requête SQL pour les passagers
$sql = "SELECT r.passenger_name, r.phone_number, r.nationality, r.passport_cin, r.with_baby, 
               t.departure_port, t.arrival_port, r.departure_date, t.departure_time, 
               ref.reference_number, tk.ticket_number, r.ticket_type
        FROM reservations r
        JOIN trips t ON r.trip_id = t.id
        JOIN reference ref ON r.reference_id = ref.id
        JOIN tickets tk ON r.ticket_id = tk.id
        WHERE r.departure_date = :filter_date";
$params = ['filter_date' => $filter_date];

// Ajouter le filtre par type de ticket si spécifié
if (!empty($filter_ticket_type)) {
    $sql .= " AND r.ticket_type = :filter_ticket_type";
    $params['filter_ticket_type'] = $filter_ticket_type;
}

$sql .= " ORDER BY $sort_column $sort_order";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des données des passagers : " . $e->getMessage());
}

$pdo = null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informations des Passagers - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/admin_info_passagers.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Tableau de Bord</span></a>
            <a href="trips.php"><i class="fas fa-ticket-alt"></i><span>Gestion des Trajets</span></a>
            <a href="finances.php"><i class="fas fa-money-bill-wave"></i><span>Finances</span></a>
            <a href="users.php"><i class="fas fa-users"></i><span>Utilisateurs</span></a>
            <a href="seats.php"><i class="fas fa-chair"></i><span>Gestion des Places</span></a>
            <a href="reservations.php"><i class="fas fa-book"></i><span>Réservations</span></a>
            <a href="tickets.php"><i class="fas fa-ticket"></i><span>Gestion des Tickets</span></a>
            <a href="references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php" class="active"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Informations des Passagers</h1>
            <p>Consultez et triez les informations des passagers et gérez l'équipage pour tous les trajets.</p>

            <!-- Messages de succès ou d'erreur -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filtre par date et type de ticket -->
            <div class="filter-section">
                <form method="GET" class="date-filter">
                    <label for="filter_date">Filtrer par date de départ :</label>
                    <input type="date" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    <label for="filter_ticket_type">Type de Ticket :</label>
                    <select id="filter_ticket_type" name="filter_ticket_type">
                        <option value="" <?php echo $filter_ticket_type === '' ? 'selected' : ''; ?>>Tous</option>
                        <option value="GM" <?php echo $filter_ticket_type === 'GM' ? 'selected' : ''; ?>>GM</option>
                        <option value="MG" <?php echo $filter_ticket_type === 'MG' ? 'selected' : ''; ?>>MG</option>
                        <option value="GA" <?php echo $filter_ticket_type === 'GA' ? 'selected' : ''; ?>>GA</option>
                        <option value="AM" <?php echo $filter_ticket_type === 'AM' ? 'selected' : ''; ?>>AM</option>
                        <option value="MA" <?php echo $filter_ticket_type === 'MA' ? 'selected' : ''; ?>>MA</option>
                    </select>
                    <button type="submit">Filtrer</button>
                </form>
            </div>

            <!-- Sections pour passagers et équipage -->
            <div class="sections-container">
                <!-- Section des passagers -->
                <div class="passenger-section">
                    <h2>Liste des Passagers - <?php echo date('d/m/Y', strtotime($filter_date)); ?><?php echo $filter_ticket_type ? ' (Type: ' . htmlspecialchars($filter_ticket_type) . ')' : ''; ?></h2>
                    <button class="export-pdf-btn" onclick="exportToPDF()">Exporter en PDF</button>
                    <?php if (!empty($passengers)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php
                                    $columns = [
                                        'passenger_name' => 'Nom du Passager',
                                        'phone_number' => 'Téléphone',
                                        'nationality' => 'Nationalité',
                                        'ticket_number' => 'Numéro de Ticket',
                                        'ticket_type' => 'Type de Ticket',
                                        'passport_cin' => 'Passeport/CIN',
                                        'with_baby' => 'Avec Bébé',
                                        'departure_date' => 'Trajet',
                                        'reference_number' => 'Référence'
                                    ];
                                    foreach ($columns as $col => $label) {
                                        $new_order = ($sort_column === $col && $sort_order === 'ASC') ? 'desc' : 'asc';
                                        echo "<th><a href='?filter_date=$filter_date&filter_ticket_type=" . urlencode($filter_ticket_type) . "&sort=$col&order=$new_order'>$label " . ($sort_column === $col ? ($sort_order === 'ASC' ? '↑' : '↓') : '') . "</a></th>";
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($passengers as $passenger): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($passenger['passenger_name']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['nationality']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['ticket_number']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['ticket_type']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['passport_cin']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['with_baby']); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['departure_port'] . ' -> ' . $passenger['arrival_port'] . ', ' . date('d/m/Y H:i', strtotime($passenger['departure_date'] . ' ' . $passenger['departure_time']))); ?></td>
                                        <td><?php echo htmlspecialchars($passenger['reference_number']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Aucun passager trouvé pour cette date<?php echo $filter_ticket_type ? ' et ce type de ticket (' . htmlspecialchars($filter_ticket_type) . ')' : ''; ?>.</p>
                    <?php endif; ?>
                </div>

                <!-- Section de l'équipage -->
                <div class="crew-section">
                    <h2>Gestion de l'Équipage - <?php echo date('d/m/Y', strtotime($filter_date)); ?></h2>
                    <form method="POST" class="crew-form">
                        <label for="crew_name">Ajouter un membre d'équipage :</label>
                        <input type="text" id="crew_name" name="crew_name" placeholder="Nom du membre d'équipage" required>
                        <input type="date" id="crew_departure_date" name="departure_date" value="<?php echo htmlspecialchars($filter_date); ?>" required>
                        <button type="submit" name="add_crew">Ajouter</button>
                    </form>
                    <button class="manage-crew-btn" onclick="openCrewModal()">Gérer l'équipage</button>
                    <?php if (!empty($crew_list)): ?>
                        <table class="crew-list-table">
                            <thead>
                                <tr>
                                    <th>Nom du Membre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($crew_list as $crew): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($crew['crew_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Aucun membre d'équipage pour cette date.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal pour gérer les membres d'équipage -->
            <div id="crewModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeCrewModal()">&times;</span>
                    <h2>Gérer les membres d'équipage - <?php echo date('d/m/Y', strtotime($filter_date)); ?></h2>
                    <?php if (!empty($crew_list)): ?>
                        <?php foreach ($crew_list as $crew): ?>
                            <div class="crew-item">
                                <form method="POST">
                                    <input type="hidden" name="crew_id" value="<?php echo $crew['id']; ?>">
                                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                                    <input type="text" name="crew_name" value="<?php echo htmlspecialchars($crew['crew_name']); ?>" required>
                                    <button type="submit" name="edit_crew" class="edit-btn"><i class="fas fa-edit"></i> Modifier</button>
                                    <button type="submit" name="delete_crew" class="delete-btn"><i class="fas fa-trash"></i> Supprimer</button>
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
    <script src="../styles/js/admin_info_passagers.js?v=<?php echo time(); ?>"></script>
</body>
</html>