import _ from 'lodash';
import moment from 'moment-timezone';

export default {
    computed: {
        Telescope() {
            return Telescope;
        },

        periodHours() {
            const hours = Number(this.$route.query.hours || 24);

            return [1, 24, 168, 336, 720].includes(hours) ? hours : 24;
        },

        periodLabel() {
            return {
                1: '1 hour',
                24: '24 hours',
                168: '7 days',
                336: '14 days',
                720: '30 days',
            }[this.periodHours];
        },
    },

    methods: {
        /**
         * Show the time ago format for the given time.
         */
        timeAgo(time) {
            moment.updateLocale('en', {
                relativeTime: {
                    future: 'in %s',
                    past: '%s ago',
                    s: (number) => number + 's ago',
                    ss: '%ds ago',
                    m: '1m ago',
                    mm: '%dm ago',
                    h: '1h ago',
                    hh: '%dh ago',
                    d: '1d ago',
                    dd: '%dd ago',
                    M: 'a month ago',
                    MM: '%d months ago',
                    y: 'a year ago',
                    yy: '%d years ago',
                },
            });

            let secondsElapsed = moment().diff(time, 'seconds');
            let dayStart = moment('2018-01-01').startOf('day').seconds(secondsElapsed);

            if (secondsElapsed > 300) {
                return moment(time).fromNow(true);
            } else if (secondsElapsed < 60) {
                return dayStart.format('s') + 's ago';
            } else {
                return dayStart.format('m:ss') + 'm ago';
            }
        },

        /**
         * Show the time in local time.
         */
        localTime(time) {
            return moment(time).local().format('MMMM Do YYYY, h:mm:ss A');
        },

        /**
         * Truncate the given string.
         */
        truncate(string, length = 70) {
            return _.truncate(string, {
                length: length,
                separator: /,? +/,
            });
        },

        setPeriod(hours) {
            this.$router.replace({
                query: Object.assign({}, this.$route.query, { hours }),
            });
        },

        formatDuration(value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }

            const ms = Number(value);

            if (Number.isNaN(ms)) {
                return '—';
            }

            if (ms >= 1000) {
                const seconds = ms / 1000;

                if (seconds >= 10 || Math.abs(seconds - Math.round(seconds)) < 0.005) {
                    return Math.round(seconds) + 's';
                }

                return String(Math.round(seconds * 100) / 100) + 's';
            }

            if (ms >= 100) {
                return Math.round(ms) + 'ms';
            }

            return String(Math.round(ms * 10) / 10) + 'ms';
        },

        formatCount(value) {
            const n = Number(value || 0);

            if (n >= 1000) {
                const thousands = n / 1000;
                const compact = thousands >= 10 ? Math.round(thousands) : Math.round(thousands * 10) / 10;

                return compact + 'k';
            }

            return n.toLocaleString();
        },

        formatBucketLabel(bucket) {
            if (!bucket) {
                return '';
            }

            const parsed = moment.utc(bucket, 'YYYY-MM-DD HH:mm:ss', true);

            return (parsed.isValid() ? parsed : moment.utc(bucket)).format('MMM D, YYYY, HH:mm:ss') + ' UTC';
        },

        /**
         * Creates a debounced function that delays invoking a callback.
         */
        debouncer: _.debounce((callback) => callback(), 500),

        /**
         * Determine if a failed request may be polled again.
         */
        mayRetry(error, signal) {
            if (signal.aborted) return false;

            // The server answered, so another attempt returns the same result...
            if (error.response) {
                this.alertError(
                    'Telescope stopped listening for new entries. The server returned a ' +
                        error.response.status +
                        ' response.'
                );

                return false;
            }

            return true;
        },

        /**
         * Show an error message.
         */
        alertError(message) {
            this.$root.alert.type = 'error';
            this.$root.alert.autoClose = false;
            this.$root.alert.message = message;
        },

        /**
         * Show a success message.
         */
        alertSuccess(message, autoClose) {
            this.$root.alert.type = 'success';
            this.$root.alert.autoClose = autoClose;
            this.$root.alert.message = message;
        },

        /**
         * Show confirmation message.
         */
        alertConfirm(message, success, failure) {
            this.$root.alert.type = 'confirmation';
            this.$root.alert.autoClose = false;
            this.$root.alert.message = message;
            this.$root.alert.confirmationProceed = success;
            this.$root.alert.confirmationCancel = failure;
        },
    },
};
