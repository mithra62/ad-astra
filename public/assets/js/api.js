fetch('/admin/dashboard/chart')
    .then(res => res.json())
    .then(({labels, data}) => {

        let options = {
            chart: {
                type: 'line',
                height: 350,
                toolbar: {show: false}
            },
            series: [{
                name: 'Requests',
                data: data
            }],
            xaxis: {
                categories: labels,
                type: 'category',
                labels: {
                    rotate: -45
                }
            },
            markers: {
                size: 3
            },
            chart: {
                type: 'area',
                height: 350,
                offsetY: 0,
                offsetX: 0,
                toolbar: {
                    show: false,
                },
            },
            stroke: {
                width: 2,
                curve: 'smooth'
            },
            dataLabels: {
                enabled: false,
            },
            fill: {
                type: "gradient",
                gradient: {
                    shadeIntensity: 1,
                    colorStops: [
                        {
                            offset: 0,
                            color: 'rgba(var(--info),.4)',
                            opacity: 1,
                        },
                        {
                            offset: 50,
                            color: 'rgba(var(--info),.4)',
                            opacity: 1,
                        },
                        {
                            offset: 100,
                            color: 'rgba(var(--info),.1)',
                            opacity: 1,
                        },
                    ]
                }
            },
            legend: {
                show: false,
            },
            colors: ['rgba(var(--info))'],
            xaxis: {
                tooltip: {
                    enabled: false,
                },
                labels: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
            },
            tooltip: {
                x: {
                    show: false,
                },
                y: {
                    formatter: val => `${val} requests`
                },
                style: {
                    fontSize: '16px',
                    fontFamily: '"Outfit", sans-serif',
                },
            },
            responsive: [{
                breakpoint: 1660,
                options: {
                    chart: {
                        height: 365
                    }
                }
            }]
        };

        new ApexCharts(document.querySelector("#api_graph"), options).render();
    });

$(function () {
    $('#apikeydtatable').DataTable({
        "order": [[4, 'desc']] // Orders by the first column (index 0) in descending order
    });
});
