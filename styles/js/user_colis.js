document.addEventListener('DOMContentLoaded', function () {
    console.log('Script user_colis.js chargé');

    const modal = document.getElementById('editPackageModal');
    const modalClose = document.querySelector('.modal-close');
    const editButtons = document.querySelectorAll('.edit-btn');

    if (!modal) {
        console.error('Erreur : l\'élément #editPackageModal n\'est pas trouvé dans le DOM');
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
            console.log('Bouton Modifier cliqué pour le colis ID:', this.dataset.id);
            
            // Remplir la modale avec les données
            document.getElementById('edit_id').value = this.dataset.id || '';
            document.getElementById('edit_sender_name').value = this.dataset.senderName || '';
            document.getElementById('edit_sender_phone').value = this.dataset.senderPhone || '';
            document.getElementById('edit_package_description').value = this.dataset.packageDescription || '';
            document.getElementById('edit_package_reference').value = this.dataset.packageReference || '';
            document.getElementById('edit_receiver_name').value = this.dataset.receiverName || '';
            document.getElementById('edit_receiver_phone').value = this.dataset.receiverPhone || '';
            document.getElementById('edit_package_fee').value = this.dataset.packageFee || '';
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