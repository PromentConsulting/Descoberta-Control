<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">

    <h1>Crear Nueva Entrada</h1>
    <p class="subtitle">Formulario no funcional inspirado en WordPress</p>

    <form class="form-card">

        <label>🔗 Sitio de destino</label>
        <select>
            <option>Descoberta</option>
            <option>Can Pere</option>
            <option>Cal Mata</option>
            <option>Can Foix</option>
            <option>El Ginebro</option>
        </select>

        <label>Título de la entrada</label>
        <input type="text" placeholder="Escribe un título atractivo...">

        <label>Contenido</label>
        <textarea rows="10" placeholder="Escribe aquí..."></textarea>

        <label>Imagen destacada (URL)</label>
        <input type="text" placeholder="https://ejemplo.com/imagen.jpg">

        <label>Metadatos SEO (JSON)</label>
        <textarea rows="3">{ "seo_title": "", "seo_description": "" }</textarea>

        <button class="btn large">Publicar Entrada</button>

    </form>

</main>

</div>
</body>
</html>
