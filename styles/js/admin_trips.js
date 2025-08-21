
document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.action-btn.edit');
    const deleteButtons = document.querySelectorAll('.action-btn.delete');
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    const closeModals = document.querySelectorAll('.modal-close');
    const mainContent = document.querySelector('.main-content');
    const sidebar = document.querySelector('.sidebar');

    // Fonction pour ouvrir une modale
    function openModal(modal) {
        modal.classList.add('active');
        mainContent.classList.add('blur');
        sidebar.classList.add('blur');
    }

    // Fonction pour fermer une modale
    function closeModal(modal) {
        modal.classList.remove('active');
        mainContent.classList.remove('blur');
        sidebar.classList.remove('blur');
    }

    // Gestion des boutons "Modifier"
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const departurePort = this.getAttribute('data-departure-port');
            const arrivalPort = this.getAttribute('data-arrival-port');
            const departureDate = this.getAttribute('data-departure-date');
            const departureTime = this.getAttribute('data-departure-time');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_departure_port').value = departurePort;
            document.getElementById('edit_arrival_port').value = arrivalPort;
            document.getElementById('edit_departure_date').value = departureDate;
            document.getElementById('edit_departure_time').value = departureTime;

            openModal(editModal);
        });
    });

    // Gestion des boutons "Supprimer"
    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            document.getElementById('delete_id').value = id;
            openModal(deleteModal);
        });
    });

    // Gestion de la fermeture des modales
    closeModals.forEach(close => {
        close.addEventListener('click', function () {
            closeModal(editModal);
            closeModal(deleteModal);
        });
    });

    // Fermer les modales en cliquant à l'extérieur
    editModal.addEventListener('click', function (e) {
        if (e.target === editModal) {
            closeModal(editModal);
        }
    });

    deleteModal.addEventListener('click', function (e) {
        if (e.target === deleteModal) {
            closeModal(deleteModal);
        }
    });
});
