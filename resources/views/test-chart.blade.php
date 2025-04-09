<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>
    <h1>Hello from Apex Test</h1>
    <div id="chart"></div>

    <script>
        console.log('Script is loaded');

        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM fully loaded');

            if (typeof ApexCharts === 'undefined') {
                console.error('ApexCharts is NOT loaded');
                return;
            }

            console.log('ApexCharts is loaded:', ApexCharts);

            var options = {
                chart: {
                    type: 'bar',
                    height: 350
                },
                series: [{
                    name: 'Work Orders',
                    data: [10, 20, 15, 30]
                }],
                xaxis: {
                    categories: ['Assigned', 'Completed', 'Closed', 'Hold']
                }
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        });
    </script>
</body>
</html>
