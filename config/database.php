<?php
function getConnection() {
    $host = 'localhost'; // Modifiez selon votre configuration
    $dbname = 'reservations_modif'; // Modifiez selon votre configuration
    $username = 'root'; // Modifiez selon votre configuration
    $password = ''; // Modifiez selon votre configuration

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException("Erreur de connexion : " . $e->getMessage());
    }
}