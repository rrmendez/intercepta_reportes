import {
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';

Chart.register(
    LineController,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
);

const DEFAULT_CHART_WIDTH = 720;
const DEFAULT_CHART_HEIGHT = 260;

const PRINT_OPTIONS = {
    responsive: false,
    maintainAspectRatio: false,
    animation: false,
    devicePixelRatio: 2,
    layout: {
        autoPadding: true,
        padding: {
            top: 6,
            right: 8,
            bottom: 6,
            left: 8,
        },
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            align: 'start',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                padding: 12,
                font: {
                    size: 10,
                },
            },
        },
        tooltip: {
            enabled: false,
        },
    },
    scales: {
        x: {
            offset: true,
            grid: {
                display: true,
                drawOnChartArea: true,
            },
            ticks: {
                maxRotation: 45,
                minRotation: 0,
                padding: 6,
                autoSkip: true,
                maxTicksLimit: 14,
                font: {
                    size: 9,
                },
            },
            title: {
                display: false,
                padding: {
                    top: 8,
                },
                font: {
                    size: 10,
                    weight: 'bold',
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                display: true,
            },
            ticks: {
                precision: 0,
                padding: 4,
                font: {
                    size: 9,
                },
            },
            title: {
                display: false,
                padding: {
                    bottom: 4,
                },
                font: {
                    size: 10,
                    weight: 'bold',
                },
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

function parsePositiveInt(value, fallback) {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function measureChartSize(canvas) {
    const wrap = canvas.closest('[data-report-chart-canvas-wrap]') ?? canvas.parentElement;

    if (!(wrap instanceof HTMLElement)) {
        return { width: DEFAULT_CHART_WIDTH, height: DEFAULT_CHART_HEIGHT };
    }

    const widthFromData = parsePositiveInt(wrap.dataset.chartWidth, 0);
    const heightFromData = parsePositiveInt(wrap.dataset.chartHeight, 0);

    const rect = wrap.getBoundingClientRect();
    const computed = window.getComputedStyle(wrap);

    const widthFromDom = Math.floor(rect.width);
    const heightFromDom = Math.floor(rect.height);
    const heightFromCss = Math.floor(Number.parseFloat(computed.height));

    const width = Math.max(
        widthFromData,
        widthFromDom > 80 ? widthFromDom : 0,
        Math.floor(window.innerWidth * 0.86),
        DEFAULT_CHART_WIDTH,
    );

    const height = Math.max(
        heightFromData,
        heightFromDom > 120 ? heightFromDom : 0,
        heightFromCss > 120 ? heightFromCss : 0,
        DEFAULT_CHART_HEIGHT,
    );

    return { width, height };
}

function applyCanvasSize(canvas, width, height) {
    canvas.width = width;
    canvas.height = height;
    canvas.style.display = 'block';
    canvas.style.width = `${width}px`;
    canvas.style.maxWidth = '100%';
    canvas.style.height = `${height}px`;
}

function renderChart(definition) {
    const canvas = document.getElementById(definition.id);

    if (!canvas || !(canvas instanceof HTMLCanvasElement)) {
        return null;
    }

    const { width, height } = measureChartSize(canvas);
    applyCanvasSize(canvas, width, height);

    const showPluginTitle = definition.display_title !== false;
    const xTitle = definition.x_axis_label
        ? { display: true, text: definition.x_axis_label, padding: { top: 10 } }
        : undefined;
    const yTitle = definition.y_axis_label
        ? { display: true, text: definition.y_axis_label, padding: { bottom: 6 } }
        : undefined;

    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: definition.labels ?? [],
            datasets: definition.datasets ?? [],
        },
        options: {
            ...PRINT_OPTIONS,
            scales: {
                x: {
                    ...PRINT_OPTIONS.scales.x,
                    ...(xTitle ? { title: xTitle } : {}),
                },
                y: {
                    ...PRINT_OPTIONS.scales.y,
                    ...(yTitle ? { title: yTitle } : {}),
                },
            },
            plugins: {
                ...PRINT_OPTIONS.plugins,
                title: {
                    display: showPluginTitle && Boolean(definition.title),
                    text: definition.title ?? '',
                    font: { size: 12 },
                    padding: {
                        top: 0,
                        bottom: 8,
                    },
                },
            },
        },
    });

    chart.update('none');
    window.__reportChartInstances.push(chart);

    return chart;
}

function resizeChartsToContainers() {
    window.__reportChartInstances.forEach((chart) => {
        const canvas = chart.canvas;

        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const { width, height } = measureChartSize(canvas);
        applyCanvasSize(canvas, width, height);
        chart.resize(width, height);
        chart.update('none');
    });
}

function chartsLookReady() {
    if (!Array.isArray(window.__reportChartInstances) || window.__reportChartInstances.length === 0) {
        return true;
    }

    return window.__reportChartInstances.every((chart) => {
        const area = chart.chartArea;

        return area != null && area.width > 0 && area.height > 0;
    });
}

function markReady() {
    window.__reportChartsReady = true;
}

function finalizeRender() {
    resizeChartsToContainers();

    requestAnimationFrame(() => {
        resizeChartsToContainers();

        requestAnimationFrame(() => {
            resizeChartsToContainers();

            window.setTimeout(() => {
                resizeChartsToContainers();

                if (! chartsLookReady()) {
                    resizeChartsToContainers();
                }

                markReady();
            }, 120);
        });
    });
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
        finalizeRender();
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
