/*
 * Price history (step lines, one per store) plus the 14-day forecast of one
 * selected store: a dashed line inside its 95% band. Colours follow the
 * validated categorical palette, stepped separately for light and dark.
 */

const PALETTE = {
    light: { series: ['#2a78d6', '#eb6834', '#1baf7a'], ink: '#52514e', muted: '#898781', grid: '#e1e0d9' },
    dark: { series: ['#3987e5', '#d95926', '#199e70'], ink: '#c3c2b7', muted: '#898781', grid: '#3f3f46' },
};

const euro = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });

function mode() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function buildSeries(data, selectedIndex, colors) {
    const series = data.series.map((store, index) => ({
        name: store.name,
        type: 'line',
        color: colors[index % colors.length],
        data: store.history.map(([x, y]) => ({ x, y })),
    }));

    const selected = data.series[selectedIndex];
    const strokes = series.map(() => ({ width: 2, curve: 'stepline', dash: 0 }));

    if (selected?.forecast) {
        const color = colors[selectedIndex % colors.length];

        series.push({
            name: `${selected.name} · 95% range`,
            type: 'rangeArea',
            color,
            data: selected.forecast.map(([x, , lower, upper]) => ({ x, y: [lower, upper] })),
        });
        series.push({
            name: `${selected.name} · forecast`,
            type: 'line',
            color,
            data: selected.forecast.map(([x, y]) => ({ x, y })),
        });
        strokes.push({ width: 0, curve: 'straight', dash: 0 }, { width: 2, curve: 'straight', dash: 5 });
    }

    return { series, strokes };
}

function options(data, selectedIndex) {
    const theme = PALETTE[mode()];
    const { series, strokes } = buildSeries(data, selectedIndex, theme.series);

    return {
        chart: {
            type: 'rangeArea',
            height: 320,
            fontFamily: 'inherit',
            foreColor: theme.muted,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: false },
        },
        theme: { mode: mode() },
        series,
        stroke: {
            width: strokes.map((s) => s.width),
            curve: strokes.map((s) => s.curve),
            dashArray: strokes.map((s) => s.dash),
        },
        fill: { opacity: series.map((s) => (s.type === 'rangeArea' ? 0.18 : 1)) },
        markers: { size: 0, hover: { size: 4 } },
        dataLabels: { enabled: false },
        grid: { borderColor: theme.grid, strokeDashArray: 0, xaxis: { lines: { show: false } } },
        xaxis: {
            type: 'datetime',
            labels: { datetimeUTC: false, style: { colors: theme.muted } },
            axisBorder: { color: theme.grid },
            axisTicks: { color: theme.grid },
            crosshairs: { show: true, stroke: { color: theme.muted, width: 1, dashArray: 0 } },
            tooltip: { enabled: false },
        },
        yaxis: { labels: { formatter: (v) => euro.format(v), style: { colors: theme.muted } } },
        // Legend keys mirror the mark: a stroke for lines, a block for the band.
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            labels: { colors: theme.ink },
            markers: { size: 6, shape: series.map((s) => (s.type === 'rangeArea' ? 'square' : 'line')) },
        },
        tooltip: {
            shared: true,
            intersect: false,
            theme: mode(),
            x: { format: 'dd MMM yyyy' },
            y: {
                formatter: (value) => (Array.isArray(value) ? value.map((v) => euro.format(v)).join(' – ') : euro.format(value)),
            },
        },
        annotations: {
            xaxis: [{ x: data.today, borderColor: theme.muted, strokeDashArray: 3, label: { text: 'Today', borderWidth: 0, style: { background: 'transparent', color: theme.ink } } }],
        },
    };
}

/**
 * Mounts the chart into a [data-price-chart] element that contains a
 * <script type="application/json" data-chart-data> and an optional
 * <select data-forecast-select>.
 */
export async function mountPriceChart(root) {
    if (!root || root.dataset.mounted) {
        return;
    }
    root.dataset.mounted = '1';

    // Loaded on demand: only product pages pay for the charting library.
    const { default: ApexCharts } = await import('apexcharts');

    const data = JSON.parse(root.querySelector('[data-chart-data]').textContent);
    const select = root.querySelector('[data-forecast-select]');
    let selectedIndex = Number(select?.value ?? 0);

    const chart = new ApexCharts(root.querySelector('[data-chart-canvas]'), options(data, selectedIndex));
    chart.render();

    const refresh = () => chart.updateOptions(options(data, selectedIndex), true, false);

    select?.addEventListener('change', () => {
        selectedIndex = Number(select.value);
        refresh();
    });

    // Follow the app's light/dark switch.
    const observer = new MutationObserver(refresh);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    document.addEventListener('livewire:navigating', () => {
        observer.disconnect();
        chart.destroy();
    }, { once: true });
}
