import { mountPriceChart } from './price-chart.js';

// Exposed for Livewire @script blocks, which run as plain inline scripts.
window.PriceWatch = { mountPriceChart };
window.dispatchEvent(new Event('pricewatch:ready'));
