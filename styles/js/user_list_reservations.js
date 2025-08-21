document.addEventListener('DOMContentLoaded', () => {
    console.log('Script user_list_reservations.js chargé');

    // Gestion de la modale de modification
    const editModal = document.getElementById('editReservationModal');
    const editClose = document.querySelector('#editReservationModal .modal-close');
    const editForm = document.getElementById('editReservationForm');
    const editButtons = document.querySelectorAll('.edit-btn');
    const cancelButtons = document.querySelectorAll('.cancel-btn');

    // Charger tous les trajets disponibles au démarrage
    let allTrips = [];
    fetch('fetch_trips.php', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erreur réseau: ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Trajets chargés au démarrage:', data);
        allTrips = data.error ? [] : data;
    })
    .catch(error => {
        console.error('Erreur lors du chargement initial des trajets:', error);
        allTrips = [];
    });

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            console.log(`Bouton Modifier cliqué pour la réservation ID: ${button.dataset.id}`);
            console.log('Données du bouton:', {
                id: button.dataset.id,
                tripId: button.dataset.tripId,
                departureDate: button.dataset.departureDate,
                departurePort: button.dataset.departurePort,
                arrivalPort: button.dataset.arrivalPort,
                departureTime: button.dataset.departureTime
            });
            // Remplir les champs de la modale
            document.getElementById('edit_id').value = button.dataset.id || '';
            document.getElementById('edit_passenger_name').value = button.dataset.passengerName || '';
            document.getElementById('edit_phone_number').value = button.dataset.phoneNumber || '';
            document.getElementById('edit_nationality').value = button.dataset.nationality || '';
            document.getElementById('edit_passenger_type').value = button.dataset.passengerType || '';
            document.getElementById('edit_with_baby').value = button.dataset.withBaby || '';
            document.getElementById('edit_departure_date').value = button.dataset.departureDate || '';
            document.getElementById('edit_ticket_type').value = button.dataset.ticketType || '';
            document.getElementById('edit_selected_seat').value = button.dataset.seatNumber || '';
            document.getElementById('edit_seat_number').value = button.dataset.seatNumber || '';
            document.getElementById('edit_original_seat_number').value = button.dataset.seatNumber || '';
            document.getElementById('edit_payment_status').value = button.dataset.paymentStatus || '';
            document.getElementById('edit_modification').value = button.dataset.modification || '';
            document.getElementById('edit_penalty').value = button.dataset.penalty || '';
            editModal.style.display = 'flex';
            console.log('Modale affichée');
            // Préremplir le menu des trajets
            const tripId = button.dataset.tripId || '';
            const departureDate = button.dataset.departureDate || '';
            const departurePort = button.dataset.departurePort || 'Inconnu';
            const arrivalPort = button.dataset.arrivalPort || 'Inconnu';
            const departureTime = button.dataset.departureTime || '00:00:00';
            populateTripSelect(tripId, departureDate, departurePort, arrivalPort, departureTime);
        });
    });

    editClose.addEventListener('click', () => {
        editModal.style.display = 'none';
        console.log('Modale fermée');
    });

    window.addEventListener('click', (event) => {
        if (event.target === editModal) {
            editModal.style.display = 'none';
            console.log('Modale fermée via clic extérieur');
        }
    });

    // Gestion de la modale des places
    const seatsModal = document.getElementById('edit_seats_modal');
    const seatsClose = document.querySelector('#edit_seats_modal .close');
    const showSeatsButton = document.getElementById('edit_show_seats');

    showSeatsButton.addEventListener('click', showAvailableSeatsForEdit);

    seatsClose.addEventListener('click', () => {
        seatsModal.style.display = 'none';
        console.log('Modale des places fermée');
    });

    window.addEventListener('click', (event) => {
        if (event.target === seatsModal) {
            seatsModal.style.display = 'none';
            console.log('Modale des places fermée via clic extérieur');
        }
    });

    // Gestion de l'annulation
    cancelButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault(); // Empêcher la soumission par défaut du formulaire
            const reservationId = button.closest('form').querySelector('input[name="cancel_reservation"]').value;
            console.log(`Bouton Annuler cliqué pour la réservation ID: ${reservationId}`);
            
            if (!confirm('Voulez-vous vraiment annuler cette réservation ?')) {
                console.log('Annulation abandonnée par l\'utilisateur');
                return;
            }

            // Envoyer une requête AJAX pour annuler la réservation
            fetch('cancel_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `cancel_reservation=${encodeURIComponent(reservationId)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Réponse de l\'annulation:', data);
                if (data.success) {
                    alert('Réservation annulée avec succès.');
                    // Mettre à jour l'interface utilisateur
                    const row = button.closest('tr');
                    const paymentStatusCell = row.querySelector('td:nth-child(13)'); // Supposant que payment_status est la 13e colonne
                    if (paymentStatusCell) {
                        paymentStatusCell.textContent = 'Annulé';
                    }
                    // Mettre à jour les data-* du bouton Modifier
                    const editButton = row.querySelector('.edit-btn');
                    if (editButton) {
                        editButton.dataset.paymentStatus = 'Annulé';
                    }
                    // Désactiver le bouton Annuler
                    button.disabled = true;
                    console.log('Interface mise à jour: payment_status = Annulé');
                } else {
                    console.error('Erreur serveur:', data.error);
                    alert(`Erreur lors de l'annulation: ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Erreur lors de l\'annulation:', error);
                alert('Erreur lors de l\'annulation. Veuillez réessayer.');
            });
        });
    });

    // Fonction pour remplir le menu déroulant des trajets
    window.populateTripSelect = function(currentTripId, departureDate, departurePort, arrivalPort, departureTime) {
        const tripSelect = document.getElementById('edit_trip_id');
        console.log(`populateTripSelect appelé avec:`, {
            currentTripId,
            departureDate,
            departurePort,
            arrivalPort,
            departureTime
        });

        // Initialiser le menu
        tripSelect.innerHTML = '<option value="">-- Sélectionner un trajet --</option>';

        // Ajouter tous les trajets disponibles
        if (allTrips.length > 0) {
            allTrips.forEach(trip => {
                const option = document.createElement('option');
                option.value = trip.id;
                option.textContent = `${trip.departure_port} → ${trip.arrival_port} (${trip.departure_date} ${trip.departure_time})`;
                if (String(trip.id) === String(currentTripId)) {
                    option.selected = true;
                }
                tripSelect.appendChild(option);
            });
            console.log(`Trajets ajoutés: ${allTrips.length} trajets`);
        } else {
            // Fallback si aucun trajet n'est chargé
            if (currentTripId) {
                const currentTripText = `${departurePort} → ${arrivalPort} (${departureDate || 'Date inconnue'} ${departureTime})`;
                const option = document.createElement('option');
                option.value = currentTripId;
                option.textContent = currentTripText;
                option.selected = true;
                tripSelect.appendChild(option);
                console.log(`Trajet actuel ajouté (fallback): ${currentTripText}`);
            } else {
                console.warn('Aucun trajet disponible et aucun tripId fourni');
                tripSelect.innerHTML += '<option value="">Aucun trajet disponible</option>';
            }
        }

        // Mettre à jour la valeur sélectionnée
        if (currentTripId) {
            tripSelect.value = currentTripId;
            console.log(`Trajet sélectionné: ${tripSelect.value}`);
        }
    };

    // Appeler populateTripSelect lorsque la date change
    window.fetchTripsForEdit = function() {
        const departureDate = document.getElementById('edit_departure_date').value;
        console.log(`fetchTripsForEdit appelé avec departureDate: ${departureDate}`);
        if (!departureDate) {
            console.log('fetchTripsForEdit ignoré: date vide');
            return;
        }
        const currentTripId = document.getElementById('edit_trip_id').value;
        const currentOption = document.querySelector(`#edit_trip_id option[value="${currentTripId}"]`);
        const departurePort = currentOption ? currentOption.textContent.split(' → ')[0] : 'Inconnu';
        const arrivalPort = currentOption ? currentOption.textContent.split(' → ')[1]?.split(' (')[0] : 'Inconnu';
        const departureTime = currentOption ? currentOption.textContent.match(/\d{2}:\d{2}:\d{2}/)?.[0] || '00:00:00' : '00:00:00';
        // Filtrer les trajets par date
        const filteredTrips = allTrips.filter(trip => trip.departure_date === departureDate);
        const tripSelect = document.getElementById('edit_trip_id');
        tripSelect.innerHTML = '<option value="">-- Sélectionner un trajet --</option>';
        if (filteredTrips.length > 0) {
            filteredTrips.forEach(trip => {
                const option = document.createElement('option');
                option.value = trip.id;
                option.textContent = `${trip.departure_port} → ${trip.arrival_port} (${trip.departure_date} ${trip.departure_time})`;
                if (String(trip.id) === String(currentTripId)) {
                    option.selected = true;
                }
                tripSelect.appendChild(option);
            });
            console.log(`Trajets filtrés ajoutés: ${filteredTrips.length} trajets`);
        } else {
            console.log('Aucun trajet disponible pour la date sélectionnée');
            tripSelect.innerHTML += '<option value="">Aucun trajet disponible</option>';
        }
        tripSelect.value = currentTripId;
    };

    // Fonction pour afficher les places disponibles
    window.showAvailableSeatsForEdit = function() {
        const tripId = document.getElementById('edit_trip_id').value;
        const originalSeat = document.getElementById('edit_original_seat_number').value;
        console.log(`Affichage des places pour le trajet ID: ${tripId}, place originale: ${originalSeat}`);
        if (!tripId) {
            alert('Veuillez sélectionner un trajet d\'abord.');
            return;
        }

        fetch(`fetch_seats.php?trip_id=${encodeURIComponent(tripId)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur réseau: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            const boatLayout = document.getElementById('edit_boat_layout');
            boatLayout.innerHTML = '';
            data.rows.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'seat-row';
                row.seats.forEach(seat => {
                    const seatDiv = document.createElement('div');
                    seatDiv.className = `seat ${seat.status}${seat.seat_number === originalSeat ? ' selected' : ''}`;
                    seatDiv.textContent = seat.seat_number;
                    if (seat.status === 'free' || seat.seat_number === originalSeat) {
                        seatDiv.addEventListener('click', () => {
                            document.querySelectorAll('#edit_boat_layout .seat.selected').forEach(s => s.classList.remove('selected'));
                            seatDiv.classList.add('selected');
                            document.getElementById('edit_selected_seat').value = seat.seat_number;
                            document.getElementById('edit_seat_number').value = seat.seat_number;
                            console.log(`Place sélectionnée: ${seat.seat_number}`);
                            seatsModal.style.display = 'none';
                        });
                    }
                    rowDiv.appendChild(seatDiv);
                });
                if (row.aisle) {
                    const aisleDiv = document.createElement('div');
                    aisleDiv.className = 'aisle';
                    rowDiv.appendChild(aisleDiv);
                }
                boatLayout.appendChild(rowDiv);
            });
            seatsModal.style.display = 'flex';
            console.log('Modale des places affichée');
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des places:', error);
            const boatLayout = document.getElementById('edit_boat_layout');
            boatLayout.innerHTML = '<p>Erreur lors du chargement des places. Veuillez réessayer.</p>';
            seatsModal.style.display = 'flex';
            alert('Impossible de charger les places. Veuillez réessayer.');
        });
    };

    // Validation et soumission du formulaire
    editForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const tripId = document.getElementById('edit_trip_id').value;
        const seatNumber = document.getElementById('edit_seat_number').value;
        console.log('Soumission du formulaire avec:', { tripId, seatNumber });

        if (!tripId) {
            alert('Veuillez sélectionner un trajet.');
            return;
        }
        if (!seatNumber) {
            alert('Veuillez sélectionner une place.');
            return;
        }

        // Vérifier la disponibilité du siège
        fetch(`fetch_seats.php?trip_id=${encodeURIComponent(tripId)}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur réseau: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Vérification des sièges:', data);
            const selectedSeat = data.rows.flatMap(row => row.seats).find(seat => seat.seat_number === seatNumber);
            const originalSeat = document.getElementById('edit_original_seat_number').value;
            if (!selectedSeat || (selectedSeat.status !== 'free' && seatNumber !== originalSeat)) {
                alert('Le siège sélectionné n\'est pas disponible.');
                return;
            }
            console.log('Validation réussie, soumission du formulaire');
            editForm.submit();
        })
        .catch(error => {
            console.error('Erreur lors de la vérification des sièges:', error);
            alert('Erreur lors de la vérification des sièges. Veuillez réessayer.');
        });
    });
});