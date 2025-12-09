<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$sites = ['descoberta', 'can-pere', 'cal-mata', 'can-foix', 'el-ginebro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedSites = $_POST['sites'] ?? [];
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $featuredUrl = trim($_POST['featured_url'] ?? '');

    foreach ($selectedSites as $siteKey) {
        $payload = [
            'title' => $title,
            'content' => $content,
            'status' => 'publish',
        ];

        if (!empty($_FILES['featured_file']['tmp_name'])) {
            $upload = wp_upload_media($siteKey, $_FILES['featured_file']);
            if ($upload['success'] && isset($upload['data']['id'])) {
                $payload['featured_media'] = $upload['data']['id'];
            }
        } elseif ($featuredUrl) {
            // subir desde url como imagen en contenido
            $payload['meta'] = ['featured_url' => $featuredUrl];
        }

        $result = wp_create_post($siteKey, $payload);
        if ($result['success']) {
            flash('success', 'Entrada creada en ' . site_config($siteKey)['name']);
        } else {
            flash('error', 'Error en ' . site_config($siteKey)['name'] . ': ' . json_encode($result['data']));
        }
    }
    redirect('/crear_blog.php');
}
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Crear Nueva Entrada</h1>
    <p class="subtitle">Publica simultáneamente en una o varias webs</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <form class="form-card" method="POST" enctype="multipart/form-data">
        <label>Webs de destino</label>
        <div class="checkbox-list horizontal">
            <?php foreach ($sites as $siteKey): $conf = site_config($siteKey); ?>
                <label><input type="checkbox" name="sites[]" value="<?php echo $siteKey; ?>"> <?php echo htmlspecialchars($conf['name']); ?></label>
            <?php endforeach; ?>
        </div>

        <label>Título de la entrada</label>
        <input type="text" name="title" required>

        <label>Contenido</label>
        <textarea name="content" class="rich" rows="10" required placeholder="Escribe aquí..."></textarea>

        <label>Imagen destacada</label>
        <input type="file" name="featured_file" accept="image/*">
        <p class="hint">O pega una URL directa</p>
        <input type="url" name="featured_url" placeholder="https://...">

        <button class="btn large" type="submit">Publicar Entrada</button>
    </form>
</main>

</div>
</body>
</html>
