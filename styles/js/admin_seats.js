
document.addEventListener('DOMContentLoaded', function () {
    const seats = document.querySelectorAll('.seat');

    seats.forEach(seat => {
        seat.addEventListener('click', function () {
            const tripId = this.getAttribute('data-trip-id');
            const seatNumber = this.getAttribute('data-seat-number');
            const currentStatus = this.classList.contains('free') ? 'free' : 
                                 this.classList.contains('occupied') ? 'occupied' : 'crew';

            // Envoyer une requête AJAX pour mettre à jour le statut
            fetch('seats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `update_seat=true&trip_id=${encodeURIComponent(tripId)}&seat_number=${encodeURIComponent(seatNumber)}&current_status=${encodeURIComponent(currentStatus)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour la classe du siège
                    seat.classList.remove('free', 'occupied', 'crew');
                    seat.classList.add(data.new_status);
                } else {
                    alert('Erreur lors de la mise à jour : ' + data.error);
                }
            })
            .catch(error => {
                alert('Erreur réseau : ' + error);
            });
        });
    });
});