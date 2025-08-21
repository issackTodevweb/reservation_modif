<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    die("Accès non autorisé.");
}

// Inclure TCPDF depuis le dossier vendor/tcpdf
require_once '../vendor/tcpdf/tcpdf.php';
require_once '../config/database.php';

try {
    $pdo = getConnection();
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
    $where_clause = '';
    $params = [$_SESSION['user_id']];
    if (!empty($search_query)) {
        $where_clause .= " AND (r.passenger_name LIKE ? OR r.phone_number LIKE ? OR ref.reference_number LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    if (!empty($date_filter)) {
        $where_clause .= " AND r.departure_date = ?";
        $params[] = $date_filter;
    }
    $sql = "SELECT r.passenger_name, r.phone_number, r.nationality, r.with_baby, r.departure_date, 
                   t.departure_port, t.arrival_port, t.departure_time, ref.reference_number
            FROM reservations r
            JOIN trips t ON r.trip_id = t.id
            JOIN reference ref ON r.reference_id = ref.id
            WHERE r.user_id = ? $where_clause
            ORDER BY r.departure_date DESC, t.departure_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $passagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}

// Créer un nouveau document PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Mode paysage
$pdf->SetCreator('Adore Comores Express');
$pdf->SetAuthor('Adore Comores Express');
$pdf->SetTitle('Liste des Passagers');
$pdf->SetSubject('Liste des Passagers');
$pdf->SetKeywords('passagers, reservations, adore comores');
$pdf->SetHeaderData('', 0, 'Liste des Passagers - Adore Comores Express', 'Généré le ' . date('d/m/Y H:i'));
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// Générer le tableau HTML pour TCPDF
$html = '
<style>
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #1e40af; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; }
    td { padding: 8px; border: 1px solid #000; }
    tr:nth-child(even) { background-color: #f0f0f0; }
</style>
<table>
    <thead>
        <tr>
            <th>Nom du Passager</th>
            <th>Téléphone</th>
            <th>Nationalité</th>
            <th>Avec Bébé</th>
            <th>Trajet</th>
            <th>Référence</th>
        </tr>
    </thead>
    <tbody>';
if (empty($passagers)) {
    $html .= '<tr><td colspan="6" style="text-align: center;">Aucun passager trouvé.</td></tr>';
} else {
    foreach ($passagers as $passager) {
        $html .= '
        <tr>
            <td>' . htmlspecialchars($passager['passenger_name']) . '</td>
            <td>' . htmlspecialchars($passager['phone_number']) . '</td>
            <td>' . htmlspecialchars($passager['nationality']) . '</td>
            <td>' . htmlspecialchars($passager['with_baby']) . '</td>
            <td>' . htmlspecialchars($passager['departure_port'] . ' → ' . $passager['arrival_port'] . ' (' . $passager['departure_date'] . ' ' . $passager['departure_time'] . ')') . '</td>
            <td>' . htmlspecialchars($passager['reference_number']) . '</td>
        </tr>';
    }
}
$html .= '
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('liste_passagers.pdf', 'D');
$pdo = null;
?>