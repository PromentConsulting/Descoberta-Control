<?php include "templates/header.php"; ?>
<?php include "templates/sidebar.php"; ?>

<main class="content fade-in">

    <h1>Editar Cases</h1>
    <p class="subtitle">Asigna activitats de dia i centres d'interès a cada casa (demo no funcional)</p>

    <table class="styled-table">
        <thead>
        <tr>
            <th>Nombre</th>
            <th>URL</th>
            <th>Activitats de dia</th>
            <th>Centres d'interès</th>
            <th>Editar</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Can Pere</td>
            <td>http://canpere.vl26404.dinaserver.com/</td>
            <td>—</td>
            <td>—</td>
            <td>
                <button
                    class="btn small edit-btn"
                    data-name="Can Pere"
                    data-url="http://canpere.vl26404.dinaserver.com/"
                >
                    Editar
                </button>
            </td>
        </tr>
        <tr>
            <td>Cal Mata</td>
            <td>https://www.calmata.cat/</td>
            <td>—</td>
            <td>—</td>
            <td>
                <button
                    class="btn small edit-btn"
                    data-name="Cal Mata"
                    data-url="https://www.calmata.cat/"
                >
                    Editar
                </button>
            </td>
        </tr>
        <tr>
            <td>Can Foix</td>
            <td>http://canfoixdescoberta.vl26404.dinaserver.com/</td>
            <td>—</td>
            <td>—</td>
            <td>
                <button
                    class="btn small edit-btn"
                    data-name="Can Foix"
                    data-url="http://canfoixdescoberta.vl26404.dinaserver.com/"
                >
                    Editar
                </button>
            </td>
        </tr>
        <tr>
            <td>El Ginebro</td>
            <td>http://elginebro.vl26404.dinaserver.com/</td>
            <td>—</td>
            <td>—</td>
            <td>
                <button
                    class="btn small edit-btn"
                    data-name="El Ginebro"
                    data-url="http://elginebro.vl26404.dinaserver.com/"
                >
                    Editar
                </button>
            </td>
        </tr>
        </tbody>
    </table>

    <!-- MODAL POPUP EDICIÓN CASA (DEMO) -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Editar casa</h2>
                <button class="modal-close" type="button" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-body">
                <form id="editCasaForm" class="form-card">

                    <label for="modalNombre">Nombre</label>
                    <input type="text" id="modalNombre" name="nombre" />

                    <label for="modalDescripcion">Descripción</label>
                    <textarea id="modalDescripcion" name="descripcion" rows="3"
                              placeholder="Breve descripción de la casa..."></textarea>

                    <div class="modal-columns">
                        <!-- Panel Activitats de dia -->
                        <div class="modal-panel">
                            <h3>Activitats de dia</h3>
                            <div class="checkbox-list">
                                <label><input type="checkbox" name="activitats[]" value="Excursió al bosc"> Excursió al bosc</label>
                                <label><input type="checkbox" name="activitats[]" value="Taller de cuina"> Taller de cuina</label>
                                <label><input type="checkbox" name="activitats[]" value="Apicultura"> Apicultura</label>
                                <label><input type="checkbox" name="activitats[]" value="Natura i biodiversitat"> Natura i biodiversitat</label>
                                <label><input type="checkbox" name="activitats[]" value="Jocs cooperatius"> Jocs cooperatius</label>
                                <label><input type="checkbox" name="activitats[]" value="Granja i vida al camp"> Granja i vida al camp</label>
                                <label><input type="checkbox" name="activitats[]" value="Contes i llegendes"> Contes i llegendes</label>
                                <label><input type="checkbox" name="activitats[]" value="Mar i litoral"> Mar i litoral</label>
                            </div>
                        </div>

                        <!-- Panel Centres d'interès -->
                        <div class="modal-panel">
                            <h3>Centres d'interès</h3>
                            <div class="checkbox-list">
                                <label><input type="checkbox" name="centres[]" value="Infantil"> Infantil</label>
                                <label><input type="checkbox" name="centres[]" value="Cicle Inicial"> Cicle Inicial</label>
                                <label><input type="checkbox" name="centres[]" value="Cicle Mitjà"> Cicle Mitjà</label>
                                <label><input type="checkbox" name="centres[]" value="Cicle Superior"> Cicle Superior</label>
                                <label><input type="checkbox" name="centres[]" value="Educació infantil"> Educació infantil</label>
                                <label><input type="checkbox" name="centres[]" value="ESO"> ESO</label>
                                <label><input type="checkbox" name="centres[]" value="Batxillerat"> Batxillerat</label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn secondary modal-close">Cancelar</button>
                        <button type="button" class="btn">Guardar (demo)</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</main>

</div>
</body>
</html>
