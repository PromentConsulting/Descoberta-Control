<?php $user = current_user(); ?>
<aside class="sidebar">
    <div class="logo">
        <img src="/assets/img/logo.png" alt="Logo">
    </div>

    <nav>
        <a class="<?php echo view_active('dashboard.php'); ?>" href="/dashboard.php"><i class="fa fa-home"></i> Tauler</a>
        <a class="<?php echo view_active('activitats_dia.php'); ?>" href="/activitats_dia.php"><i class="fa fa-sun"></i> Activitats de dia</a>
        <a class="<?php echo view_active('centres_interes.php'); ?>" href="/centres_interes.php"><i class="fa fa-star"></i> Centres d'interès</a>
        <a class="<?php echo view_active('credits_sintesi.php'); ?>" href="/credits_sintesi.php"><i class="fa fa-book"></i> Crèdits de síntesi</a>
        <a class="<?php echo view_active('propostes_aula.php'); ?>" href="/propostes_aula.php"><i class="fa fa-chalkboard-teacher"></i> Propostes a l'aula</a>
        <a class="<?php echo view_active('estades_final_curs.php'); ?>" href="/estades_final_curs.php"><i class="fa fa-flag"></i> Estades de final de curs</a>
        <a class="<?php echo view_active('colonies_afa.php'); ?>" href="/colonies_afa.php"><i class="fa fa-campground"></i> Colònies per afa</a>
        <a class="<?php echo view_active('crear_blog.php'); ?>" href="/crear_blog.php"><i class="fa fa-pen"></i> Crear blogs</a>
        <?php if ($user && $user['role'] === 'admin'): ?>
            <a class="<?php echo view_active('ajustes.php'); ?>" href="/ajustes.php"><i class="fa fa-cog"></i> Configuració</a>
        <?php endif; ?>
        <a href="/logout.php"><i class="fa fa-door-open"></i> Sortir</a>
    </nav>
</aside>
