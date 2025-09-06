<?php
// modules/admin/dashboard.php
// Wird von modules/admin/index.php geladen, daher sind $pdo, formatEuroCurrency etc. bereits verfügbar

// Dashboard-Statistiken abrufen
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Umsatzstatistiken
$totalExpectedIncome = $pdo->query("SELECT SUM(total_amount) FROM orders")->fetchColumn();
// Summe aller abgeschlossenen Zahlungen
$totalReceivedIncome = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn();
$totalOutstanding = $totalExpectedIncome - $totalReceivedIncome;

?>
<div class="dashboard-stats">
    <div class="stat-card">
        <h3>Gesamtbestellungen</h3>
        <p><?php echo $totalOrders; ?></p>
    </div>
    <div class="stat-card">
        <h3>Offene Bestellungen</h3>
        <p><?php echo $pendingOrders; ?></p>
    </div>
    <div class="stat-card">
        <h3>Registrierte Kunden</h3>
        <p><?php echo $totalCustomers; ?></p>
    </div>
    <div class="stat-card">
        <h3>Verfügbare Produkte</h3>
        <p><?php echo $totalProducts; ?></p>
    </div>
</div>

<h3>Finanzübersicht</h3>
<div class="dashboard-stats">
    <div class="stat-card">
        <h3>Erwarteter Umsatz</h3>
        <p><?php echo formatEuroCurrency($totalExpectedIncome); ?></p>
    </div>
    <div class="stat-card">
        <h3>Eingegangener Umsatz</h3>
        <p><?php echo formatEuroCurrency($totalReceivedIncome); ?></p>
    </div>
    <div class="stat-card <?php echo ($totalOutstanding > 0 ? 'income-negative' : 'income-positive'); ?>">
        <h3>Offener Betrag</h3>
        <p><?php echo formatEuroCurrency($totalOutstanding); ?></p>
    </div>
</div>

<h3>Umsatzentwicklung (Platzhalter für Grafik)</h3>
<div class="chart-container">
    <canvas id="incomeChart" style="display: none;"></canvas>
    <p id="chartFallback">Lade Grafik...</p>
    <pre style="display: none;" id="chartData">
        <?php
        // Beispiel-Daten für eine Grafik (würden normalerweise komplexer abgefragt)
        $monthlySales = $pdo->query("
            SELECT
                DATE_FORMAT(order_date, '%Y-%m') as month,
                SUM(total_amount) as total_ordered
            FROM orders
            GROUP BY month
            ORDER BY month ASC
            LIMIT 12 -- Letzte 12 Monate
        ")->fetchAll();

        $monthlyPayments = $pdo->query("
            SELECT
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as total_paid
            FROM payments
            WHERE status = 'completed'
            GROUP BY month
            ORDER BY month ASC
            LIMIT 12
        ")->fetchAll();

        $chartLabels = [];
        $chartOrderedData = [];
        $chartPaidData = [];

        // Monate der letzten 12 Monate generieren
        $period = new DatePeriod(
            new DateTime('-11 months first day of this month'),
            new DateInterval('P1M'),
            new DateTime('first day of next month')
        );

        $allMonths = [];
        foreach ($period as $dt) {
            $allMonths[$dt->format('Y-m')] = 0;
        }

        // Daten zusammenführen
        $mergedSales = $allMonths;
        foreach ($monthlySales as $sale) {
            $mergedSales[$sale['month']] = (float)$sale['total_ordered'];
        }
        $mergedPayments = $allMonths;
        foreach ($monthlyPayments as $payment) {
            $mergedPayments[$payment['month']] = (float)$payment['total_paid'];
        }

        foreach ($mergedSales as $month => $total) {
            $chartLabels[] = (new DateTime($month . '-01'))->format('M Y');
            $chartOrderedData[] = $total;
            $chartPaidData[] = $mergedPayments[$month] ?? 0; // Sicherstellen, dass ein Wert existiert
        }

        echo json_encode([
            'labels' => $chartLabels,
            'ordered' => $chartOrderedData,
            'paid' => $chartPaidData
        ]);
        ?>
    </pre>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartDataElement = document.getElementById('chartData');
            const chartFallback = document.getElementById('chartFallback');
            const canvas = document.getElementById('incomeChart');

            if (chartDataElement && canvas) {
                try {
                    const chartConfig = JSON.parse(chartDataElement.textContent);

                    if (chartConfig.labels && chartConfig.labels.length > 0) {
                        chartFallback.style.display = 'none';
                        canvas.style.display = 'block';

                        const ctx = canvas.getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: chartConfig.labels,
                                datasets: [
                                    {
                                        label: 'Erwarteter Umsatz',
                                        data: chartConfig.ordered,
                                        backgroundColor: 'rgba(52, 104, 192, 0.7)', // var(--color-primary)
                                        borderColor: 'rgba(52, 104, 192, 1)',
                                        borderWidth: 1
                                    },
                                    {
                                        label: 'Eingegangener Umsatz',
                                        data: chartConfig.paid,
                                        backgroundColor: 'rgba(0, 191, 99, 0.7)', // var(--color-secondary)
                                        borderColor: 'rgba(0, 191, 99, 1)',
                                        borderWidth: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Betrag (€)'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Monat'
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        chartFallback.textContent = 'Keine Umsatzdaten für die Darstellung verfügbar.';
                    }
                } catch (e) {
                    console.error("Fehler beim Parsen der Chart-Daten oder Initialisieren von Chart.js:", e);
                    chartFallback.textContent = 'Fehler beim Laden der Grafikdaten.';
                }
            } else {
                chartFallback.textContent = 'Chart-Container oder Daten nicht gefunden.';
            }
        });
    </script>
</div>