<?php
function base_path(string $path = ''): string {
    $root = __DIR__ . '/..';
    return $path ? $root . '/' . ltrim($path, '/') : $root;
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function flash(?string $type = null, ?string $message = null): array {
    if ($type && $message) {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        return [];
    }

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function view_active(string $path): string {
    return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
}

function status_label(?string $status): string {
    return strtolower((string)$status) === 'publish' ? 'Visible' : 'No Visible';
}

function formatted_product_date(?string $dateString): array {
    try {
        $dt = new DateTime($dateString ?: 'now');
        $dt->modify('+1 hour');
        return [
            'display' => $dt->format('d-m-Y H:i'),
            'timestamp' => $dt->getTimestamp(),
        ];
    } catch (Exception $e) {
        return ['display' => '', 'timestamp' => 0];
    }
}