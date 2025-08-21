document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le graphique
    const ctx = document.getElementById('periodicChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('fr-FR') + ' KMF';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString('fr-FR') + ' KMF';
                        }
                    }
                }
            }
        }
    });

    // Gérer l'affichage des champs de date
    function toggleDateInputs() {
        const period = document.getElementById('report_period').value;
        document.getElementById('daily-input').style.display = period === 'daily' ? 'inline-block' : 'none';
        document.getElementById('monthly-input').style.display = period === 'monthly' ? 'inline-block' : 'none';
        document.getElementById('yearly-input').style.display = period === 'yearly' ? 'inline-block' : 'none';
    }

    // Attacher l'événement au changement de période
    document.getElementById('report_period').addEventListener('change', toggleDateInputs);

    // Initialiser l'affichage des champs de date au chargement
    toggleDateInputs();
});

// Fonction pour exporter le rapport en PDF
function exportToPDF() {
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
}