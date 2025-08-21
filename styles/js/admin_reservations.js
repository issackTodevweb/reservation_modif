document.addEventListener('DOMContentLoaded', function () {
    console.log('Script admin_reservations.js chargé');

    const modal = document.getElementById('editReservationModal');
    const modalClose = document.querySelector('.modal-close');
    const editButtons = document.querySelectorAll('.edit-btn');

    if (!modal) {
        console.error('Erreur : l\'élément #editReservationModal n\'est pas trouvé dans le DOM');
        return;
    }
    if (!modalClose) {
        console.error('Erreur : l\'élément .modal-close n\'est pas trouvé dans le DOM');
        return;
    }
    if (editButtons.length === 0) {
        console.warn('Aucun bouton .edit-btn trouvé dans le DOM');
    }

    editButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('Bouton Modifier cliqué pour la réservation ID:', this.dataset.id);
            
            // Remplir la modale avec les données
            document.getElementById('edit_id').value = this.dataset.id || '';
            document.getElementById('edit_passenger_name').value = this.dataset.passengerName || '';
            document.getElementById('edit_phone_number').value = this.dataset.phoneNumber || '';
            document.getElementById('edit_nationality').value = this.dataset.nationality || '';
            document.getElementById('edit_passenger_type').value = this.dataset.passengerType || '';
            document.getElementById('edit_with_baby').value = this.dataset.withBaby || '';
            document.getElementById('edit_departure_date').value = this.dataset.departureDate || '';
            document.getElementById('edit_trip_id').value = this.dataset.tripId || '';
            document.getElementById('edit_seat_number').value = this.dataset.seatNumber || '';
            document.getElementById('edit_payment_status').value = this.dataset.paymentStatus || '';
            document.getElementById('edit_user_id').value = `${this.dataset.cashierName} (ID: ${this.dataset.userId})` || '';
            document.getElementById('edit_user_id_hidden').value = this.dataset.userId || '';

            // Afficher la modale
            modal.style.display = 'flex';
            console.log('Modale affichée');
        });
    });

    modalClose.addEventListener('click', function () {
        modal.style.display = 'none';
        console.log('Modale fermée via bouton de fermeture');
    });

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
            console.log('Modale fermée en cliquant à l\'extérieur');
        }
    });
});