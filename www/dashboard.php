<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$messages = flash();
$cards = [
    ['key' => 'descoberta', 'title' => 'DESCOBERTA', 'url' => 'https://descobertaweb.promentconsulting.com/', 'highlight' => true],
    ['key' => 'can-pere', 'title' => 'Can Pere', 'url' => 'https://canperedescoberta.promentconsulting.com/'],
    ['key' => 'cal-mata', 'title' => 'Cal Mata', 'url' => 'https://escolesdescoberta.promentconsulting.com/'],
    ['key' => 'can-foix', 'title' => 'Can Foix', 'url' => 'https://canfoixdescoberta.promentconsulting.com/'],
    ['key' => 'el-ginebro', 'title' => 'El Ginebro', 'url' => 'https://elginebrodescoberta.promentconsulting.com/'],
];

function site_stats(string $siteKey): array {
    $response = woo_products($siteKey);
    if (!$response['success']) {
        return ['activitats' => 0, 'centres' => 0, 'error' => $response['error'] ?? 'No s\'ha pogut carregar la informació'];
    }

    $products = $response['data'] ?? [];
    $activitats = array_filter(
        filter_products_by_category($products, 'activitat-de-dia'),
        function ($p) {
            return ($p['status'] ?? '') === 'publish';
        }
    );
    $centres = array_filter(
        filter_products_by_category($products, 'centre-interes'),
        function ($p) {
            return ($p['status'] ?? '') === 'publish';
        }
    );

    return [
        'activitats' => count($activitats),
        'centres' => count($centres),
        'error' => null,
    ];
}

foreach ($cards as &$card) {
    $card['stats'] = site_stats($card['key']);
}
unset($card);
?>
<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">
    <h1>Tauler Descoberta</h1>
    <p class="subtitle">Gestió centralitzada de blogs i activitats</p>

    <?php foreach ($messages as $msg): ?>
        <div class="alert <?php echo $msg['type']; ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
    <?php endforeach; ?>

    <div class="cards-grid dashboard-cards">
        <?php foreach ($cards as $card): ?>
            <div class="card <?php echo ($card['highlight'] ?? false) ? 'principal card-main' : 'card-secondary'; ?>">
                <div class="card-header-row">
                    <div>
                        <p class="card-eyebrow"><?php echo strtoupper($card['key']); ?></p>
                        <h2><?php echo htmlspecialchars($card['title']); ?></h2>
                        <p class="card-url"><a href="<?php echo htmlspecialchars($card['url']); ?>" target="_blank">Visitar web</a></p>
                    </div>
                    <?php if (($card['highlight'] ?? false)): ?>
                        <span class="badge badge-primary">Principal</span>
                    <?php endif; ?>
                </div>
                <div class="card-stats-grid">
                    <div class="stat-chip">
                        <span class="stat-label">Activitats de dia</span>
                        <strong class="stat-value"><?php echo (int)($card['stats']['activitats'] ?? 0); ?></strong>
                        <small>publicades</small>
                    </div>
                    <div class="stat-chip">
                        <span class="stat-label">Centres d'interès</span>
                        <strong class="stat-value"><?php echo (int)($card['stats']['centres'] ?? 0); ?></strong>
                        <small>publicats</small>
                    </div>
                </div>
                <?php if (!empty($card['stats']['error'])): ?>
                    <p class="card-error"><?php echo htmlspecialchars($card['stats']['error']); ?></p>
                <?php endif; ?>
                <?php if (($card['highlight'] ?? false)): ?>
                    <div class="btn-group card-actions">
                        <a class="btn secondary" href="editar_cases.php">Editar cases</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</main>

</div>
</body>
</html>