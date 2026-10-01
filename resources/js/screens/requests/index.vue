<script type="text/ecmascript-6">
import axios from 'axios';
import StylesMixin from './../../mixins/entriesStyles';
import ResourceSparkline from '../../components/ResourceSparkline.vue';
import RequestDetailsPanel from '../../components/RequestDetailsPanel.vue';

export default {
    components: { ResourceSparkline, RequestDetailsPanel },

    mixins: [
        StylesMixin,
    ],

    data() {
        return {
            expandedId: null,
            filters: {
                ip: '',
                email: '',
                endpoint: '',
            },
            journey: null,
            journeyReady: false,
            journeyController: null,
        };
    },

    computed: {
        hasActiveFilters() {
            return Boolean(
                this.filters.ip ||
                this.filters.email ||
                this.filters.endpoint ||
                this.$route.query.min_duration
            );
        },

        hasJourneyFilter() {
            return Boolean(this.filters.ip || this.filters.email);
        },
    },

    watch: {
        '$route.query': {
            immediate: true,
            handler() {
                this.filters = {
                    ip: this.$route.query.ip || '',
                    email: this.$route.query.email || '',
                    endpoint: this.$route.query.endpoint || '',
                };

                this.loadJourney();
            },
        },
    },

    destroyed() {
        if (this.journeyController) {
            this.journeyController.abort();
        }
    },

    methods: {
        toggleExpanded(id) {
            this.expandedId = this.expandedId === id ? null : id;
        },

        applyFilters() {
            this.debouncer(() => {
                const query = Object.assign({}, this.$route.query, {
                    ip: this.filters.ip.trim() || undefined,
                    email: this.filters.email.trim() || undefined,
                    endpoint: this.filters.endpoint.trim() || undefined,
                });

                ['ip', 'email', 'endpoint'].forEach((key) => {
                    if (!query[key]) {
                        delete query[key];
                    }
                });

                this.$router.push({ query }).catch(() => {});
            });
        },

        clearFilters() {
            const query = Object.assign({}, this.$route.query);

            delete query.ip;
            delete query.email;
            delete query.endpoint;
            delete query.min_duration;
            delete query.tag;

            this.filters = { ip: '', email: '', endpoint: '' };
            this.journey = null;

            this.$router.push({ query }).catch(() => {});
        },

        loadJourney() {
            if (this.journeyController) {
                this.journeyController.abort();
            }

            if (!this.hasJourneyFilter) {
                this.journey = null;
                this.journeyReady = true;
                return;
            }

            this.journeyReady = false;
            this.journeyController = new AbortController();

            return axios
                .get(Telescope.basePath + '/telescope-api/journey', {
                    params: {
                        ip: this.filters.ip || undefined,
                        email: this.filters.email || undefined,
                        hours: this.periodHours,
                    },
                    signal: this.journeyController.signal,
                })
                .then((response) => {
                    this.journey = response.data;
                    this.journeyReady = true;
                })
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') {
                        return;
                    }

                    this.journey = null;
                    this.journeyReady = true;
                });
        },

        filterEndpoint(endpoint) {
            this.filters.endpoint = endpoint.method + ':' + endpoint.uri;
            this.applyFilters();
        },
    },
}
</script>

<template>
    <div>
        <resource-sparkline type="request" title="Requests"></resource-sparkline>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="h6 m-0">Search</h2>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="nw-shell-action border-0 bg-transparent p-0"
                    v-on:click="clearFilters"
                >
                    Clear filters
                </button>
            </div>

            <div class="px-3 pb-3">
                <div class="row">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="small text-muted mb-1" for="request-filter-ip">IP</label>
                        <input
                            id="request-filter-ip"
                            type="search"
                            class="form-control"
                            placeholder="203.0.113.10"
                            v-model="filters.ip"
                            @input="applyFilters"
                        />
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="small text-muted mb-1" for="request-filter-email">Email</label>
                        <input
                            id="request-filter-email"
                            type="search"
                            class="form-control"
                            placeholder="user@example.com"
                            v-model="filters.email"
                            @input="applyFilters"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted mb-1" for="request-filter-path">Path</label>
                        <input
                            id="request-filter-path"
                            type="search"
                            class="form-control"
                            placeholder="/v1/cards/*"
                            v-model="filters.endpoint"
                            @input="applyFilters"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="hasJourneyFilter" class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="h6 m-0">Journey</h2>
                <span class="text-muted small" v-if="journeyReady && journey">
                    {{ journey.requests }} requests · {{ journey.endpoints.length }} endpoints
                </span>
            </div>

            <div v-if="!journeyReady" class="p-4 text-muted text-center">Loading journey...</div>
            <div v-else-if="!journey || !journey.endpoints.length" class="p-4 text-muted text-center">
                No endpoints for this filter in the selected period.
            </div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Verb</th>
                            <th>Path</th>
                            <th class="text-right">Hits</th>
                            <th>Last seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="endpoint in journey.endpoints"
                            :key="endpoint.method + ':' + endpoint.uri"
                            class="cursor-pointer"
                            v-on:click="filterEndpoint(endpoint)"
                        >
                            <td class="table-fit">
                                <span class="badge" :class="'badge-' + requestMethodClass(endpoint.method)">
                                    {{ endpoint.method }}
                                </span>
                            </td>
                            <td :title="endpoint.uri">{{ truncate(endpoint.uri, 70) }}</td>
                            <td class="table-fit text-right">{{ endpoint.count }}</td>
                            <td class="table-fit text-muted">{{ timeAgo(endpoint.last_seen) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="$route.query.min_duration" class="d-flex align-items-center mb-3">
            <span class="text-muted small mr-3">
                Showing requests slower than {{ formatDuration($route.query.min_duration) }}
            </span>
            <button type="button" class="nw-shell-action border-0 bg-transparent p-0" v-on:click="clearFilters">
                Clear filter
            </button>
        </div>

        <monitored-requests></monitored-requests>

        <index-screen title="Requests" resource="requests" :hide-search="true" remember-closed-key="telescopeRequestsCardClosed" :row-clickable="true" v-on:row-click="toggleExpanded($event.id)">
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
                <button
                    type="button"
                    class="control-action border-0 bg-transparent p-0"
                    v-on:click.stop="toggleExpanded(slotProps.entry.id)"
                    :title="expandedId === slotProps.entry.id ? 'Fechar' : 'Request Details'"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        :style="{ transform: expandedId === slotProps.entry.id ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s' }"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
            </td>
        </template>

        <template slot="after-row" slot-scope="slotProps">
            <tr v-if="expandedId === slotProps.entry.id" :key="'detail-' + slotProps.entry.id">
                <td colspan="6" class="p-3">
                    <request-details-panel :id="slotProps.entry.id" :update-title="false"></request-details-panel>
                </td>
            </tr>
        </template>
        </index-screen>
    </div>
</template>
