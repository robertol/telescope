<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from '../../components/BarChart.vue';
import LineChart from '../../components/LineChart.vue';
import PeriodSelector from '../../components/PeriodSelector.vue';

export default {
    components: { BarChart, LineChart, PeriodSelector },

    data() {
        return {
            dashboard: null,
            ready: false,
            statusSeries: [
                { key: '123xx', color: '#8b93a7', label: '1/2/3XX' },
                { key: '4xx', color: '#e8a54b', label: '4XX' },
                { key: '5xx', color: '#e85d6c', label: '5XX' },
            ],
            durationSeries: [
                { key: 'avg', color: '#9aa3af', label: 'AVG' },
                { key: 'p95', color: '#e8a54b', label: 'P95' },
            ],
            jobSeries: [
                { key: 'processed', color: '#8b7cf7', label: 'PROCESSED' },
                { key: 'pending', color: '#e8a54b', label: 'RELEASED' },
                { key: 'failed', color: '#e85d6c', label: 'FAILED' },
            ],
        };
    },

    computed: {
        requestBuckets() {
            return this.dashboard ? this.dashboard.requests.buckets : [];
        },

        jobBuckets() {
            return this.dashboard ? this.dashboard.jobs.buckets : [];
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
        load() {
            this.ready = false;

            axios
                .get(Telescope.basePath + '/telescope-api/dashboard', { params: { hours: this.periodHours } })
                .then((response) => {
                    this.dashboard = response.data;
                    this.ready = true;
                })
                .catch(() => {
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
                    <div class="nw-pane">
                        <div class="nw-pane-head">
                            <div>
                                <div class="nw-kicker">Requests</div>
                                <div class="nw-metric">{{ formatCount(dashboard.requests.total) }}</div>
                            </div>
                            <div class="nw-legend">
                                <div v-for="item in statusSeries" :key="item.key" class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" :style="{ background: item.color }"></span>
                                        {{ item.label }}
                                    </span>
                                    <strong>{{ formatCount(dashboard.requests.status[item.key]) }}</strong>
                                </div>
                            </div>
                        </div>
                        <bar-chart :points="requestBuckets" :series="statusSeries" :height="188"></bar-chart>
                        <div class="nw-axis">
                            <span>{{ formatBucketLabel(firstBucket) }}</span>
                            <span>{{ formatBucketLabel(lastBucket) }}</span>
                        </div>
                    </div>
                    <div class="nw-pane">
                        <div class="nw-pane-head">
                            <div>
                                <div class="nw-kicker">Duration</div>
                                <div class="nw-metric">
                                    {{ formatDuration(dashboard.requests.duration.min) }} –
                                    {{ formatDuration(dashboard.requests.duration.max) }}
                                </div>
                            </div>
                            <div class="nw-legend">
                                <div class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" style="background: #9aa3af"></span>
                                        AVG
                                    </span>
                                    <strong>{{ formatDuration(dashboard.requests.duration.avg) }}</strong>
                                </div>
                                <div class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" style="background: #e8a54b"></span>
                                        P95
                                    </span>
                                    <strong>{{ formatDuration(dashboard.requests.duration.p95) }}</strong>
                                </div>
                            </div>
                        </div>
                        <line-chart :points="requestBuckets" :series="durationSeries" :height="188"></line-chart>
                        <div class="nw-axis">
                            <span>{{ formatBucketLabel(firstBucket) }}</span>
                            <span>{{ formatBucketLabel(lastBucket) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="nw-shell">
                <div class="nw-shell-head">
                    <div class="nw-shell-title">Application</div>
                    <router-link to="/exceptions" class="nw-shell-action">View ↗</router-link>
                </div>
                <div class="nw-shell-grid nw-shell-grid-3">
                    <div class="nw-pane">
                        <div class="nw-kicker">Exceptions</div>
                        <div class="nw-copy">
                            <strong>{{ Number(dashboard.exceptions.total || 0).toLocaleString() }} exceptions</strong>
                            reported in the last {{ periodLabel }}.
                        </div>
                        <div class="text-muted small mt-3">
                            Errors have impacted {{ dashboard.exceptions.users || 0 }} users.
                        </div>
                    </div>
                    <div class="nw-pane">
                        <div class="nw-kicker">Routes</div>
                        <div class="nw-copy mb-3">
                            <strong>{{ dashboard.slow_routes.length }} routes exceeded</strong>
                            performance thresholds
                        </div>
                        <div v-if="!dashboard.slow_routes.length" class="text-muted small">No routes above 1000ms</div>
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
                    <div class="nw-pane">
                        <div class="nw-pane-head">
                            <div>
                                <div class="nw-kicker">Job attempts</div>
                                <div class="nw-metric">{{ formatCount(dashboard.jobs.processed + dashboard.jobs.pending + dashboard.jobs.failed) }}</div>
                            </div>
                            <div class="nw-legend">
                                <div class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" style="background: #8b7cf7"></span>
                                        PROCESSED
                                    </span>
                                    <strong>{{ formatCount(dashboard.jobs.processed) }}</strong>
                                </div>
                                <div class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" style="background: #e8a54b"></span>
                                        RELEASED
                                    </span>
                                    <strong>{{ formatCount(dashboard.jobs.pending) }}</strong>
                                </div>
                                <div class="nw-legend-item">
                                    <span class="nw-legend-label">
                                        <span class="nw-legend-dot" style="background: #e85d6c"></span>
                                        FAILED
                                    </span>
                                    <strong>{{ formatCount(dashboard.jobs.failed) }}</strong>
                                </div>
                            </div>
                        </div>
                        <bar-chart :points="jobBuckets" :series="jobSeries" :height="128"></bar-chart>
                        <div class="nw-axis">
                            <span>{{ formatBucketLabel(jobFirstBucket) }}</span>
                            <span>{{ formatBucketLabel(jobLastBucket) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
