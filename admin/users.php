<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Accès non autorisé.";
    header("Location: ../index.php");
    exit();
}

// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $conn = getConnection();
} catch (PDOException $e) {
    die("Erreur : Impossible de se connecter à la base de données. Veuillez contacter l'administrateur.");
}

// Initialiser les variables pour le formulaire
$username = '';
$edit_mode = false;
$edit_user_id = 0;
$error = $success = '';

// Gérer la création ou la modification d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Le nom d'utilisateur et le mot de passe sont obligatoires.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif (strlen($username) < 3) {
        $error = "Le nom d'utilisateur doit contenir au moins 3 caractères.";
    } else {
        try {
            if (isset($_POST['edit_user_id']) && !empty($_POST['edit_user_id'])) {
                // Mode modification
                $edit_user_id = (int)$_POST['edit_user_id'];
                if ($edit_user_id === (int)$_SESSION['user_id']) {
                    $error = "Vous ne pouvez pas modifier votre propre compte ici.";
                } else {
                    $sql = "UPDATE users SET username = :username";
                    $params = [':username' => $username];
                    if (!empty($password)) {
                        $sql .= ", password = :password";
                        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id = :id AND role = 'user'";
                    $params[':id'] = $edit_user_id;
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);

                    // Journalisation
                    $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, action_details, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt_log->execute([$_SESSION['user_id'], 'Updated user', "Updated user ID $edit_user_id: $username"]);
                    $success = "Utilisateur modifié avec succès.";
                }
            } else {
                // Mode création
                $sql = "SELECT id FROM users WHERE username = :username";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':username' => $username]);
                if ($stmt->rowCount() > 0) {
                    $error = "Ce nom d'utilisateur existe déjà.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password, role) VALUES (:username, :password, 'user')";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => $hashed_password
                    ]);

                    // Journalisation
                    $new_user_id = $conn->lastInsertId();
                    $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, action_details, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt_log->execute([$_SESSION['user_id'], 'Created user', "Created user ID $new_user_id: $username"]);
                    $success = "Utilisateur créé avec succès.";
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'opération. Veuillez réessayer ou contacter l'administrateur.";
        }
    }
}

// Gérer la suppression d'un utilisateur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $delete_id = (int)$_GET['delete'];
        if ($delete_id === (int)$_SESSION['user_id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            $sql = "SELECT username FROM users WHERE id = :id AND role = 'user'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $delete_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $sql = "DELETE FROM users WHERE id = :id AND role = 'user'";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':id' => $delete_id]);

                // Journalisation
                $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, action_details, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_log->execute([$_SESSION['user_id'], 'Deleted user', "Deleted user ID $delete_id: {$user['username']}"]);
                $success = "Utilisateur supprimé avec succès.";
            } else {
                $error = "Utilisateur introuvable ou non supprimable.";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression. Veuillez réessayer ou contacter l'administrateur.";
    }
}

// Récupérer la liste des utilisateurs avec le rôle user
try {
    $sql = "SELECT id, username, role FROM users WHERE role = 'user'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des utilisateurs. Veuillez contacter l'administrateur.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../styles/css/admin_users.css?v=<?php echo time(); ?>">
    <script src="../styles/js/admin_users.js?v=<?php echo time(); ?>" defer></script>
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
            <a href="trips.php"><i class="fas fa-ticket-alt"></i> Gestion des Trajets</a>
            <a href="finances.php"><i class="fas fa-money-bill-wave"></i> Finances</a>
            <a href="users.php" class="active"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="seats.php"><i class="fas fa-chair"></i> Gestion des Places</a>
            <a href="reservations.php"><i class="fas fa-book"></i> Réservations</a>
            <a href="tickets.php"><i class="fas fa-ticket"></i><span>Gestion des Tickets</span></a>
            <a href="references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
            <a href="info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>

            <a href="../chat.php"><i class="fas fa-comments"></i><span>Chat de Groupe</span></a>
            <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Gestion des Utilisateurs</h1>

            <!-- Messages d'erreur ou de succès -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Formulaire de création -->
            <div class="form-container">
                <h2>Créer un Utilisateur</h2>
                <form method="POST" action="users.php">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="submit-btn">Créer</button>
                </form>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="users-list">
                <h2>Liste des Utilisateurs</h2>
                <?php if (!empty($users)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Rôle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td>
                                        <button class="action-btn edit" data-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>"><i class="fas fa-edit"></i> Modifier</button>
                                        <a href="users.php?delete=<?php echo $user['id']; ?>" class="action-btn delete" onclick="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');"><i class="fas fa-trash"></i> Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun utilisateur trouvé.</p>
                <?php endif; ?>
            </div>

            <!-- Modale pour la modification -->
            <div class="modal" id="editModal">
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <h2>Modifier un Utilisateur</h2>
                    <form method="POST" action="users.php" id="edit-user-form">
                        <input type="hidden" id="edit-user-id" name="edit_user_id">
                        <div class="form-group">
                            <label for="edit-username">Nom d'utilisateur</label>
                            <input type="text" id="edit-username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-password">Nouveau mot de passe (laisser vide pour ne pas modifier)</label>
                            <input type="password" id="edit-password" name="password" placeholder="Entrez un nouveau mot de passe">
                        </div>
                        <button type="submit" class="submit-btn">Modifier</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
<?php $conn = null; ?>