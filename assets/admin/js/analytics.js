/**
 * Analytics Dashboard JavaScript
 *
 * Handles Chart.js visualizations and analytics data export
 *
 * @package GHL_CRM_Integration
 */

(function($) {
	'use strict';

	/**
	 * Initialize analytics dashboard
	 * 
	 * @param {Object} analyticsData - Analytics data from PHP
	 */
	function initAnalytics(analyticsData) {
		if (!analyticsData) {
			console.error('Analytics data not provided');
			return;
		}

		const { daily_activity, sync_type_breakdown, hourly_activity, success_failure_rates } = analyticsData;

		// Initialize all charts
		initDailyActivityChart(daily_activity);
		initSyncTypeChart(sync_type_breakdown);
		initSuccessFailureChart(daily_activity);
		initHourlyActivityChart(hourly_activity);
		initSuccessRateTrendChart(success_failure_rates);

		// Initialize event handlers
		initExportButton(analyticsData);
		initRefreshButton();
	}

	/**
	 * Initialize Daily Activity Line Chart
	 */
	function initDailyActivityChart(dailyActivity) {
		const canvas = document.getElementById('ghl-daily-activity-chart');
		if (!canvas) return;

		const dailyActivityLabels = Object.keys(dailyActivity);
		const successData = dailyActivityLabels.map(date => dailyActivity[date].success);
		const failedData = dailyActivityLabels.map(date => dailyActivity[date].failed);

		new Chart(canvas, {
			type: 'line',
			data: {
				labels: dailyActivityLabels.map(date => {
					const d = new Date(date);
					return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
				}),
				datasets: [
					{
						label: 'Successful Syncs',
						data: successData,
						borderColor: '#10b981',
						backgroundColor: 'rgba(16, 185, 129, 0.1)',
						fill: true,
						tension: 0.4
					},
					{
						label: 'Failed Syncs',
						data: failedData,
						borderColor: '#ef4444',
						backgroundColor: 'rgba(239, 68, 68, 0.1)',
						fill: true,
						tension: 0.4
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						mode: 'index',
						intersect: false,
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff',
						borderColor: '#475569',
						borderWidth: 1
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize Sync Type Breakdown Pie Chart
	 */
	function initSyncTypeChart(syncTypeBreakdown) {
		const canvas = document.getElementById('ghl-sync-type-chart');
		if (!canvas) return;

		const syncTypes = Object.keys(syncTypeBreakdown);
		const syncTypeCounts = Object.values(syncTypeBreakdown);
		const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

		new Chart(canvas, {
			type: 'pie',
			data: {
				labels: syncTypes.map(type => type.charAt(0).toUpperCase() + type.slice(1)),
				datasets: [{
					data: syncTypeCounts,
					backgroundColor: colors.slice(0, syncTypes.length),
					borderWidth: 2,
					borderColor: '#fff'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							padding: 15,
							font: {
								size: 13
							}
						}
					},
					tooltip: {
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff',
						callbacks: {
							label: function(context) {
								const total = context.dataset.data.reduce((a, b) => a + b, 0);
								const percentage = ((context.parsed / total) * 100).toFixed(1);
								return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
							}
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize Success vs Failure Overall Pie Chart
	 */
	function initSuccessFailureChart(dailyActivity) {
		const canvas = document.getElementById('ghl-success-failure-chart');
		if (!canvas) return;

		const dailyActivityLabels = Object.keys(dailyActivity);
		const successData = dailyActivityLabels.map(date => dailyActivity[date].success);
		const failedData = dailyActivityLabels.map(date => dailyActivity[date].failed);

		const totalSuccess = successData.reduce((a, b) => a + b, 0);
		const totalFailed = failedData.reduce((a, b) => a + b, 0);

		new Chart(canvas, {
			type: 'pie',
			data: {
				labels: ['Successful', 'Failed'],
				datasets: [{
					data: [totalSuccess, totalFailed],
					backgroundColor: ['#10b981', '#ef4444'],
					borderWidth: 2,
					borderColor: '#fff'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							padding: 15,
							font: {
								size: 13
							}
						}
					},
					tooltip: {
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff',
						callbacks: {
							label: function(context) {
								const total = context.dataset.data.reduce((a, b) => a + b, 0);
								const percentage = ((context.parsed / total) * 100).toFixed(1);
								return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
							}
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize Hourly Activity Bar Chart
	 */
	function initHourlyActivityChart(hourlyActivity) {
		const canvas = document.getElementById('ghl-hourly-activity-chart');
		if (!canvas) return;

		const hourLabels = Array.from({length: 24}, (_, i) => {
			const hour = i % 12 || 12;
			const period = i < 12 ? 'AM' : 'PM';
			return hour + period;
		});

		new Chart(canvas, {
			type: 'bar',
			data: {
				labels: hourLabels,
				datasets: [{
					label: 'Sync Events',
					data: hourlyActivity,
					backgroundColor: '#6366f1',
					borderRadius: 4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff'
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize Success Rate Trend Line Chart
	 */
	function initSuccessRateTrendChart(successFailureRates) {
		const canvas = document.getElementById('ghl-success-rate-trend-chart');
		if (!canvas) return;

		const trendLabels = Object.keys(successFailureRates);
		const successRateData = trendLabels.map(date => successFailureRates[date].success_rate);

		new Chart(canvas, {
			type: 'line',
			data: {
				labels: trendLabels.map(date => {
					const d = new Date(date);
					return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
				}),
				datasets: [{
					label: 'Success Rate %',
					data: successRateData,
					borderColor: '#10b981',
					backgroundColor: 'rgba(16, 185, 129, 0.1)',
					fill: true,
					tension: 0.4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: '#1e293b',
						padding: 12,
						titleColor: '#fff',
						bodyColor: '#fff',
						callbacks: {
							label: function(context) {
								return 'Success Rate: ' + context.parsed.y.toFixed(1) + '%';
							}
						}
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						max: 100,
						ticks: {
							callback: function(value) {
								return value + '%';
							}
						}
					}
				}
			}
		});
	}

	/**
	 * Initialize Export CSV Button
	 */
	function initExportButton(analyticsData) {
		$('#ghl-export-analytics').on('click', function() {
			exportAnalyticsCSV(analyticsData);
		});
	}

	/**
	 * Initialize Refresh Button
	 */
	function initRefreshButton() {
		$('#ghl-refresh-analytics').on('click', function() {
			location.reload();
		});
	}

	/**
	 * Export analytics data to CSV
	 */
	function exportAnalyticsCSV(analyticsData) {
		const { daily_activity, sync_type_breakdown, hourly_activity, success_failure_rates } = analyticsData;
		let csv = [];
		
		// Daily Activity
		csv.push(['Daily Activity - Last 30 Days']);
		csv.push(['Date', 'Successful Syncs', 'Failed Syncs']);
		Object.keys(daily_activity).forEach(date => {
			csv.push([date, daily_activity[date].success, daily_activity[date].failed]);
		});
		csv.push([]);
		
		// Sync Type Breakdown
		csv.push(['Sync Type Breakdown']);
		csv.push(['Type', 'Count']);
		Object.keys(sync_type_breakdown).forEach(type => {
			csv.push([type, sync_type_breakdown[type]]);
		});
		csv.push([]);
		
		// Hourly Activity
		csv.push(['Hourly Activity - Last 24 Hours']);
		csv.push(['Hour', 'Sync Events']);
		hourly_activity.forEach((count, hour) => {
			csv.push([hour + ':00', count]);
		});
		csv.push([]);
		
		// Success Rate Trend
		csv.push(['Success Rate Trend - Last 7 Days']);
		csv.push(['Date', 'Success Rate %']);
		Object.keys(success_failure_rates).forEach(date => {
			csv.push([date, success_failure_rates[date].success_rate]);
		});
		
		// Convert to CSV string
		const csvContent = csv.map(row => row.join(',')).join('\n');
		
		// Download
		const blob = new Blob([csvContent], { type: 'text/csv' });
		const url = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = 'ghl-analytics-' + new Date().toISOString().split('T')[0] + '.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		window.URL.revokeObjectURL(url);
		
		Swal.fire({
			icon: 'success',
			title: 'Exported Successfully',
			text: 'Analytics data has been exported to CSV',
			toast: true,
			position: 'top-end',
			showConfirmButton: false,
			timer: 3000
		});
	}

	// Expose globally for SPA router
	window.initAnalytics = initAnalytics;

})(jQuery);
