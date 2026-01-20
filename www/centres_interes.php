<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$modalState = $_GET['modal'] ?? '';
$modalMessage = null;
if ($modalState === 'edit' && ($_GET['saved'] ?? '') === '1') {
    $modalMessage = 'Canvis desats correctament';
}
if ($modalState === 'create' && ($_GET['created'] ?? '') === '1') {
    $modalMessage = 'Centre d\'interès creat correctament';
}

$products = [];
$apiError = null;
$response = woo_all_products('descoberta');
if ($response['success']) {
    $products = filter_products_by_category($response['data'], 'centre-interes');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['product_action'] ?? '') === 'toggle_status') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $targetStatus = $_POST['target_status'] === 'publish' ? 'publish' : 'draft';
    $product = product_by_id($products, $productId);

    if (!$product) {
        flash('error', 'No s\'ha trobat el producte.');
        redirect('/centres_interes.php');
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

    redirect('/centres_interes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['product_action'] ?? '') === 'edit_product') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $product = product_by_id($products, $productId);

    if (!$product) {
        flash('error', 'No s\'ha trobat el producte.');
        redirect('/centres_interes.php');
    }

    $title = trim($_POST['title'] ?? ($product['name'] ?? ''));
    $description = trim($_POST['description'] ?? ($product['description'] ?? ''));
    $status = ($_POST['status'] ?? '') === 'publish' ? 'publish' : 'draft';
    $featuredUrl = trim($_POST['featured_url'] ?? '');
    $slug = normalize_slug_input($_POST['slug'] ?? ($product['slug'] ?? ''));

    if ($title === '' || $description === '') {
        flash('error', 'Cal omplir el títol i la descripció.');
        redirect('/centres_interes.php');
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

    $payload = [
        'name' => $title,
        'status' => $status,
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['centres']['competencies'], 'value' => trim($_POST['competencies'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['competencies']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['metodologia'], 'value' => trim($_POST['metodologia'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['metodologia']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['cicles'], 'value' => $cicles],
            ['key' => $ACF_FIELD_KEYS['centres']['categoria'], 'value' => $categoria],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_1'], 'value' => trim($_POST['titol_programa_1'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['titol_programa_1']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_1'], 'value' => trim($_POST['descripcio_programa_1'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['descripcio_programa_1']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_2'], 'value' => trim($_POST['titol_programa_2'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['titol_programa_2']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_2'], 'value' => trim($_POST['descripcio_programa_2'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['descripcio_programa_2']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_3'], 'value' => trim($_POST['titol_programa_3'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['titol_programa_3']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_3'], 'value' => trim($_POST['descripcio_programa_3'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['descripcio_programa_3']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_4'], 'value' => trim($_POST['titol_programa_4'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['titol_programa_4']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_4'], 'value' => trim($_POST['descripcio_programa_4'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['descripcio_programa_4']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['titol_programa_5'], 'value' => trim($_POST['titol_programa_5'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['titol_programa_5']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['descripcio_programa_5'], 'value' => trim($_POST['descripcio_programa_5'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['descripcio_programa_5']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['preus'], 'value' => trim($_POST['preus'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['preus']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['inclou'], 'value' => trim($_POST['inclou'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['inclou']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['altres_activitats'], 'value' => trim($_POST['altres_activitats'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['altres_activitats']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['cases_on_es_pot_fer'], 'value' => trim($_POST['cases_on_es_pot_fer'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['cases_on_es_pot_fer']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['altres_propostes'], 'value' => trim($_POST['altres_propostes'] ?? (string)(meta_value($product, $ACF_FIELD_KEYS['centres']['altres_propostes']) ?? ''))],
            ['key' => $ACF_FIELD_KEYS['centres']['logotip_ods'], 'value' => meta_value($product, $ACF_FIELD_KEYS['centres']['logotip_ods']) ?? ''],
        ],
    ];
    if ($slug !== '') {
        $payload['slug'] = $slug;
    }

    $catId = category_id('descoberta', 'centre-interes');
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

    if (!empty($_FILES['logotip_ods_file']['tmp_name'])) {
        $upload = wp_upload_media('descoberta', $_FILES['logotip_ods_file']);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $logotipKey = $ACF_FIELD_KEYS['centres']['logotip_ods'];
            $found = false;
            foreach ($payload['meta_data'] as &$meta) {
                if (($meta['key'] ?? '') === $logotipKey) {
                    $meta['value'] = $upload['data']['id'];
                    $found = true;
                    break;
                }
            }
            unset($meta);
            if (!$found) {
                $payload['meta_data'][] = ['key' => $logotipKey, 'value' => $upload['data']['id']];
            }
        } else {
            flash('error', 'No s\'ha pogut pujar el logotip ODS: ' . ($upload['error'] ?? 'error desconegut'));
        }
    }

    $update = woo_update_product('descoberta', $productId, $payload);

    if ($update['success']) {
        redirect('/centres_interes.php?modal=edit&product_id=' . $productId . '&saved=1');
    } else {
        flash('error', 'No s\'ha pogut actualitzar la fitxa: ' . ($update['error'] ?? json_encode($update['data'])));
    }

    redirect('/centres_interes.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['product_action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $featuredUrl = trim($_POST['featured_url'] ?? '');
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
    $payload = [
        'name' => $title,
        'status' => $status,
        'description' => $description,
        'categories' => [],
        'meta_data' => [
            ['key' => $ACF_FIELD_KEYS['centres']['competencies'], 'value' => trim($_POST['competencies'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['metodologia'], 'value' => trim($_POST['metodologia'] ?? '')],
            ['key' => $ACF_FIELD_KEYS['centres']['cicles'], 'value' => $cicles],
            ['key' => $ACF_FIELD_KEYS['centres']['categoria'], 'value' => $categoria],
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

    if ($title === '' || $description === '') {
        flash('error', 'Cal omplir el títol i la descripció.');
        redirect('/centres_interes.php');
    }

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

    if (!empty($_FILES['logotip_ods_file']['tmp_name'])) {
        $upload = wp_upload_media('descoberta', $_FILES['logotip_ods_file']);
        if ($upload['success'] && isset($upload['data']['id'])) {
            $payload['meta_data'][] = [
                'key' => $ACF_FIELD_KEYS['centres']['logotip_ods'],
                'value' => $upload['data']['id'],
            ];
        } else {
            flash('error', 'No s\'ha pogut pujar el logotip ODS: ' . ($upload['error'] ?? 'error desconegut'));
        }
    }

    $create = woo_create_product('descoberta', $payload);
    if ($create['success']) {
        redirect('/centres_interes.php?modal=create&created=1');
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

    <div class="table-wrapper scrollable">
        <table class="styled-table">
            <thead>
                <tr>
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
                    <tr>
                        <td data-col="title" data-sort-value="<?php echo htmlspecialchars(strtolower($product['name'] ?? '')); ?>"><?php echo htmlspecialchars($product['name'] ?? ''); ?></td>
                        <td data-col="status" data-sort-value="<?php echo htmlspecialchars($statusText); ?>"><?php echo htmlspecialchars($statusText); ?></td>
                        <td><?php if (!empty($product['images'][0]['src'])): ?><img class="thumb" src="<?php echo htmlspecialchars($product['images'][0]['src']); ?>" alt="thumb"><?php endif; ?></td>
                        <td data-col="updated" data-sort-value="<?php echo htmlspecialchars((string)$dateInfo['timestamp']); ?>"><?php echo htmlspecialchars($dateInfo['display']); ?></td>
                        <td class="actions-cell">
                            <button type="button"
                                    class="icon-btn primary"
                                    title="Editar"
                                    data-open="modalEditCentre"
                                    data-edit-centre="<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-centre-id="<?php echo htmlspecialchars((string)($product['id'] ?? '')); ?>">
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

    <div class="modal-overlay" id="modalEditCentre">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar centre d'interès</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body scrollable">
                <?php if ($modalState === 'edit' && $modalMessage): ?>
                    <div class="alert success"><?php echo htmlspecialchars($modalMessage); ?></div>
                <?php endif; ?>
                <form class="form-card" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_action" value="edit_product">
                    <input type="hidden" name="product_id">
                    <input type="hidden" name="status">
                    <input type="hidden" name="existing_image_id">
                    <input type="hidden" name="existing_image_src">

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
                        <div class="program-section">
                            <label>Títol programa <?php echo $i; ?></label>
                            <input type="text" name="titol_programa_<?php echo $i; ?>">

                            <label>Descripció programa <?php echo $i; ?></label>
                            <div class="rich-wrapper" data-rich-editor data-grid-enabled="true">
                                <div class="rich-toolbar">
                                    <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                    <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                    <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                    <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                    <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                    <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                                    <button type="button" data-grid-picker title="Inserir graella"><i class="fa fa-table"></i></button>
                                </div>
                                <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció programa <?php echo $i; ?>"></div>
                                <textarea name="descripcio_programa_<?php echo $i; ?>" class="rich" rows="3"></textarea>
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

                    <label>Logotip ODS</label>
                    <input type="file" name="logotip_ods_file" accept="image/*">

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancel·lar</button>
                        <button type="submit" class="btn">Desar canvis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <button class="fab" type="button" data-open="modalCentre">+</button>

    <div class="modal-overlay" id="modalCentre">
        <div class="modal">
            <div class="modal-header">
                <h2>Crear centre d'interès</h2>
                <button class="modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body scrollable">
                <?php if ($modalState === 'create' && $modalMessage): ?>
                    <div class="alert success"><?php echo htmlspecialchars($modalMessage); ?></div>
                <?php endif; ?>
                <form class="form-card" method="POST" enctype="multipart/form-data">
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
                        <div class="program-section">
                            <label>Títol programa <?php echo $i; ?></label>
                            <input type="text" name="titol_programa_<?php echo $i; ?>">

                            <label>Descripció programa <?php echo $i; ?></label>
                            <div class="rich-wrapper" data-rich-editor data-grid-enabled="true">
                                <div class="rich-toolbar">
                                    <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                                    <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                                    <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                                    <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                                    <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                                    <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
                                    <button type="button" data-grid-picker title="Inserir graella"><i class="fa fa-table"></i></button>
                                </div>
                                <div class="rich-editor" contenteditable="true" aria-label="Editor ric per Descripció programa <?php echo $i; ?>"></div>
                                <textarea name="descripcio_programa_<?php echo $i; ?>" class="rich" rows="3"></textarea>
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

                    <label>Logotip ODS</label>
                    <input type="file" name="logotip_ods_file" accept="image/*">

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
        window.CENTRE_META_KEYS = <?php echo json_encode($ACF_FIELD_KEYS['centres']); ?>;
    </script>
</main>

</div>
</body>
</html>
