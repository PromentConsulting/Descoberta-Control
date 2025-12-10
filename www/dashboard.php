<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$cards = [
    ['key' => 'descoberta', 'title' => 'DESCOBERTA', 'url' => 'http://descoberta.vl26404.dinaserver.com/', 'highlight' => true],
    ['key' => 'can-pere', 'title' => 'Can Pere', 'url' => 'http://canpere.vl26404.dinaserver.com/'],
    ['key' => 'cal-mata', 'title' => 'Cal Mata', 'url' => 'https://www.calmata.cat/'],
    ['key' => 'can-foix', 'title' => 'Can Foix', 'url' => 'http://canfoixdescoberta.vl26404.dinaserver.com/'],
    ['key' => 'el-ginebro', 'title' => 'El Ginebro', 'url' => 'http://elginebro.vl26404.dinaserver.com/'],
];
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Tauler Descoberta</h1>
    <p class="subtitle">Gestió centralitzada de blogs i activitats</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="cards-grid">
        <?php foreach ($cards as $card): ?>
            <div class="card <?php echo $card['highlight'] ?? false ? 'principal' : ''; ?>">
                <h2><?php echo htmlspecialchars($card['title']); ?></h2>
                <p><a href="<?php echo htmlspecialchars($card['url']); ?>" target="_blank">Visitar web</a></p>
                <div class="btn-group">
                    <a class="btn" href="crear_blog.php">Crear blog</a>
                    <?php if (($card['highlight'] ?? false)): ?>
                        <a class="btn secondary" href="editar_cases.php">Editar cases</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

</div>
</body>
</html>
