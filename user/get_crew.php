<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $pdo = getConnection();
    $trip_id = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;

    // Récupérer les membres d'équipage disponibles (non assignés à ce trajet)
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.name, cm.role 
        FROM crew_members cm 
        WHERE cm.status = 'available'
        AND cm.id NOT IN (
            SELECT crew_member_id 
            FROM trip_crew 
            WHERE trip_id = ?
        )
    ");
    $stmt->execute([$trip_id]);
    $crew_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['crew_members' => $crew_members]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erreur lors de la récupération des membres d\'équipage']);
}
?>