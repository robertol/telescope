<script type="text/ecmascript-6">
import _ from 'lodash';
import axios from 'axios';
import StylesMixin from './../mixins/entriesStyles';
import RequestDetailsPanel from './RequestDetailsPanel.vue';

const CLOSED_ENDPOINTS_KEY = 'telescopeMonitoredRequestsClosed';

export default {
    components: {RequestDetailsPanel},

    mixins: [StylesMixin],

    data() {
        return {
            endpoints: [],
            sections: {},
            closedEndpoints: [],
            ready: false,
            requestController: new AbortController(),
            entriesPerRequest: 25,
            newEntriesTimeout: null,
            newEntriesTimer: 2500,
            expandedKey: null,
        };
    },

    mounted() {
        this.closedEndpoints = this.readClosedEndpoints();
        this.loadMonitoredEndpoints();
    },

    destroyed() {
        this.requestController.abort();
        clearTimeout(this.newEntriesTimeout);
    },

    methods: {
        readClosedEndpoints() {
            try {
                const stored = JSON.parse(localStorage[CLOSED_ENDPOINTS_KEY] || '[]');

                return Array.isArray(stored) ? stored : [];
            } catch (error) {
                return [];
            }
        },

        rememberClosedEndpoints() {
            if (this.closedEndpoints.length) {
                localStorage[CLOSED_ENDPOINTS_KEY] = JSON.stringify(this.closedEndpoints);

                return;
            }

            localStorage.removeItem(CLOSED_ENDPOINTS_KEY);
        },

        isClosed(endpoint) {
            return this.closedEndpoints.indexOf(endpoint) !== -1;
        },

        expansionKey(endpoint, entryId) {
            return endpoint + ':' + entryId;
        },

        isExpanded(endpoint, entryId) {
            return this.expandedKey === this.expansionKey(endpoint, entryId);
        },

        toggleExpanded(endpoint, entryId) {
            const key = this.expansionKey(endpoint, entryId);

            this.expandedKey = this.expandedKey === key ? null : key;
        },

        toggleEndpoint(endpoint) {
            if (this.isClosed(endpoint)) {
                this.closedEndpoints = this.closedEndpoints.filter((item) => item !== endpoint);
                this.rememberClosedEndpoints();

                if (!this.sections[endpoint] || !this.sections[endpoint].ready) {
                    this.loadSection(endpoint);
                }

                return;
            }

            this.closedEndpoints = this.closedEndpoints.concat([endpoint]);
            this.rememberClosedEndpoints();
        },

        blankSection() {
            return {
                entries: [],
                ready: false,
                pageBefore: '',
                nextBefore: '',
                pageCursors: [''],
                currentPage: 0,
                hasMoreEntries: true,
                loadingPage: false,
                hasNewEntries: false,
                loadingNewEntries: false,
            };
        },

        loadMonitoredEndpoints() {
            const {signal} = this.requestController;

            axios.get(Telescope.basePath + '/telescope-api/monitored-endpoints', {signal}).then((response) => {
                if (signal.aborted) return;

                this.endpoints = response.data.endpoints || [];
                this.closedEndpoints = this.closedEndpoints.filter((endpoint) => this.endpoints.indexOf(endpoint) !== -1);
                this.rememberClosedEndpoints();

                this.endpoints.forEach((endpoint) => {
                    if (!this.sections[endpoint]) {
                        this.$set(this.sections, endpoint, this.blankSection());
                    }

                    if (!this.isClosed(endpoint)) {
                        this.loadSection(endpoint);
                    }
                });

                this.ready = true;
                this.checkForNewEntries();
            }).catch((error) => {
                if (signal.aborted) return;

                this.ready = true;

                if (this.mayRetry(error, signal)) {
                    this.newEntriesTimeout = setTimeout(() => this.loadMonitoredEndpoints(), this.newEntriesTimer);
                }
            });
        },

        loadSection(endpoint) {
            const section = this.sections[endpoint];

            if (!section) {
                return;
            }

            section.loadingPage = true;

            this.loadEntries(endpoint, (entries) => {
                section.entries = entries;
                section.loadingPage = false;
                section.loadingNewEntries = false;
                section.ready = true;
            });
        },

        loadEntries(endpoint, after) {
            const section = this.sections[endpoint];
            const {signal} = this.requestController;

            return axios
                .post(
                    Telescope.basePath +
                        '/telescope-api/requests' +
                        '?endpoint=' +
                        encodeURIComponent(endpoint) +
                        '&before=' +
                        section.pageBefore +
                        '&take=' +
                        this.entriesPerRequest,
                    null,
                    {signal}
                )
                .then((response) => {
                    if (signal.aborted) return;

                    section.nextBefore = response.data.entries.length
                        ? _.last(response.data.entries).sequence
                        : '';

                    section.hasMoreEntries = response.data.entries.length >= this.entriesPerRequest;

                    if (_.isFunction(after)) {
                        after(response.data.entries);
                    }
                })
                .catch((error) => {
                    if (signal.aborted) return;

                    section.ready = true;
                    section.loadingNewEntries = false;
                    section.loadingPage = false;

                    if (this.mayRetry(error, signal)) {
                        this.checkForNewEntries();
                    }
                });
        },

        checkForNewEntries() {
            const {signal} = this.requestController;

            clearTimeout(this.newEntriesTimeout);

            this.newEntriesTimeout = setTimeout(() => {
                const openEndpoints = this.endpoints.filter((endpoint) => {
                    const section = this.sections[endpoint];

                    return section && !this.isClosed(endpoint) && section.currentPage === 0 && section.ready;
                });

                if (!openEndpoints.length) {
                    if (!signal.aborted) {
                        this.checkForNewEntries();
                    }

                    return;
                }

                Promise.all(openEndpoints.map((endpoint) => this.detectNewEntries(endpoint, signal)))
                    .catch(() => {})
                    .then(() => {
                        if (!signal.aborted) {
                            this.checkForNewEntries();
                        }
                    });
            }, this.newEntriesTimer);
        },

        detectNewEntries(endpoint, signal) {
            const section = this.sections[endpoint];

            return axios
                .post(
                    Telescope.basePath + '/telescope-api/requests?endpoint=' + encodeURIComponent(endpoint) + '&take=1',
                    null,
                    {signal}
                )
                .then((response) => {
                    if (signal.aborted || !response.data.entries.length) {
                        return;
                    }

                    const latest = _.first(response.data.entries);

                    if (!section.entries.length || latest.id !== _.first(section.entries).id) {
                        if (this.$root.autoLoadsNewEntries) {
                            this.loadNewEntries(endpoint);
                        } else {
                            section.hasNewEntries = true;
                        }
                    }
                });
        },

        goToNextPage(endpoint) {
            const section = this.sections[endpoint];

            if (!section || section.loadingPage || !section.hasMoreEntries || section.nextBefore === '') {
                return;
            }

            section.pageCursors.splice(section.currentPage + 1, section.pageCursors.length, section.nextBefore);
            section.currentPage += 1;
            section.pageBefore = section.nextBefore;
            this.loadSection(endpoint);
        },

        goToPreviousPage(endpoint) {
            const section = this.sections[endpoint];

            if (!section || section.loadingPage || section.currentPage === 0) {
                return;
            }

            section.currentPage -= 1;
            section.pageBefore = section.pageCursors[section.currentPage];
            section.hasMoreEntries = true;
            this.loadSection(endpoint);
        },

        loadNewEntries(endpoint) {
            const section = this.sections[endpoint];

            if (!section) {
                return;
            }

            section.hasNewEntries = false;
            section.currentPage = 0;
            section.pageBefore = '';
            section.nextBefore = '';
            section.pageCursors = [''];
            section.hasMoreEntries = true;
            section.loadingNewEntries = true;
            this.loadSection(endpoint);
        },
    },
};
</script>

<template>
    <div class="card overflow-hidden mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 m-0">Monitored Requests</h2>

            <router-link to="/monitored-tags" class="small text-muted">Manage</router-link>
        </div>

        <div v-if="!ready" class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon spin mr-2 fill-text-color">
                <path
                    d="M12 10a2 2 0 0 1-3.41 1.41A2 2 0 0 1 10 8V0a9.97 9.97 0 0 1 10 10h-8zm7.9 1.41A10 10 0 1 1 8.59.1v2.03a8 8 0 1 0 9.29 9.29h2.02zm-4.07 0a6 6 0 1 1-7.25-7.25v2.1a3.99 3.99 0 0 0-1.4 6.57 4 4 0 0 0 6.56-1.42h2.1z"
                ></path>
            </svg>

            <span>Scanning...</span>
        </div>

        <div
            v-if="ready && endpoints.length == 0"
            class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <span>
                No endpoints are currently being monitored.
                <router-link to="/monitored-tags">Monitor an endpoint</router-link>
            </span>
        </div>

        <template v-if="ready && endpoints.length">
        <div v-for="endpoint in endpoints" :key="endpoint">
            <button
                type="button"
                class="btn btn-link d-flex align-items-center w-100 text-left border-0 rounded-0 px-3 py-2"
                :class="isClosed(endpoint) ? 'text-muted' : 'card-bg-secondary'"
                :style="isClosed(endpoint) ? null : { boxShadow: 'inset 3px 0 0 #6366f1' }"
                v-on:click="toggleEndpoint(endpoint)"
                :title="isClosed(endpoint) ? 'Abrir' : 'Fechar'"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    class="icon mr-2 fill-text-color"
                    :style="{ transform: isClosed(endpoint) ? 'none' : 'rotate(90deg)', transition: 'transform 0.15s', width: '0.9rem', height: '0.9rem' }"
                >
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>

                <code class="mb-0" :class="isClosed(endpoint) ? 'text-muted' : 'text-body'">{{ endpoint }}</code>
            </button>

            <div v-show="!isClosed(endpoint)">
                <div
                    v-if="sections[endpoint] && !sections[endpoint].ready"
                    class="d-flex align-items-center justify-content-center card-bg-secondary p-4"
                >
                    <span>Scanning...</span>
                </div>

                <div
                    v-if="sections[endpoint] && sections[endpoint].ready && sections[endpoint].entries.length == 0"
                    class="text-center text-muted p-4"
                >
                    <span>We didn't find anything - just empty space.</span>
                </div>

                <table
                    class="table table-hover mb-0 penultimate-column-right"
                    v-if="sections[endpoint] && sections[endpoint].ready && sections[endpoint].entries.length > 0"
                >
                    <thead>
                        <tr>
                            <th scope="col">Verb</th>
                            <th scope="col">Path</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-right">Duration</th>
                            <th scope="col">Happened</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="sections[endpoint].hasNewEntries" class="dontanimate">
                            <td colspan="100" class="text-center card-bg-secondary py-2">
                                <small>
                                    <button type="button" class="btn btn-link btn-sm p-0" v-on:click="loadNewEntries(endpoint)" v-if="!sections[endpoint].loadingNewEntries">Load New Entries</button>
                                </small>

                                <small v-if="sections[endpoint].loadingNewEntries">Loading...</small>
                            </td>
                        </tr>

                        <template v-for="entry in sections[endpoint].entries">
                            <tr
                                :key="endpoint + '-' + entry.id"
                                class="cursor-pointer"
                                v-on:click="toggleExpanded(endpoint, entry.id)"
                            >
                                <td class="table-fit pr-0">
                                    <span class="badge" :class="'badge-' + requestMethodClass(entry.content.method)">
                                        {{ entry.content.method }}
                                    </span>
                                </td>

                                <td :title="entry.content.uri">
                                    {{ truncate(entry.content.uri, 50) }}
                                </td>

                                <td class="table-fit text-center">
                                    <span class="badge" :class="'badge-' + requestStatusClass(entry.content.response_status)">
                                        {{ entry.content.response_status }}
                                    </span>
                                </td>

                                <td class="table-fit text-right text-muted">
                                    <span v-if="entry.content.duration">{{ entry.content.duration }}ms</span>
                                    <span v-else>-</span>
                                </td>

                                <td class="table-fit text-muted" :data-timeago="entry.created_at" :title="entry.created_at">
                                    {{ timeAgo(entry.created_at) }}
                                </td>

                                <td class="table-fit">
                                    <button
                                        type="button"
                                        class="control-action border-0 bg-transparent p-0"
                                        v-on:click.stop="toggleExpanded(endpoint, entry.id)"
                                        :title="isExpanded(endpoint, entry.id) ? 'Fechar' : 'Request Details'"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            :style="{ transform: isExpanded(endpoint, entry.id) ? 'rotate(90deg)' : 'none', transition: 'transform 0.15s' }"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="isExpanded(endpoint, entry.id)" :key="endpoint + '-detail-' + entry.id">
                                <td colspan="6" class="p-3">
                                    <request-details-panel :id="entry.id" :update-title="false"></request-details-panel>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div
                    v-if="sections[endpoint] && sections[endpoint].ready && (sections[endpoint].entries.length > 0 || sections[endpoint].currentPage > 0)"
                    class="d-flex align-items-center justify-content-between border-top px-3 py-2"
                >
                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0"
                        :disabled="sections[endpoint].currentPage === 0 || sections[endpoint].loadingPage"
                        v-on:click="goToPreviousPage(endpoint)"
                    >
                        Anterior
                    </button>

                    <small v-if="sections[endpoint].loadingPage">Loading...</small>
                    <small v-else>&nbsp;</small>

                    <button
                        type="button"
                        class="btn btn-link btn-sm p-0"
                        :disabled="!sections[endpoint].hasMoreEntries || sections[endpoint].loadingPage"
                        v-on:click="goToNextPage(endpoint)"
                    >
                        Próximo
                    </button>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>
