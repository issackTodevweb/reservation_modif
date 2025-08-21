document.addEventListener('DOMContentLoaded', () => {
    console.log('Script admin_info_passagers.js chargé');

    // Animation de la liste des passagers
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
        let title = 'Liste des Passagers et Équipage - Adore Comores Express';
        const filterTicketType = document.querySelector('#filter_ticket_type').value;
        if (filterTicketType) {
            title += ` (Type: ${filterTicketType})`;
        }
        doc.text(title, 14, 20);
        doc.setFontSize(12);
        doc.text(`Date: ${document.querySelector('.passenger-section h2').textContent.split(' - ')[1].split(' (')[0]}`, 14, 30);

        // Section des passagers
        doc.setFontSize(14);
        doc.text('Liste des Passagers', 14, 40);

        const passengerTable = document.querySelector('.passenger-section table');
        let finalY = 40;
        if (passengerTable) {
            const passengerHeaders = ['Nom du Passager', 'Téléphone', 'Nationalité', 'Numéro de Ticket', 'Type de Ticket', 'Passeport/CIN', 'Avec Bébé', 'Trajet', 'Référence'];
            const passengerData = [];
            const ticketTypes = ['GM', 'MG', 'GA', 'AM', 'MA'];

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
                    cells[7].textContent,
                    cells[8].textContent
                ]);
            });

            // Si trié par ticket_type, regrouper les données
            const urlParams = new URLSearchParams(window.location.search);
            const sortColumn = urlParams.get('sort');
            if (sortColumn === 'ticket_type' && !filterTicketType) {
                let groupedData = ticketTypes.reduce((acc, type) => {
                    acc[type] = passengerData.filter(row => row[4] === type);
                    return acc;
                }, {});
                let startY = 50;
                ticketTypes.forEach(type => {
                    if (groupedData[type].length > 0) {
                        doc.setFontSize(12);
                        doc.text(`Type de Ticket: ${type}`, 14, startY);
                        doc.autoTable({
                            head: [passengerHeaders],
                            body: groupedData[type],
                            startY: startY + 5,
                            styles: {
                                fontSize: 8,
                                cellPadding: 2,
                                overflow: 'linebreak'
                            },
                            columnStyles: {
                                0: { cellWidth: 35 }, // Nom du Passager
                                1: { cellWidth: 25 }, // Téléphone
                                2: { cellWidth: 25 }, // Nationalité
                                3: { cellWidth: 25 }, // Numéro de Ticket
                                4: { cellWidth: 20 }, // Type de Ticket
                                5: { cellWidth: 25 }, // Passeport/CIN
                                6: { cellWidth: 15 }, // Avec Bébé
                                7: { cellWidth: 45 }, // Trajet
                                8: { cellWidth: 25 }  // Référence
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
                        startY = doc.lastAutoTable.finalY + 10;
                    }
                });
                finalY = startY;
            } else {
                // Tableau unique
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
                        3: { cellWidth: 25 }, // Numéro de Ticket
                        4: { cellWidth: 20 }, // Type de Ticket
                        5: { cellWidth: 25 }, // Passeport/CIN
                        6: { cellWidth: 15 }, // Avec Bébé
                        7: { cellWidth: 45 }, // Trajet
                        8: { cellWidth: 25 }  // Référence
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
            }
        } else {
            doc.setFontSize(10);
            doc.text(`Aucun passager trouvé pour cette date${filterTicketType ? ' et ce type de ticket (' + filterTicketType + ')' : ''}.`, 14, 50);
            finalY = 60;
        }

        // Section de l'équipage
        doc.setFontSize(14);
        doc.text('Liste des Membres d\'Équipage', 14, finalY);

        const crewTable = document.querySelector('.crew-section .crew-list-table');
        if (crewTable) {
            const crewHeaders = ['Nom du Membre'];
            const crewData = [];
            const crewRows = crewTable.querySelectorAll('tbody tr');
            crewRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                crewData.push([cells[0].textContent]);
            });

            // Générer le tableau de l'équipage
            doc.autoTable({
                head: [crewHeaders],
                body: crewData,
                startY: finalY + 10,
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    overflow: 'linebreak'
                },
                columnStyles: {
                    0: { cellWidth: 100 } // Nom du Membre
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
        let filename = `passagers_et_equipage_${document.querySelector('#filter_date').value}`;
        if (filterTicketType) {
            filename += `_${filterTicketType}`;
        }
        doc.save(`${filename}.pdf`);
    };
});