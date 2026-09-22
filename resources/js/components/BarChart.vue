<script type="text/ecmascript-6">
import ChartHover from '../mixins/chartHover';
import { RESOURCE_COLORS } from '../charts/series';

export default {
    mixins: [ChartHover],

    props: {
        points: { type: Array, default: () => [] },
        color: { type: String, default: RESOURCE_COLORS.request },
        series: { type: Array, default: null },
        height: { type: Number, default: 128 },
        stacked: { type: Boolean, default: true },
        format: { type: String, default: 'count' },
    },

    data() {
        return {
            hover: null,
        };
    },

    computed: {
        keys() {
            return this.series && this.series.length ? this.series : [{ key: 'total', color: this.color }];
        },

        plotHeight() {
            return this.height - 16;
        },

        isStacked() {
            return this.stacked && this.keys.length > 0;
        },

        maxValue() {
            if (this.isStacked) {
                const sums = this.points.map((point) =>
                    this.keys.reduce((sum, item) => sum + Number(point[item.key] || 0), 0)
                );

                return Math.max(...sums, 1);
            }

            const values = this.points.flatMap((point) => this.keys.map((item) => Number(point[item.key] || 0)));

            return Math.max(...values, 1);
        },

        groups() {
            const count = Math.max(this.points.length, 1);
            const groupWidth = 1000 / count;

            if (this.isStacked) {
                const barWidth = Math.min(6, Math.max(0.65, groupWidth * 0.55));
                const offset = Math.max(0, (groupWidth - barWidth) / 2);

                return this.points.map((point, index) => {
                    let yCursor = this.plotHeight;
                    const bars = this.keys.map((item) => {
                        const value = Number(point[item.key] || 0);
                        const barHeight = value > 0 ? (value / this.maxValue) * (this.plotHeight - 6) : 0;

                        yCursor -= barHeight;

                        return {
                            key: item.key,
                            color: item.color,
                            value,
                            x: index * groupWidth + offset,
                            y: yCursor,
                            width: barWidth,
                            height: barHeight,
                        };
                    });

                    return {
                        index,
                        point,
                        bars,
                        x: index * groupWidth,
                        width: groupWidth,
                    };
                });
            }

            const barWidth = Math.min(3.1, Math.max(0.65, groupWidth * 0.22));
            const gap = Math.min(0.45, barWidth * 0.18);
            const cluster = this.keys.length * barWidth + Math.max(0, this.keys.length - 1) * gap;
            const offset = Math.max(0, (groupWidth - cluster) / 2);

            return this.points.map((point, index) => {
                const bars = this.keys.map((item, seriesIndex) => {
                    const value = Number(point[item.key] || 0);
                    const barHeight = value > 0 ? Math.max(2.2, (value / this.maxValue) * (this.plotHeight - 6)) : 0;

                    return {
                        key: item.key,
                        color: item.color,
                        value,
                        x: index * groupWidth + offset + seriesIndex * (barWidth + gap),
                        y: this.plotHeight - barHeight,
                        width: barWidth,
                        height: barHeight,
                    };
                });

                return {
                    index,
                    point,
                    bars,
                    x: index * groupWidth,
                    width: groupWidth,
                };
            });
        },

        tickStep() {
            return Math.max(1, Math.floor(this.groups.length / 24));
        },
    },
};
</script>

<template>
    <div class="nw-chart" :style="{ height: height + 'px' }" v-on:mouseleave="hover = null">
        <svg :viewBox="'0 0 1000 ' + height" preserveAspectRatio="none">
            <line
                class="nw-chart-axis"
                x1="0"
                :y1="plotHeight"
                x2="1000"
                :y2="plotHeight"
                stroke-width="0.6"
                vector-effect="non-scaling-stroke"
            />
            <g v-for="group in groups" :key="group.index">
                <rect
                    class="nw-chart-hit"
                    :x="group.x"
                    y="0"
                    :width="group.width"
                    :height="plotHeight"
                    fill="transparent"
                    v-on:mousemove="showHover(group, $event)"
                />
                <rect
                    v-for="bar in group.bars"
                    :key="bar.key"
                    :x="bar.x"
                    :y="bar.y"
                    :width="bar.width"
                    :height="bar.height"
                    :fill="bar.color"
                    rx="0.5"
                />
            </g>
            <g v-for="group in groups" :key="'tick-' + group.index">
                <g v-if="group.index % tickStep === 0">
                    <rect
                        v-for="n in 4"
                        :key="n"
                        class="nw-chart-tick"
                        :x="group.x + group.width / 2 + (n - 2.5) * 2.1"
                        :y="plotHeight + 5"
                        width="1.35"
                        height="3.2"
                    />
                </g>
            </g>
        </svg>
        <div v-if="hover" class="nw-tooltip" :style="{ left: hover.x + 'px', top: hover.y + 'px' }">
            <div class="nw-tooltip-time">{{ formatBucketLabel(hover.point.bucket) }}</div>
            <div v-for="item in keys" :key="item.key" class="nw-tooltip-row">
                <span class="nw-legend-dot" :style="{ background: item.color }"></span>
                <span>{{ item.label || item.key }}</span>
                <strong>{{ formatPointValue(hover.point[item.key] || 0) }}</strong>
            </div>
        </div>
    </div>
</template>
