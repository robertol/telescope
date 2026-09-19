import Vue from 'vue';
import Base from './base';
import axios from 'axios';
import Routes from './routes';
import VueRouter from 'vue-router';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';
import moment from 'moment-timezone';
import popper from 'popper.js';
import relatedEntries from './components/RelatedEntries.vue';
import indexScreen from './components/IndexScreen.vue';
import monitoredRequests from './components/MonitoredRequests.vue';
import previewScreen from './components/PreviewScreen.vue';
import alert from './components/Alert.vue';
import copyClipboard from './components/CopyClipboard.vue';
import sidebar from './components/Sidebar.vue';

import 'bootstrap';

const LOCALSTORAGE_AUTOLOAD_KEY = 'telescopeAutoLoadsNewEntries';

let token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

Vue.use(VueRouter);

window.Popper = popper;

moment.tz.setDefault(Telescope.timezone);

window.Telescope.basePath = '/' + window.Telescope.path;

let routerBasePath = window.Telescope.basePath + '/';

if (window.Telescope.path === '' || window.Telescope.path === '/') {
    routerBasePath = '/';
    window.Telescope.basePath = '';
}

const router = new VueRouter({
    routes: Routes,
    mode: 'history',
    base: routerBasePath,
});

Vue.component('vue-json-pretty', VueJsonPretty);
Vue.component('related-entries', relatedEntries);
Vue.component('index-screen', indexScreen);
Vue.component('monitored-requests', monitoredRequests);
Vue.component('preview-screen', previewScreen);
Vue.component('alert', alert);
Vue.component('copy-clipboard', copyClipboard);
Vue.component('sidebar', sidebar);

Vue.mixin(Base);

new Vue({
    el: '#telescope',

    router,

    data() {
        return {
            alert: {
                type: null,
                autoClose: 0,
                message: '',
                confirmationProceed: null,
                confirmationCancel: null,
            },

            autoLoadsNewEntries: localStorage[LOCALSTORAGE_AUTOLOAD_KEY] === '1',

            recording: Telescope.recording,

            pruneHours: 48,

            pruning: false,

            pruneAllowed: null,

            pruneLimit: 20000,

            pruneCheckTimer: null,

            sidebarOpen: false,
        };
    },

    computed: {
        pruneLabel() {
            if (this.pruning) {
                return '...';
            }

            if (this.pruneAllowed === false) {
                return 'Bloqueado';
            }

            return 'Prune';
        },

        pruneTitle() {
            if (this.pruneAllowed === false) {
                return 'Mais de ' + this.pruneLimit + ' registros nesse período. O prune pelo navegador foi bloqueado para não segurar um worker.';
            }

            if (this.pruneAllowed === null) {
                return 'Verificando volume de registros...';
            }

            return 'Apagar registros mais antigos que essas horas';
        },
    },

    watch: {
        pruneHours() {
            this.schedulePruneCheck();
        },

        $route() {
            this.sidebarOpen = false;
        },
    },

    created() {
        window.addEventListener('keydown', this.keydownListener);
        this.refreshPruneAvailability();
    },

    destroyed() {
        window.removeEventListener('keydown', this.keydownListener);
    },

    methods: {
        autoLoadNewEntries() {
            this.autoLoadsNewEntries = !this.autoLoadsNewEntries;
            localStorage[LOCALSTORAGE_AUTOLOAD_KEY] = Number(this.autoLoadsNewEntries);
        },

        toggleRecording() {
            axios.post(Telescope.basePath + '/telescope-api/toggle-recording');

            window.Telescope.recording = !Telescope.recording;
            this.recording = !this.recording;
        },

        clearEntries(shouldConfirm = true, preserveMonitoring = false) {
            if (shouldConfirm) {
                return;
            }

            axios.delete(Telescope.basePath + '/telescope-api/entries', {
                data: {preserve_monitoring: preserveMonitoring},
            }).then(() => {
                window.location.reload();
            }).catch((error) => {
                const blocked = error.response && error.response.status === 409;

                window.alert(blocked ? error.response.data.message : 'Não foi possível limpar os registros.');
            });
        },

        schedulePruneCheck() {
            window.clearTimeout(this.pruneCheckTimer);
            this.pruneAllowed = null;
            this.pruneCheckTimer = window.setTimeout(() => this.refreshPruneAvailability(), 400);
        },

        refreshPruneAvailability() {
            const hours = Number(this.pruneHours);

            if (!Number.isInteger(hours) || hours < 1 || hours > 8760) {
                this.pruneAllowed = false;

                return;
            }

            axios.get(Telescope.basePath + '/telescope-api/entries/prune', {params: {hours}}).then((response) => {
                this.pruneAllowed = response.data.allowed === true;
                this.pruneLimit = response.data.limit;
            }).catch(() => {
                this.pruneAllowed = false;
            });
        },

        pruneEntries() {
            const hours = Number(this.pruneHours);

            if (this.pruneAllowed !== true) {
                return;
            }

            if (!Number.isInteger(hours) || hours < 1 || hours > 8760) {
                window.alert('Informe um número inteiro de horas entre 1 e 8760.');

                return;
            }

            if (!window.confirm('Apagar registros do Telescope com mais de ' + hours + ' horas? Lotes de endpoints monitorados são mantidos.')) {
                return;
            }

            this.pruning = true;

            axios.post(Telescope.basePath + '/telescope-api/entries/prune', {hours}).then((response) => {
                window.alert((response.data.pruned ?? 0) + ' registros removidos.');
                window.location.reload();
            }).catch((error) => {
                this.pruning = false;
                const blocked = error.response && error.response.status === 409;

                window.alert(blocked ? error.response.data.message : 'Não foi possível prunar os registros.');
                this.refreshPruneAvailability();
            });
        },

        keydownListener(event) {
            if (event.key === 'Escape' && this.sidebarOpen) {
                this.sidebarOpen = false;

                return;
            }

            if (event.metaKey && event.key === 'k') {
                this.clearEntries(false);
            }
        },
    },
});
