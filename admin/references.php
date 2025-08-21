
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

// Ajouter des références
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_references'])) {
    $reference_type = isset($_POST['reference_type']) ? $_POST['reference_type'] : '';
    $year = isset($_POST['year']) ? $_POST['year'] : '';
    $start_number = isset($_POST['start_number']) ? $_POST['start_number'] : '';
    $end_number = isset($_POST['end_number']) ? $_POST['end_number'] : '';

    // Validation des champs
    if (empty($reference_type) || empty($year) || empty($start_number) || empty($end_number)) {
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
            $stmt = $pdo->prepare("INSERT INTO reference (reference_number, reference_type, status) VALUES (?, ?, 'available')");
            $existing_references = $pdo->query("SELECT reference_number FROM reference WHERE reference_type = '$reference_type' AND reference_number LIKE '$reference_type-$year%'")->fetchAll(PDO::FETCH_COLUMN);
            $inserted = 0;

            for ($i = (int)$start_number; $i <= (int)$end_number; $i++) {
                $reference_number = sprintf("%s-%s-%04d", $reference_type, $year, $i);
                if (in_array($reference_number, $existing_references)) {
                    continue; // Ignore les références existantes
                }
                $stmt->execute([$reference_number, $reference_type]);
                $inserted++;
            }

            $pdo->commit();
            $alert = '<div class="alert alert-success">' . $inserted . ' référence(s) ajoutée(s) avec succès.</div>';
        } catch (PDOException $e) {
            $pdo->rollback();
            $alert = '<div class="alert alert-error">Erreur lors de l\'ajout des références : ' . $e->getMessage() . '</div>';
        }
    }
}

// Mettre à jour une référence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reference'])) {
    $id = (int)$_POST['id'];
    $reference_number = $_POST['reference_number'];
    $reference_type = $_POST['reference_type'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE reference SET reference_number = ?, reference_type = ?, status = ? WHERE id = ?");
        $stmt->execute([$reference_number, $reference_type, $status, $id]);
        $alert = '<div class="alert alert-success">Référence mise à jour avec succès.</div>';
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la mise à jour : ' . $e->getMessage() . '</div>';
    }
}

// Supprimer une référence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reference'])) {
    $id = (int)$_POST['delete_reference'];
    try {
        $stmt = $pdo->prepare("DELETE FROM reference WHERE id = ?");
        $stmt->execute([$id]);
        $alert = '<div class="alert alert-success">Référence supprimée avec succès.</div>';
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la suppression : ' . $e->getMessage() . '</div>';
    }
}

// Récupérer les références avec recherche
$where_clause = $search ? "WHERE reference_number = ?" : "";
$query = "SELECT id, reference_number, reference_type, status, created_at FROM reference $where_clause ORDER BY reference_number";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->execute([$search]);
} else {
    $stmt->execute();
}
$references = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Générer les options d'année (2020 à 2030)
$current_year = date('Y');
$years = range(2020, 2030);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des références - Adore Comores Express</title>
    <link rel="stylesheet" href="../styles/css/admin_references.css?v=<?php echo time(); ?>">
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
            <a href="seats.php"><i class="fas fa-chair"></i><span>Gestion des Places</span></a>
            <a href="reservations.php"><i class="fas fa-ticket-alt"></i><span>Réservations</span></a>
            <a href="tickets.php"><i class="fas fa-ticket"></i><span> Gestion des Tickets</span></a>
            <a href="references.php" class="active"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>

            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des références</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Ajouter des références</h2>
                <form method="POST">
                    <input type="hidden" name="add_references" value="1">
                    <div class="form-group">
                        <label for="reference_type">Type de référence</label>
                        <select name="reference_type" id="reference_type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="R">R</option>
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
                        <label for="start_number">Numéro de début (ex. : 0001)</label>
                        <input type="number" name="start_number" id="start_number" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="end_number">Numéro de fin (ex. : 9999)</label>
                        <input type="number" name="end_number" id="end_number" min="1" required>
                    </div>
                    <button type="submit" class="btn">Générer références</button>
                </form>
            </div>

            <div class="references-container">
                <h2>Liste des références</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Rechercher une référence</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ex. : R-2025-0002">
                        <button type="submit" class="btn">Rechercher</button>
                    </div>
                </form>
                <?php if (!empty($references)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Numéro de référence</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($references as $reference): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reference['reference_number']); ?></td>
                                    <td><?php echo htmlspecialchars($reference['reference_type']); ?></td>
                                    <td><?php echo $reference['status'] === 'available' ? 'Disponible' : 'Réservé'; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($reference['created_at'])); ?></td>
                                    <td>
                                        <button class="btn edit-btn" 
                                                data-id="<?php echo $reference['id']; ?>" 
                                                data-reference-number="<?php echo htmlspecialchars($reference['reference_number']); ?>" 
                                                data-reference-type="<?php echo htmlspecialchars($reference['reference_type']); ?>" 
                                                data-status="<?php echo $reference['status']; ?>">Modifier</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer cette référence ?');">
                                            <input type="hidden" name="delete_reference" value="<?php echo $reference['id']; ?>">
                                            <button type="submit" class="btn delete-btn">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune référence trouvée.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modale pour modification -->
        <div id="editReferenceModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Modifier la référence</h2>
                <form method="POST">
                    <input type="hidden" name="update_reference" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_reference_number">Numéro de référence</label>
                        <input type="text" name="reference_number" id="edit_reference_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_reference_type">Type de référence</label>
                        <select name="reference_type" id="edit_reference_type" required>
                            <option value="R">R</option>
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

    <script src="../styles/js/admin_references.js?v=<?php echo time(); ?>"></script>
</body>
</html>
