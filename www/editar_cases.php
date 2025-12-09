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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $selectedActivitats = array_map('intval', $_POST['activitats'] ?? []);
    $selectedCentres = array_map('intval', $_POST['centres'] ?? []);

    $payload = [
        'related_ids' => array_merge($selectedActivitats, $selectedCentres),
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
                sync_case_to_site($siteKey, $sourceProduct, ['url' => $ACF_FIELD_KEYS['url']]);
            }
        }
        flash('success', 'Relaciones guardadas');
    } else {
        flash('error', 'Error al actualizar: ' . json_encode($update['data']));
    }
    redirect('/editar_cases.php');
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Editar Cases</h1>
    <p class="subtitle">Asigna activitats de dia i centres d'interès</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Activitats</th>
                    <th>Centres</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $case): $highlight = isset($CASE_SPECIAL_MAPPING[$case['id']]); ?>
                    <tr class="<?php echo $highlight ? 'highlight' : ''; ?>">
                        <td><?php echo htmlspecialchars($case['name']); ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="product_id" value="<?php echo $case['id']; ?>">
                                <select name="activitats[]" multiple size="5">
                                    <?php foreach ($activitats as $act): ?>
                                        <option value="<?php echo $act['id']; ?>" <?php echo in_array($act['id'], $case['related_ids'] ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($act['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                        </td>
                        <td>
                                <select name="centres[]" multiple size="5">
                                    <?php foreach ($centres as $centre): ?>
                                        <option value="<?php echo $centre['id']; ?>" <?php echo in_array($centre['id'], $case['related_ids'] ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($centre['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                        </td>
                        <td>
                                <button type="submit" class="btn small">Guardar</button>
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
