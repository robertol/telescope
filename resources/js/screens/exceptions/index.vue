<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from '../../components/BarChart.vue';
import ChartPane from '../../components/ChartPane.vue';
import PeriodSelector from '../../components/PeriodSelector.vue';
import AutoRefresh from '../../mixins/autoRefresh';
import { RESOURCE_COLORS, CHART_HEIGHT } from '../../charts/series';

export default {
    components: { BarChart, ChartPane, PeriodSelector },

    mixins: [AutoRefresh],

    data() {
        return {
            summary: { handled: 0, unhandled: 0, families: [], timeline: [] },
            search: '',
            status: 'all',
            ready: false,
            exceptionColor: RESOURCE_COLORS.exception,
            sparklineHeight: CHART_HEIGHT.sparkline,
        };
    },

    computed: {
        families() {
            const term = this.search.trim().toLowerCase();

            if (!term) {
                return this.summary.families;
            }

            return this.summary.families.filter((family) => {
                return [family.class, family.message]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(term));
            });
        },

        titleCount() {
            return this.families.length;
        },

        firstBucket() {
            return this.summary.timeline.length ? this.summary.timeline[0].bucket : null;
        },

        lastBucket() {
            const timeline = this.summary.timeline;

            return timeline.length ? timeline[timeline.length - 1].bucket : null;
        },

        volumeTotal() {
            return this.summary.handled + this.summary.unhandled;
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
        status() {
            this.load();
        },
    },

    mounted() {
        document.title = 'Exceptions - Telescope';
        this.load();
    },

    methods: {
        load(options) {
            const silent = Boolean(options && options.silent);
            const params = { hours: this.periodHours };

            if (this.status !== 'all') {
                params.status = this.status;
            }

            return axios
                .get(Telescope.basePath + '/telescope-api/exceptions/summary', {
                    params,
                    signal: this.autoRefreshSignal(),
                })
                .then((response) => {
                    this.summary = response.data;
                    this.ready = true;
                })
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') {
                        return;
                    }
                });
        },
    },
};
</script>

<template>
    <div>
        <div class="nw-dashboard-head">
            <h1 class="nw-page-title mb-0">{{ titleCount }} Exceptions</h1>
            <period-selector></period-selector>
        </div>

        <div class="nw-shell mb-4">
            <chart-pane
                sparkline
                kicker="Volume"
                :metric="formatCount(volumeTotal)"
                :first-bucket="firstBucket"
                :last-bucket="lastBucket"
            >
                <bar-chart
                    :points="summary.timeline"
                    :color="exceptionColor"
                    :height="sparklineHeight"
                    :stacked="false"
                ></bar-chart>
            </chart-pane>
        </div>

        <div class="d-flex align-items-center mb-3">
            <input v-model="search" type="search" class="form-control w-auto mr-3" placeholder="Search class or message" />
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: status === 'all' }" v-on:click="status = 'all'">
                    View all ({{ summary.handled + summary.unhandled }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-muted"
                    :class="{ active: status === 'handled' }"
                    v-on:click="status = 'handled'"
                >
                    Handled ({{ summary.handled }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-muted"
                    :class="{ active: status === 'unhandled' }"
                    v-on:click="status = 'unhandled'"
                >
                    Unhandled ({{ summary.unhandled }})
                </button>
            </div>
        </div>

        <div class="card">
            <div v-if="!ready" class="p-5 text-center text-muted">Fetching...</div>
            <table v-else class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Last seen</th>
                        <th></th>
                        <th>Exception</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Users</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!families.length">
                        <td colspan="6" class="text-center text-muted p-4">No exceptions in this period.</td>
                    </tr>
                    <tr v-for="family in families" :key="family.family_hash">
                        <td class="table-fit text-muted">{{ timeAgo(family.last_seen) }}</td>
                        <td class="table-fit">
                            <span v-if="!family.handled" class="badge badge-danger">UNHANDLED</span>
                        </td>
                        <td>
                            <div class="font-weight-bold">{{ family.class }}</div>
                            <small class="text-muted">{{ truncate(family.message, 120) }}</small>
                        </td>
                        <td class="text-right">{{ family.count }}</td>
                        <td class="text-right">{{ family.users }}</td>
                        <td class="table-fit">
                            <router-link
                                :to="{ name: 'exception-preview', params: { id: family.latest_id } }"
                                class="control-action"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </router-link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
