<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$sites = ['descoberta', 'can-pere', 'cal-mata', 'can-foix', 'el-ginebro'];

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
            $slug = end($parts) ?: '';
        }
    }
    $slug = preg_replace('/[^\\pL\\pN]+/u', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') {
        return '';
    }
    $slug = function_exists('iconv')
        ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug
        : $slug;
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9-]+/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedSites = $_POST['sites'] ?? [];
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $featuredUrl = trim($_POST['featured_url'] ?? '');
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDescription = trim($_POST['seo_description'] ?? '');
    $slug = normalize_slug_input($_POST['slug'] ?? '');

    if (empty($selectedSites)) {
        flash('error', 'Tria almenys una web de destí.');
        redirect('/crear_blog.php');
    }

    foreach ($selectedSites as $siteKey) {
        $payload = [
            'title' => $title,
            'content' => $content,
            'status' => 'publish',
        ];
        if ($slug !== '') {
            $payload['slug'] = $slug;
        }

        $meta = [];
        if (!empty($_FILES['featured_file']['tmp_name'])) {
            $upload = wp_upload_media($siteKey, $_FILES['featured_file']);
            if ($upload['success'] && isset($upload['data']['id'])) {
                $payload['featured_media'] = $upload['data']['id'];
            } else {
                flash('error', 'No s\'ha pogut pujar la imatge a ' . site_config($siteKey)['name'] . ': ' . ($upload['error'] ?? 'error desconegut'));
                continue;
            }
        } elseif ($featuredUrl) {
            $meta['featured_url'] = $featuredUrl;
        }

        if ($seoTitle !== '') {
            $meta['_yoast_wpseo_title'] = $seoTitle;
        }
        if ($seoDescription !== '') {
            $meta['_yoast_wpseo_metadesc'] = $seoDescription;
        }

        if ($meta) {
            $payload['meta'] = $meta;
        }

        $result = wp_create_post($siteKey, $payload);
        if ($result['success']) {
            flash('success', 'Entrada creada a ' . site_config($siteKey)['name']);
        } else {
            $errorMessage = $result['error'] ?? json_encode($result['data']);
            flash('error', 'Error a ' . site_config($siteKey)['name'] . ': ' . $errorMessage);
        }
    }
    redirect('/crear_blog.php');
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Crear nova entrada</h1>
    <p class="subtitle">Publica simultàniament en una o diverses webs</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <form class="form-card" method="POST" enctype="multipart/form-data">
        <label>Webs de destí</label>
        <div class="checkbox-list horizontal">
            <?php foreach ($sites as $siteKey): $conf = site_config($siteKey); ?>
                <label><input type="checkbox" name="sites[]" value="<?php echo $siteKey; ?>"> <?php echo htmlspecialchars($conf['name']); ?></label>
            <?php endforeach; ?>
        </div>

        <label>Títol de l\'entrada</label>
        <input type="text" name="title" required>

        <label>URL de l\'entrada</label>
        <input type="text" name="slug" placeholder="exemple-url">

        <label>Contingut</label>
        <div class="rich-wrapper" data-rich-editor>
            <div class="rich-toolbar">
                <button type="button" data-command="bold" title="Negreta"><i class="fa fa-bold"></i></button>
                <button type="button" data-command="italic" title="Cursiva"><i class="fa fa-italic"></i></button>
                <button type="button" data-command="underline" title="Subratllat"><i class="fa fa-underline"></i></button>
                <button type="button" data-command="createLink" title="Enllaç"><i class="fa fa-link"></i></button>
                <button type="button" data-command="foreColor" data-value="#4f46e5" title="Color destacat"><i class="fa fa-palette"></i></button>
                <button type="button" data-command="insertUnorderedList" title="Llista"><i class="fa fa-list-ul"></i></button>
            </div>
            <div class="rich-editor" contenteditable="true" data-target="content" aria-label="Editor ric"></div>
            <textarea name="content" class="rich" rows="10" required placeholder="Escriu aquí..."></textarea>
        </div>

        <label>Imatge destacada</label>
        <input type="file" name="featured_file" accept="image/*">
        <p class="hint">O enganxa una URL directa</p>
        <input type="url" name="featured_url" placeholder="https://...">

        <label>Títol SEO</label>
        <input type="text" name="seo_title" placeholder="Títol per al SEO">

        <label>Descripció SEO</label>
        <textarea name="seo_description" rows="3" placeholder="Descripció per al SEO"></textarea>

        <button class="btn large" type="submit">Publicar entrada</button>
    </form>
</main>

</div>
</body>
</html>
