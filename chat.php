<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder au chat.";
    header("Location: index.php");
    exit();
}

// Inclure la configuration de la base de données
try {
    require_once 'config/database.php';
    $pdo = getConnection();
} catch (PDOException $e) {
    die("Erreur : Impossible de se connecter à la base de données : " . $e->getMessage());
}

// Gérer l'envoi d'un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        try {
            // Insérer le message
            $sql = "INSERT INTO chat_messages (user_id, message, created_at) VALUES (:user_id, :message, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'message' => $message
            ]);
            $message_id = $pdo->lastInsertId();

            // Enregistrer l'action dans le journal d'activité
            $sql_log = "INSERT INTO activity_logs (user_id, action_type, action_details, created_at) 
                        VALUES (:user_id, :action_type, :action_details, NOW())";
            $stmt_log = $pdo->prepare($sql_log);
            $stmt_log->execute([
                'user_id' => $_SESSION['user_id'],
                'action_type' => 'Sent chat message',
                'action_details' => "Sent message: " . substr($message, 0, 50) . "..."
            ]);

            // Ajouter des notifications pour tous les autres utilisateurs
            $sql_users = "SELECT id FROM users WHERE id != :current_user_id";
            $stmt_users = $pdo->prepare($sql_users);
            $stmt_users->execute(['current_user_id' => $_SESSION['user_id']]);
            $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

            $sql_notification = "INSERT INTO chat_notifications (user_id, message_id, is_read, created_at) 
                                VALUES (:user_id, :message_id, 0, NOW())";
            $stmt_notification = $pdo->prepare($sql_notification);
            foreach ($users as $user) {
                $stmt_notification->execute([
                    'user_id' => $user['id'],
                    'message_id' => $message_id
                ]);
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de l'envoi du message : " . $e->getMessage();
        }
    }
    header("Location: chat.php");
    exit();
}

// Marquer les messages comme lus pour l'utilisateur actuel
try {
    $sql_mark_read = "UPDATE chat_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
    $stmt_mark_read = $pdo->prepare($sql_mark_read);
    $stmt_mark_read->execute(['user_id' => $_SESSION['user_id']]);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la mise à jour des notifications : " . $e->getMessage();
}

// Compter les notifications non lues
try {
    $sql_notifications = "SELECT COUNT(*) as unread_count 
                         FROM chat_notifications 
                         WHERE user_id = :user_id AND is_read = 0";
    $stmt_notifications = $pdo->prepare($sql_notifications);
    $stmt_notifications->execute(['user_id' => $_SESSION['user_id']]);
    $unread_count = $stmt_notifications->fetch(PDO::FETCH_ASSOC)['unread_count'];
} catch (PDOException $e) {
    $unread_count = 0;
    $_SESSION['error'] = "Erreur lors du comptage des notifications : " . $e->getMessage();
}

// Récupérer les messages (limite initiale : 50 messages)
$sql = "SELECT cm.id, cm.user_id, cm.message, cm.created_at, u.username 
        FROM chat_messages cm 
        JOIN users u ON cm.user_id = u.id 
        ORDER BY cm.created_at DESC LIMIT 50";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)); // Inverser pour afficher du plus ancien au plus récent
} catch (PDOException $e) {
    die("Erreur lors de la récupération des messages : " . $e->getMessage());
}

$pdo = null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat de Groupe - Adore Comores Express</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="styles/css/chat.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        <nav class="nav-menu">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Tableau de Bord</span></a>
                <a href="admin/trips.php"><i class="fas fa-ticket-alt"></i><span>Gestion des Trajets</span></a>
                <a href="admin/finances.php"><i class="fas fa-money-bill-wave"></i><span>Finances</span></a>
                <a href="admin/users.php"><i class="fas fa-users"></i><span>Utilisateurs</span></a>
                <a href="admin/seats.php"><i class="fas fa-chair"></i><span>Gestion des Places</span></a>
                <a href="admin/reservations.php"><i class="fas fa-book"></i><span>Réservations</span></a>
                <a href="admin/tickets.php"><i class="fas fa-ticket"></i><span>Gestion des Tickets</span></a>
                <a href="admin/references.php"><i class="fas fa-barcode"></i><span>Références</span></a>
                <a href="admin/info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>

            <?php endif; ?>
            <?php if ($_SESSION['role'] === 'user'): ?>
                <a href="user/dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Tableau de Bord</span></a>
                <a href="user/reservations.php"><i class="fas fa-book"></i><span>Réservations</span></a>
                <a href="user/list_reservations.php"><i class="fas fa-list"></i><span>Liste des Réservations</span></a>
                <a href="user/info_passagers.php"><i class="fas fa-user-friends"></i><span>Infos Passagers</span></a>
                <a href="user/colis.php"><i class="fas fa-box"></i><span>Colis</span></a>
            <?php endif; ?>
      
            <a href="chat.php" class="active">
                <i class="fas fa-comments"></i>
                <span>Chat de Groupe</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i><span>Se Déconnecter</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="overlay">
            <h1>Chat de Groupe</h1>
            <p>Discutez en temps réel avec les autres utilisateurs d'Adore Comores Express.</p>

            <!-- Messages d'erreur -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Interface de chat -->
            <div class="chat-container">
                <div class="chat-messages" id="chat-messages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['user_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>" data-id="<?php echo $message['id']; ?>">
                            <div class="message-header">
                                <i class="fas fa-user-circle"></i>
                                <span class="username"><?php echo $message['user_id'] == $_SESSION['user_id'] ? 'Vous' : htmlspecialchars($message['username']); ?></span>
                            </div>
                            <div class="message-timestamp"><?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></div>
                            <div class="message-content"><?php echo htmlspecialchars($message['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form class="chat-form" id="chat-form" method="POST">
                    <input type="text" id="message-input" name="message" placeholder="Tapez votre message..." autocomplete="off">
                    <button type="submit"><i class="fas fa-paper-plane"></i> Envoyer</button>
                </form>
            </div>
        </div>
    </main>
    <script src="styles/js/chat.js?v=<?php echo time(); ?>"></script>
</body>
</html>