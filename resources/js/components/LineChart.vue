<script type="text/ecmascript-6">
export default {
    props: {
        points: { type: Array, default: () => [] },
        series: { type: Array, required: true },
        height: { type: Number, default: 128 },
    },

    data() {
        return {
            hover: null,
        };
    },

    computed: {
        plotHeight() {
            return this.height - 16;
        },

        maxValue() {
            const values = this.points.flatMap((point) =>
                this.series.map((item) => {
                    const raw = point[item.key];

                    return raw === null || raw === undefined ? null : Number(raw);
                })
            ).filter((value) => value !== null && !Number.isNaN(value));

            return Math.max(...values, 1);
        },

        polylines() {
            const count = Math.max(this.points.length, 1);

            return this.series.map((item) => {
                const coords = this.points
                    .map((point, index) => {
                        const raw = point[item.key];

                        if (raw === null || raw === undefined || raw === '') {
                            return null;
                        }

                        const value = Number(raw);

                        if (Number.isNaN(value)) {
                            return null;
                        }

                        const x = count === 1 ? 500 : (index / (count - 1)) * 1000;
                        const y = this.plotHeight - (value / this.maxValue) * (this.plotHeight - 8);

                        return { x, y, value, point };
                    })
                    .filter(Boolean);

                return {
                    ...item,
                    coords,
                    d: coords.map((coord, index) => (index === 0 ? 'M' : 'L') + coord.x + ' ' + coord.y).join(' '),
                };
            });
        },

        groups() {
            const count = Math.max(this.points.length, 1);
            const width = 1000 / count;

            return this.points.map((point, index) => ({
                point,
                x: index * width,
                width,
            }));
        },

        tickStep() {
            return Math.max(1, Math.floor(this.groups.length / 24));
        },
    },

    methods: {
        showHover(group, event) {
            this.hover = {
                point: group.point,
                x: event.offsetX,
                y: event.offsetY,
            };
        },
    },
};
</script>

<template>
    <div class="nw-chart" :style="{ height: height + 'px' }" v-on:mouseleave="hover = null">
        <svg :viewBox="'0 0 1000 ' + height" preserveAspectRatio="none">
            <line
                x1="0"
                :y1="plotHeight"
                x2="1000"
                :y2="plotHeight"
                stroke="#2a2a32"
                stroke-width="0.6"
                vector-effect="non-scaling-stroke"
            />
            <path
                v-for="line in polylines"
                :key="line.key"
                :d="line.d"
                fill="none"
                :stroke="line.color"
                :stroke-width="line.key === 'p95' ? 2.1 : 1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
            />
            <rect
                v-for="(group, index) in groups"
                :key="'hit-' + index"
                class="nw-chart-hit"
                :x="group.x"
                y="0"
                :width="group.width"
                :height="plotHeight"
                fill="transparent"
                v-on:mousemove="showHover(group, $event)"
            />
            <g v-for="(group, index) in groups" :key="'tick-' + index">
                <g v-if="index % tickStep === 0">
                    <rect
                        v-for="n in 4"
                        :key="n"
                        :x="group.x + group.width / 2 + (n - 2.5) * 2.1"
                        :y="plotHeight + 5"
                        width="1.35"
                        height="3.2"
                        fill="#3f3f46"
                    />
                </g>
            </g>
        </svg>
        <div v-if="hover" class="nw-tooltip" :style="{ left: hover.x + 'px', top: hover.y + 'px' }">
            <div class="nw-tooltip-time">{{ formatBucketLabel(hover.point.bucket) }}</div>
            <div v-for="item in series" :key="item.key" class="nw-tooltip-row">
                <span class="nw-legend-dot" :style="{ background: item.color }"></span>
                <span>{{ item.label }}</span>
                <strong>{{ formatDuration(hover.point[item.key]) }}</strong>
            </div>
        </div>
    </div>
</template>
