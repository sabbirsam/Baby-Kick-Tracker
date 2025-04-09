(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize charts if they exist
        if ($('#baby-kick-chart-weekly').length) {
            initWeeklyChart();
        }
        
        // Date range filters in reports
        $('#baby-kick-date-filter').on('submit', function(e) {
            e.preventDefault();
            const startDate = $('#start-date').val();
            const endDate = $('#end-date').val();
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }
            
            const queryParams = new URLSearchParams(window.location.search);
            queryParams.set('start_date', startDate);
            queryParams.set('end_date', endDate);
            
            window.location.href = window.location.pathname + '?' + queryParams.toString();
        });
    });
    
    // Weekly kicks chart
    function initWeeklyChart() {
        const ctx = document.getElementById('baby-kick-chart-weekly').getContext('2d');
        
        // Get chart data from the DOM
        const labels = JSON.parse(document.getElementById('chart-labels').textContent);
        const data = JSON.parse(document.getElementById('chart-data').textContent);
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Kicks',
                    data: data,
                    backgroundColor: 'rgba(75, 192, 192, 0.4)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Kicks'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }
})(jQuery);