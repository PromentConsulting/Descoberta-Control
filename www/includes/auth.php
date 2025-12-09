<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function users_path(): string {
    return base_path('data/users.json');
}

function ensure_admin_seed(): void {
    $path = users_path();
    if (!file_exists($path)) {
        $data = [
            ADMIN_SEED['username'] => ADMIN_SEED,
        ];
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }
}

function load_users(): array {
    ensure_admin_seed();
    $raw = file_get_contents(users_path());
    return $raw ? json_decode($raw, true) : [];
}

function save_users(array $users): void {
    file_put_contents(users_path(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function attempt_login(string $username, string $password): bool {
    $users = load_users();
    if (!isset($users[$username])) {
        return false;
    }

    if (!password_verify($password, $users[$username]['password_hash'])) {
        return false;
    }

    $_SESSION['user'] = [
        'username' => $username,
        'role' => $users[$username]['role'] ?? 'user',
    ];
    return true;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        redirect('/login.php');
    }
}

function require_admin(): void {
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        flash('error', 'Solo el administrador puede acceder a Ajustes.');
        redirect('/dashboard.php');
    }
}

function logout(): void {
    session_destroy();
    redirect('/login.php');
}
