/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * Chart.js - Make globally available for Alpine components
 */
import Chart from 'chart.js/auto';
window.Chart = Chart;

/**
 * Alpine.js chart components
 */
import './alpine-charts';

/**
 * Alpine.js animated counter components
 */
import './alpine-counter';
