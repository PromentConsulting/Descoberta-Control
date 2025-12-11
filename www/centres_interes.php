<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();

$products = [];
$apiError = null;
$response = woo_products('descoberta');
if ($response['success']) {
    $products = filter_products_by_category($response['data'], 'centre-interes');
} else {
    $apiError = $response['error'] ?? 'No s\'ha pogut connectar amb la API de Descoberta';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $featuredUrl = trim($_POST['featured_url'] ?? '');
    $payload = [
        'name' => $title,
        'status' => 'publish',
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['centres']['competencies'], 'value' => trim($_POST['competencies'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['metodologia'], 'value' => trim($_POST['metodologia'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_1'], 'value' => trim($_POST['titol_programa_1'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_1'], 'value' => trim($_POST['descripcio_programa_1'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_2'], 'value' => trim($_POST['titol_programa_2'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_2'], 'value' => trim($_POST['descripcio_programa_2'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_3'], 'value' => trim($_POST['titol_programa_3'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_3'], 'value' => trim($_POST['descripcio_programa_3'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_4'], 'value' => trim($_POST['titol_programa_4'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_4'], 'value' => trim($_POST['descripcio_programa_4'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_5'], 'value' => trim($_POST['titol_programa_5'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_5'], 'value' => trim($_POST['descripcio_programa_5'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['preus'], 'value' => trim($_POST['preus'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['inclou'], 'value' => trim($_POST['inclou'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['altres_activitats'], 'value' => trim($_POST['altres_activitats'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['cases_on_es_pot_fer'], 'value' => trim($_POST['cases_on_es_pot_fer'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['altres_propostes'], 'value' => trim($_POST['altres_propostes'] ?? '')],
        ],
    ];

    $catId = category_id('descoberta', 'centre-interes');
    if ($catId) {
        $payload['categories'][] = ['id' => $catId];
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
    }

    $create = woo_create_product('descoberta', $payload);
    if ($create['success']) {
        flash('success', 'Centre d\'interès creat');
        redirect('/centres_interes.php');
    } else {
        flash('error', 'Error en crear la fitxa: ' . json_encode($create['data']));
        redirect('/centres_interes.php');
    }
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Centres d'interès</h1>

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
                    <th>Títol</th>
                    <th>Estat</th>
                    <th>Imatge</th>
                    <th>Actualitzat</th>
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

    <button class="fab" type="button" data-open="modalCentre">+</button>

    <div class="modal-overlay" id="modalCentre">
        <div class="modal">
            <div class="modal-header">
                <h2>Crear centre d'interès</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body scrollable">
                <form class="form-card" method="POST" enctype="multipart/form-data">
                    <label>Títol del producte</label>
                    <input type="text" name="title" required>

                    <label>Descripció</label>
                    <textarea name="description" rows="4" required></textarea>

                    <label>Competències</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Competències"></div>
                        <textarea name="competencies" class="rich" rows="4"></textarea>
                    </div>
                    <label>Metodologia</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Metodologia"></div>
                        <textarea name="metodologia" class="rich" rows="4"></textarea>
                    </div>

                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="two-columns">
                            <div>
                                <label>Títol programa <?php echo $i; ?></label>
                                <input type="text" name="titol_programa_<?php echo $i; ?>">
                            </div>
                            <div>
                                <label>Descripció programa <?php echo $i; ?></label>
                                <div class="rich-wrapper" data-rich-editor>
                                    <div class="rich-toolbar">
                                        <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                        <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                        <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                        <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                        <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                        <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                                    </div>
                                    <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció programa <?php echo $i; ?>"></div>
                                    <textarea name="descripcio_programa_<?php echo $i; ?>" class="rich" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>

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
                    <label>Altres activitats</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Altres activitats"></div>
                        <textarea name="altres_activitats" class="rich" rows="4"></textarea>
                    </div>
                    <label>Cases on es pot fer el centre d'interès</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Cases on es pot fer el centre d'interès"></div>
                        <textarea name="cases_on_es_pot_fer" class="rich" rows="4"></textarea>
                    </div>
                    <label>Altres propostes semblants</label>
                    <div class="rich-wrapper" data-rich-editor>
                        <div class="rich-toolbar">
                            <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                            <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                            <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                            <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                            <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                            <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                        </div>
                        <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Altres propostes semblants"></div>
                        <textarea name="altres_propostes" class="rich" rows="4"></textarea>
                    </div>

                    <label>Imatge destacada</label>
                    <input type="file" name="featured_file" accept="image/*">
                    <p class="hint">O enganxa una URL directa</p>
                    <input type="url" name="featured_url" placeholder="https://...">

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancel·lar</button>
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
