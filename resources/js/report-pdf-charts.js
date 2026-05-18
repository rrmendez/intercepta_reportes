import {
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';

Chart.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
);

const PRINT_OPTIONS = {
    responsive: false,
    maintainAspectRatio: false,
    animation: false,
    devicePixelRatio: 2,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
        },
    },
    scales: {
        x: {
            ticks: {
                maxRotation: 45,
                minRotation: 0,
            },
        },
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
            },
        },
    },
};

function destroyExistingCharts() {
    if (!Array.isArray(window.__reportChartInstances)) {
        window.__reportChartInstances = [];

        return;
    }

    window.__reportChartInstances.forEach((chart) => chart.destroy());
    window.__reportChartInstances = [];
}

function renderChart(definition) {
    const canvas = document.getElementById(definition.id);

    if (!canvas || !(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: definition.labels ?? [],
            datasets: definition.datasets ?? [],
        },
        options: {
            ...PRINT_OPTIONS,
            plugins: {
                ...PRINT_OPTIONS.plugins,
                title: {
                    display: Boolean(definition.title),
                    text: definition.title ?? '',
                    font: { size: 14 },
                },
            },
        },
    });

    window.__reportChartInstances.push(chart);
}

function markReady() {
    window.__reportChartsReady = true;
}

window.ReportPdfCharts = {
    render(config) {
        window.__reportChartsReady = false;
        destroyExistingCharts();

        const charts = config?.charts ?? [];

        if (charts.length === 0) {
            markReady();

            return;
        }

        charts.forEach((definition) => renderChart(definition));
        markReady();
    },

    renderFromDom(root = document) {
        const configElement = root.querySelector('#report-charts-config');

        if (!configElement) {
            markReady();

            return;
        }

        try {
            const config = JSON.parse(configElement.textContent ?? '{}');
            this.render(config);
        } catch {
            markReady();
        }
    },
};
