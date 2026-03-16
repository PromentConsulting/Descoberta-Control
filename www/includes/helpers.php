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

function dc_meta_value(array $product, string $key) {
    foreach ($product['meta_data'] ?? [] as $meta) {
        if (($meta['key'] ?? '') === $key) {
            return $meta['value'];
        }
    }
    return null;
}

function dc_translation_parent_id(array $product): int {
    $parent = dc_meta_value($product, TRANSLATION_PARENT_META_KEY);
    if ($parent === null || $parent === '') {
        $parent = $product[TRANSLATION_PARENT_META_KEY] ?? $product['translation_parent_id'] ?? $product['translation_of'] ?? 0;
    }
    return (int)$parent;
}

function dc_translation_child_id(array $product): int {
    $child = dc_meta_value($product, TRANSLATION_CHILD_META_KEY);
    if ($child === null || $child === '') {
        $child = $product[TRANSLATION_CHILD_META_KEY] ?? $product['translation_child_id'] ?? 0;
    }
    return (int)$child;
}

function dc_product_translations(array $product): array {
    $translations = $product['translations']
        ?? dc_meta_value($product, 'translations')
        ?? dc_meta_value($product, '_translations')
        ?? [];

    if (is_string($translations)) {
        $decoded = json_decode($translations, true);
        if (is_array($decoded)) {
            $translations = $decoded;
        }
    }

    if (!is_array($translations)) {
        return [];
    }

    $normalized = [];
    foreach ($translations as $lang => $id) {
        $normLang = strtolower(trim((string)$lang));
        $normLang = preg_split('/[-_]/', $normLang)[0] ?: $normLang;
        if ($normLang !== '' && (int)$id > 0) {
            $normalized[$normLang] = (int)$id;
        }
    }

    return $normalized;
}

function dc_product_lang(array $product): string {
    $lang = dc_meta_value($product, TRANSLATION_LANG_META_KEY);
    if ($lang === null || $lang === '') {
        $lang = dc_meta_value($product, 'lang')
            ?? dc_meta_value($product, 'language')
            ?? dc_meta_value($product, '_pll_language')
            ?? dc_meta_value($product, 'pll_language')
            ?? $product[TRANSLATION_LANG_META_KEY]
            ?? $product['lang']
            ?? $product['language']
            ?? $product['locale']
            ?? '';
    }

    if ($lang === '' || $lang === null) {
        $translations = dc_product_translations($product);
        $productId = (int)($product['id'] ?? 0);
        foreach ($translations as $translationLang => $translationId) {
            if ((int)$translationId === $productId) {
                $lang = (string)$translationLang;
                break;
            }
        }
    }

    $normalized = strtolower(trim((string)$lang));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_split('/[-_]/', $normalized)[0] ?: $normalized;

    $aliases = [
        'cat' => 'ca',
        'catala' => 'ca',
        'català' => 'ca',
        'spa' => 'es',
        'esp' => 'es',
        'castella' => 'es',
        'castellano' => 'es',
    ];

    return $aliases[$normalized] ?? $normalized;
}

function dc_is_translation_product(array $product): bool {
    return dc_translation_parent_id($product) > 0;
}

function dc_product_by_id(array $products, int $id): ?array {
    foreach ($products as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            return $product;
        }
    }
    return null;
}

function dc_find_translation_product(array $products, int $baseProductId, string $targetLang = 'es'): ?array {
    $targetLang = strtolower(trim($targetLang));
    $base = dc_product_by_id($products, $baseProductId);

    if ($base) {
        $translations = dc_product_translations($base);
        $candidateId = (int)($translations[$targetLang] ?? 0);
        if ($candidateId > 0) {
            $candidate = dc_product_by_id($products, $candidateId);
            if ($candidate) {
                return $candidate;
            }
        }

        $childId = dc_translation_child_id($base);
        if ($childId > 0) {
            $child = dc_product_by_id($products, $childId);
            if ($child && ($targetLang === '' || dc_product_lang($child) === $targetLang)) {
                return $child;
            }
        }
    }

    foreach ($products as $product) {
        if (dc_translation_parent_id($product) === $baseProductId) {
            if ($targetLang === '' || dc_product_lang($product) === $targetLang) {
                return $product;
            }
        }
    }

    return null;
}

function dc_is_primary_language_product(array $product, array $allProducts, string $primaryLang = 'ca'): bool {
    $lang = dc_product_lang($product);
    if ($lang !== '' && $lang !== $primaryLang) {
        return false;
    }

    if (dc_is_translation_product($product)) {
        return false;
    }

    $productId = (int)($product['id'] ?? 0);
    if ($productId <= 0) {
        return false;
    }

    foreach ($allProducts as $other) {
        if ((int)($other['id'] ?? 0) === $productId) {
            continue;
        }

        if (dc_translation_parent_id($other) === $productId) {
            return true;
        }
    }

    $translations = dc_product_translations($product);
    foreach ($translations as $translationLang => $translationId) {
        if ($translationLang === $primaryLang && (int)$translationId !== $productId) {
            return false;
        }
    }

    return true;
}
