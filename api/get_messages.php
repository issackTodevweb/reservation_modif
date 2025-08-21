<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Non autorisé']));
}

// Inclure la configuration de la base de données
try {
    require_once '../config/database.php';
    $pdo = getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erreur de connexion à la base de données']));
}

$last_id = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;

// Récupérer les nouveaux messages
$sql = "SELECT cm.id, cm.user_id, cm.message, u.username, 
               DATE_FORMAT(cm.created_at, '%d/%m/%Y %H:%i') AS created_at
        FROM chat_messages cm 
        JOIN users u ON cm.user_id = u.id 
        WHERE cm.id > :last_id 
        ORDER BY cm.created_at ASC";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['last_id' => $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compter les notifications non lues
    $sql_notifications = "SELECT COUNT(*) as unread_count 
                         FROM chat_notifications 
                         WHERE user_id = :user_id AND is_read = 0";
    $stmt_notifications = $pdo->prepare($sql_notifications);
    $stmt_notifications->execute(['user_id' => $_SESSION['user_id']]);
    $unread_count = $stmt_notifications->fetch(PDO::FETCH_ASSOC)['unread_count'];

    header('Content-Type: application/json');
    echo json_encode(['messages' => $messages, 'unread_count' => $unread_count]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des messages']);
}

$pdo = null;
?>