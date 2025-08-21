document.addEventListener('DOMContentLoaded', () => {
    console.log('Script user_info_passagers.js chargé');

    // Animation de la section des passagers
    const passengerSection = document.querySelector('.passenger-section');
    if (passengerSection) {
        passengerSection.style.opacity = '0';
        setTimeout(() => {
            passengerSection.style.transition = 'opacity 0.7s ease';
            passengerSection.style.opacity = '1';
        }, 300);
    }

    // Animation de la section équipage
    const crewSection = document.querySelector('.crew-section');
    if (crewSection) {
        crewSection.style.opacity = '0';
        setTimeout(() => {
            crewSection.style.transition = 'opacity 0.7s ease';
            crewSection.style.opacity = '1';
        }, 400);
    }

    // Animation de la liste des membres d'équipage
    const crewList = document.querySelector('.crew-list');
    if (crewList) {
        crewList.style.opacity = '0';
        setTimeout(() => {
            crewList.style.transition = 'opacity 0.7s ease';
            crewList.style.opacity = '1';
        }, 500);
    }

    // Interaction au clic sur les lignes du tableau des passagers
    const tableRows = document.querySelectorAll('.passenger-section tr');
    tableRows.forEach(row => {
        row.addEventListener('click', () => {
            tableRows.forEach(r => r.classList.remove('highlight'));
            row.classList.add('highlight');
        });
    });

    // Fonction pour ouvrir le modal de gestion de l'équipage
    window.openCrewModal = function() {
        const modal = document.getElementById('crewModal');
        modal.style.display = 'flex';
    };

    // Fonction pour fermer le modal
    window.closeCrewModal = function() {
        const modal = document.getElementById('crewModal');
        modal.style.display = 'none';
    };

    // Fermer le modal en cliquant à l'extérieur
    window.onclick = function(event) {
        const modal = document.getElementById('crewModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    // Ajoute une confirmation pour les actions de suppression
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            if (!confirm('Voulez-vous vraiment supprimer ce membre d\'équipage ?')) {
                e.preventDefault();
            }
        });
    });

    // Fonction pour exporter le tableau en PDF
    window.exportToPDF = function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });

        // Titre et date
        doc.setFontSize(16);
        const title = 'Liste des Passagers - Adore Comores Express';
        doc.text(title, 14, 20);
        doc.setFontSize(12);
        const dateFilter = document.querySelector('input[name="date_filter"]').value;
        doc.text(`Date: ${dateFilter ? new Date(dateFilter).toLocaleDateString('fr-FR') : new Date().toLocaleDateString('fr-FR')}`, 14, 30);

        // Section des passagers
        doc.setFontSize(14);
        doc.text('Liste des Passagers', 14, 40);

        const passengerTable = document.querySelector('.passenger-section table');
        let finalY = 40;
        if (passengerTable) {
            const passengerHeaders = ['Nom du Passager', 'Téléphone', 'Nationalité', 'Passeport/CIN', 'Avec Bébé', 'Trajet', 'Référence', 'Type de Ticket'];
            const passengerData = [];

            // Collecter les données des passagers
            const passengerRows = passengerTable.querySelectorAll('tbody tr');
            passengerRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                passengerData.push([
                    cells[0].textContent,
                    cells[1].textContent,
                    cells[2].textContent,
                    cells[3].textContent,
                    cells[4].textContent,
                    cells[5].textContent,
                    cells[6].textContent,
                    cells[7].textContent
                ]);
            });

            // Tableau des passagers
            doc.autoTable({
                head: [passengerHeaders],
                body: passengerData,
                startY: 50,
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    overflow: 'linebreak'
                },
                columnStyles: {
                    0: { cellWidth: 35 }, // Nom du Passager
                    1: { cellWidth: 25 }, // Téléphone
                    2: { cellWidth: 25 }, // Nationalité
                    3: { cellWidth: 25 }, // Passeport/CIN
                    4: { cellWidth: 15 }, // Avec Bébé
                    5: { cellWidth: 45 }, // Trajet
                    6: { cellWidth: 25 }, // Référence
                    7: { cellWidth: 20 }  // Type de Ticket
                },
                headStyles: {
                    fillColor: [30, 64, 175],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [240, 240, 240]
                }
            });
            finalY = doc.lastAutoTable.finalY + 20;
        } else {
            doc.setFontSize(10);
            doc.text('Aucun passager trouvé pour cette date.', 14, 50);
            finalY = 60;
        }

        // Section des membres d'équipage
        doc.setFontSize(14);
        doc.text('Liste des Membres d\'Équipage', 14, finalY);
        const crewList = document.querySelectorAll('.crew-list li');
        const crewData = Array.from(crewList).map(li => [li.textContent]);
        if (crewData.length > 0) {
            doc.autoTable({
                head: [['Nom du Membre']],
                body: crewData,
                startY: finalY + 10,
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    overflow: 'linebreak'
                },
                columnStyles: {
                    0: { cellWidth: 100 }
                },
                headStyles: {
                    fillColor: [30, 64, 175],
                    textColor: [255, 255, 255],
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [240, 240, 240]
                }
            });
            finalY = doc.lastAutoTable.finalY + 20;
        } else {
            doc.setFontSize(10);
            doc.text('Aucun membre d\'équipage pour cette date.', 14, finalY + 10);
            finalY += 20;
        }

        // Ajouter les informations de contact
        doc.setFontSize(8);
        doc.text(
            'Pour plus d’Information : Gde Comores : +269 320 72 13 | Moheli : +269 320 72 18 | Anjouan : +269 320 72 19 | Direction : +269 320 72 23',
            14,
            finalY
        );

        // Sauvegarder le PDF
        const filename = `passagers_et_equipage_${dateFilter || 'tous'}`;
        doc.save(`${filename}.pdf`);
    };
});