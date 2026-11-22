// Dimuat hanya di halaman Analytics (entry Vite terpisah) — tidak membebani halaman lain.
import Chart from 'chart.js/auto';

const css = getComputedStyle(document.documentElement);
const brand = (css.getPropertyValue('--color-brand-700') || '#1b5434').trim();
const brandLight = (css.getPropertyValue('--color-brand-200') || '#bbdbc9').trim();
// Palet kategorikal netral yang tetap terbaca untuk buta warna umum.
const palette = [brand, '#2563eb', '#d97706', '#7c3aed', '#0891b2', '#dc2626', '#65a30d', '#db2777', '#475569', '#ca8a04'];

Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
Chart.defaults.color = '#4b5563';
Chart.defaults.plugins.legend.labels.boxWidth = 12;

const data = JSON.parse(document.getElementById('analytics-data')?.textContent || '{}');
const make = (id, config) => { const el = document.getElementById(id); if (el) new Chart(el, config); };
const noGrid = { grid: { display: false } };
const intTicks = { beginAtZero: true, ticks: { precision: 0 } };

make('chart-trend', {
    type: 'line',
    data: { labels: data.trend.labels, datasets: [
        { label: 'Tiket masuk', data: data.trend.created, borderColor: brand, backgroundColor: brand, tension: 0.25, pointRadius: 2 },
        { label: 'Tiket selesai', data: data.trend.resolved, borderColor: '#2563eb', backgroundColor: '#2563eb', borderDash: [5, 4], tension: 0.25, pointRadius: 2 },
    ] },
    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { x: noGrid, y: intTicks } },
});
make('chart-unit', {
    type: 'bar',
    data: { labels: data.byUnit.map(r => r.code), datasets: [{ label: 'Tiket', data: data.byUnit.map(r => r.total), backgroundColor: brand, borderRadius: 4 }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { title: (i) => data.byUnit[i[0].dataIndex].name } } }, scales: { x: intTicks, y: noGrid } },
});
make('chart-category', {
    type: 'bar',
    data: { labels: data.byCategory.map(r => r.name), datasets: [{ label: 'Tiket', data: data.byCategory.map(r => r.total), backgroundColor: brandLight, borderColor: brand, borderWidth: 1, borderRadius: 4 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: noGrid, y: intTicks } },
});
make('chart-status', {
    type: 'doughnut',
    data: { labels: Object.keys(data.byStatus), datasets: [{ data: Object.values(data.byStatus), backgroundColor: ['#2563eb', '#d97706', '#7c3aed', brand, '#9ca3af'] }] },
    options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '60%' },
});
make('chart-priority', {
    type: 'bar',
    data: { labels: Object.keys(data.byPriority), datasets: [{ label: 'Tiket', data: Object.values(data.byPriority), backgroundColor: ['#9ca3af', '#0284c7', '#ea580c', '#dc2626'], borderRadius: 4 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: noGrid, y: intTicks } },
});
