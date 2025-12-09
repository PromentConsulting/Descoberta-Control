<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">

    <h1>Panel Descoberta</h1>
    <p class="subtitle">Gestión centralizada de blogs y actividades</p>

    <div class="cards-grid">

        <!-- TARJETA PRINCIPAL DESTACADA -->
        <div class="card principal">
            <h2>DESCOBERTA</h2>
            <p><a href="http://descoberta.vl26404.dinaserver.com/" target="_blank">Visitar Web</a></p>

            <div class="btn-group">
                <a class="btn" href="crear_blog.php">Crear Blog</a>
                <a class="btn secondary" href="editar_cases.php">Editar Cases</a>
            </div>
        </div>

        <!-- OTRAS 4 TARJETAS -->
        <?php
        $centres = [
            ["Can Pere", "http://canpere.vl26404.dinaserver.com/"],
            ["Cal Mata", "https://www.calmata.cat/"],
            ["Can Foix", "http://canfoixdescoberta.vl26404.dinaserver.com/"],
            ["El Ginebro", "http://elginebro.vl26404.dinaserver.com/"]
        ];

        foreach ($centres as $c) {
            echo "
            <div class='card'>
                <h2>{$c[0]}</h2>
                <p><a href='{$c[1]}' target='_blank'>Visitar Web</a></p>
                <div class='btn-group'>
                    <a class='btn' href='crear_blog.php'>Crear Blog</a>
                </div>
            </div>";
        }
        ?>

    </div>
</main>

</div>
</body>
</html>
