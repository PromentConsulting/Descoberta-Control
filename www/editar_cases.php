<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();

$casesResponse = woo_products('descoberta');
$activitatsResponse = woo_products('descoberta');
$centresResponse = woo_products('descoberta');

$cases = $casesResponse['success'] ? filter_products_by_category($casesResponse['data'], 'cases-de-colonies') : [];
$activitats = $activitatsResponse['success'] ? filter_products_by_category($activitatsResponse['data'], 'activitat-de-dia') : [];
$centres = $centresResponse['success'] ? filter_products_by_category($centresResponse['data'], 'centre-interes') : [];

function meta_value(array $product, string $key) {
    foreach ($product['meta_data'] ?? [] as $meta) {
        if (($meta['key'] ?? '') === $key) {
            return $meta['value'];
        }
    }
    return null;
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
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Editar cases</h1>
    <p class="subtitle">Assigna activitats de dia i centres d'interès</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Activitats</th>
                    <th>Centres</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $case): $highlight = isset($CASE_SPECIAL_MAPPING[$case['id']]);
                    $caseActivitats = selected_items($case, 'related_activitats', $case['upsell_ids'] ?? []);
                    $caseCentres = selected_items($case, 'related_centres', $case['cross_sell_ids'] ?? []);
                ?>
                    <tr class="<?php echo $highlight ? 'highlight' : ''; ?>">
                        <td><?php echo htmlspecialchars($case['name']); ?></td>
                        <td>
                            <form method="POST" class="inline-form">
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
                        <td>
                                <button type="submit" class="btn small">Desar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

</div>
</body>
</html>
