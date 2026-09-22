<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from '../../components/BarChart.vue';
import LineChart from '../../components/LineChart.vue';
import ChartPane from '../../components/ChartPane.vue';
import PeriodSelector from '../../components/PeriodSelector.vue';
import AutoRefresh from '../../mixins/autoRefresh';
import { STATUS_SERIES, DURATION_SERIES, JOB_SERIES, RESOURCE_COLORS, CHART_HEIGHT } from '../../charts/series';

export default {
    components: { BarChart, LineChart, ChartPane, PeriodSelector },

    mixins: [AutoRefresh],

    data() {
        return {
            dashboard: null,
            ready: false,
            statusSeries: STATUS_SERIES,
            durationSeries: DURATION_SERIES,
            jobSeries: JOB_SERIES,
            exceptionColor: RESOURCE_COLORS.exception,
            dashboardHeight: CHART_HEIGHT.dashboard,
            paneHeight: CHART_HEIGHT.pane,
            sparklineHeight: CHART_HEIGHT.sparkline,
        };
    },

    computed: {
        requestBuckets() {
            return this.dashboard ? this.dashboard.requests.buckets : [];
        },

        jobBuckets() {
            return this.dashboard ? this.dashboard.jobs.buckets : [];
        },

        exceptionBuckets() {
            return this.dashboard && this.dashboard.exceptions.timeline ? this.dashboard.exceptions.timeline : [];
        },

        firstBucket() {
            return this.requestBuckets.length ? this.requestBuckets[0].bucket : null;
        },

        lastBucket() {
            return this.requestBuckets.length ? this.requestBuckets[this.requestBuckets.length - 1].bucket : null;
        },

        jobFirstBucket() {
            return this.jobBuckets.length ? this.jobBuckets[0].bucket : null;
        },

        jobLastBucket() {
            return this.jobBuckets.length ? this.jobBuckets[this.jobBuckets.length - 1].bucket : null;
        },

        exceptionFirstBucket() {
            return this.exceptionBuckets.length ? this.exceptionBuckets[0].bucket : null;
        },

        exceptionLastBucket() {
            return this.exceptionBuckets.length ? this.exceptionBuckets[this.exceptionBuckets.length - 1].bucket : null;
        },

        jobTotals() {
            if (!this.dashboard) {
                return {};
            }

            return {
                processed: this.dashboard.jobs.processed,
                pending: this.dashboard.jobs.pending,
                failed: this.dashboard.jobs.failed,
            };
        },

        durationRange() {
            if (!this.dashboard) {
                return '';
            }

            return (
                this.formatDuration(this.dashboard.requests.duration.min) +
                ' – ' +
                this.formatDuration(this.dashboard.requests.duration.max)
            );
        },

        slowRoutesTotal() {
            if (!this.dashboard) {
                return 0;
            }

            return Number(this.dashboard.slow_routes_total || this.dashboard.slow_routes.length || 0);
        },

        slowRoutesLink() {
            return {
                path: '/requests',
                query: {
                    min_duration: this.dashboard && this.dashboard.slow_route_threshold_ms
                        ? this.dashboard.slow_route_threshold_ms
                        : 1000,
                    hours: this.periodHours,
                },
            };
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
    },

    mounted() {
        document.title = 'Dashboard - Telescope';
        this.load();
    },

    methods: {
        load(options) {
            const silent = Boolean(options && options.silent);

            if (!silent) {
                this.ready = false;
            }

            const params = { hours: this.periodHours };

            if (silent) {
                params.fresh = 1;
            }

            return axios
                .get(Telescope.basePath + '/telescope-api/dashboard', {
                    params,
                    signal: this.autoRefreshSignal(),
                })
                .then((response) => {
                    this.dashboard = response.data;
                    this.ready = true;
                })
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') {
                        return;
                    }

                    this.ready = true;
                });
        },

        routeTone(index) {
            return ['#34d399', '#e8a54b', '#e85d6c', '#8b93a7'][index] || '#8b93a7';
        },
    },
};
</script>

<template>
    <div class="nw-dashboard">
        <div class="nw-dashboard-head">
            <h1 class="nw-page-title mb-0">Dashboard</h1>
            <period-selector></period-selector>
        </div>

        <div v-if="!ready" class="card p-5 text-center text-muted">Fetching...</div>

        <template v-else-if="dashboard">
            <div class="nw-shell">
                <div class="nw-shell-head">
                    <div class="nw-shell-title">Activity</div>
                    <router-link to="/requests" class="nw-shell-action">Requests ↗</router-link>
                </div>
                <div class="nw-shell-grid nw-shell-grid-2">
                    <chart-pane
                        kicker="Requests"
                        :metric="formatCount(dashboard.requests.total)"
                        :series="statusSeries"
                        :totals="dashboard.requests.status"
                        :first-bucket="firstBucket"
                        :last-bucket="lastBucket"
                    >
                        <bar-chart :points="requestBuckets" :series="statusSeries" :height="dashboardHeight"></bar-chart>
                    </chart-pane>
                    <chart-pane
                        kicker="Duration"
                        :metric="durationRange"
                        :series="durationSeries"
                        :totals="dashboard.requests.duration"
                        format="duration"
                        :first-bucket="firstBucket"
                        :last-bucket="lastBucket"
                    >
                        <line-chart :points="requestBuckets" :series="durationSeries" :height="dashboardHeight"></line-chart>
                    </chart-pane>
                </div>
            </div>

            <div class="nw-shell">
                <div class="nw-shell-head">
                    <div class="nw-shell-title">Application</div>
                </div>
                <div class="nw-shell-grid nw-shell-grid-3">
                    <chart-pane
                        kicker="Exceptions"
                        :first-bucket="exceptionFirstBucket"
                        :last-bucket="exceptionLastBucket"
                    >
                        <router-link slot="action" to="/exceptions" class="nw-shell-action">View ↗</router-link>
                        <template slot="copy">
                            <div class="nw-copy">
                                <strong>{{ Number(dashboard.exceptions.total || 0).toLocaleString() }} exceptions</strong>
                                reported in the last {{ periodLabel }}.
                            </div>
                            <div class="text-muted small mt-3 mb-3">
                                Errors have impacted {{ dashboard.exceptions.users || 0 }} users.
                            </div>
                        </template>
                        <bar-chart
                            :points="exceptionBuckets"
                            :color="exceptionColor"
                            :height="sparklineHeight"
                            :stacked="false"
                        ></bar-chart>
                    </chart-pane>
                    <div class="nw-pane">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="nw-kicker mb-0">Routes</div>
                            <router-link
                                v-if="slowRoutesTotal"
                                :to="slowRoutesLink"
                                class="nw-shell-action"
                            >
                                View all ↗
                            </router-link>
                        </div>
                        <div class="nw-copy mb-3">
                            <strong>{{ slowRoutesTotal }} routes exceeded</strong>
                            performance thresholds
                        </div>
                        <div v-if="!slowRoutesTotal" class="text-muted small">
                            No routes above {{ formatDuration(dashboard.slow_route_threshold_ms || 1000) }}
                        </div>
                        <div
                            v-for="(route, index) in dashboard.slow_routes"
                            :key="route.method + route.uri"
                            class="nw-route"
                        >
                            <span class="nw-route-dot" :style="{ background: routeTone(index) }"></span>
                            <span class="nw-route-method">{{ route.method }}</span>
                            <span class="nw-route-path">{{ route.uri }}</span>
                            <span class="ml-auto text-muted small">MAX {{ formatDuration(route.max_duration) }}</span>
                        </div>
                    </div>
                    <chart-pane
                        kicker="Job attempts"
                        :metric="formatCount(dashboard.jobs.processed + dashboard.jobs.pending + dashboard.jobs.failed)"
                        :series="jobSeries"
                        :totals="jobTotals"
                        :first-bucket="jobFirstBucket"
                        :last-bucket="jobLastBucket"
                    >
                        <bar-chart :points="jobBuckets" :series="jobSeries" :height="paneHeight"></bar-chart>
                    </chart-pane>
                </div>
            </div>
        </template>
    </div>
</template>
