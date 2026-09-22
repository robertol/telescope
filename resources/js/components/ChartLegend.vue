<script type="text/ecmascript-6">
export default {
    props: {
        series: { type: Array, required: true },
        totals: { type: Object, default: null },
        format: { type: String, default: 'count' },
    },
};
</script>

<template>
    <div class="nw-legend">
        <div v-for="item in series" :key="item.key" class="nw-legend-item">
            <span class="nw-legend-label">
                <span class="nw-legend-dot" :style="{ background: item.color }"></span>
                {{ item.label }}
            </span>
            <strong v-if="totals">{{ formatChartValue(totals[item.key], format) }}</strong>
        </div>
    </div>
</template>
