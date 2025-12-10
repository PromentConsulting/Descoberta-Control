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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $selectedActivitats = array_map('intval', $_POST['activitats'] ?? []);
    $selectedCentres = array_map('intval', $_POST['centres'] ?? []);

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
            // obtener detalles del producto
            foreach ($cases as $c) {
                if ((int)$c['id'] === $productId) {
                    $sourceProduct = $c;
                    break;
                }
            }
            if (!empty($sourceProduct)) {
                $sync = sync_case_to_site($siteKey, $sourceProduct, ['url' => $ACF_FIELD_KEYS['url']]);
                if (!$sync['success']) {
                    flash('error', 'No s\'ha pogut sincronitzar la casa a ' . site_config($siteKey)['name'] . ': ' . ($sync['error'] ?? 'Error desconegut'));
                }
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
