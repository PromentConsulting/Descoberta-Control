<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();

$productsResponse = woo_all_products('descoberta');

$allProducts = $productsResponse['success'] ? ($productsResponse['data'] ?? []) : [];
$cases = $productsResponse['success'] ? filter_products_by_category($productsResponse['data'], 'cases-de-colonies') : [];
$cases = array_values(array_filter($cases, fn($case) => !has_category_slug($case, 'preu')));
$activitats = $productsResponse['success'] ? filter_products_by_category($productsResponse['data'], 'activitat-de-dia') : [];
$centres = $productsResponse['success'] ? filter_products_by_category($productsResponse['data'], 'centre-interes') : [];

define('PREU_LINK_META_KEY', 'linked_case_id');

function meta_value(array $product, string $key) {
    foreach ($product['meta_data'] ?? [] as $meta) {
        if (($meta['key'] ?? '') === $key) {
            return $meta['value'];
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

function has_category_slug(array $product, string $slug): bool {
    foreach ($product['categories'] ?? [] as $cat) {
        if (($cat['slug'] ?? '') === $slug) {
            return true;
        }
    }
    return false;
}

function selected_items(array $product, string $metaKey, array $fallback = []): array {
    $stored = meta_value($product, $metaKey);
    if (is_array($stored)) {
        return array_map('intval', $stored);
    }
    if (!empty($product[$metaKey]) && is_array($product[$metaKey])) {
        return array_map('intval', $product[$metaKey]);
    }
    return $fallback;
}

function regims_array(string $value): array {
    $parts = preg_split('/-+/', $value) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
}

function normalize_yes_no($value): string {
    $val = strtolower((string)$value);
    if ($val === 'si' || $val === 'sí') {
        return 'Si';
    }
    if ($val === 'no') {
        return 'No';
    }
    return '';
}

function find_product_by_id(array $products, int $id): ?array {
    foreach ($products as $product) {
        if ((int)($product['id'] ?? 0) === $id) {
            return $product;
        }
    }
    return null;
}

function find_product_by_meta_value(array $products, string $key, string $value): ?array {
    foreach ($products as $product) {
        foreach ($product['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? '') === $key && (string)($meta['value'] ?? '') === $value) {
                return $product;
            }
        }
    }
    return null;
}

function upload_gallery_files(string $fieldName): array {
    $files = $_FILES[$fieldName] ?? null;
    if (!$files || empty($files['tmp_name']) || !is_array($files['tmp_name'])) {
        return [];
    }

    $uploads = [];
    $count = count($files['tmp_name']);
    for ($i = 0; $i < $count; $i++) {
        if (empty($files['tmp_name'][$i])) {
            continue;
        }

        $file = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i] ?? 0,
            'size' => $files['size'][$i] ?? 0,
        ];

        $upload = wp_upload_media('descoberta', $file);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $uploads[] = ['id' => $upload['data']['id']];
        } else {
            flash('error', 'No s\'ha pogut pujar una imatge de la galeria: ' . ($upload['error'] ?? 'Error desconegut'));
        }
    }

    return $uploads;
}

function prepare_case_images(
    array $existingImages,
    string $featuredUrl,
    int $existingImageId,
    string $existingImageSrc,
    array $removedGallery
): array {
    $removedIds = [];
    $removedSrcs = [];
    foreach ($removedGallery as $removed) {
        if (!empty($removed['id'])) {
            $removedIds[] = (int)$removed['id'];
        }
        if (!empty($removed['src'])) {
            $removedSrcs[] = (string)$removed['src'];
        }
    }

    $images = [];
    foreach ($existingImages as $index => $img) {
        if ($index === 0) {
            $images[] = $img;
            continue;
        }
        $imgId = (int)($img['id'] ?? 0);
        $imgSrc = (string)($img['src'] ?? '');
        if ($imgId && in_array($imgId, $removedIds, true)) {
            continue;
        }
        if ($imgSrc !== '' && in_array($imgSrc, $removedSrcs, true)) {
            continue;
        }
        $images[] = $img;
    }
    $thumbnailId = null;
    $changed = count($images) !== count($existingImages);

    if (!empty($_FILES['featured_file']['tmp_name'])) {
        $upload = wp_upload_media('descoberta', $_FILES['featured_file']);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $images[0] = ['id' => $upload['data']['id']];
            $thumbnailId = $upload['data']['id'];
            $changed = true;
        } else {
            flash('error', 'No s\'ha pogut pujar la imatge destacada: ' . ($upload['error'] ?? 'Error desconegut'));
        }
    } elseif ($featuredUrl !== '') {
        $images[0] = ['src' => $featuredUrl];
        $changed = true;
    } elseif ($existingImageId) {
        $images[0] = ['id' => $existingImageId];
        $thumbnailId = $existingImageId;
        $changed = true;
    } elseif ($existingImageSrc !== '') {
        $images[0] = ['src' => $existingImageSrc];
        $changed = true;
    }

    $galleryUploads = upload_gallery_files('gallery_files');
    if ($galleryUploads) {
        $images = array_merge($images, $galleryUploads);
        $changed = true;
        if (!$thumbnailId && isset($galleryUploads[0]['id'])) {
            $thumbnailId = $galleryUploads[0]['id'];
        }
    }

    return [
        'images' => array_values($images),
        'thumbnail_id' => $thumbnailId,
        'changed' => $changed,
    ];
}

function build_meta_payload(array $sourceProduct, array $allowedKeys, string $urlKey): array {
    $metaMap = [$urlKey => $sourceProduct['permalink'] ?? ''];

    foreach ($sourceProduct['meta_data'] ?? [] as $meta) {
        $metaKey = $meta['key'] ?? '';
        if (in_array($metaKey, $allowedKeys, true)) {
            $metaMap[$metaKey] = $meta['value'] ?? '';
        }
    }

    $result = [];
    foreach ($metaMap as $key => $value) {
        $result[] = ['key' => $key, 'value' => $value];
    }

    return $result;
}

function sync_group_to_site(
    string $siteKey,
    array $selectedIds,
    array $previousIds,
    array $sourceProducts,
    array &$targetProducts,
    array $allowedMetaKeys,
    string $urlKey,
    string $categorySlug,
    array &$errors
): void {
    foreach ($selectedIds as $id) {
        $source = find_product_by_id($sourceProducts, $id);
        if (!$source) {
            continue;
        }

        $permalink = $source['permalink'] ?? '';
        $existing = $permalink ? find_product_by_meta_value($targetProducts, $urlKey, $permalink) : null;

        $payload = [
            'name' => $source['name'] ?? '',
            'status' => 'publish',
            'description' => $source['description'] ?? '',
            'categories' => [],
            'meta_data' => build_meta_payload($source, $allowedMetaKeys, $urlKey),
        ];

        $catId = category_id($siteKey, $categorySlug);
        if ($catId) {
            $payload['categories'][] = ['id' => $catId];
        }

        if (!empty($source['images'][0]['src'])) {
            $payload['images'] = [['src' => $source['images'][0]['src']]];
        }

        $response = $existing
            ? woo_update_product($siteKey, (int)$existing['id'], $payload)
            : woo_create_product($siteKey, $payload);

        if ($response['success']) {
            if (!$existing && isset($response['data'])) {
                $targetProducts[] = $response['data'];
            }
        } else {
            $errors[] = 'Error sincronitzant "' . ($source['name'] ?? 'Producte') . '" a ' . ($siteKey ?: 'web') . ': ' . ($response['error'] ?? json_encode($response['data']));
        }
    }

    $removed = array_diff($previousIds, $selectedIds);
    foreach ($removed as $id) {
        $source = find_product_by_id($sourceProducts, $id);
        if (!$source) {
            continue;
        }

        $permalink = $source['permalink'] ?? '';
        $existing = $permalink ? find_product_by_meta_value($targetProducts, $urlKey, $permalink) : null;

        if ($existing) {
            $response = woo_update_product($siteKey, (int)$existing['id'], ['status' => 'draft']);
            if (!$response['success']) {
                $errors[] = 'No s\'ha pogut despublicar "' . ($source['name'] ?? 'Producte') . '" a ' . ($siteKey ?: 'web') . ': ' . ($response['error'] ?? json_encode($response['data']));
            }
        }
    }
}

function sync_related_content_to_site(
    string $siteKey,
    array $selectedActivitats,
    array $selectedCentres,
    array $previousActivitats,
    array $previousCentres,
    array $activitats,
    array $centres,
    array $acfKeys
): array {
    $targetResponse = woo_products($siteKey);
    if (!$targetResponse['success']) {
        return ['success' => false, 'error' => $targetResponse['error'] ?? 'No s\'ha pogut obtenir els productes de destí'];
    }

    $targetProducts = $targetResponse['data'] ?? [];
    $errors = [];

    $urlKey = $acfKeys['url'];
    $activitatMetaKeys = array_values($acfKeys['activitats']);
    $centreMetaKeys = array_values($acfKeys['centres']);

    sync_group_to_site($siteKey, $selectedActivitats, $previousActivitats, $activitats, $targetProducts, $activitatMetaKeys, $urlKey, 'activitat-de-dia', $errors);
    sync_group_to_site($siteKey, $selectedCentres, $previousCentres, $centres, $targetProducts, $centreMetaKeys, $urlKey, 'centre-interes', $errors);

    return ['success' => empty($errors), 'error' => empty($errors) ? null : implode(' | ', $errors)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['product_action'] ?? '';

    if ($action === 'generate_preu_duplicate') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $currentCase = find_product_by_id($cases, $productId);
        if (!$currentCase) {
            flash('error', 'No s\'ha pogut trobar la casa per generar els preus.');
            redirect('/editar_cases.php');
        }

        $preuDuplicate = find_product_by_meta_value($allProducts, PREU_LINK_META_KEY, (string)$productId);
        if ($preuDuplicate) {
            flash('info', 'Ja existeix una casa de preus vinculada.');
            redirect('/editar_cases.php');
        }

        $preuCatId = category_id('descoberta', 'preu');
        if (!$preuCatId) {
            flash('error', 'No s\'ha pogut trobar la categoria preu.');
            redirect('/editar_cases.php');
        }

        $slug = $currentCase['slug'] ?? '';
        $duplicatePayload = [
            'name' => $currentCase['name'] ?? '',
            'status' => $currentCase['status'] ?? 'publish',
            'description' => $currentCase['description'] ?? '',
            'short_description' => $currentCase['short_description'] ?? '',
            'categories' => [['id' => $preuCatId]],
            'meta_data' => $currentCase['meta_data'] ?? [],
        ];
        if (!empty($currentCase['images'])) {
            $duplicatePayload['images'] = $currentCase['images'];
        }
        if ($slug !== '') {
            $baseSlug = preg_replace('/-preus$/', '', $slug);
            $duplicatePayload['slug'] = $baseSlug . '-preus';
        }
        $duplicatePayload['meta_data'][] = ['key' => PREU_LINK_META_KEY, 'value' => (string)$productId];

        $duplicate = woo_create_product('descoberta', $duplicatePayload);
        if ($duplicate['success']) {
            flash('success', 'Casa duplicada de preus generada correctament');
        } else {
            flash('error', 'No s\'ha pogut crear la casa duplicada de preus: ' . ($duplicate['error'] ?? json_encode($duplicate['data'])));
        }

        redirect('/editar_cases.php');
    } elseif ($action === 'edit_case') {
        global $ACF_FIELD_KEYS;
        $productId = (int)($_POST['product_id'] ?? 0);
        $currentCase = find_product_by_id($cases, $productId);
        $caseKeys = $ACF_FIELD_KEYS['cases'] ?? [];

        if (!$currentCase) {
            flash('error', 'No s\'ha trobat la casa seleccionada.');
            redirect('/editar_cases.php');
        }

        $title = trim($_POST['title'] ?? ($currentCase['name'] ?? ''));
        $description = trim($_POST['description'] ?? ($currentCase['description'] ?? ''));
        $shortDescription = trim($_POST['short_description'] ?? ($currentCase['short_description'] ?? ''));
        $featuredUrl = trim($_POST['featured_url'] ?? '');
        $existingImageId = (int)($_POST['existing_image_id'] ?? 0);
        $existingImageSrc = trim($_POST['existing_image_src'] ?? '');
        $slug = normalize_slug_input($_POST['slug'] ?? ($currentCase['slug'] ?? ''));

        if ($title === '' || $description === '') {
            flash('error', 'Cal omplir el títol i la descripció.');
            redirect('/editar_cases.php');
        }

        $getMeta = function (string $key) use ($currentCase, $caseKeys) {
            return meta_value($currentCase, $caseKeys[$key] ?? '') ?? '';
        };

        $normativaVal = trim($_POST['existing_normativa'] ?? (string)$getMeta('normativa'));
        if (!empty($_FILES['normativa']['tmp_name'])) {
            $upload = wp_upload_media('descoberta', $_FILES['normativa']);
            if ($upload['success'] && isset($upload['data']['id'])) {
                $normativaVal = (string)$upload['data']['id'];
            } else {
                flash('error', 'No s\'ha pogut pujar la normativa: ' . ($upload['error'] ?? 'Error desconegut'));
            }
        }

        $piscinaVal = normalize_yes_no($_POST['piscina'] ?? $getMeta('piscina')) ?: normalize_yes_no($getMeta('piscina'));
        $wifiVal = normalize_yes_no($_POST['wifi'] ?? $getMeta('wifi')) ?: normalize_yes_no($getMeta('wifi'));

        $metaPayload = [
            ['key' => $caseKeys['places'] ?? 'places', 'value' => (int)($_POST['places'] ?? (int)$getMeta('places'))],
            ['key' => $caseKeys['regims_admessos'] ?? 'Regims_admessos', 'value' => trim($_POST['regims_admessos'] ?? (string)$getMeta('regims_admessos'))],
            ['key' => $caseKeys['exclusivitat'] ?? 'exclusivitat', 'value' => (int)($_POST['exclusivitat'] ?? (int)$getMeta('exclusivitat'))],
            ['key' => $caseKeys['habitacions'] ?? 'habitacions', 'value' => trim($_POST['habitacions'] ?? (string)$getMeta('habitacions'))],
            ['key' => $caseKeys['provincia'] ?? 'provincia', 'value' => trim($_POST['provincia'] ?? (string)$getMeta('provincia'))],
            ['key' => $caseKeys['comarca'] ?? 'comarca', 'value' => trim($_POST['comarca'] ?? (string)$getMeta('comarca'))],
            ['key' => $caseKeys['calefaccio'] ?? 'calefaccio', 'value' => trim($_POST['calefaccio'] ?? (string)$getMeta('calefaccio'))],
            ['key' => $caseKeys['sales_activitats'] ?? 'sales_activitats', 'value' => trim($_POST['sales_activitats'] ?? (string)$getMeta('sales_activitats'))],
            ['key' => $caseKeys['exteriors'] ?? 'exteriors', 'value' => trim($_POST['exteriors'] ?? (string)$getMeta('exteriors'))],
            ['key' => $caseKeys['piscina'] ?? 'piscina', 'value' => $piscinaVal ?: (string)$getMeta('piscina')],
            ['key' => $caseKeys['places_adaptades'] ?? 'places_adptades', 'value' => (int)($_POST['places_adaptades'] ?? (int)$getMeta('places_adaptades'))],
            ['key' => $caseKeys['wifi'] ?? 'wifi', 'value' => $wifiVal ?: (string)$getMeta('wifi')],
            ['key' => $caseKeys['normativa'] ?? 'normativa_de_la_casa', 'value' => $normativaVal],
            ['key' => $caseKeys['preus'] ?? 'preus', 'value' => trim($_POST['preus'] ?? (string)$getMeta('preus'))],
            ['key' => $caseKeys['google_maps'] ?? 'google_maps', 'value' => trim($_POST['google_maps'] ?? (string)$getMeta('google_maps'))],
        ];

        $payload = [
            'name' => $title,
            'description' => $description,
            'short_description' => $shortDescription,
            'meta_data' => $metaPayload,
        ];
        if ($slug !== '') {
            $payload['slug'] = $slug;
        }

        $removedGallery = json_decode($_POST['removed_gallery_images'] ?? '[]', true);
        if (!is_array($removedGallery)) {
            $removedGallery = [];
        }
        $imagesResult = prepare_case_images($currentCase['images'] ?? [], $featuredUrl, $existingImageId, $existingImageSrc, $removedGallery);
        if ($imagesResult['changed']) {
            $payload['images'] = $imagesResult['images'];
            if ($imagesResult['thumbnail_id']) {
                $payload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $imagesResult['thumbnail_id']];
            }
        }

        $update = woo_update_product('descoberta', $productId, $payload);

        $preuDuplicate = find_product_by_meta_value($allProducts, PREU_LINK_META_KEY, (string)$productId);
        if ($preuDuplicate) {
            $duplicatePayload = $payload;
            $preuCatId = category_id('descoberta', 'preu');
            if ($preuCatId) {
                $duplicatePayload['categories'] = [['id' => $preuCatId]];
            }
            if ($slug !== '') {
                $baseSlug = preg_replace('/-preus$/', '', $slug);
                $duplicatePayload['slug'] = $baseSlug . '-preus';
            }
            $duplicatePayload['meta_data'][] = ['key' => PREU_LINK_META_KEY, 'value' => (string)$productId];
            $dupUpdate = woo_update_product('descoberta', (int)$preuDuplicate['id'], $duplicatePayload);
            if (!$dupUpdate['success']) {
                flash('error', 'No s\'ha pogut actualitzar la casa duplicada de preus: ' . ($dupUpdate['error'] ?? json_encode($dupUpdate['data'])));
            }
        }

        if ($update['success']) {
            flash('success', 'Casa actualitzada correctament');
        } else {
            flash('error', 'No s\'ha pogut actualitzar la casa: ' . ($update['error'] ?? json_encode($update['data'])));
        }

        redirect('/editar_cases.php');
    } elseif ($action === 'create_case') {
        global $ACF_FIELD_KEYS;
        $caseKeys = $ACF_FIELD_KEYS['cases'] ?? [];

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $shortDescription = trim($_POST['short_description'] ?? '');
        $featuredUrl = trim($_POST['featured_url'] ?? '');

        if ($title === '' || $description === '') {
            flash('error', 'Cal omplir el títol i la descripció.');
            redirect('/editar_cases.php');
        }

        $normativaVal = '';
        if (!empty($_FILES['normativa']['tmp_name'])) {
            $upload = wp_upload_media('descoberta', $_FILES['normativa']);
            if ($upload['success'] && isset($upload['data']['id'])) {
                $normativaVal = (string)$upload['data']['id'];
            } else {
                flash('error', 'No s\'ha pogut pujar la normativa: ' . ($upload['error'] ?? 'Error desconegut'));
            }
        }

        $piscinaVal = normalize_yes_no($_POST['piscina'] ?? '');
        $wifiVal = normalize_yes_no($_POST['wifi'] ?? '');

        $metaPayload = [
            ['key' => $caseKeys['places'] ?? 'places', 'value' => (int)($_POST['places'] ?? 0)],
            ['key' => $caseKeys['regims_admessos'] ?? 'Regims_admessos', 'value' => trim($_POST['regims_admessos'] ?? '')],
            ['key' => $caseKeys['exclusivitat'] ?? 'exclusivitat', 'value' => (int)($_POST['exclusivitat'] ?? 0)],
            ['key' => $caseKeys['habitacions'] ?? 'habitacions', 'value' => trim($_POST['habitacions'] ?? '')],
            ['key' => $caseKeys['provincia'] ?? 'provincia', 'value' => trim($_POST['provincia'] ?? '')],
            ['key' => $caseKeys['comarca'] ?? 'comarca', 'value' => trim($_POST['comarca'] ?? '')],
            ['key' => $caseKeys['calefaccio'] ?? 'calefaccio', 'value' => trim($_POST['calefaccio'] ?? '')],
            ['key' => $caseKeys['sales_activitats'] ?? 'sales_activitats', 'value' => trim($_POST['sales_activitats'] ?? '')],
            ['key' => $caseKeys['exteriors'] ?? 'exteriors', 'value' => trim($_POST['exteriors'] ?? '')],
            ['key' => $caseKeys['piscina'] ?? 'piscina', 'value' => $piscinaVal],
            ['key' => $caseKeys['places_adaptades'] ?? 'places_adptades', 'value' => (int)($_POST['places_adaptades'] ?? 0)],
            ['key' => $caseKeys['wifi'] ?? 'wifi', 'value' => $wifiVal],
            ['key' => $caseKeys['normativa'] ?? 'normativa_de_la_casa', 'value' => $normativaVal],
            ['key' => $caseKeys['preus'] ?? 'preus', 'value' => trim($_POST['preus'] ?? '')],
            ['key' => $caseKeys['google_maps'] ?? 'google_maps', 'value' => trim($_POST['google_maps'] ?? '')],
        ];

        $payload = [
            'name' => $title,
            'status' => 'publish',
            'description' => $description,
            'short_description' => $shortDescription,
            'categories' => [],
            'meta_data' => $metaPayload,
        ];

        $imagesResult = prepare_case_images([], $featuredUrl, 0, '', []);
        if ($imagesResult['changed']) {
            $payload['images'] = $imagesResult['images'];
            if ($imagesResult['thumbnail_id']) {
                $payload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $imagesResult['thumbnail_id']];
            }
        }

        $catId = category_id('descoberta', 'cases-de-colonies');
        if ($catId) {
            $payload['categories'][] = ['id' => $catId];
        }

        $create = woo_create_product('descoberta', $payload);

        if ($create['success']) {
            $createdProduct = $create['data'] ?? [];
            $preuCatId = category_id('descoberta', 'preu');
            if ($preuCatId && !empty($createdProduct['id'])) {
                $baseSlug = $createdProduct['slug'] ?? '';
                $duplicatePayload = $payload;
                $duplicatePayload['categories'] = [['id' => $preuCatId]];
                if ($baseSlug !== '') {
                    $duplicatePayload['slug'] = $baseSlug . '-preus';
                }
                $duplicatePayload['meta_data'][] = ['key' => PREU_LINK_META_KEY, 'value' => (string)$createdProduct['id']];
                if (!empty($imagesResult['images'])) {
                    $duplicatePayload['images'] = $imagesResult['images'];
                }
                if ($imagesResult['thumbnail_id']) {
                    $duplicatePayload['meta_data'][] = ['key' => '_thumbnail_id', 'value' => $imagesResult['thumbnail_id']];
                }
                $duplicate = woo_create_product('descoberta', $duplicatePayload);
                if (!$duplicate['success']) {
                    flash('error', 'No s\'ha pogut crear la casa duplicada de preus: ' . ($duplicate['error'] ?? json_encode($duplicate['data'])));
                }
            }

            flash('success', 'Casa creada correctament');
        } else {
            flash('error', 'No s\'ha pogut crear la casa: ' . ($create['error'] ?? json_encode($create['data'])));
        }

        redirect('/editar_cases.php');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $selectedActivitats = array_map('intval', $_POST['activitats'] ?? []);
    $selectedCentres = array_map('intval', $_POST['centres'] ?? []);

    $currentCase = find_product_by_id($cases, $productId) ?? [];
    $previousActivitats = selected_items($currentCase, 'related_activitats', $currentCase['upsell_ids'] ?? []);
    $previousCentres = selected_items($currentCase, 'related_centres', $currentCase['cross_sell_ids'] ?? []);

    $payload = [
        'upsell_ids' => $selectedActivitats,
        'cross_sell_ids' => $selectedCentres,
        'meta_data' => [
            ['key' => 'related_activitats', 'value' => $selectedActivitats],
            ['key' => 'related_centres', 'value' => $selectedCentres],
        ],
    ];
    $update = woo_update_product('descoberta', $productId, $payload);

    if ($update['success']) {
        global $CASE_SPECIAL_MAPPING, $ACF_FIELD_KEYS;
        if (isset($CASE_SPECIAL_MAPPING[$productId])) {
            $siteKey = $CASE_SPECIAL_MAPPING[$productId];
            $sync = sync_related_content_to_site(
                $siteKey,
                $selectedActivitats,
                $selectedCentres,
                $previousActivitats,
                $previousCentres,
                $activitats,
                $centres,
                $ACF_FIELD_KEYS
            );

            if (!$sync['success']) {
                flash('error', 'No s\'ha pogut sincronitzar el contingut a ' . site_config($siteKey)['name'] . ': ' . ($sync['error'] ?? 'Error desconegut'));
            }
        }
        flash('success', 'Relacions desades');
    } else {
        flash('error', 'Error en actualitzar: ' . json_encode($update['data']));
    }
    redirect('/editar_cases.php');
}

global $ACF_FIELD_KEYS;
$caseKeys = $ACF_FIELD_KEYS['cases'] ?? [];
$placesRange = ['min' => 0, 'max' => 300];

$regimsOptions = [];
$provinciesOptions = [];
$comarquesOptions = [];

foreach ($cases as $case) {
    $regimsVal = (string)meta_value($case, $caseKeys['regims_admessos'] ?? '');
    foreach (regims_array($regimsVal) as $regim) {
        $regimsOptions[] = $regim;
    }

    $provincia = trim((string)meta_value($case, $caseKeys['provincia'] ?? ''));
    $comarca = trim((string)meta_value($case, $caseKeys['comarca'] ?? ''));

    if ($provincia !== '') {
        $provinciesOptions[] = $provincia;
    }
    if ($comarca !== '') {
        $comarquesOptions[] = $comarca;
    }
}

$regimsOptions = array_values(array_unique($regimsOptions));
sort($regimsOptions, SORT_NATURAL | SORT_FLAG_CASE);
$provinciesOptions = array_values(array_unique($provinciesOptions));
sort($provinciesOptions, SORT_NATURAL | SORT_FLAG_CASE);
$comarquesOptions = array_values(array_unique($comarquesOptions));
sort($comarquesOptions, SORT_NATURAL | SORT_FLAG_CASE);

$filters = [
    'places_min' => max($placesRange['min'], (int)($_GET['places_min'] ?? $placesRange['min'])),
    'places_max' => min($placesRange['max'], (int)($_GET['places_max'] ?? $placesRange['max'])),
    'exclusivitat_min' => max(0, (int)($_GET['exclusivitat_min'] ?? 0)),
    'exclusivitat_max' => min(300, (int)($_GET['exclusivitat_max'] ?? 300)),
    'regims' => array_filter($_GET['regims'] ?? [], fn($val) => $val !== ''),
    'provincia' => trim($_GET['provincia'] ?? ''),
    'comarca' => trim($_GET['comarca'] ?? ''),
    'piscina' => normalize_yes_no($_GET['piscina'] ?? ''),
    'wifi' => normalize_yes_no($_GET['wifi'] ?? ''),
];

if ($filters['places_min'] > $filters['places_max']) {
    $filters['places_min'] = $placesRange['min'];
    $filters['places_max'] = $placesRange['max'];
}
if ($filters['exclusivitat_min'] > $filters['exclusivitat_max']) {
    $filters['exclusivitat_min'] = 0;
    $filters['exclusivitat_max'] = 300;
}

$cases = array_values(array_filter($cases, function ($case) use ($filters, $caseKeys) {
    $places = (int)(meta_value($case, $caseKeys['places'] ?? '') ?? 0);
    $exclusivitat = (int)(meta_value($case, $caseKeys['exclusivitat'] ?? '') ?? 0);
    $regims = regims_array((string)meta_value($case, $caseKeys['regims_admessos'] ?? '') ?? '');
    $provincia = trim((string)meta_value($case, $caseKeys['provincia'] ?? ''));
    $comarca = trim((string)meta_value($case, $caseKeys['comarca'] ?? ''));
    $piscina = normalize_yes_no(meta_value($case, $caseKeys['piscina'] ?? ''));
    $wifi = normalize_yes_no(meta_value($case, $caseKeys['wifi'] ?? ''));

    if ($places < $filters['places_min'] || $places > $filters['places_max']) {
        return false;
    }

    if ($exclusivitat < $filters['exclusivitat_min'] || $exclusivitat > $filters['exclusivitat_max']) {
        return false;
    }

    if ($filters['regims']) {
        $regimsLower = array_map('strtolower', $regims);
        foreach ($filters['regims'] as $selected) {
            if (!in_array(strtolower($selected), $regimsLower, true)) {
                return false;
            }
        }
    }

    if ($filters['provincia'] !== '' && strcasecmp($filters['provincia'], $provincia) !== 0) {
        return false;
    }

    if ($filters['comarca'] !== '' && strcasecmp($filters['comarca'], $comarca) !== 0) {
        return false;
    }

    if ($filters['piscina'] !== '' && $filters['piscina'] !== $piscina) {
        return false;
    }

    if ($filters['wifi'] !== '' && $filters['wifi'] !== $wifi) {
        return false;
    }

    return true;
}));
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Editar cases</h1>
    <p class="subtitle">Assigna activitats de dia i centres d'interès</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="filters-card">
        <form method="GET" class="filters-grid">
            <div class="filter-block">
                <label class="filter-label">Cercar per nom</label>
                <input type="search" name="search" placeholder="Escriu el nom de la casa" data-table-search data-table-target="#cases-table">
            </div>
            <div class="filter-block range" data-range-filter>
                <div class="filter-header">
                    <label class="filter-label">Places</label>
                    <div class="range-values" data-range-display><?php echo htmlspecialchars($filters['places_min'] . ' - ' . $filters['places_max']); ?></div>
                </div>
                <div class="range-inputs">
                    <input type="range" name="places_min" min="<?php echo $placesRange['min']; ?>" max="<?php echo $placesRange['max']; ?>" value="<?php echo htmlspecialchars((string)$filters['places_min']); ?>" data-range-min>
                    <input type="range" name="places_max" min="<?php echo $placesRange['min']; ?>" max="<?php echo $placesRange['max']; ?>" value="<?php echo htmlspecialchars((string)$filters['places_max']); ?>" data-range-max>
                </div>
            </div>

            <div class="filter-block">
                <label class="filter-label">Règims admessos</label>
                <select name="regims[]" multiple>
                    <?php foreach ($regimsOptions as $regim): ?>
                        <option value="<?php echo htmlspecialchars($regim); ?>" <?php echo in_array($regim, $filters['regims'], true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($regim); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-block range" data-range-filter>
                <div class="filter-header">
                    <label class="filter-label">Exclusivitat</label>
                    <div class="range-values" data-range-display><?php echo htmlspecialchars($filters['exclusivitat_min'] . ' - ' . $filters['exclusivitat_max']); ?></div>
                </div>
                <div class="range-inputs">
                    <input type="range" name="exclusivitat_min" min="0" max="300" value="<?php echo htmlspecialchars((string)$filters['exclusivitat_min']); ?>" data-range-min>
                    <input type="range" name="exclusivitat_max" min="0" max="300" value="<?php echo htmlspecialchars((string)$filters['exclusivitat_max']); ?>" data-range-max>
                </div>
            </div>

            <div class="filter-block">
                <label class="filter-label">Província</label>
                <select name="provincia">
                    <option value="">Totes</option>
                    <?php foreach ($provinciesOptions as $provincia): ?>
                        <option value="<?php echo htmlspecialchars($provincia); ?>" <?php echo strcasecmp($filters['provincia'], $provincia) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($provincia); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-block">
                <label class="filter-label">Comarca</label>
                <select name="comarca">
                    <option value="">Totes</option>
                    <?php foreach ($comarquesOptions as $comarca): ?>
                        <option value="<?php echo htmlspecialchars($comarca); ?>" <?php echo strcasecmp($filters['comarca'], $comarca) === 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars($comarca); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-block">
                <label class="filter-label">Piscina</label>
                <select name="piscina">
                    <option value="" <?php echo $filters['piscina'] === '' ? 'selected' : ''; ?>>Totes</option>
                    <option value="Si" <?php echo $filters['piscina'] === 'Si' ? 'selected' : ''; ?>>Si</option>
                    <option value="No" <?php echo $filters['piscina'] === 'No' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>

            <div class="filter-block">
                <label class="filter-label">WIFI</label>
                <select name="wifi">
                    <option value="" <?php echo $filters['wifi'] === '' ? 'selected' : ''; ?>>Totes</option>
                    <option value="Si" <?php echo $filters['wifi'] === 'Si' ? 'selected' : ''; ?>>Si</option>
                    <option value="No" <?php echo $filters['wifi'] === 'No' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn">Aplicar filtres</button>
                <a class="btn ghost" href="/editar_cases.php">Restablir</a>
            </div>
        </form>
    </div>

    <div class="table-wrapper scrollable">
        <table class="styled-table" id="cases-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Activitats</th>
                    <th>Centres</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $case):
                    $highlight = isset($CASE_SPECIAL_MAPPING[$case['id']]);
                    $caseActivitats = selected_items($case, 'related_activitats', $case['upsell_ids'] ?? []);
                    $caseCentres = selected_items($case, 'related_centres', $case['cross_sell_ids'] ?? []);

                    $caseMeta = [
                        'places' => (int)meta_value($case, $caseKeys['places'] ?? ''),
                        'regims_admessos' => (string)meta_value($case, $caseKeys['regims_admessos'] ?? ''),
                        'exclusivitat' => (int)meta_value($case, $caseKeys['exclusivitat'] ?? ''),
                        'habitacions' => (string)meta_value($case, $caseKeys['habitacions'] ?? ''),
                        'provincia' => (string)meta_value($case, $caseKeys['provincia'] ?? ''),
                        'comarca' => (string)meta_value($case, $caseKeys['comarca'] ?? ''),
                        'calefaccio' => (string)meta_value($case, $caseKeys['calefaccio'] ?? ''),
                        'sales_activitats' => (string)meta_value($case, $caseKeys['sales_activitats'] ?? ''),
                        'exteriors' => (string)meta_value($case, $caseKeys['exteriors'] ?? ''),
                        'piscina' => normalize_yes_no(meta_value($case, $caseKeys['piscina'] ?? '')),
                        'places_adaptades' => (int)meta_value($case, $caseKeys['places_adaptades'] ?? ''),
                        'wifi' => normalize_yes_no(meta_value($case, $caseKeys['wifi'] ?? '')),
                        'normativa' => (string)meta_value($case, $caseKeys['normativa'] ?? ''),
                        'preus' => (string)meta_value($case, $caseKeys['preus'] ?? ''),
                        'google_maps' => (string)meta_value($case, $caseKeys['google_maps'] ?? ''),
                    ];

                    $preuDuplicate = find_product_by_meta_value($allProducts, PREU_LINK_META_KEY, (string)$case['id']);
                    $preuLink = '';
                    if ($preuDuplicate) {
                        $preuLink = $preuDuplicate['permalink'] ?? ($preuDuplicate['link'] ?? '');
                    }

                    $casePayload = [
                        'id' => $case['id'],
                        'name' => $case['name'] ?? '',
                        'slug' => $case['slug'] ?? '',
                        'description' => $case['description'] ?? '',
                        'short_description' => $case['short_description'] ?? '',
                        'meta' => $caseMeta,
                        'meta_data' => $case['meta_data'] ?? [],
                        'images' => $case['images'] ?? [],
                        'preu_link' => $preuLink,
                    ];
                ?>
                    <tr class="<?php echo $highlight ? 'highlight' : ''; ?>" data-search-value="<?php echo htmlspecialchars(strtolower($case['name'])); ?>">
                        <td><?php echo htmlspecialchars($case['name']); ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="product_action" value="update_relations">
                                <input type="hidden" name="product_id" value="<?php echo $case['id']; ?>">
                                <div class="selector-box">
                                    <?php foreach ($activitats as $act): ?>
                                        <label class="selector-item">
                                            <input type="checkbox" name="activitats[]" value="<?php echo $act['id']; ?>" <?php echo in_array($act['id'], $caseActivitats) ? 'checked' : ''; ?>>
                                            <span><?php echo htmlspecialchars($act['name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                        </td>
                        <td>
                                <div class="selector-box">
                                    <?php foreach ($centres as $centre): ?>
                                        <label class="selector-item">
                                            <input type="checkbox" name="centres[]" value="<?php echo $centre['id']; ?>" <?php echo in_array($centre['id'], $caseCentres) ? 'checked' : ''; ?>>
                                            <span><?php echo htmlspecialchars($centre['name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                        </td>
                        <td class="actions-cell">
                                <div class="actions-group">
                                    <button type="submit" class="btn small">Desar</button>
                                    <button type="button"
                                            class="btn secondary small"
                                            title="Editar"
                                            data-open="modalEditCase"
                                            data-edit-case='<?php echo json_encode($casePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                                        Editar
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal-overlay" id="modalEditCase">
        <div class="modal large">
            <div class="modal-header">
                <h2>Editar casa</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form class="form-card" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_action" value="edit_case">
                    <input type="hidden" name="product_id">
                    <input type="hidden" name="existing_normativa">
                    <input type="hidden" name="existing_image_id">
                    <input type="hidden" name="existing_image_src">
                    <input type="hidden" name="removed_gallery_images" value="[]">

                    <label>Títol <span class="required-asterisk">*</span></label>
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

                    <label>Descripció curta del producte</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció curta"></div>
                        <textarea name="short_description" class="rich" rows="3"></textarea>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Places</label>
                            <input type="number" name="places" min="0" step="1">
                        </div>
                        <div>
                            <label>Règims admessos</label>
                            <input type="text" name="regims_admessos" placeholder="Ex: -A-DE-MP-">
                            <p class="hint">Separar cada règim amb guions com a WooCommerce.</p>
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Exclusivitat a partir de</label>
                            <input type="number" name="exclusivitat" min="0" step="1">
                        </div>
                        <div>
                            <label>Habitacions</label>
                            <input type="text" name="habitacions">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Província</label>
                            <input type="text" name="provincia">
                        </div>
                        <div>
                            <label>Comarca</label>
                            <input type="text" name="comarca">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Calefacció</label>
                            <input type="text" name="calefaccio">
                        </div>
                        <div>
                            <label>Sales activitats</label>
                            <input type="text" name="sales_activitats">
                        </div>
                    </div>

                    <label>Exteriors</label>
                    <input type="text" name="exteriors">

                    <div class="two-columns">
                        <div>
                            <label>Piscina</label>
                            <select name="piscina">
                                <option value="">Selecciona...</option>
                                <option value="Si">Si</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div>
                            <label>Places adaptades (nº)</label>
                            <input type="number" name="places_adaptades" min="0" step="1">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>WIFI</label>
                            <select name="wifi">
                                <option value="">Selecciona...</option>
                                <option value="Si">Si</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div>
                            <label>Google Maps</label>
                            <input type="text" name="google_maps" placeholder="Enllaç o embed">
                        </div>
                    </div>

                    <label>Preus</label>
                    <p class="hint" data-preu-link>
                        Enllaç del preu:
                        <a href="#" target="_blank" rel="noopener noreferrer" data-preu-link-url></a>
                        <span data-preu-link-empty>No disponible</span>
                        <button type="button" class="btn secondary small" data-preu-link-generate>Generar</button>
                    </p>
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
                        <textarea name="preus" class="rich" rows="3"></textarea>
                    </div>

                    <label>Imatge destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O enganxa una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <label>Galeria d'imatges</label>
                    <div class="gallery-manager">
                        <div class="gallery-grid" data-gallery-grid></div>
                        <div>
                            <input type="file" name="gallery_files[]" accept="image/*" multiple>
                            <p class="hint">Pots seleccionar diverses imatges</p>
                        </div>
                    </div>

                    <label>Normativa de la casa</label>
                    <input type="file" name="normativa" accept="application/pdf,.doc,.docx,.png,.jpg,.jpeg">
                    <p class="hint" data-current-normativa>No hi ha cap fitxer pujat.</p>

                    <div class="actions-row">
                        <button type="submit" class="btn">Desar canvis</button>
                        <button type="button" class="btn ghost modal-close">Cancel·lar</button>
                    </div>
                </form>
                <form method="POST" data-preu-generate-form class="inline-form">
                    <input type="hidden" name="product_action" value="generate_preu_duplicate">
                    <input type="hidden" name="product_id" value="">
                </form>
            </div>
        </div>
    </div>

    <button class="fab" type="button" data-open="modalCreateCase">+</button>

    <div class="modal-overlay" id="modalCreateCase">
        <div class="modal large">
            <div class="modal-header">
                <h2>Crear casa</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form class="form-card" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_action" value="create_case">

                    <label>Títol <span class="required-asterisk">*</span></label>
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

                    <label>Descripció curta del producte</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció curta"></div>
                        <textarea name="short_description" class="rich" rows="3"></textarea>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Places</label>
                            <input type="number" name="places" min="0" step="1">
                        </div>
                        <div>
                            <label>Règims admessos</label>
                            <input type="text" name="regims_admessos" placeholder="Ex: -A-DE-MP-">
                            <p class="hint">Separar cada règim amb guions com a WooCommerce.</p>
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Exclusivitat a partir de</label>
                            <input type="number" name="exclusivitat" min="0" step="1">
                        </div>
                        <div>
                            <label>Habitacions</label>
                            <input type="text" name="habitacions">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Província</label>
                            <input type="text" name="provincia">
                        </div>
                        <div>
                            <label>Comarca</label>
                            <input type="text" name="comarca">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>Calefacció</label>
                            <input type="text" name="calefaccio">
                        </div>
                        <div>
                            <label>Sales activitats</label>
                            <input type="text" name="sales_activitats">
                        </div>
                    </div>

                    <label>Exteriors</label>
                    <input type="text" name="exteriors">

                    <div class="two-columns">
                        <div>
                            <label>Piscina</label>
                            <select name="piscina">
                                <option value="">Selecciona...</option>
                                <option value="Si">Si</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div>
                            <label>Places adaptades (nº)</label>
                            <input type="number" name="places_adaptades" min="0" step="1">
                        </div>
                    </div>

                    <div class="two-columns">
                        <div>
                            <label>WIFI</label>
                            <select name="wifi">
                                <option value="">Selecciona...</option>
                                <option value="Si">Si</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div>
                            <label>Google Maps</label>
                            <input type="text" name="google_maps" placeholder="Enllaç o embed">
                        </div>
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
                        <textarea name="preus" class="rich" rows="3"></textarea>
                    </div>

                    <label>Imatge destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O enganxa una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <label>Galeria d'imatges</label>
                    <input type="file" name="gallery_files[]" accept="image/*" multiple>
                    <p class="hint">Pots seleccionar diverses imatges</p>

                    <label>Normativa de la casa</label>
                    <input type="file" name="normativa" accept="application/pdf,.doc,.docx,.png,.jpg,.jpeg">

                    <div class="actions-row">
                        <button type="submit" class="btn">Crear casa</button>
                        <button type="button" class="btn ghost modal-close">Cancel·lar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.CASE_META_KEYS = <?php echo json_encode($caseKeys); ?>;
    </script>
</main>

</div>
</body>
</html>
