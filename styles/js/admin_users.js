document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editModal');
    const closeModal = document.querySelector('.modal-close');
    const editButtons = document.querySelectorAll('.action-btn.edit');
    const editForm = document.getElementById('edit-user-form');
    const submitBtn = editForm.querySelector('.submit-btn');

    // Effacer les messages d'alerte après 5 secondes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Ouvrir la modale et préremplir les champs
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');

            document.getElementById('edit-user-id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-password').value = ''; // Toujours vide pour sécurité

            modal.classList.add('active');
            submitBtn.disabled = false;
        });
    });

    // Fermer la modale
    closeModal.addEventListener('click', function () {
        modal.classList.remove('active');
    });

    // Fermer la modale en cliquant à l'extérieur
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Validation du formulaire de modification
    editForm.addEventListener('submit', function (event) {
        const username = document.getElementById('edit-username').value.trim();
        const password = document.getElementById('edit-password').value.trim();

        if (!username) {
            event.preventDefault();
            alert('Le nom d\'utilisateur est obligatoire.');
            submitBtn.disabled = true;
            setTimeout(() => { submitBtn.disabled = false; }, 2000);
            return;
        }

        if (username.length < 3) {
            event.preventDefault();
            alert('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
            submitBtn.disabled = true;
            setTimeout(() => { submitBtn.disabled = false; }, 2000);
            return;
        }

        if (password && password.length < 6) {
            event.preventDefault();
            alert('Le mot de passe doit contenir au moins 6 caractères.');
            submitBtn.disabled = true;
            setTimeout(() => { submitBtn.disabled = false; }, 2000);
            return;
        }
    });
});