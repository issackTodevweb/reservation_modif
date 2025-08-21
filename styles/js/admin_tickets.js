
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editTicketModal');
    const closeModal = document.querySelector('.modal-close');
    const editButtons = document.querySelectorAll('.edit-btn');

    // Ouvrir la modale et préremplir les champs
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const ticketNumber = this.getAttribute('data-ticket-number');
            const ticketType = this.getAttribute('data-ticket-type');
            const status = this.getAttribute('data-status');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_ticket_number').value = ticketNumber;
            document.getElementById('edit_ticket_type').value = ticketType;
            document.getElementById('edit_status').value = status;

            modal.style.display = 'flex';
        });
    });

    // Fermer la modale
    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Fermer la modale en cliquant à l'extérieur
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
