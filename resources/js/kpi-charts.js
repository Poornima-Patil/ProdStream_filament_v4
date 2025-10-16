// KPI Charts JavaScript
// This file provides chart initialization functions for the KPI Analytics Dashboard

window.createUtilizationChart = function(series, categories) {
    return {
        chart: null,
        init() {
            const initChart = () => {
                if (typeof window.ApexCharts === 'undefined') {
                    setTimeout(initChart, 100);
                    return;
                }

                this.chart = new window.ApexCharts(this.$el, {
                    series: series,
                    chart: {
                        type: 'line',
                        height: 350,
                        toolbar: {
                            show: true,
                            tools: {
                                download: true,
                                zoom: true,
                                pan: true,
                            }
                        },
                        animations: {
                            enabled: true,
                            speed: 500,
                        }
                    },
                    colors: ['#3b82f6', '#10b981', '#93c5fd', '#6ee7b7'],
                    stroke: {
                        width: [3, 3, 2, 2],
                        curve: 'smooth',
                        dashArray: [0, 0, 5, 5]
                    },
                    markers: {
                        size: 4,
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            style: {
                                colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Utilization %',
                            style: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                            }
                        },
                        labels: {
                            formatter: (val) => val.toFixed(1) + '%',
                            style: {
                                colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                            }
                        },
                        min: 0,
                        max: 100
                    },
                    tooltip: {
                        theme: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? 'dark' : 'light',
                        y: {
                            formatter: (val) => val.toFixed(1) + '%'
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left',
                        labels: {
                            colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                        }
                    },
                    grid: {
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#374151' : '#e5e7eb'
                    }
                });

                this.chart.render();
            };

            initChart();
        }
    };
};

window.createProductionChart = function(unitsProduced, uptimeHours, downtimeHours, categories) {
    return {
        chart: null,
        init() {
            const initChart = () => {
                if (typeof window.ApexCharts === 'undefined') {
                    setTimeout(initChart, 100);
                    return;
                }

                this.chart = new window.ApexCharts(this.$el, {
                    series: [
                        {
                            name: 'Units Produced',
                            type: 'column',
                            data: unitsProduced
                        },
                        {
                            name: 'Uptime (hours)',
                            type: 'line',
                            data: uptimeHours
                        },
                        {
                            name: 'Downtime (hours)',
                            type: 'line',
                            data: downtimeHours
                        }
                    ],
                    chart: {
                        type: 'line',
                        height: 350,
                        toolbar: {
                            show: true,
                            tools: {
                                download: true,
                            }
                        },
                        animations: {
                            enabled: true,
                            speed: 500,
                        }
                    },
                    colors: ['#8b5cf6', '#10b981', '#ef4444'],
                    stroke: {
                        width: [0, 3, 3],
                        curve: 'smooth'
                    },
                    plotOptions: {
                        bar: {
                            columnWidth: '50%'
                        }
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            style: {
                                colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                            }
                        }
                    },
                    yaxis: [
                        {
                            title: {
                                text: 'Units Produced',
                                style: {
                                    color: '#8b5cf6'
                                }
                            },
                            labels: {
                                style: {
                                    colors: '#8b5cf6'
                                }
                            }
                        },
                        {
                            opposite: true,
                            title: {
                                text: 'Hours',
                                style: {
                                    color: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                                }
                            },
                            labels: {
                                formatter: (val) => val.toFixed(1) + 'h',
                                style: {
                                    colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                                }
                            }
                        }
                    ],
                    tooltip: {
                        theme: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? 'dark' : 'light',
                        shared: true,
                        intersect: false
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left',
                        labels: {
                            colors: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#9ca3af' : '#6b7280'
                        }
                    },
                    grid: {
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('color-scheme') === 'dark' ? '#374151' : '#e5e7eb'
                    }
                });

                this.chart.render();
            };

            initChart();
        }
    };
};