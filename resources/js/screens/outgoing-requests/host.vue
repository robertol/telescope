<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from '../../components/BarChart.vue';
import LineChart from '../../components/LineChart.vue';
import ChartPane from '../../components/ChartPane.vue';
import PeriodSelector from '../../components/PeriodSelector.vue';
import StylesMixin from '../../mixins/entriesStyles';
import AutoRefresh from '../../mixins/autoRefresh';
import { STATUS_SERIES, DURATION_SERIES, CHART_HEIGHT } from '../../charts/series';

export default {
    components: { BarChart, LineChart, ChartPane, PeriodSelector },

    mixins: [StylesMixin, AutoRefresh],

    data() {
        return {
            payload: null,
            filter: 'all',
            ready: false,
            statusSeries: STATUS_SERIES,
            durationSeries: DURATION_SERIES,
            paneHeight: CHART_HEIGHT.pane,
        };
    },

    computed: {
        host() {
            return this.$route.params.host;
        },

        avg() {
            return this.payload && this.payload.requests.duration.avg;
        },

        p95() {
            return this.payload && this.payload.requests.duration.p95;
        },

        requestBuckets() {
            return this.payload ? this.payload.requests.buckets : [];
        },

        firstBucket() {
            return this.requestBuckets.length ? this.requestBuckets[0].bucket : null;
        },

        lastBucket() {
            return this.requestBuckets.length ? this.requestBuckets[this.requestBuckets.length - 1].bucket : null;
        },

        durationRange() {
            if (!this.payload) {
                return '';
            }

            return (
                this.formatDuration(this.payload.requests.duration.min) +
                ' – ' +
                this.formatDuration(this.payload.requests.duration.max)
            );
        },

        entries() {
            if (!this.payload) {
                return [];
            }

            return this.payload.entries.filter((entry) => {
                const status = Number(entry.content.response_status || 0);
                const duration = Number(entry.content.duration || 0);

                if (this.filter === '2xx') return status >= 200 && status < 300;
                if (this.filter === '4xx') return status >= 400 && status < 500;
                if (this.filter === '5xx') return status >= 500;
                if (this.filter === 'avg') return this.avg != null && duration >= this.avg;
                if (this.filter === 'p95') return this.p95 != null && duration >= this.p95;

                return true;
            });
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
        host() {
            this.load();
        },
    },

    mounted() {
        document.title = this.host + ' - Telescope';
        this.load();
    },

    methods: {
        load(options) {
            const silent = Boolean(options && options.silent);

            if (!silent) {
                this.ready = false;
            }

            return axios
                .get(Telescope.basePath + '/telescope-api/outgoing-requests', {
                    params: { host: this.host, hours: this.periodHours },
                    signal: this.autoRefreshSignal(),
                })
                .then((response) => {
                    this.payload = response.data;
                    this.ready = true;
                })
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') {
                        return;
                    }

                    this.ready = true;
                });
        },

        rowClass(entry) {
            const status = Number(entry.content.response_status || 0);

            if (status >= 500) return 'nw-row-5xx';
            if (status >= 400) return 'nw-row-4xx';

            return '';
        },
    },
};
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="nw-page-title mb-0">{{ host }}</h1>
            <period-selector></period-selector>
        </div>

        <div v-if="!ready || !payload" class="card p-5 text-center text-muted">Fetching...</div>

        <div v-else>
            <div class="nw-shell mb-4">
                <div class="nw-shell-grid nw-shell-grid-2">
                    <chart-pane
                        kicker="Requests"
                        :metric="formatCount(payload.requests.total)"
                        :series="statusSeries"
                        :totals="payload.requests.status"
                        :first-bucket="firstBucket"
                        :last-bucket="lastBucket"
                    >
                        <bar-chart :points="requestBuckets" :series="statusSeries" :height="paneHeight"></bar-chart>
                    </chart-pane>
                    <chart-pane
                        kicker="Duration"
                        :metric="durationRange"
                        :series="durationSeries"
                        :totals="payload.requests.duration"
                        format="duration"
                        :first-bucket="firstBucket"
                        :last-bucket="lastBucket"
                    >
                        <line-chart :points="requestBuckets" :series="durationSeries" :height="paneHeight"></line-chart>
                    </chart-pane>
                </div>
            </div>

            <div class="btn-group mb-3">
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === 'all' }" v-on:click="filter = 'all'">
                    View all
                </button>
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === 'avg' }" v-on:click="filter = 'avg'">
                    ≥ AVG
                </button>
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === 'p95' }" v-on:click="filter = 'p95'">
                    ≥ P95
                </button>
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === '2xx' }" v-on:click="filter = '2xx'">
                    2xx
                </button>
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === '4xx' }" v-on:click="filter = '4xx'">
                    4xx
                </button>
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: filter === '5xx' }" v-on:click="filter = '5xx'">
                    5xx
                </button>
            </div>

            <div class="card">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Source</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>URL</th>
                            <th class="text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries"
                            :key="entry.id"
                            :class="rowClass(entry)"
                            class="cursor-pointer"
                            v-on:click="$router.push({ name: 'client-request-preview', params: { id: entry.id } })"
                        >
                            <td class="table-fit text-muted">{{ timeAgo(entry.created_at) }}</td>
                            <td class="table-fit">
                                <span v-if="entry.content.source" class="badge badge-secondary">
                                    {{ String(entry.content.source).toUpperCase() }}
                                </span>
                            </td>
                            <td class="table-fit">
                                <span class="badge" :class="'badge-' + requestMethodClass(entry.content.method)">
                                    {{ entry.content.method }}
                                </span>
                            </td>
                            <td class="table-fit">
                                <span class="badge" :class="'badge-' + requestStatusClass(entry.content.response_status)">
                                    {{ entry.content.response_status || 'N/A' }}
                                </span>
                            </td>
                            <td>{{ truncate(entry.content.uri, 80) }}</td>
                            <td class="text-right table-fit">{{ formatDuration(entry.content.duration) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
