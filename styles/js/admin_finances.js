document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM chargé, initialisation du script admin_finances.js');
    
    const modal = document.getElementById('editFinanceModal');
    const closeModal = document.querySelector('.modal-close');
    const editButtons = document.querySelectorAll('.edit-btn');

    // Vérifier si les boutons Modifier sont trouvés
    console.log('Nombre de boutons Modifier trouvés :', editButtons.length);
    
    // Ouvrir la modale et préremplir les champs
    editButtons.forEach((button, index) => {
        button.addEventListener('click', function (event) {
            event.preventDefault(); // Empêcher tout comportement par défaut
            console.log('Clic sur le bouton Modifier', index + 1);

            try {
                const id = this.getAttribute('data-id');
                const passengerType = this.getAttribute('data-passenger-type');
                const tripType = this.getAttribute('data-trip-type');
                const tariff = this.getAttribute('data-tariff');
                const portFee = this.getAttribute('data-port-fee');
                const tax = this.getAttribute('data-tax');

                console.log('Données récupérées :', { id, passengerType, tripType, tariff, portFee, tax });

                // Vérifier si les éléments du formulaire existent
                const editIdInput = document.getElementById('edit_id');
                const editPassengerType = document.getElementById('edit_passenger_type');
                const editTripType = document.getElementById('edit_trip_type');
                const editTariff = document.getElementById('edit_tariff');
                const editPortFee = document.getElementById('edit_port_fee');
                const editTax = document.getElementById('edit_tax');

                if (!editIdInput || !editPassengerType || !editTripType || !editTariff || !editPortFee || !editTax) {
                    console.error('Un ou plusieurs éléments du formulaire sont introuvables');
                    alert('Erreur : Formulaire de modification incomplet.');
                    return;
                }

                // Préremplir les champs
                editIdInput.value = id || '';
                editPassengerType.value = passengerType || '';
                editTripType.value = tripType || '';
                editTariff.value = tariff || '0';
                editPortFee.value = portFee || '0';
                editTax.value = tax || '0';

                // Afficher la modale
                if (modal) {
                    modal.style.display = 'flex';
                    console.log('Modale affichée avec display: flex');
                } else {
                    console.error('Modale introuvable');
                    alert('Erreur : Modale de modification introuvable.');
                }
            } catch (error) {
                console.error('Erreur lors du clic sur Modifier :', error);
                alert('Erreur lors de l\'ouverture de la modale : ' + error.message);
            }
        });
    });

    // Fermer la modale
    if (closeModal) {
        closeModal.addEventListener('click', function () {
            console.log('Clic sur le bouton de fermeture de la modale');
            modal.style.display = 'none';
        });
    } else {
        console.error('Bouton de fermeture de la modale introuvable');
    }

    // Fermer la modale en cliquant à l'extérieur
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            console.log('Clic à l\'extérieur de la modale');
            modal.style.display = 'none';
        }
    });
});