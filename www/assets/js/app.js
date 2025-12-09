document.addEventListener("DOMContentLoaded", () => {
    console.log("UI Loaded (modo demo, no funcional)");
});
document.addEventListener("DOMContentLoaded", () => {
    console.log("UI Loaded (modo demo)");

    const modal = document.getElementById("editModal");
    const nombreInput = document.getElementById("modalNombre");
    const descripcionInput = document.getElementById("modalDescripcion");

    const openModal = (nombre) => {
        if (nombreInput) nombreInput.value = nombre || "";
        if (descripcionInput) descripcionInput.value = "";
        if (modal) modal.classList.add("open");
        document.body.style.overflow = "hidden";
    };

    const closeModal = () => {
        if (modal) modal.classList.remove("open");
        document.body.style.overflow = "";
    };

    // Botones "Editar" de la tabla
    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            const nombre = btn.dataset.name || "";
            openModal(nombre);
        });
    });

    // Botones de cerrar dentro del modal
    document.querySelectorAll(".modal-close").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            closeModal();
        });
    });

    // Cerrar al clicar fuera de la caja
    if (modal) {
        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
});
