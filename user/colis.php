<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder à cette page.";
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

// Ajouter un colis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    $package_description = trim($_POST['package_description']);
    $package_reference = trim($_POST['package_reference']);
    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $package_fee = (float)$_POST['package_fee'];
    $payment_status = $_POST['payment_status'];
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("INSERT INTO colis (sender_name, sender_phone, package_description, package_reference, receiver_name, receiver_phone, package_fee, payment_status, user_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sender_name, $sender_phone, $package_description, $package_reference, $receiver_name, $receiver_phone, $package_fee, $payment_status, $user_id]);
        $_SESSION['success'] = "Colis ajouté avec succès.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de l'ajout du colis : " . $e->getMessage();
    }
    header("Location: colis.php");
    exit();
}

// Mettre à jour un colis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_package'])) {
    $id = (int)$_POST['id'];
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    $package_description = trim($_POST['package_description']);
    $package_reference = trim($_POST['package_reference']);
    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $package_fee = (float)$_POST['package_fee'];
    $payment_status = $_POST['payment_status'];
    // Récupérer l'user_id existant
    try {
        $stmt = $conn->prepare("SELECT user_id FROM colis WHERE id = ?");
        $stmt->execute([$id]);
        $colis = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $colis['user_id'];
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la récupération de l'ID caissier : " . $e->getMessage();
        header("Location: colis.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE colis SET sender_name = ?, sender_phone = ?, package_description = ?, package_reference = ?, 
                                receiver_name = ?, receiver_phone = ?, package_fee = ?, payment_status = ?, user_id = ? WHERE id = ?");
        $stmt->execute([$sender_name, $sender_phone, $package_description, $package_reference, $receiver_name, $receiver_phone, 
                        $package_fee, $payment_status, $user_id, $id]);
        $_SESSION['success'] = "Colis mis à jour avec succès.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
    header("Location: colis.php");
    exit();
}

// Supprimer un colis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package'])) {
    $id = (int)$_POST['delete_package'];
    try {
        $stmt = $conn->prepare("DELETE FROM colis WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Colis supprimé avec succès.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    header("Location: colis.php");
    exit();
}

// Gestion de la recherche
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
if (!empty($search_query)) {
    $where_clause = " WHERE c.sender_name LIKE ? OR c.package_reference LIKE ? OR c.receiver_name LIKE ?";
    $search_param = '%' . $search_query . '%';
    $params = [$search_param, $search_param, $search_param];
}

// Récupérer la liste des colis
try {
    $sql = "SELECT c.id, c.sender_name, c.sender_phone, c.package_description, c.package_reference, 
                   c.receiver_name, c.receiver_phone, c.package_fee, c.payment_status, c.user_id, u.username AS cashier_name
            FROM colis c
            LEFT JOIN users u ON c.user_id = u.id
            $where_clause
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $colis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des colis : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Colis - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/user_colis.css?v=<?php echo time(); ?>">
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
            <a href="reservations.php"><i class="fas fa-book"></i> Réservations</a>
            <a href="list_reservations.php"><i class="fas fa-list"></i> Liste des Réservations</a>
            <a href="info_passagers.php"><i class="fas fa-users"></i> Infos Passagers</a>
            <a href="colis.php" class="active"><i class="fas fa-box"></i> Colis</a>
          
            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="trips.php"><i class="fas fa-ticket-alt"></i> Gestion des Trajets</a>
                <a href="finances.php"><i class="fas fa-money-bill-wave"></i> Finances</a>
                <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
                <a href="seats.php"><i class="fas fa-chair"></i> Gestion des Places</a>
                <a href="tickets.php"><i class="fas fa-ticket"></i> Tickets</a>
                <a href="references.php"><i class="fas fa-barcode"></i> Références</a>
            <?php endif; ?>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Se Déconnecter</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des Colis</h1>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Formulaire d'ajout -->
            <div class="add-colis-container">
                <h2>Ajouter un Colis</h2>
                <form method="POST" class="add-colis-form">
                    <input type="hidden" name="add_package" value="1">
                    <div class="form-group">
                        <label for="sender_name">Nom de l'Envoyeur</label>
                        <input type="text" name="sender_name" id="sender_name" required>
                    </div>
                    <div class="form-group">
                        <label for="sender_phone">Téléphone de l'Envoyeur</label>
                        <input type="text" name="sender_phone" id="sender_phone" required>
                    </div>
                    <div class="form-group">
                        <label for="package_description">Description du Colis</label>
                        <textarea name="package_description" id="package_description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="package_reference">Référence du Colis</label>
                        <input type="text" name="package_reference" id="package_reference" required>
                    </div>
                    <div class="form-group">
                        <label for="receiver_name">Nom du Récepteur</label>
                        <input type="text" name="receiver_name" id="receiver_name" required>
                    </div>
                    <div class="form-group">
                        <label for="receiver_phone">Téléphone du Récepteur</label>
                        <input type="text" name="receiver_phone" id="receiver_phone" required>
                    </div>
                    <div class="form-group">
                        <label for="package_fee">Frais du Colis (KMF)</label>
                        <input type="number" name="package_fee" id="package_fee" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Statut de Paiement</label>
                        <select name="payment_status" id="payment_status" required>
                            <option value="Mvola">Mvola</option>
                            <option value="Espèce">Espèce</option>
                            <option value="Non payé">Non payé</option>
                        </select>
                    </div>
                    <button type="submit" class="btn add-btn">Ajouter</button>
                </form>
            </div>

            <!-- Liste des colis -->
            <div class="colis-list-container">
                <h2>Liste des Colis</h2>
                <!-- Barre de recherche -->
                <div class="search-container">
                    <form method="GET" action="colis.php">
                        <input type="text" name="search" placeholder="Rechercher par envoyeur, référence, récepteur..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn"><i class="fas fa-search"></i> Rechercher</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="colis.php" class="btn reset-btn">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if (!empty($colis)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID d'utilisateur</th>
                                <th>Nom de l'Envoyeur</th>
                                <th>Téléphone Envoyeur</th>
                                <th>Description</th>
                                <th>Référence</th>
                                <th>Nom du Récepteur</th>
                                <th>Téléphone Récepteur</th>
                                <th>Frais (KMF)</th>
                                <th>Statut de Paiement</th>
                                <th>Caissier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($colis as $package): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($package['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($package['sender_name']); ?></td>
                                    <td><?php echo htmlspecialchars($package['sender_phone']); ?></td>
                                    <td><?php echo htmlspecialchars($package['package_description']); ?></td>
                                    <td><?php echo htmlspecialchars($package['package_reference']); ?></td>
                                    <td><?php echo htmlspecialchars($package['receiver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($package['receiver_phone']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($package['package_fee'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($package['payment_status']); ?></td>
                                    <td><?php echo htmlspecialchars($package['cashier_name']); ?></td>
                                    <td>
                                        <button class="btn edit-btn" 
                                                data-id="<?php echo $package['id']; ?>" 
                                                data-sender-name="<?php echo htmlspecialchars($package['sender_name']); ?>" 
                                                data-sender-phone="<?php echo htmlspecialchars($package['sender_phone']); ?>" 
                                                data-package-description="<?php echo htmlspecialchars($package['package_description']); ?>" 
                                                data-package-reference="<?php echo htmlspecialchars($package['package_reference']); ?>" 
                                                data-receiver-name="<?php echo htmlspecialchars($package['receiver_name']); ?>" 
                                                data-receiver-phone="<?php echo htmlspecialchars($package['receiver_phone']); ?>" 
                                                data-package-fee="<?php echo htmlspecialchars($package['package_fee']); ?>" 
                                                data-payment-status="<?php echo htmlspecialchars($package['payment_status']); ?>" 
                                                data-user-id="<?php echo $package['user_id']; ?>" 
                                                data-cashier-name="<?php echo htmlspecialchars($package['cashier_name']); ?>">Modifier</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment supprimer ce colis ?');">
                                            <input type="hidden" name="delete_package" value="<?php echo $package['id']; ?>">
                                            <button type="submit" class="btn delete-btn">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun colis trouvé.</p>
                <?php endif; ?>
            </div>

            <!-- Modale pour modification -->
            <div id="editPackageModal" class="modal">
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <h2>Modifier le Colis</h2>
                    <form id="editPackageForm" method="POST">
                        <input type="hidden" name="update_package" value="1">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_sender_name">Nom de l'Envoyeur</label>
                            <input type="text" name="sender_name" id="edit_sender_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_sender_phone">Téléphone de l'Envoyeur</label>
                            <input type="text" name="sender_phone" id="edit_sender_phone" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_package_description">Description du Colis</label>
                            <textarea name="package_description" id="edit_package_description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_package_reference">Référence du Colis</label>
                            <input type="text" name="package_reference" id="edit_package_reference" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_receiver_name">Nom du Récepteur</label>
                            <input type="text" name="receiver_name" id="edit_receiver_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_receiver_phone">Téléphone du Récepteur</label>
                            <input type="text" name="receiver_phone" id="edit_receiver_phone" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_package_fee">Frais du Colis (KMF)</label>
                            <input type="number" name="package_fee" id="edit_package_fee" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_payment_status">Statut de Paiement</label>
                            <select name="payment_status" id="edit_payment_status" required>
                                <option value="Mvola">Mvola</option>
                                <option value="Espèce">Espèce</option>
                                <option value="Non payé">Non payé</option>
                            </select>
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
        </div>
    </main>

    <script src="../styles/js/user_colis.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php $conn = null; ?>