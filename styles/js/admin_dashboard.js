document.addEventListener('DOMContentLoaded', () => {
    console.log('Script admin_dashboard.js chargé');

    // Animation des cartes au chargement
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => {
        card.style.opacity = '0';
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
        }, 100 * (Array.from(cards).indexOf(card) + 1));
    });

    // Interaction au clic sur une carte
    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('highlight'));
            card.classList.add('highlight');
        });
    });

    // Animation du rapport journalier
    const dailyReport = document.querySelector('.daily-report');
    if (dailyReport) {
        dailyReport.style.opacity = '0';
        setTimeout(() => {
            dailyReport.style.transition = 'opacity 0.7s ease';
            dailyReport.style.opacity = '1';
        }, 300);
    }

    // Animation de la section rapport par utilisateur
    const userReportSection = document.querySelector('.user-report-section');
    if (userReportSection) {
        userReportSection.style.opacity = '0';
        setTimeout(() => {
            userReportSection.style.transition = 'opacity 0.7s ease';
            userReportSection.style.opacity = '1';
        }, 500);
    }

    // Interaction au clic sur les lignes du tableau
    const tableRows = document.querySelectorAll('.daily-report tr, .user-report-section tr');
    tableRows.forEach(row => {
        row.addEventListener('click', () => {
            tableRows.forEach(r => r.classList.remove('highlight'));
            row.classList.add('highlight');
        });
    });

    // Initialisation du graphique
    const ctx = document.getElementById('dailyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString('fr-FR') + ' KMF';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#ffffff',
                        callback: function(value) {
                            return value.toLocaleString('fr-FR') + ' KMF';
                        }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.2)'
                    }
                },
                x: {
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Gérer l'affichage des champs de date pour le rapport global
    function toggleDateInputs() {
        const period = document.getElementById('report_period').value;
        document.getElementById('daily-input').style.display = period === 'daily' ? 'inline-block' : 'none';
        document.getElementById('monthly-input').style.display = period === 'monthly' ? 'inline-block' : 'none';
        document.getElementById('yearly-input').style.display = period === 'yearly' ? 'inline-block' : 'none';
    }

    // Gérer l'affichage des champs de date pour le rapport par utilisateur
    function toggleUserDateInputs() {
        const period = document.getElementById('user_report_period').value;
        document.getElementById('user-daily-input').style.display = period === 'daily' ? 'inline-block' : 'none';
        document.getElementById('user-monthly-input').style.display = period === 'monthly' ? 'inline-block' : 'none';
        document.getElementById('user-yearly-input').style.display = period === 'yearly' ? 'inline-block' : 'none';
    }

    // Attacher les événements
    document.getElementById('report_period').addEventListener('change', toggleDateInputs);
    document.getElementById('user_report_period').addEventListener('change', toggleUserDateInputs);

    // Initialiser l'affichage des champs de date
    toggleDateInputs();
    toggleUserDateInputs();

    // Stocker les données du rapport utilisateur pour l'exportation PDF
    let currentUserReport = null;

    // Fonction pour récupérer le rapport utilisateur via AJAX
    window.fetchUserReport = function() {
        const userId = document.getElementById('user_id').value;
        const reportPeriod = document.getElementById('user_report_period').value;
        const reportDate = document.getElementById('user_report_date').value;
        const reportMonth = document.getElementById('user_report_month').value;
        const reportYear = document.getElementById('user_report_year').value;

        if (!userId) {
            alert('Veuillez sélectionner un utilisateur.');
            return;
        }

        const data = new FormData();
        data.append('user_id', userId);
        data.append('report_period', reportPeriod);
        if (reportPeriod === 'daily') data.append('report_date', reportDate);
        if (reportPeriod === 'monthly') data.append('report_month', reportMonth);
        if (reportPeriod === 'yearly') data.append('report_year', reportYear);

        fetch('fetch_user_report.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            // Mettre à jour le titre
            document.getElementById('user-report-title').textContent = data.title;

            // Mettre à jour le tableau des totaux
            const totalsTableBody = document.querySelector('#user-totals-table tbody');
            totalsTableBody.innerHTML = `
                <tr>
                    <td>${Number(data.totals.user_tariff).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_tax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_port_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_mvola).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_cash).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_unpaid).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.totals.user_total).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td>${Number(data.colis_totals.user_package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                </tr>
            `;

            // Mettre à jour le tableau des réservations
            const reservationsTableBody = document.querySelector('#user-reservations-table tbody');
            reservationsTableBody.innerHTML = '';
            if (data.reservations.length > 0) {
                data.reservations.forEach(res => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${res.passenger_name}</td>
                        <td>${Number(res.tariff || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                        <td>${Number(res.port_fee || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                        <td>${Number(res.tax || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                        <td>${res.payment_status}</td>
                        <td>${Number(res.total_amount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    `;
                    reservationsTableBody.appendChild(row);
                });
            } else {
                reservationsTableBody.innerHTML = '<tr><td colspan="6">Aucune réservation trouvée pour cet utilisateur.</td></tr>';
            }

            // Mettre à jour le tableau des colis
            const colisTableBody = document.querySelector('#user-colis-table tbody');
            colisTableBody.innerHTML = '';
            if (data.colis.length > 0) {
                data.colis.forEach(col => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${col.sender_name}</td>
                        <td>${col.package_reference}</td>
                        <td>${Number(col.package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    `;
                    colisTableBody.appendChild(row);
                });
            } else {
                colisTableBody.innerHTML = '<tr><td colspan="3">Aucun colis trouvé pour cet utilisateur.</td></tr>';
            }

            // Afficher le conteneur du rapport
            const userReportContainer = document.getElementById('user-report-container');
            userReportContainer.style.display = 'block';
            userReportContainer.style.opacity = '0';
            setTimeout(() => {
                userReportContainer.style.transition = 'opacity 0.5s ease';
                userReportContainer.style.opacity = '1';
            }, 100);

            // Stocker les données pour l'exportation PDF
            currentUserReport = data;
        })
        .catch(error => {
            console.error('Erreur lors de la récupération du rapport:', error);
            alert('Erreur lors de la récupération du rapport. Veuillez réessayer.');
        });
    };

    // Fonction pour exporter le rapport global en PDF
    window.exportToPDF = function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Définir la police et les styles
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(12);

        // Ajouter le titre
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text(reportData.title, 105, 20, { align: 'center' });
        doc.setFontSize(12);
        doc.setFont('helvetica', 'normal');
        doc.text('Généré le ' + new Date().toLocaleDateString('fr-FR'), 105, 30, { align: 'center' });

        // Section Totaux Financiers
        doc.setFontSize(14);
        doc.text('Totaux Financiers', 14, 45);
        doc.autoTable({
            startY: 50,
            head: [['Total Tarifs (KMF)', 'Total Taxes (KMF)', 'Total Frais Portuaires (KMF)', 'Total Mvola (KMF)', 'Total Espèces (KMF)', 'Total Non Payé (KMF)', 'Total Réservations (KMF)', 'Total Frais de Colis (KMF)']],
            body: [[
                Number(reportData.totals.periodic_tariff).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_tax).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_port_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_mvola).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_cash).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_unpaid).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.totals.periodic_total).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(reportData.colis_totals.periodic_package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
            ]],
            styles: { fontSize: 10, cellPadding: 2 },
            headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] },
            margin: { top: 50 }
        });

        // Section Réservations
        let finalY = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(14);
        doc.text('Réservations', 14, finalY);
        if (reportData.reservations.length > 0) {
            doc.autoTable({
                startY: finalY + 5,
                head: [['Passager', 'Tarif (KMF)', 'Frais Portuaires (KMF)', 'Taxes (KMF)', 'Type de Paiement', 'Total (KMF)']],
                body: reportData.reservations.map(res => [
                    res.passenger_name,
                    Number(res.tariff || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    Number(res.port_fee || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    Number(res.tax || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    res.payment_status,
                    Number(res.total_amount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                ]),
                styles: { fontSize: 10, cellPadding: 2 },
                headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] }
            });
        } else {
            doc.setFontSize(12);
            doc.text('Aucune réservation trouvée pour cette période.', 14, finalY + 10);
        }

        // Section Colis
        finalY = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(14);
        doc.text('Colis', 14, finalY);
        if (reportData.colis.length > 0) {
            doc.autoTable({
                startY: finalY + 5,
                head: [['Expéditeur', 'Référence', 'Frais de Port (KMF)']],
                body: reportData.colis.map(col => [
                    col.sender_name,
                    col.package_reference,
                    Number(col.package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                ]),
                styles: { fontSize: 10, cellPadding: 2 },
                headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] }
            });
        } else {
            doc.setFontSize(12);
            doc.text('Aucun colis trouvé pour cette période.', 14, finalY + 10);
        }

        // Enregistrer le PDF
        const filename = reportData.title.toLowerCase().replace(/ /g, '_') + '.pdf';
        doc.save(filename);
    };

    // Fonction pour exporter le rapport utilisateur en PDF
    window.exportUserReportPDF = function() {
        if (!currentUserReport) {
            alert('Aucun rapport utilisateur chargé.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Définir la police et les styles
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(12);

        // Ajouter le titre
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text(currentUserReport.title, 105, 20, { align: 'center' });
        doc.setFontSize(12);
        doc.setFont('helvetica', 'normal');
        doc.text('Généré le ' + new Date().toLocaleDateString('fr-FR'), 105, 30, { align: 'center' });

        // Section Totaux Financiers
        doc.setFontSize(14);
        doc.text('Totaux Financiers', 14, 45);
        doc.autoTable({
            startY: 50,
            head: [['Total Tarifs (KMF)', 'Total Taxes (KMF)', 'Total Frais Portuaires (KMF)', 'Total Mvola (KMF)', 'Total Espèces (KMF)', 'Total Non Payé (KMF)', 'Total Réservations (KMF)', 'Total Frais de Colis (KMF)']],
            body: [[
                Number(currentUserReport.totals.user_tariff).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_tax).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_port_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_mvola).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_cash).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_unpaid).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.totals.user_total).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                Number(currentUserReport.colis_totals.user_package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
            ]],
            styles: { fontSize: 10, cellPadding: 2 },
            headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] },
            margin: { top: 50 }
        });

        // Section Réservations
        let finalY = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(14);
        doc.text('Réservations', 14, finalY);
        if (currentUserReport.reservations.length > 0) {
            doc.autoTable({
                startY: finalY + 5,
                head: [['Passager', 'Tarif (KMF)', 'Frais Portuaires (KMF)', 'Taxes (KMF)', 'Type de Paiement', 'Total (KMF)']],
                body: currentUserReport.reservations.map(res => [
                    res.passenger_name,
                    Number(res.tariff || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    Number(res.port_fee || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    Number(res.tax || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
                    res.payment_status,
                    Number(res.total_amount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                ]),
                styles: { fontSize: 10, cellPadding: 2 },
                headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] }
            });
        } else {
            doc.setFontSize(12);
            doc.text('Aucune réservation trouvée pour cet utilisateur.', 14, finalY + 10);
        }

        // Section Colis
        finalY = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(14);
        doc.text('Colis', 14, finalY);
        if (currentUserReport.colis.length > 0) {
            doc.autoTable({
                startY: finalY + 5,
                head: [['Expéditeur', 'Référence', 'Frais de Port (KMF)']],
                body: currentUserReport.colis.map(col => [
                    col.sender_name,
                    col.package_reference,
                    Number(col.package_fee).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                ]),
                styles: { fontSize: 10, cellPadding: 2 },
                headStyles: { fillColor: [30, 64, 175], textColor: [255, 255, 255] }
            });
        } else {
            doc.setFontSize(12);
            doc.text('Aucun colis trouvé pour cet utilisateur.', 14, finalY + 10);
        }

        // Enregistrer le PDF
        const filename = currentUserReport.title.toLowerCase().replace(/ /g, '_') + '.pdf';
        doc.save(filename);
    };
});