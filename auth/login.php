<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validation des entrées
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs.";
        header("Location: ../index.php");
        exit();
    }

    try {
        // Connexion à la base de données
        $pdo = getConnection();

        // Préparation de la requête
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification de l'utilisateur et du mot de passe
        if ($user && password_verify($password, $user['password'])) {
            // Création de la session utilisateur
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirection selon le rôle
            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Nom d'utilisateur ou mot de passe incorrect.";
            header("Location: ../index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de connexion : " . $e->getMessage();
        header("Location: ../index.php");
        exit();
    }
} else {
    header("Location: ../index.php");
    exit();
}