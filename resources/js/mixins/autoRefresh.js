export default {
    data() {
        return {
            autoRefreshTimer: null,
            autoRefreshController: null,
        };
    },

    computed: {
        autoRefreshEnabled() {
            return Boolean(this.$root && this.$root.autoLoadsNewEntries);
        },
    },

    watch: {
        autoRefreshEnabled(enabled) {
            if (enabled) {
                this.refreshNow();
                this.scheduleAutoRefresh();

                return;
            }

            this.stopAutoRefresh();
        },
    },

    mounted() {
        if (this.autoRefreshEnabled) {
            this.scheduleAutoRefresh();
        }
    },

    destroyed() {
        this.stopAutoRefresh();
    },

    methods: {
        autoRefreshMs() {
            return 5000;
        },

        scheduleAutoRefresh() {
            this.stopAutoRefreshTimer();

            if (!this.autoRefreshEnabled) {
                return;
            }

            this.autoRefreshTimer = setTimeout(() => {
                Promise.resolve(this.refreshNow()).finally(() => {
                    this.scheduleAutoRefresh();
                });
            }, this.autoRefreshMs());
        },

        stopAutoRefreshTimer() {
            if (this.autoRefreshTimer) {
                clearTimeout(this.autoRefreshTimer);
                this.autoRefreshTimer = null;
            }
        },

        stopAutoRefresh() {
            this.stopAutoRefreshTimer();

            if (this.autoRefreshController) {
                this.autoRefreshController.abort();
                this.autoRefreshController = null;
            }
        },

        autoRefreshSignal() {
            if (this.autoRefreshController) {
                this.autoRefreshController.abort();
            }

            this.autoRefreshController = new AbortController();

            return this.autoRefreshController.signal;
        },

        refreshNow() {
            return this.load({ silent: true });
        },
    },
};
