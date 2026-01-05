<?php
require_once __DIR__ . '/includes/api.php';

$sites = array_keys($SITE_APIS);

foreach ($sites as $site) {
    $result = woo_products($site, ['per_page' => 1]);
    $status = $result['status'] ?? 'n/a';
    $success = $result['success'] ? 'OK' : 'FAIL';
    $error = $result['error'] ?? '';
    $body = $result['data'];

    if (is_array($body)) {
        $body = json_encode($body);
    }

    echo sprintf(
        "[%s] %s (status: %s)%s%s\n",
        $site,
        $success,
        $status,
        $error ? " | error: $error" : '',
        !$result['success'] && $body ? " | body: $body" : ''
    );
}