export default {
    methods: {
        showHover(group, event) {
            const el = this.$el;
            const rect = el.getBoundingClientRect();
            const tooltipWidth = 180;
            const pad = 8;
            let x = event.clientX - rect.left;
            let y = event.clientY - rect.top;

            x = Math.min(Math.max(x, pad), Math.max(pad, rect.width - tooltipWidth * 0.65));
            y = Math.max(y, pad);

            this.hover = {
                point: group.point,
                x,
                y,
            };
        },

        formatPointValue(value) {
            return this.formatChartValue(value, this.format);
        },
    },
};
