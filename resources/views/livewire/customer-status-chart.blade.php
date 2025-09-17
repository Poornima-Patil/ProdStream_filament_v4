@php
    $data = $this->getData();
    $chartId = 'customer-chart-' . uniqid();
@endphp

@php
    $hasData = collect($data['datasets'][0]['data'])->sum() > 0;
@endphp

<div style="width: 100%; height: 280px; position: relative;">
    @if($hasData)
        <canvas id="{{ $chartId }}" style="width: 100%; height: 280px;"></canvas>
    @else
        <div style="display: flex; align-items: center; justify-content: center; height: 280px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="text-align: center; color: #6b7280;">
                <svg style="width: 48px; height: 48px; margin: 0 auto 16px; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20a3 3 0 01-3-3v-2a3 3 0 013-3h3a3 3 0 013 3v2a3 3 0 01-3 3z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">No Customer Work Orders Found</h3>
                <p style="font-size: 14px; margin-bottom: 4px;">No work orders for this customer in the selected date range</p>
                <p style="font-size: 12px;">Try selecting a different date range</p>
            </div>
        </div>
    @endif
</div>

{{-- Include Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let chartInstance = null;

    // Wait for Chart.js to load
    function initChart() {
        const ctx = document.getElementById('{{ $chartId }}');

        if (ctx && typeof Chart !== 'undefined') {
            // Destroy existing chart if it exists
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }

            // Also check if Chart.js has a chart on this canvas
            const existingChart = Chart.getChart(ctx);
            if (existingChart) {
                existingChart.destroy();
            }

            const chartData = @json($data);

            chartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 1.1,
                    cutout: '50%',
                    plugins: {
                        tooltip: {
                            enabled: true,
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + value + ' (' + percentage + '%)';
                                },
                            }
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 20,
                                padding: 15,
                                font: {
                                    size: 12,
                                },
                            },
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    animation: {
                        onComplete: function() {
                            const chart = this;
                            const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const ctx = chart.ctx;

                            // Calculate center using chartArea for more accurate positioning
                            const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                            const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

                            ctx.save();
                            ctx.font = 'bold 24px sans-serif';

                            // Detect dark mode using Tailwind/Filament class on <html>
                            const isDark = document.documentElement.classList.contains('dark');
                            ctx.fillStyle = isDark ? '#fff' : '#111';

                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(total.toString(), centerX, centerY);
                            ctx.restore();
                        }
                    }
                }
            });
        }
    }

    // Initialize chart when page loads with a small delay to ensure Chart.js is loaded
    setTimeout(initChart, 100);
});
</script>
