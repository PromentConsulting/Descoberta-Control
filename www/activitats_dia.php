<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$enableSpanishPublishing = true;

$products = [];
$allProducts = [];
$apiError = null;
$response = woo_all_products('descoberta');
if ($response['success']) {
    $allProducts = $response['data'] ?? [];
    $products = filter_products_by_category($allProducts, 'activitat-de-dia');
} else {
    $apiError = $response['error'] ?? 'No s\'ha pogut connectar amb la API de Descoberta';
}

function product_by_id(array $products, int $id): ?array {
    foreach ($products as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            return $product;
        }
    }
    return null;
}

function build_payload_with_status(array $product, string $status): array {
    $payload = [
        'status' => $status,
        'name' => $product['name'] ?? '',
        'description' => $product['description'] ?? '',
    ];

    if (!empty($product['short_description'])) {
        $payload['short_description'] = $product['short_description'];
    }

    if (!empty($product['categories'])) {
        $categories = array_values(array_filter(array_map(function ($cat) {
            if (!empty($cat['id'])) {
                return ['id' => $cat['id']];
            }
            return null;
        }, $product['categories'])));
        if ($categories) {
            $payload['categories'] = $categories;
        }
    }

    if (!empty($product['images'])) {
        $payload['images'] = array_values(array_filter(array_map(function ($img) {
            $data = [];
            if (!empty($img['id'])) {
                $data['id'] = $img['id'];
            }
            if (!empty($img['src'])) {
                $data['src'] = $img['src'];
            }
            return $data ? $data : null;
        }, $product['images'])));
    }

    if (!empty($product['meta_data'])) {
        $payload['meta_data'] = array_values(array_filter(array_map(function ($meta) {
            return isset($meta['key']) ? ['key' => $meta['key'], 'value' => $meta['value'] ?? ''] : null;
        }, $product['meta_data'])));
    }

    return $payload;
}

function meta_value(array $product, string $key) {
    foreach ($product['meta_data'] ?? [] as $meta) {
        if (($meta['key'] ?? '') === $key) {
            return $meta['value'];
        }
    }
    return null;
}

function translation_parent_id(array $product): int {
    $parent = meta_value($product, TRANSLATION_PARENT_META_KEY);
    if ($parent === null || $parent === '') {
        $parent = $product[TRANSLATION_PARENT_META_KEY] ?? $product['translation_parent_id'] ?? $product['translation_of'] ?? 0;
    }
    return (int)$parent;
}

function is_translation_product(array $product): bool {
    return translation_parent_id($product) > 0;
}

function product_lang(array $product): string {
    $lang = meta_value($product, TRANSLATION_LANG_META_KEY);
    if ($lang === null || $lang === '') {
        $lang = meta_value($product, 'lang')
            ?? $product[TRANSLATION_LANG_META_KEY]
            ?? $product['lang']
            ?? $product['locale']
            ?? '';
    }

    $normalized = strtolower(trim((string)$lang));
    if ($normalized === '') {
        return '';
    }

    return preg_split('/[-_]/', $normalized)[0] ?: $normalized;
}

function is_catalan_product(array $product): bool {
    $lang = product_lang($product);
    return in_array($lang, ['ca', 'cat', 'catala', 'català'], true);
}

function find_translation_product(array $products, int $parentId): ?array {
    foreach ($products as $product) {
        if (translation_parent_id($product) === $parentId) {
            return $product;
        }
    }
    return null;
}

function normalize_slug_input(string $slug): string {
    $slug = trim($slug);
    if ($slug === '') {
        return '';
    }
    if (preg_match('/^https?:\\/\\//i', $slug)) {
        $path = parse_url($slug, PHP_URL_PATH) ?? '';
        $path = trim($path, '/');
        if ($path !== '') {
            $parts = explode('/', $path);
            return end($parts) ?: '';
        }
    }
    return $slug;
}

function normalize_categories(array $product, int $mainCategoryId): array {
    $categories = array_values(array_filter(array_map(function ($cat) {
        if (!empty($cat['id'])) {
            return ['id' => (int)$cat['id']];
        }
        return null;
    }, $product['categories'] ?? [])));

    $ids = array_map(function ($cat) {
        return (int)($cat['id'] ?? 0);
    }, $categories);

    if ($mainCategoryId && !in_array($mainCategoryId, $ids, true)) {
        $categories[] = ['id' => $mainCategoryId];
    }

    return $categories;
}

if ($products) {
    $products = array_values(array_filter($products, fn($product) => !is_translation_product($product)));
    $products = array_values(array_filter($products, fn($product) => is_catalan_product($product)));
}

usort($products, function ($a, $b) {
    $orderA = (int)($a['menu_order'] ?? 0);
    $orderB = (int)($b['menu_order'] ?? 0);
    if ($orderA === $orderB) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    }
    return $orderA <=> $orderB;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['product_action'] ?? '') === 'update_product_order') {
    $orderNumbers = $_POST['order_numbers'] ?? [];
    if (!is_array($orderNumbers)) {
        $orderNumbers = [];
    }
    $errors = [];
    foreach ($orderNumbers as $productId => $orderNumber) {
        $productId = (int)$productId;
        if ($productId <= 0) {
            continue;
        }
        $orderValue = max(0, (int)$orderNumber - 1);
        $updates = [
            ['id' => $productId, 'label' => 'base'],
        ];
        $translation = find_translation_product($allProducts, $productId);
        if ($translation) {
            $updates[] = ['id' => (int)$translation['id'], 'label' => 'traducció'];
        }
        foreach ($updates as $target) {
            $update = woo_update_product('descoberta', (int)$target['id'], [
                'menu_order' => $orderValue,
            ]);
            if (!$update['success']) {
                $errors[] = $target['id'] . ' (' . $target['label'] . ')';
            }
        }
    }
    if ($errors) {
        flash('error', 'No s\'ha pogut actualitzar l\'ordre per a: ' . implode(', ', $errors));
    } else {
        flash('success', 'Ordre de les activitats desat correctament.');
    }

    redirect('/activitats_dia.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['product_action'] ?? '') === 'toggle_status') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $targetStatus = $_POST['target_status'] === 'publish' ? 'publish' : 'draft';
    $product = product_by_id($products, $productId);

    if (!$product) {
        flash('error', 'No s\'ha trobat el producte.');
        redirect('/activitats_dia.php');
    }

    $payload = build_payload_with_status($product, $targetStatus);
    $update = woo_update_product('descoberta', $productId, $payload);

    if ($update['success']) {
        $message = $targetStatus === 'publish'
            ? 'Producte actualitzat i publicat de nou'
            : 'Producte passat a borrador';
        flash('success', $message);
    } else {
        flash('error', 'No s\'ha pogut actualitzar l\'estat: ' . ($update['error'] ?? json_encode($update['data'])));
    }

    redirect('/activitats_dia.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['product_action'] ?? '') === 'edit_product') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $product = product_by_id($products, $productId);

    if (!$product) {
        flash('error', 'No s\'ha trobat el producte.');
        redirect('/activitats_dia.php');
    }

    $title = trim($_POST['title'] ?? ($product['name'] ?? ''));
    $description = trim($_POST['description'] ?? ($product['description'] ?? ''));
    $status = ($_POST['status'] ?? '') === 'publish' ? 'publish' : 'draft';
    $slug = normalize_slug_input($_POST['slug'] ?? ($product['slug'] ?? ''));

    if ($title === '' || $description === '') {
        flash('error', 'Cal omplir el títol i la descripció.');
        redirect('/activitats_dia.php');
    }

    $cicles = $_POST['cicles'] ?? [];
    if (!is_array($cicles)) {
        $cicles = [$cicles];
    }
    $cicles = array_values(array_filter(array_map('trim', $cicles)));

    $categoria = $_POST['categoria'] ?? [];
    if (!is_array($categoria)) {
        $categoria = [$categoria];
    }
    $categoria = array_values(array_filter(array_map('trim', $categoria)));

    $continguts = trim($_POST['continguts'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['activitats']['continguts']) ?? ''));
    $programa = trim($_POST['programa'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['activitats']['programa']) ?? ''));
    $preus = trim($_POST['preus'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['activitats']['preus']) ?? ''));
    $inclou = trim($_POST['inclou'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['activitats']['inclou']) ?? ''));
    $featuredUrl = trim($_POST['featured_url'] ?? '');

    $payload = [
        'name' => $title,
        'status' => $status,
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['activitats']['cicles'], 'value' => $cicles],
            ['key' => $ACF_FIELD_KEYS['activitats']['categoria'], 'value' => $categoria],
            ['key' => $ACF_FIELD_KEYS['activitats']['continguts'], 'value' => $continguts],
            ['key' => $ACF_FIELD_KEYS['activitats']['programa'], 'value' => $programa],
            ['key' => $ACF_FIELD_KEYS['activitats']['preus'], 'value' => $preus],
            ['key' => $ACF_FIELD_KEYS['activitats']['inclou'], 'value' => $inclou],
            ['key' => TRANSLATION_LANG_META_KEY, 'value' => 'ca'],
        ],
    ];
    $payload['lang'] = 'ca';
    if ($slug !== '') {
        $payload['slug'] = $slug;
    }

    $catId = category_id('descoberta', 'activitat-de-dia');
    if ($catId) {
        $payload['categories'] = normalize_categories($product, $catId);
    }

    if (!empty($_FILES['featured_file']['tmp_name'])) {
        $upload = wp_upload_media('descoberta', $_FILES['featured_file']);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $payload['images'] = [['id' => $upload['data']['id']]];
            $payload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $upload['data']['id']];
        } else {
            flash('error', 'No s\'ha pogut pujar la imatge: ' . ($upload['error'] ?? 'error desconegut'));
        }
    } elseif ($featuredUrl) {
        $payload['images'] = [['src' => $featuredUrl]];
    } else {
        $existingImageId = (int)($_POST['existing_image_id'] ?? 0);
        $existingImageSrc = trim($_POST['existing_image_src'] ?? '');
        if ($existingImageId) {
            $payload['images'] = [['id' => $existingImageId]];
            $payload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $existingImageId];
        } elseif ($existingImageSrc) {
            $payload['images'] = [['src' => $existingImageSrc]];
        }
    }

    $update = woo_update_product('descoberta', $productId, $payload);

    if ($update['success']) {
        $translationProduct = find_translation_product($allProducts, $productId);
        $titleEs = trim($_POST['title_es'] ?? ($translationProduct['name'] ?? ''));
        $descriptionEs = trim($_POST['description_es'] ?? ($translationProduct['description'] ?? ''));
        $statusEs = ($_POST['status_es'] ?? ($translationProduct['status'] ?? 'draft')) === 'publish' ? 'publish' : 'draft';
        $slugEs = normalize_slug_input($_POST['slug_es'] ?? ($translationProduct['slug'] ?? ''));

        $ciclesEs = $_POST['cicles_es'] ?? (array)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['cicles']) ?? []);
        if (!is_array($ciclesEs)) {
            $ciclesEs = [$ciclesEs];
        }
        $ciclesEs = array_values(array_filter(array_map('trim', $ciclesEs)));

        $categoriaEs = $_POST['categoria_es'] ?? (array)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['categoria']) ?? []);
        if (!is_array($categoriaEs)) {
            $categoriaEs = [$categoriaEs];
        }
        $categoriaEs = array_values(array_filter(array_map('trim', $categoriaEs)));

        $contingutsEs = trim($_POST['continguts_es'] ?? (string)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['continguts']) ?? ''));
        $programaEs = trim($_POST['programa_es'] ?? (string)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['programa']) ?? ''));
        $preusEs = trim($_POST['preus_es'] ?? (string)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['preus']) ?? ''));
        $inclouEs = trim($_POST['inclou_es'] ?? (string)(meta_value($translationProduct ?? [], $ACF_FIELD_KEYS['activitats']['inclou']) ?? ''));

        $hasTranslationContent = $titleEs !== '' || $descriptionEs !== '';
        if ($enableSpanishPublishing && $hasTranslationContent) {
            if ($titleEs === '' || $descriptionEs === '') {
                flash('error', 'Cal omplir el títol i la descripció en castellà.');
            } else {
                $translationPayload = [
                    'name' => $titleEs,
                    'status' => $statusEs,
                    'description' => $descriptionEs,
                    'lang' => 'es',
                    'categories' => [],
                    'meta_data' => [
                        ['key' => $ACF_FIELD_KEYS['activitats']['cicles'], 'value' => $ciclesEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['categoria'], 'value' => $categoriaEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['continguts'], 'value' => $contingutsEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['programa'], 'value' => $programaEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['preus'], 'value' => $preusEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['inclou'], 'value' => $inclouEs],
                        ['key' => TRANSLATION_PARENT_META_KEY, 'value' => (string)$productId],
                        ['key' => TRANSLATION_LANG_META_KEY, 'value' => 'es'],
                    ],
                ];
                if ($slugEs !== '') {
                    $translationPayload['slug'] = $slugEs;
                }

                $catId = category_id('descoberta', 'activitat-de-dia');
                if ($catId) {
                    $translationPayload['categories'] = normalize_categories($translationProduct ?? $product, $catId);
                }

                if ($translationProduct) {
                    $translationUpdate = woo_update_product('descoberta', (int)$translationProduct['id'], $translationPayload);
                    if (!$translationUpdate['success']) {
                        flash('error', 'No s\'ha pogut actualitzar la versió en castellà: ' . ($translationUpdate['error'] ?? json_encode($translationUpdate['data'])));
                    }
                } else {
                    if (!empty($product['images'])) {
                        $translationPayload['images'] = $product['images'];
                    }
                    $translationCreate = woo_create_product('descoberta', $translationPayload);
                    if (!$translationCreate['success']) {
                        flash('error', 'No s\'ha pogut crear la versió en castellà: ' . ($translationCreate['error'] ?? json_encode($translationCreate['data'])));
                    }
                }
            }
        }
        flash('success', 'Activitat actualitzada correctament');
    } else {
        flash('error', 'No s\'ha pogut actualitzar la fitxa: ' . ($update['error'] ?? json_encode($update['data'])));
    }

    redirect('/activitats_dia.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['product_action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'publish';
    $cicles = $_POST['cicles'] ?? [];
    if (!is_array($cicles)) {
        $cicles = [$cicles];
    }
    $cicles = array_values(array_filter(array_map('trim', $cicles)));

    $categoria = $_POST['categoria'] ?? [];
    if (!is_array($categoria)) {
        $categoria = [$categoria];
    }
    $categoria = array_values(array_filter(array_map('trim', $categoria)));
    $continguts = trim($_POST['continguts'] ?? '');
    $programa = trim($_POST['programa'] ?? '');
    $preus = trim($_POST['preus'] ?? '');
    $inclou = trim($_POST['inclou'] ?? '');
    $featuredUrl = trim($_POST['featured_url'] ?? '');

    $payload = [
        'name' => $title,
        'status' => $status,
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['activitats']['cicles'], 'value' => $cicles],
            ['key' => $ACF_FIELD_KEYS['activitats']['categoria'], 'value' => $categoria],
            ['key' => $ACF_FIELD_KEYS['activitats']['continguts'], 'value' => $continguts],
            ['key' => $ACF_FIELD_KEYS['activitats']['programa'], 'value' => $programa],
            ['key' => $ACF_FIELD_KEYS['activitats']['preus'], 'value' => $preus],
            ['key' => $ACF_FIELD_KEYS['activitats']['inclou'], 'value' => $inclou],
            ['key' => TRANSLATION_LANG_META_KEY, 'value' => 'ca'],
        ],
    ];
    $payload['lang'] = 'ca';

    if ($title === '' || $description === '') {
        flash('error', 'Cal omplir el títol i la descripció.');
        redirect('/activitats_dia.php');
    }

    $catId = category_id('descoberta', 'activitat-de-dia');
    if ($catId) {
        $payload['categories'][] = ['id' => $catId];
    }

    // Imagen destacada
    if (!empty($_FILES['featured_file']['tmp_name'])) {
        $upload = wp_upload_media('descoberta', $_FILES['featured_file']);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $payload['images'] = [['id' => $upload['data']['id']]];
            $payload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $upload['data']['id']];
        } else {
            flash('error', 'No s\'ha pogut pujar la imatge: ' . ($upload['error'] ?? 'error desconegut'));
        }
    } elseif ($featuredUrl) {
        $payload['images'] = [['src' => $featuredUrl]];
    }

    $create = woo_create_product('descoberta', $payload);
    if ($create['success']) {
        $createdProduct = $create['data'] ?? [];
        $titleEs = trim($_POST['title_es'] ?? '');
        $descriptionEs = trim($_POST['description_es'] ?? '');
        $statusEs = ($_POST['status_es'] ?? '') === 'draft' ? 'draft' : 'publish';
        $slugEs = normalize_slug_input($_POST['slug_es'] ?? '');

        $ciclesEs = $_POST['cicles_es'] ?? [];
        if (!is_array($ciclesEs)) {
            $ciclesEs = [$ciclesEs];
        }
        $ciclesEs = array_values(array_filter(array_map('trim', $ciclesEs)));

        $categoriaEs = $_POST['categoria_es'] ?? [];
        if (!is_array($categoriaEs)) {
            $categoriaEs = [$categoriaEs];
        }
        $categoriaEs = array_values(array_filter(array_map('trim', $categoriaEs)));

        $contingutsEs = trim($_POST['continguts_es'] ?? '');
        $programaEs = trim($_POST['programa_es'] ?? '');
        $preusEs = trim($_POST['preus_es'] ?? '');
        $inclouEs = trim($_POST['inclou_es'] ?? '');

        $hasTranslationContent = $titleEs !== '' || $descriptionEs !== '';
        if ($enableSpanishPublishing && $hasTranslationContent) {
            if ($titleEs === '' || $descriptionEs === '') {
                flash('error', 'Cal omplir el títol i la descripció en castellà.');
            } else {
                $translationPayload = [
                    'name' => $titleEs,
                    'status' => $statusEs,
                    'description' => $descriptionEs,
                    'lang' => 'es',
                    'categories' => $payload['categories'],
                    'meta_data' => [
                        ['key' => $ACF_FIELD_KEYS['activitats']['cicles'], 'value' => $ciclesEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['categoria'], 'value' => $categoriaEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['continguts'], 'value' => $contingutsEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['programa'], 'value' => $programaEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['preus'], 'value' => $preusEs],
                        ['key' => $ACF_FIELD_KEYS['activitats']['inclou'], 'value' => $inclouEs],
                        ['key' => TRANSLATION_PARENT_META_KEY, 'value' => (string)($createdProduct['id'] ?? 0)],
                        ['key' => TRANSLATION_LANG_META_KEY, 'value' => 'es'],
                    ],
                ];
                if ($slugEs !== '') {
                    $translationPayload['slug'] = $slugEs;
                }
                if (!empty($createdProduct['images'])) {
                    $translationPayload['images'] = $createdProduct['images'];
                }
                $translationCreate = woo_create_product('descoberta', $translationPayload);
                if (!$translationCreate['success']) {
                    flash('error', 'No s\'ha pogut crear la versió en castellà: ' . ($translationCreate['error'] ?? json_encode($translationCreate['data'])));
                }
            }
        }
        flash('success', 'Activitat creada correctament');
        redirect('/activitats_dia.php');
    } else {
        flash('error', 'Error en crear la fitxa: ' . json_encode($create['data']));
        redirect('/activitats_dia.php');
    }
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Activitats de dia</h1>
    <p class="subtitle">Productes de WooCommerce amb la categoria activitat-de-dia</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <?php if ($apiError): ?>
        <div class="alert error"><?php echo htmlspecialchars($apiError); ?></div>
    <?php endif; ?>

    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-block">
                <label class="filter-label">Cercar per nom</label>
                <input type="search" placeholder="Escriu el nom de l'activitat" data-table-search data-table-target="#activitats-table">
            </div>
        </div>
    </div>

    <div class="actions-row">
        <button type="submit" class="btn secondary" form="activitats-order-form">Desar ordre</button>
        <p class="hint">Introdueix un número per definir l'ordre de les activitats.</p>
    </div>
    <form method="POST" class="inline-form" id="activitats-order-form" data-order-form>
        <input type="hidden" name="product_action" value="update_product_order">
    </form>

    <div class="table-wrapper scrollable">
        <table class="styled-table" id="activitats-table" data-orderable-table>
            <thead>
                <tr>
                    <th>Ordre</th>
                    <th class="sortable" data-sort-key="title">Títol <i class="fa fa-sort"></i></th>
                    <th class="sortable" data-sort-key="status">Estat <i class="fa fa-sort"></i></th>
                    <th>Imatge</th>
                    <th class="sortable" data-sort-key="updated">Actualitzat <i class="fa fa-sort"></i></th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php $dateInfo = formatted_product_date($product['date_modified'] ?? ''); ?>
                    <?php $statusText = status_label($product['status'] ?? ''); ?>
                    <?php
                        $translationProduct = find_translation_product($allProducts, (int)($product['id'] ?? 0));
                        $editorPayload = $product;
                        $editorPayload['translation'] = $translationProduct ? [
                            'id' => $translationProduct['id'] ?? 0,
                            'name' => $translationProduct['name'] ?? '',
                            'slug' => $translationProduct['slug'] ?? '',
                            'description' => $translationProduct['description'] ?? '',
                            'status' => $translationProduct['status'] ?? 'draft',
                            'meta_data' => $translationProduct['meta_data'] ?? [],
                            'images' => $translationProduct['images'] ?? [],
                            'permalink' => $translationProduct['permalink'] ?? '',
                            'link' => $translationProduct['link'] ?? '',
                        ] : null;
                    ?>
                    <tr data-search-value="<?php echo htmlspecialchars(strtolower($product['name'] ?? '')); ?>">
                        <td>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                name="order_numbers[<?php echo (int)($product['id'] ?? 0); ?>]"
                                value="<?php echo (int)($product['menu_order'] ?? 0) + 1; ?>"
                                class="order-input"
                                form="activitats-order-form"
                            >
                        </td>
                        <td data-col="title" data-sort-value="<?php echo htmlspecialchars(strtolower($product['name'] ?? '')); ?>"><?php echo htmlspecialchars($product['name'] ?? ''); ?></td>
                        <td data-col="status" data-sort-value="<?php echo htmlspecialchars($statusText); ?>"><?php echo htmlspecialchars($statusText); ?></td>
                        <td><?php if (!empty($product['images'][0]['src'])): ?><img class="thumb" src="<?php echo htmlspecialchars($product['images'][0]['src']); ?>" alt="thumb"><?php endif; ?></td>
                        <td data-col="updated" data-sort-value="<?php echo htmlspecialchars((string)$dateInfo['timestamp']); ?>"><?php echo htmlspecialchars($dateInfo['display']); ?></td>
                        <td class="actions-cell">
                            <button type="button"
                                    class="icon-btn primary"
                                    title="Editar"
                                    data-open="modalEditActivitat"
                                    data-edit-activitat="<?php echo htmlspecialchars(json_encode($editorPayload), ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa fa-pen"></i>
                            </button>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="product_action" value="toggle_status">
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars((string)($product['id'] ?? '')); ?>">
                                <input type="hidden" name="target_status" value="<?php echo ($product['status'] ?? '') === 'publish' ? 'draft' : 'publish'; ?>">
                                <?php if (($product['status'] ?? '') === 'publish'): ?>
                                    <button type="submit" class="icon-btn danger" title="Passar a borrador"><i class="fa fa-trash"></i></button>
                                <?php else: ?>
                                    <button type="submit" class="icon-btn success" title="Publicar de nou"><i class="fa fa-arrow-up"></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal-overlay" id="modalEditActivitat">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar activitat</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form class="form-card" method="POST" enctype="multipart/form-data" data-language-scope>
                    <input type="hidden" name="product_action" value="edit_product">
                    <input type="hidden" name="product_id">
                    <input type="hidden" name="status">
                    <input type="hidden" name="existing_image_id">
                    <input type="hidden" name="existing_image_src">
                    <input type="hidden" name="product_id_es">
                    <input type="hidden" name="status_es">

                    <div class="language-toggle" data-language-toggle data-default-lang="ca">
                        <button type="button" data-lang="ca">Català</button>
                        <button type="button" data-lang="es">Castellà</button>
                    </div>

                    <div class="language-panel" data-language-panel data-lang="ca">
                        <label>Títol del producte <span class="required-asterisk">*</span></label>
                        <input type="text" name="title" required>

                        <label>URL</label>
                        <input type="text" name="slug" placeholder="exemple-url">

                        <label>Descripció <span class="required-asterisk">*</span></label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció"></div>
                            <textarea name="description" class="rich" rows="4" required></textarea>
                        </div>

                        <div class="two-columns">
                            <div>
                                <label>Cicles</label>
                                <select name="cicles[]" multiple required>
                                    <?php foreach ($CICLES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona un o més cicles</p>
                            </div>
                            <div>
                                <label>Categoria</label>
                                <select name="categoria[]" multiple required>
                                    <?php foreach ($CATEGORIES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona una o més categories</p>
                            </div>
                        </div>

                        <label>Competències i metodologia</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Competències i metodologia"></div>
                            <textarea name="continguts" class="rich" rows="4"></textarea>
                        </div>

                        <label>Programa</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Programa"></div>
                            <textarea name="programa" class="rich" rows="4"></textarea>
                        </div>

                        <label>Preus</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Preus"></div>
                            <textarea name="preus" class="rich" rows="4"></textarea>
                        </div>

                        <label>Inclou</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Inclou"></div>
                            <textarea name="inclou" class="rich" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="language-panel" data-language-panel data-lang="es">
                        <label>Título del producto <span class="required-asterisk">*</span></label>
                        <input type="text" name="title_es">

                        <label>URL</label>
                        <input type="text" name="slug_es" placeholder="ejemplo-url">

                        <label>Descripción <span class="required-asterisk">*</span></label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció (Castellà)"></div>
                            <textarea name="description_es" class="rich" rows="4"></textarea>
                        </div>

                        <div class="two-columns">
                            <div>
                                <label>Ciclos</label>
                                <select name="cicles_es[]" multiple>
                                    <?php foreach ($CICLES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona uno o más ciclos</p>
                            </div>
                            <div>
                                <label>Categoría</label>
                                <select name="categoria_es[]" multiple>
                                    <?php foreach ($CATEGORIES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona una o más categorías</p>
                            </div>
                        </div>

                        <label>Competencias y metodología</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Competències i metodologia (Castellà)"></div>
                            <textarea name="continguts_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Programa</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Programa (Castellà)"></div>
                            <textarea name="programa_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Precios</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Preus (Castellà)"></div>
                            <textarea name="preus_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Incluye</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Inclou (Castellà)"></div>
                            <textarea name="inclou_es" class="rich" rows="4"></textarea>
                        </div>
                    </div>

                    <label>Imatge destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O enganxa una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancel·lar</button>
                        <button type="submit" class="btn">Desar canvis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <button class="fab" type="button" data-open="modalActivitat">+</button>

    <div class="modal-overlay" id="modalActivitat">
        <div class="modal">
            <div class="modal-header">
                <h2>Crear activitat de dia</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form class="form-card" method="POST" enctype="multipart/form-data" data-language-scope>
                    <div class="language-toggle" data-language-toggle data-default-lang="ca">
                        <button type="button" data-lang="ca">Català</button>
                        <button type="button" data-lang="es">Castellà</button>
                    </div>

                    <div class="language-panel" data-language-panel data-lang="ca">
                        <label>Títol del producte <span class="required-asterisk">*</span></label>
                        <input type="text" name="title" required>

                        <label>Descripció <span class="required-asterisk">*</span></label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció"></div>
                            <textarea name="description" class="rich" rows="4" required></textarea>
                        </div>

                        <div class="two-columns">
                            <div>
                                <label>Cicles</label>
                                <select name="cicles[]" multiple required>
                                    <?php foreach ($CICLES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona un o més cicles</p>
                            </div>
                            <div>
                                <label>Categoria</label>
                                <select name="categoria[]" multiple required>
                                    <?php foreach ($CATEGORIES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona una o més categories</p>
                            </div>
                        </div>

                        <label>Competències i metodologia</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Competències i metodologia"></div>
                            <textarea name="continguts" class="rich" rows="4"></textarea>
                        </div>

                        <label>Programa</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Programa"></div>
                            <textarea name="programa" class="rich" rows="4"></textarea>
                        </div>

                        <label>Preus</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Preus"></div>
                            <textarea name="preus" class="rich" rows="4"></textarea>
                        </div>

                        <label>Inclou</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Inclou"></div>
                            <textarea name="inclou" class="rich" rows="4"></textarea>
                        </div>
                    </div>

                    <div class="language-panel" data-language-panel data-lang="es">
                        <label>Título del producto <span class="required-asterisk">*</span></label>
                        <input type="text" name="title_es">

                        <label>Descripción <span class="required-asterisk">*</span></label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció (Castellà)"></div>
                            <textarea name="description_es" class="rich" rows="4"></textarea>
                        </div>

                        <div class="two-columns">
                            <div>
                                <label>Ciclos</label>
                                <select name="cicles_es[]" multiple>
                                    <?php foreach ($CICLES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona uno o más ciclos</p>
                            </div>
                            <div>
                                <label>Categoría</label>
                                <select name="categoria_es[]" multiple>
                                    <?php foreach ($CATEGORIES_OPTIONS as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">Selecciona una o más categorías</p>
                            </div>
                        </div>

                        <label>Competencias y metodología</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Competències i metodologia (Castellà)"></div>
                            <textarea name="continguts_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Programa</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Programa (Castellà)"></div>
                            <textarea name="programa_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Precios</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Preus (Castellà)"></div>
                            <textarea name="preus_es" class="rich" rows="4"></textarea>
                        </div>

                        <label>Incluye</label>
                        <div class="rich-wrapper" data-rich-editor>
                            <div class="rich-toolbar">
                                <button type="button" data-command="bold" title="Negrita"><i class="fa fa-bold"></i></button>
                                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                <button type="button" data-command="underline" title="Subrayado"><i class="fa fa-underline"></i></button>
                                <button type="button" data-command="createLink" title="Enlace"><i class="fa fa-link"></i></button>
                                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacado"><i class="fa fa-palette"></i></button>
                                <button type="button" data-command="insertUnorderedList" title="Lista"><i class="fa fa-list-ul"></i></button>
                            </div>
                            <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Inclou (Castellà)"></div>
                            <textarea name="inclou_es" class="rich" rows="4"></textarea>
                        </div>
                    </div>

                    <label>Imatge destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O enganxa una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <label>Estat</label>
                    <select name="status">
                        <option value="publish" selected>Publicar</option>
                        <option value="draft">Borrador</option>
                    </select>

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancel·lar</button>
                        <button type="submit" class="btn">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.ACTIVITAT_META_KEYS = <?php echo json_encode($ACF_FIELD_KEYS['activitats']); ?>;
    </script>
</main>

</div>
</body>
</html>
