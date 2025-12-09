<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();

$products = [];
$apiError = null;
$response = woo_products('descoberta');
if ($response['success']) {
    $products = filter_products_by_category($response['data'], 'activitat-de-dia');
} else {
    $apiError = $response['error'] ?? 'No se pudo conectar con la API de Descoberta';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cicles = $_POST['cicles'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $continguts = trim($_POST['continguts'] ?? '');
    $programa = trim($_POST['programa'] ?? '');
    $preus = trim($_POST['preus'] ?? '');
    $inclou = trim($_POST['inclou'] ?? '');
    $featuredUrl = trim($_POST['featured_url'] ?? '');

    $payload = [
        'name' => $title,
        'status' => 'publish',
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['activitats']['cicles'], 'value' => $cicles],
            ['key' => $ACF_FIELD_KEYS['activitats']['categoria'], 'value' => $categoria],
            ['key' => $ACF_FIELD_KEYS['activitats']['continguts'], 'value' => $continguts],
            ['key' => $ACF_FIELD_KEYS['activitats']['programa'], 'value' => $programa],
            ['key' => $ACF_FIELD_KEYS['activitats']['preus'], 'value' => $preus],
            ['key' => $ACF_FIELD_KEYS['activitats']['inclou'], 'value' => $inclou],
        ],
    ];

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
            flash('error', 'No se pudo subir la imagen: ' . ($upload['error'] ?? 'error desconocido'));
        }
    } elseif ($featuredUrl) {
        $payload['images'] = [['src' => $featuredUrl]];
    }

    $create = woo_create_product('descoberta', $payload);
    if ($create['success']) {
        flash('success', 'Activitat creada correctamente');
        redirect('/activitats_dia.php');
    } else {
        flash('error', 'Error al crear: ' . json_encode($create['data']));
        redirect('/activitats_dia.php');
    }
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Activitats de Dia</h1>
    <p class="subtitle">Productos de WooCommerce con categoría activitat-de-dia</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <?php if ($apiError): ?>
        <div class="alert error"><?php echo htmlspecialchars($apiError); ?></div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Imagen</th>
                    <th>Actualizado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($product['status'] ?? ''); ?></td>
                        <td><?php if (!empty($product['images'][0]['src'])): ?><img class="thumb" src="<?php echo htmlspecialchars($product['images'][0]['src']); ?>" alt="thumb"><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($product['date_modified'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button class="fab" type="button" data-open="modalActivitat">+</button>

    <div class="modal-overlay" id="modalActivitat">
        <div class="modal">
            <div class="modal-header">
                <h2>Crear Activitat de dia</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body">
                <form class="form-card" method="POST" enctype="multipart/form-data">
                    <label>Título del producto</label>
                    <input type="text" name="title" required>

                    <label>Descripción</label>
                    <textarea name="description" rows="4" required></textarea>

                    <div class="two-columns">
                        <div>
                            <label>Cicles</label>
                            <select name="cicles" required>
                                <?php foreach ($CICLES_OPTIONS as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Categoria</label>
                            <select name="categoria" required>
                                <?php foreach ($CATEGORIES_OPTIONS as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label>Continguts</label>
                    <input type="text" name="continguts">

                    <label>Programa</label>
                    <input type="text" name="programa">

                    <label>Preus</label>
                    <input type="text" name="preus">

                    <label>Inclou</label>
                    <input type="text" name="inclou">

                    <label>Imagen destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O pega una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancelar</button>
                        <button type="submit" class="btn">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

</div>
</body>
</html>
