<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$pdo = getConnection();
$alert = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ajouter des tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tickets'])) {
    $ticket_type = isset($_POST['ticket_type']) ? $_POST['ticket_type'] : '';
    $year = isset($_POST['year']) ? $_POST['year'] : '';
    $start_number = isset($_POST['start_number']) ? $_POST['start_number'] : '';
    $end_number = isset($_POST['end_number']) ? $_POST['end_number'] : '';

    // Validation des champs
    if (empty($ticket_type) || empty($year) || empty($start_number) || empty($end_number)) {
        $alert = '<div class="alert alert-error">Tous les champs sont requis.</div>';
    } elseif (!preg_match('/^\d+$/', $start_number) || !preg_match('/^\d+$/', $end_number)) {
        $alert = '<div class="alert alert-error">Les numéros de début et de fin doivent être des nombres entiers.</div>';
    } elseif ($start_number > $end_number) {
        $alert = '<div class="alert alert-error">Le numéro de début doit être inférieur ou égal au numéro de fin.</div>';
    } elseif (!preg_match('/^\d{4}$/', $year)) {
        $alert = '<div class="alert alert-error">Veuillez sélectionner une année valide.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, ticket_type, status) VALUES (?, ?, 'available')");
            $existing_tickets = $pdo->query("SELECT ticket_number FROM tickets WHERE ticket_type = '$ticket_type' AND ticket_number LIKE '$ticket_type$year%'")->fetchAll(PDO::FETCH_COLUMN);
            $inserted = 0;

            for ($i = (int)$start_number; $i <= (int)$end_number; $i++) {
                $ticket_number = sprintf("%s%s-%02d", $ticket_type, $year, $i);
                if (in_array($ticket_number, $existing_tickets)) {
                    continue; // Ignore les tickets existants
                }
                $stmt->execute([$ticket_number, $ticket_type]);
                $inserted++;
            }

            $pdo->commit();
            $alert = '<div class="alert alert-success">' . $inserted . ' ticket(s) ajouté(s) avec succès.</div>';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $alert = '<div class="alert alert-error">Erreur lors de l\'ajout des tickets : ' . $e->getMessage() . '</div>';
        }
    }
}

// Mettre à jour un ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $id = (int)$_POST['id'];
    $ticket_number = $_POST['ticket_number'];
    $ticket_type = $_POST['ticket_type'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE tickets SET ticket_number = ?, ticket_type = ?, status = ? WHERE id = ?");
        $stmt->execute([$ticket_number, $ticket_type, $status, $id]);
        $alert = '<div class="alert alert-success">Ticket mis à jour avec succès.</div>';
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la mise à jour : ' . $e->getMessage() . '</div>';
    }
}

// Supprimer un ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $id = (int)$_POST['delete_ticket'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $alert = '<div class="alert alert-success">Ticket supprimé avec succès.</div>';
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la suppression : ' . $e->getMessage() . '</div>';
    }
}

// Récupérer les tickets avec recherche
$where_clause = $search ? "WHERE ticket_number = ?" : "";
$query = "SELECT id, ticket_number, ticket_type, status, created_at FROM tickets $where_clause ORDER BY ticket_number";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->execute([$search]);
} else {
    $stmt->execute();
}
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Générer les options d'année (2020 à 2030)
$current_year = date('Y');
$years = range(2020, 2030);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des tickets - Adore Comores Express</title>
    <link rel="stylesheet" href="../styles/css/admin_tickets.css?v=<?php echo time(); ?>">
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
            <a href="users.php"><i class="fas fa-users"></i><span>Utilisateurs</span></a>
            <a href="trips.php"><i class="fas fa-ship"></i><span>Trajets</span></a>
            <a href="finances.php"><i class="fas fa-dollar-sign"></i><span>Finances</span></a>
            <a href="seats.php"><i class="fas fa-chair"></i><span>Sièges</span></a>
            <a href="reservations.php"><i class="fas fa-ticket-alt"></i><span>Réservations</span></a>
            <a href="tickets.php" class="active"><i class="fas fa-ticket"></i><span>Gestion des Tickets</span></a>
            <a href="references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>
            <a href="../ticket.php"><i class="fas fa-ticket-alt"></i> Tickets</a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des tickets</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Ajouter des tickets</h2>
                <form method="POST">
                    <input type="hidden" name="add_tickets" value="1">
                    <div class="form-group">
                        <label for="ticket_type">Type de ticket</label>
                        <select name="ticket_type" id="ticket_type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="GM">GM</option>
                            <option value="MG">MG</option>
                            <option value="GA">GA</option>
                            <option value="AM">AM</option>
                            <option value="MA">MA</option>
                            <option value="AG">AG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Année</label>
                        <select name="year" id="year" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_number">Numéro de début (ex. : 01)</label>
                        <input type="number" name="start_number" id="start_number" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="end_number">Numéro de fin (ex. : 100)</label>
                        <input type="number" name="end_number" id="end_number" min="1" required>
                    </div>
                    <button type="submit" class="btn">Générer tickets</button>
                </form>
            </div>

            <div class="tickets-container">
                <h2>Liste des tickets</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Rechercher un ticket</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ex. : GM2025-53">
                        <button type="submit" class="btn">Rechercher</button>
                    </div>
                </form>
                <?php if (!empty($tickets)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Numéro de ticket</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['ticket_type']); ?></td>
                                    <td><?php echo $ticket['status'] === 'available' ? 'Disponible' : 'Réservé'; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></td>
                                    <td>
                                        <button class="btn edit-btn" 
                                                data-id="<?php echo $ticket['id']; ?>" 
                                                data-ticket-number="<?php echo htmlspecialchars($ticket['ticket_number']); ?>" 
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['ticket_type']); ?>" 
                                                data-status="<?php echo $ticket['status']; ?>">Modifier</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer ce ticket ?');">
                                            <input type="hidden" name="delete_ticket" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn delete-btn">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun ticket trouvé.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modale pour modification -->
        <div id="editTicketModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Modifier le ticket</h2>
                <form method="POST">
                    <input type="hidden" name="update_ticket" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_ticket_number">Numéro de ticket</label>
                        <input type="text" name="ticket_number" id="edit_ticket_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_ticket_type">Type de ticket</label>
                        <select name="ticket_type" id="edit_ticket_type" required>
                            <option value="GM">GM</option>
                            <option value="MG">MG</option>
                            <option value="GA">GA</option>
                            <option value="AM">AM</option>
                            <option value="MA">MA</option>
                            <option value="AG">AG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Statut</label>
                        <select name="status" id="edit_status" required>
                            <option value="available">Disponible</option>
                            <option value="reserved">Réservé</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Mettre à jour</button>
                </form>
            </div>
        </div>
    </main>

    <script src="../styles/js/admin_tickets.js?v=<?php echo time(); ?>"></script>
</body>
</html>