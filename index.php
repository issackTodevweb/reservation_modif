<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Réservation Maritime - Connexion</title>
    <link rel="stylesheet" href="styles/css/login.css">
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <img src="images/ACX.png" alt="Logo de l'Entreprise" class="logo">
            <h1>Système de Gestion Maritime</h1>
            <p>Plateforme sécurisée pour la gestion des réservations et le suivi des flux financiers de votre compagnie maritime.</p>
        </div>
        <div class="login-section">
            <h2>Connexion au Système</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <form action="auth/login.php" method="POST">
                <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                <input type="password" name="password" placeholder="Mot de passe" required>
                <button type="submit">Se Connecter</button>
            </form>
        </div>
    </div>
</body>
</html>