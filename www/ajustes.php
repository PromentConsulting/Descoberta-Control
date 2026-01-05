<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();
$messages = flash();
$users = load_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_user'])) {
        $username = trim($_POST['new_username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $role = $_POST['new_role'] ?? 'user';
        if ($username && $password) {
            $users[$username] = [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ];
            save_users($users);
            flash('success', 'Usuario creado');
        } else {
            flash('error', 'Debe indicar usuario y contraseña');
        }
    }

    if (isset($_POST['change_admin'])) {
        $password = $_POST['admin_password'] ?? '';
        if ($password) {
            $users['admin']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            save_users($users);
            flash('success', 'Contraseña de admin actualizada');
        } else {
            flash('error', 'La contraseña no puede estar vacía');
        }
    }
    redirect('/ajustes.php');
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Ajustes</h1>
    <p class="subtitle">Gestión de usuarios</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="cards-grid single">
        <div class="card">
            <h2>Crear usuario</h2>
            <form method="POST" class="form-card compact">
                <input type="hidden" name="new_user" value="1">
                <label>Usuario</label>
                <input type="text" name="new_username" required>
                <label>Contraseña</label>
                <input type="password" name="new_password" required>
                <label>Rol</label>
                <select name="new_role">
                    <option value="user">Usuario</option>
                    <option value="admin">Admin</option>
                </select>
                <button class="btn" type="submit">Crear</button>
            </form>
        </div>

        <div class="card">
            <h2>Cambiar contraseña admin</h2>
            <form method="POST" class="form-card compact">
                <input type="hidden" name="change_admin" value="1">
                <label>Nueva contraseña</label>
                <input type="password" name="admin_password" required>
                <button class="btn" type="submit">Actualizar</button>
            </form>
        </div>
    </div>

    <h3>Usuarios existentes</h3>
    <div class="table-wrapper">
        <table class="styled-table">
            <thead><tr><th>Usuario</th><th>Rol</th></tr></thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr><td><?php echo htmlspecialchars($user['username']); ?></td><td><?php echo htmlspecialchars($user['role']); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

</div>
</body>
</html>