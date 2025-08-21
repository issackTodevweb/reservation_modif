document.addEventListener('DOMContentLoaded', function () {
    const departureDateInput = document.getElementById('departure_date');
    const tripSelect = document.getElementById('trip_id');
    const ticketTypeSelect = document.getElementById('ticket_type');
    const ticketNumberInput = document.getElementById('ticket_number');
    const ticketIdInput = document.getElementById('ticket_id');
    const seatNumberInput = document.getElementById('seat_number');
    const selectedSeatInput = document.getElementById('selected_seat');
    const seatsModal = document.getElementById('seats_modal');
    const boatLayout = document.getElementById('boat_layout');
    const reservationForm = document.getElementById('reservation-form');
    const closeModal = document.querySelector('.modal .close');
    const totalAmountInput = document.getElementById('total_amount');
    const formTotalAmountInput = document.getElementById('form_total_amount');

    // Mettre à jour les trajets lorsque la date change
    window.fetchTrips = function () {
        const date = departureDateInput.value;
        if (!date) {
            tripSelect.innerHTML = '<option value="">-- Sélectionner une date d\'abord --</option>';
            seatsModal.style.display = 'none';
            updateAmount();
            return;
        }

        fetch(`../user/fetch_trips.php?departure_date=${encodeURIComponent(date)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau lors de la récupération des trajets');
            }
            return response.json();
        })
        .then(data => {
            tripSelect.innerHTML = '<option value="">-- Sélectionner un trajet --</option>';
            if (data.error) {
                tripSelect.innerHTML += `<option value="">${data.error}</option>`;
                alert(data.error);
            } else if (data.length === 0) {
                tripSelect.innerHTML += '<option value="">Aucun trajet disponible</option>';
                alert('Aucun trajet disponible pour cette date.');
            } else {
                data.forEach(trip => {
                    const option = document.createElement('option');
                    option.value = trip.id;
                    option.textContent = `${trip.departure_port} → ${trip.arrival_port} (${trip.departure_date} ${trip.departure_time})`;
                    tripSelect.appendChild(option);
                });
            }
            seatsModal.style.display = 'none';
            updateAmount();
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des trajets:', error);
            tripSelect.innerHTML = '<option value="">Erreur lors du chargement des trajets</option>';
            alert('Impossible de charger les trajets. Veuillez réessayer.');
        });
    };

    // Mettre à jour le numéro de ticket lorsque le type de ticket change
    window.updateTicketNumber = function () {
        const ticketType = ticketTypeSelect.value;
        if (!ticketType) {
            ticketNumberInput.value = 'Sélectionnez un type de ticket';
            ticketIdInput.value = '';
            return;
        }

        fetch(`../user/fetch_tickets.php?ticket_type=${encodeURIComponent(ticketType)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau lors de la récupération du ticket');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                ticketIdInput.value = '';
                ticketNumberInput.value = data.error;
                alert(data.error);
            } else if (data.id && data.ticket_number) {
                ticketIdInput.value = data.id;
                ticketNumberInput.value = data.ticket_number;
            } else {
                ticketIdInput.value = '';
                ticketNumberInput.value = 'Aucun ticket disponible';
                alert('Aucun ticket disponible pour ce type.');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération du ticket:', error);
            ticketIdInput.value = '';
            ticketNumberInput.value = 'Erreur lors du chargement des tickets';
            alert('Impossible de charger le numéro de ticket. Veuillez réessayer.');
        });
    };

    // Afficher les places disponibles dans une modale
    window.showAvailableSeats = function () {
        const tripId = tripSelect.value;
        if (!tripId) {
            alert('Veuillez sélectionner un trajet.');
            return;
        }

        fetch('../admin/seats.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `trip_id=${encodeURIComponent(tripId)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau lors de la récupération des places');
            }
            return response.text();
        })
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const newBoatLayout = doc.querySelector('.boat-layout');
            if (newBoatLayout) {
                boatLayout.innerHTML = newBoatLayout.innerHTML;
                seatsModal.style.display = 'block';
                document.querySelectorAll('.seat').forEach(seat => {
                    seat.addEventListener('click', function () {
                        if (!this.classList.contains('free')) {
                            alert('Cette place n\'est pas disponible.');
                            return;
                        }
                        seatNumberInput.value = this.dataset.seatNumber;
                        selectedSeatInput.value = this.dataset.seatNumber;
                        document.querySelectorAll('.seat').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        seatsModal.style.display = 'none';
                    });
                });
            } else {
                boatLayout.innerHTML = '<p>Aucune place disponible pour ce trajet.</p>';
                seatsModal.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des places:', error);
            boatLayout.innerHTML = '<p>Erreur lors du chargement des places. Veuillez réessayer.</p>';
            seatsModal.style.display = 'block';
            alert('Impossible de charger les places. Veuillez réessayer.');
        });
    };

    // Fermer la modale
    closeModal.addEventListener('click', function () {
        seatsModal.style.display = 'none';
    });

    // Fermer la modale en cliquant à l'extérieur
    window.addEventListener('click', function (event) {
        if (event.target === seatsModal) {
            seatsModal.style.display = 'none';
        }
    });

    // Calculer le montant total
    window.updateAmount = function () {
        const passengerType = document.getElementById('passenger_type').value;
        const tripId = tripSelect.value;
        const ticketType = ticketTypeSelect.value;

        console.log('updateAmount: passengerType=', passengerType, 'tripId=', tripId, 'ticketType=', ticketType);

        if (!passengerType || !tripId || !ticketType) {
            totalAmountInput.value = '0.00';
            formTotalAmountInput.value = '0.00';
            console.log('updateAmount: Paramètres manquants, montant réinitialisé à 0.00');
            return;
        }

        fetch('../user/get_amount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `passenger_type=${encodeURIComponent(passengerType)}&trip_id=${encodeURIComponent(tripId)}&ticket_type=${encodeURIComponent(ticketType)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau lors du calcul du montant');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                totalAmountInput.value = '0.00';
                formTotalAmountInput.value = '0.00';
                console.log('updateAmount: Erreur -', data.error);
                alert(data.error);
            } else {
                totalAmountInput.value = data.total_amount ? data.total_amount.toFixed(2) : '0.00';
                formTotalAmountInput.value = data.total_amount ? data.total_amount.toFixed(2) : '0.00';
                console.log('updateAmount: Montant mis à jour =', totalAmountInput.value);
            }
        })
        .catch(error => {
            console.error('Erreur lors du calcul du montant:', error);
            totalAmountInput.value = '0.00';
            formTotalAmountInput.value = '0.00';
            alert('Impossible de calculer le montant. Veuillez réessayer.');
        });
    };

    // Rafraîchir la disposition des places après soumission du formulaire
    reservationForm.addEventListener('submit', function (event) {
        setTimeout(() => {
            const alertSuccess = document.querySelector('.alert-success');
            const tripId = tripSelect.value;
            if (alertSuccess && tripId) {
                showAvailableSeats();
                seatNumberInput.value = '';
                selectedSeatInput.value = '';
                updateAmount();
            }
        }, 500);
    });

    // Initialiser le menu déroulant des trajets
    fetchTrips();
});