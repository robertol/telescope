<script type="text/ecmascript-6">
import ChartLegend from './ChartLegend.vue';

export default {
    components: { ChartLegend },

    props: {
        kicker: { type: String, default: '' },
        metric: { type: [String, Number], default: null },
        series: { type: Array, default: null },
        totals: { type: Object, default: null },
        format: { type: String, default: 'count' },
        firstBucket: { type: String, default: null },
        lastBucket: { type: String, default: null },
        sparkline: { type: Boolean, default: false },
        split: { type: Boolean, default: false },
    },

    computed: {
        showHead() {
            return Boolean(this.kicker || this.metric != null || (this.series && this.series.length));
        },

        showAxis() {
            return Boolean(this.firstBucket || this.lastBucket);
        },
    },
};
</script>

<template>
    <div class="nw-pane" :class="{ 'nw-pane-sparkline': sparkline, 'nw-pane-split': split }">
        <div class="nw-pane-head" v-if="showHead">
            <div>
                <div v-if="kicker" class="nw-kicker">{{ kicker }}</div>
                <div v-if="metric != null && metric !== ''" class="nw-metric">{{ metric }}</div>
                <slot name="copy"></slot>
            </div>
            <div class="d-flex align-items-start">
                <slot name="action"></slot>
                <chart-legend
                    v-if="series && series.length"
                    :series="series"
                    :totals="totals"
                    :format="format"
                ></chart-legend>
            </div>
        </div>
        <slot></slot>
        <div class="nw-axis" v-if="showAxis">
            <span>{{ formatBucketLabel(firstBucket) }}</span>
            <span>{{ formatBucketLabel(lastBucket) }}</span>
        </div>
    </div>
</template>
