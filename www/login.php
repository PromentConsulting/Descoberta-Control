<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_login($username, $password)) {
        redirect('/dashboard.php');
    } else {
        $error = 'Credencials incorrectes';
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Inici de sessió | Panell Descoberta</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-header">
            <img src="/assets/img/logo.png" alt="Descoberta" class="auth-logo">
            <h1>Panell Descoberta</h1>
            <p>Accedeix per gestionar totes les teves webs</p>
        </div>
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <label for="username">Usuari</label>
            <input id="username" name="username" type="text" required autofocus>

            <label for="password">Contrasenya</label>
            <input id="password" name="password" type="password" required>

            <button type="submit" class="btn full">Entrar</button>
        </form>
    </div>
</body>
</html>