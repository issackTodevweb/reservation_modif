<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$pdo = getConnection();

$ticket_type = isset($_POST['ticket_type']) ? $_POST['ticket_type'] : '';

if (!in_array($ticket_type, ['GM', 'MG', 'GA', 'AM', 'MA'])) {
    echo json_encode(['ticket_id' => null, 'ticket_number' => null]);
    exit;
}

$ticket_stmt = $pdo->prepare("SELECT id, ticket_number FROM tickets WHERE ticket_type = ? AND status = 'available' ORDER BY ticket_number ASC LIMIT 1");
$ticket_stmt->execute([$ticket_type]);
$ticket = $ticket_stmt->fetch(PDO::FETCH_ASSOC);

if ($ticket) {
    echo json_encode(['ticket_id' => $ticket['id'], 'ticket_number' => $ticket['ticket_number']]);
} else {
    echo json_encode(['ticket_id' => null, 'ticket_number' => null]);
}
?>