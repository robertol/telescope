<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from './BarChart.vue';
import ChartPane from './ChartPane.vue';
import PeriodSelector from './PeriodSelector.vue';
import AutoRefresh from '../mixins/autoRefresh';
import { RESOURCE_COLORS, CHART_HEIGHT } from '../charts/series';

export default {
    components: { BarChart, ChartPane, PeriodSelector },

    mixins: [AutoRefresh],

    props: {
        type: { type: String, required: true },
        title: { type: String, required: true },
    },

    data() {
        return {
            payload: null,
            ready: false,
            color: RESOURCE_COLORS[this.type] || RESOURCE_COLORS.request,
            sparklineHeight: CHART_HEIGHT.sparkline,
        };
    },

    computed: {
        timeline() {
            return this.payload ? this.payload.timeline : [];
        },

        total() {
            return this.payload ? this.payload.total : 0;
        },

        firstBucket() {
            return this.timeline.length ? this.timeline[0].bucket : null;
        },

        lastBucket() {
            return this.timeline.length ? this.timeline[this.timeline.length - 1].bucket : null;
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
    },

    mounted() {
        this.load();
    },

    methods: {
        load(options) {
            const silent = Boolean(options && options.silent);
            const params = { hours: this.periodHours };

            if (silent) {
                params.fresh = 1;
            }

            return axios
                .get(Telescope.basePath + '/telescope-api/summaries/' + this.type, {
                    params,
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
    },
};
</script>

<template>
    <div>
        <div class="nw-dashboard-head">
            <h1 class="nw-page-title mb-0">{{ title }}</h1>
            <period-selector></period-selector>
        </div>
        <div class="nw-shell mb-4">
            <chart-pane
                sparkline
                kicker="Volume"
                :metric="ready ? formatCount(total) : '…'"
                :first-bucket="firstBucket"
                :last-bucket="lastBucket"
            >
                <bar-chart
                    :points="timeline"
                    :color="color"
                    :height="sparklineHeight"
                    format="count"
                    :stacked="false"
                ></bar-chart>
            </chart-pane>
        </div>
    </div>
</template>
