<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $ticket_type = isset($_GET['ticket_type']) ? trim($_GET['ticket_type']) : '';

    error_log("fetch_tickets: Type de ticket reçu = " . $ticket_type);

    if (!$ticket_type) {
        error_log("fetch_tickets: Erreur - Type de ticket non spécifié");
        echo json_encode(['error' => 'Type de ticket non spécifié']);
        exit;
    }

    if (!in_array($ticket_type, ['GM', 'MG', 'GA', 'AM', 'MA'])) {
        error_log("fetch_tickets: Erreur - Type de ticket invalide: " . $ticket_type);
        echo json_encode(['error' => 'Type de ticket invalide']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, ticket_number 
        FROM tickets 
        WHERE ticket_type = ? 
        AND status = 'available' 
        AND id NOT IN (SELECT ticket_id FROM reservations WHERE ticket_id IS NOT NULL)
        ORDER BY ticket_number ASC
        LIMIT 1
    ");
    $stmt->execute([$ticket_type]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("fetch_tickets: Ticket trouvé = " . ($ticket ? json_encode($ticket) : 'Aucun'));

    if ($ticket) {
        echo json_encode($ticket);
    } else {
        echo json_encode(['error' => 'Aucun ticket disponible pour ce type']);
    }
} catch (PDOException $e) {
    error_log("fetch_tickets: Erreur PDO = " . $e->getMessage());
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
$pdo = null;
?>