<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function site_config(string $key): array {
    global $SITE_APIS;
    return $SITE_APIS[$key] ?? [];
}

function api_request(string $siteKey, string $method, string $endpoint, array $data = [], array $headers = [], bool $json = true): array {
    $config = site_config($siteKey);
    if (!$config || empty($config['base_url'])) {
        return ['success' => false, 'error' => 'API no configurada'];
    }

    $url = rtrim($config['base_url'], '/') . '/' . ltrim($endpoint, '/');
    $ch = curl_init();

    $defaultHeaders = [];
    if ($json) {
        $defaultHeaders[] = 'Content-Type: application/json';
    }

    // Autenticación para pasar protecciones básicas del sitio (por ejemplo .htpasswd)
    if (!empty($config['basic_user']) || !empty($config['consumer_key'])) {
        $authUser = $config['basic_user'] ?: $config['consumer_key'];
        $authPass = $config['basic_password'] ?: ($config['consumer_secret'] ?? '');
        curl_setopt($ch, CURLOPT_USERPWD, $authUser . ':' . $authPass);
    }

    $urlParams = [];

    if ($method === 'GET' && !empty($data)) {
        $urlParams = $data;
    }

    // Algunos hosts WooCommerce en HTTP requieren consumer_key/consumer_secret en la URL.
    if (!empty($config['consumer_key']) && !empty($config['consumer_secret']) && strpos($endpoint, 'wc/') !== false) {
        $urlParams = array_merge($urlParams, [
            'consumer_key' => $config['consumer_key'],
            'consumer_secret' => $config['consumer_secret'],
        ]);
    }

    if (!empty($urlParams)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($urlParams);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if ($method !== 'GET' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($data) : $data);
    }

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => $err];
    }

    $decoded = json_decode($response, true);
    $success = $status >= 200 && $status < 300;
    $error = null;

    if (!$success) {
        $bodyMessage = is_string($response) ? $response : json_encode($decoded);
        $error = "HTTP $status" . ($bodyMessage ? ": $bodyMessage" : '');
    }

    return [
        'success' => $success,
        'status' => $status,
        'data' => $decoded ?? $response,
        'error' => $error,
    ];
}

function woo_products(string $siteKey, array $params = []): array {
    $params = array_merge(['per_page' => 100, 'status' => 'any'], $params);
    return api_request($siteKey, 'GET', 'wp-json/wc/v3/products', $params);
}

function woo_create_product(string $siteKey, array $payload): array {
    return api_request($siteKey, 'POST', 'wp-json/wc/v3/products', $payload);
}

function woo_update_product(string $siteKey, int $productId, array $payload): array {
    return api_request($siteKey, 'PUT', 'wp-json/wc/v3/products/' . $productId, $payload);
}

function wp_create_post(string $siteKey, array $payload): array {
    return api_request($siteKey, 'POST', 'wp-json/wp/v2/posts', $payload);
}

function wp_upload_media(string $siteKey, array $file): array {
    $config = site_config($siteKey);
    if (!$config || empty($config['base_url'])) {
        return ['success' => false, 'error' => 'API no configurada'];
    }
    $url = rtrim($config['base_url'], '/') . '/wp-json/wp/v2/media';

    $ch = curl_init();
    $headers = [
        'Content-Disposition: attachment; filename="' . basename($file['name']) . '"',
    ];

    if (!empty($config['basic_user']) || !empty($config['consumer_key'])) {
        $authUser = $config['basic_user'] ?: $config['consumer_key'];
        $authPass = $config['basic_password'] ?: ($config['consumer_secret'] ?? '');
        curl_setopt($ch, CURLOPT_USERPWD, $authUser . ':' . $authPass);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => ['file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])],
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => $err];
    }

    $decoded = json_decode($response, true);
    $success = $status >= 200 && $status < 300;
    $error = null;

    if (!$success) {
        if (is_array($decoded) && isset($decoded['message'])) {
            $error = $decoded['message'];
        } else {
            $error = "HTTP $status" . ($response ? ": $response" : '');
        }
    }

    return [
        'success' => $success,
        'status' => $status,
        'data' => $decoded ?? $response,
        'error' => $error,
    ];
}

function category_id(string $siteKey, string $slug): ?int {
    $config = site_config($siteKey);
    $configured = $config['categories'][$slug] ?? null;
    if (!empty($configured)) {
        return (int)$configured;
    }

    $response = api_request($siteKey, 'GET', 'wp-json/wc/v3/products/categories', [
        'slug' => $slug,
        'per_page' => 1,
    ]);
    if ($response['success'] && !empty($response['data'][0]['id'])) {
        return (int)$response['data'][0]['id'];
    }

    return null;
}

function filter_products_by_category(array $products, string $slug): array {
    return array_values(array_filter($products, function ($product) use ($slug) {
        if (!isset($product['categories'])) {
            return false;
        }
        foreach ($product['categories'] as $cat) {
            if (($cat['slug'] ?? '') === $slug) {
                return true;
            }
        }
        return false;
    }));
}

function sync_case_to_site(string $siteKey, array $sourceProduct, array $acfKeys): array {
    $payload = [
        'name' => $sourceProduct['name'] ?? '',
        'status' => 'publish',
        'description' => $sourceProduct['description'] ?? '',
        'categories' => [],
        'meta_data' => [
            ['key' => $acfKeys['url'], 'value' => $sourceProduct['permalink'] ?? ''],
        ],
    ];

    $caseCategoryId = category_id($siteKey, 'cases-de-colonies');
    if ($caseCategoryId) {
        $payload['categories'][] = ['id' => $caseCategoryId];
    }

    if (!empty($sourceProduct['images'][0]['src'])) {
        $payload['images'] = [['src' => $sourceProduct['images'][0]['src']]];
    }

    return woo_create_product($siteKey, $payload);
}
