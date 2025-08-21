<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$pdo = getConnection();
$alert = '';

// Ajouter un nouvel enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_finance'])) {
    $passenger_type = $_POST['passenger_type'];
    $trip_type = $_POST['trip_type'];
    $tariff = floatval($_POST['tariff']);
    $port_fee = floatval($_POST['port_fee']);
    $tax = floatval($_POST['tax']);

    if ($tariff >= 0 && $port_fee >= 0 && $tax >= 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO finances (passenger_type, trip_type, tariff, port_fee, tax) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$passenger_type, $trip_type, $tariff, $port_fee, $tax]);
            $alert = '<div class="alert alert-success">Tarif ajouté avec succès.</div>';
        } catch (PDOException $e) {
            $alert = '<div class="alert alert-error">Erreur lors de l\'ajout : ' . $e->getMessage() . '</div>';
        }
    } else {
        $alert = '<div class="alert alert-error">Veuillez entrer des valeurs positives pour les tarifs, frais port, et taxe.</div>';
    }
}

// Mettre à jour un enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_finance'])) {
    $id = (int)$_POST['id'];
    $passenger_type = $_POST['passenger_type'];
    $trip_type = $_POST['trip_type'];
    $tariff = floatval($_POST['tariff']);
    $port_fee = floatval($_POST['port_fee']);
    $tax = floatval($_POST['tax']);

    if ($tariff >= 0 && $port_fee >= 0 && $tax >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE finances SET passenger_type = ?, trip_type = ?, tariff = ?, port_fee = ?, tax = ? WHERE id = ?");
            $stmt->execute([$passenger_type, $trip_type, $tariff, $port_fee, $tax, $id]);
            $alert = '<div class="alert alert-success">Tarif mis à jour avec succès.</div>';
        } catch (PDOException $e) {
            $alert = '<div class="alert alert-error">Erreur lors de la mise à jour : ' . $e->getMessage() . '</div>';
        }
    } else {
        $alert = '<div class="alert alert-error">Veuillez entrer des valeurs positives pour les tarifs, frais port, et taxe.</div>';
    }
}

// Supprimer un enregistrement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_finance'])) {
    $id = (int)$_POST['delete_finance'];
    try {
        $stmt = $pdo->prepare("DELETE FROM finances WHERE id = ?");
        $stmt->execute([$id]);
        $alert = '<div class="alert alert-success">Tarif supprimé avec succès.</div>';
    } catch (PDOException $e) {
        $alert = '<div class="alert alert-error">Erreur lors de la suppression : ' . $e->getMessage() . '</div>';
    }
}

// Récupérer tous les enregistrements
try {
    $stmt = $pdo->query("SELECT id, passenger_type, trip_type, tariff, port_fee, tax FROM finances ORDER BY passenger_type, trip_type");
    $finances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alert = '<div class="alert alert-error">Erreur lors de la récupération des tarifs : ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion financière - Adore Comores Express</title>
    <link rel="stylesheet" href="../styles/css/admin_finances.css?v=<?php echo time(); ?>">
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
            <a href="finances.php" class="active"><i class="fas fa-money-bill-wave"></i> Finances</a>
            <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="seats.php"><i class="fas fa-chair"></i> Gestion des Places</a>
            <a href="reservations.php"><i class="fas fa-book"></i> Réservations</a>
            <a href="tickets.php"><i class="fas fa-ticket"></i><span>Gestions des Tickets</span></a>
            <a href="references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="overlay">
            <h1>Gestion financière</h1>
            <?php echo $alert; ?>
            <div class="form-container">
                <h2>Ajouter un tarif</h2>
                <form method="POST">
                    <input type="hidden" name="add_finance" value="1">
                    <div class="form-group">
                        <label for="passenger_type">Type de passager</label>
                        <select name="passenger_type" id="passenger_type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="Comorien Adulte">Comorien Adulte</option>
                            <option value="Comorien Enfant">Comorien Enfant</option>
                            <option value="Étranger Adulte">Étranger Adulte</option>
                            <option value="Étranger Enfant">Étranger Enfant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="trip_type">Type de trajet</label>
                        <select name="trip_type" id="trip_type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="Standard">Standard (MWA-ANJ, MWA-GCO, GCO-MWA, ANJ-MWA)</option>
                            <option value="Hors Standard">Hors Standard (ANJ-GCO, GCO-ANJ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tariff">Tarifs (KMF)</label>
                        <input type="number" name="tariff" id="tariff" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="port_fee">Frais Port (KMF)</label>
                        <input type="number" name="port_fee" id="port_fee" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="tax">Taxe (KMF)</label>
                        <input type="number" name="tax" id="tax" min="0" step="0.01" required>
                    </div>
                    <button type="submit" class="btn">Ajouter</button>
                </form>
            </div>

            <div class="finances-container">
                <h2>Liste des tarifs</h2>
                <?php if (!empty($finances)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Type de passager</th>
                                <th>Type de trajet</th>
                                <th>Tarifs (KMF)</th>
                                <th>Frais Port (KMF)</th>
                                <th>Taxe (KMF)</th>
                                <th>Total Tarif (KMF)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finances as $finance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($finance['passenger_type']); ?></td>
                                    <td><?php echo htmlspecialchars($finance['trip_type']); ?></td>
                                    <td><?php echo number_format($finance['tariff'], 2); ?></td>
                                    <td><?php echo number_format($finance['port_fee'], 2); ?></td>
                                    <td><?php echo number_format($finance['tax'], 2); ?></td>
                                    <td><?php echo number_format($finance['tariff'] + $finance['port_fee'] + $finance['tax'], 2); ?></td>
                                    <td>
                                        <button class="btn edit-btn" 
                                                data-id="<?php echo $finance['id']; ?>" 
                                                data-passenger-type="<?php echo htmlspecialchars($finance['passenger_type']); ?>" 
                                                data-trip-type="<?php echo htmlspecialchars($finance['trip_type']); ?>" 
                                                data-tariff="<?php echo $finance['tariff']; ?>" 
                                                data-port-fee="<?php echo $finance['port_fee']; ?>" 
                                                data-tax="<?php echo $finance['tax']; ?>">Modifier</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer ce tarif ?');">
                                            <input type="hidden" name="delete_finance" value="<?php echo $finance['id']; ?>">
                                            <button type="submit" class="btn delete-btn">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun tarif enregistré.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modale pour modification -->
        <div id="editFinanceModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2>Modifier le tarif</h2>
                <form method="POST">
                    <input type="hidden" name="update_finance" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_passenger_type">Type de passager</label>
                        <select name="passenger_type" id="edit_passenger_type" required>
                            <option value="Comorien Adulte">Comorien Adulte</option>
                            <option value="Comorien Enfant">Comorien Enfant</option>
                            <option value="Étranger Adulte">Étranger Adulte</option>
                            <option value="Étranger Enfant">Étranger Enfant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_trip_type">Type de trajet</label>
                        <select name="trip_type" id="edit_trip_type" required>
                            <option value="Standard">Standard (MWA-ANJ, MWA-GCO, GCO-MWA, ANJ-MWA)</option>
                            <option value="Hors Standard">Hors Standard (ANJ-GCO, GCO-ANJ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_tariff">Tarifs (KMF)</label>
                        <input type="number" name="tariff" id="edit_tariff" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_port_fee">Frais Port (KMF)</label>
                        <input type="number" name="port_fee" id="edit_port_fee" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_tax">Taxe (KMF)</label>
                        <input type="number" name="tax" id="edit_tax" min="0" step="0.01" required>
                    </div>
                    <button type="submit" class="btn">Mettre à jour</button>
                </form>
            </div>
        </div>
    </main>

    <script src="../styles/js/admin_finances.js?v=<?php echo time(); ?>"></script>
</body>
</html>