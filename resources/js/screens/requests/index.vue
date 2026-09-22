<script type="text/ecmascript-6">
import axios from 'axios';
import StylesMixin from './../../mixins/entriesStyles';
import ResourceSparkline from '../../components/ResourceSparkline.vue';

export default {
    components: { ResourceSparkline },

    mixins: [
        StylesMixin,
    ],

    data() {
        return {
            ipInput: '',
            endpoints: [],
            endpointsReady: true,
            endpointsError: '',
        };
    },

    computed: {
        filteredIp() {
            return this.$route.query.ip || '';
        },
    },

    watch: {
        filteredIp: {
            immediate: true,
            handler() {
                this.ipInput = this.filteredIp;
                this.loadEndpoints();
            },
        },
    },

    methods: {
        applyIpFilter() {
            const ip = (this.ipInput || '').trim();
            const query = Object.assign({}, this.$route.query);

            if (ip) {
                query.ip = ip;
            } else {
                delete query.ip;
            }

            this.$router.push({ path: '/requests', query }).catch(() => {});
        },

        clearIpFilter() {
            this.ipInput = '';

            const query = Object.assign({}, this.$route.query);
            delete query.ip;

            this.$router.push({ path: '/requests', query }).catch(() => {});
        },

        loadEndpoints() {
            this.endpoints = [];
            this.endpointsError = '';

            if (!this.filteredIp) {
                this.endpointsReady = true;

                return;
            }

            this.endpointsReady = false;

            axios
                .get(Telescope.basePath + '/telescope-api/requests/endpoints', {
                    params: { ip: this.filteredIp },
                })
                .then((response) => {
                    this.endpoints = response.data.endpoints || [];
                    this.endpointsReady = true;
                })
                .catch((error) => {
                    this.endpoints = [];
                    this.endpointsReady = true;
                    this.endpointsError =
                        (error.response && error.response.data && error.response.data.message) ||
                        'Unable to load endpoints for this IP.';
                });
        },

        clearMinDurationQuery() {
            const query = { hours: this.periodHours };

            if (this.filteredIp) {
                query.ip = this.filteredIp;
            }

            return query;
        },
    },
}
</script>

<template>
    <div>
        <resource-sparkline type="request" title="Requests"></resource-sparkline>

        <div v-if="$route.query.min_duration" class="d-flex align-items-center mb-3">
            <span class="text-muted small mr-3">
                Showing requests slower than {{ formatDuration($route.query.min_duration) }}
            </span>
            <router-link :to="{ path: '/requests', query: clearMinDurationQuery() }" class="nw-shell-action">
                Clear filter
            </router-link>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h2 class="h6 m-0">Filter by IP</h2>
            </div>
            <div class="card-body">
                <form class="form-row align-items-end" v-on:submit.prevent="applyIpFilter">
                    <div class="col-md-4">
                        <label class="small text-muted" for="requestIpFilter">IP address</label>
                        <input
                            id="requestIpFilter"
                            v-model="ipInput"
                            type="text"
                            class="form-control"
                            placeholder="203.0.113.10"
                            autocomplete="off"
                        />
                    </div>
                    <div class="col-md-4 mt-2 mt-md-0">
                        <button type="submit" class="btn btn-muted">Filter</button>
                        <button
                            v-if="filteredIp"
                            type="button"
                            class="btn btn-sm btn-muted ml-2"
                            v-on:click="clearIpFilter"
                        >
                            Clear
                        </button>
                    </div>
                </form>

                <p v-if="filteredIp" class="text-muted small mb-0 mt-3">
                    Showing requests from {{ filteredIp }}
                </p>
                <p v-if="endpointsError" class="text-danger small mb-0 mt-3">{{ endpointsError }}</p>
            </div>

            <div v-if="filteredIp" class="table-responsive">
                <div v-if="!endpointsReady" class="card-bg-secondary p-4 text-muted small">
                    Loading endpoints...
                </div>
                <div
                    v-else-if="endpoints.length === 0"
                    class="card-bg-secondary p-4 text-muted small"
                >
                    No endpoints recorded for this IP.
                </div>
                <table v-else class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Verb</th>
                            <th scope="col">Path</th>
                            <th scope="col" class="text-right">Hits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="endpoint in endpoints" :key="endpoint.method + ':' + endpoint.uri">
                            <td class="table-fit pr-0">
                                <span class="badge" :class="'badge-' + requestMethodClass(endpoint.method)">
                                    {{ endpoint.method }}
                                </span>
                            </td>
                            <td :title="endpoint.uri">{{ truncate(endpoint.uri, 80) }}</td>
                            <td class="table-fit text-right text-muted">{{ endpoint.count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <monitored-requests></monitored-requests>

        <index-screen title="Requests" resource="requests" endpoint-override="" remember-closed-key="telescopeRequestsCardClosed">
        <tr slot="table-header">
            <th scope="col">Verb</th>
            <th scope="col">Path</th>
            <th scope="col" class="text-center">Status</th>
            <th scope="col" class="text-right">Duration</th>
            <th scope="col">Happened</th>
            <th scope="col"></th>
        </tr>

        <template slot="row" slot-scope="slotProps">
            <td class="table-fit pr-0">
                <span class="badge" :class="'badge-' + requestMethodClass(slotProps.entry.content.method)">
                    {{ slotProps.entry.content.method }}
                </span>
            </td>

            <td :title="slotProps.entry.content.uri">
                {{ truncate(slotProps.entry.content.uri, 50) }}
            </td>

            <td class="table-fit text-center">
                <span class="badge" :class="'badge-' + requestStatusClass(slotProps.entry.content.response_status)">
                    {{ slotProps.entry.content.response_status }}
                </span>
            </td>

            <td class="table-fit text-right text-muted">
                <span v-if="slotProps.entry.content.duration">{{ formatDuration(slotProps.entry.content.duration) }}</span>
                <span v-else>-</span>
            </td>

            <td
                class="table-fit text-muted"
                :data-timeago="slotProps.entry.created_at"
                :title="slotProps.entry.created_at"
            >
                {{ timeAgo(slotProps.entry.created_at) }}
            </td>

            <td class="table-fit">
                <router-link
                    :to="{
                        name: 'request-preview',
                        params: { id: slotProps.entry.id },
                    }"
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
        </template>
        </index-screen>
    </div>
</template>
