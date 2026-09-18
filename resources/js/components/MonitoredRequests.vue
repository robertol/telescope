<script type="text/ecmascript-6">
import _ from 'lodash';
import axios from 'axios';
import StylesMixin from './../mixins/entriesStyles';

export default {
    mixins: [StylesMixin],

    data() {
        return {
            endpoints: [],
            entries: [],
            ready: false,
            requestController: new AbortController(),
            pageBefore: '',
            nextBefore: '',
            pageCursors: [''],
            currentPage: 0,
            hasMoreEntries: true,
            hasNewEntries: false,
            entriesPerRequest: 25,
            loadingNewEntries: false,
            loadingPage: false,
            newEntriesTimeout: null,
            newEntriesTimer: 2500,
        };
    },

    computed: {
        endpointFilter() {
            return this.endpoints.join(',');
        },
    },

    mounted() {
        this.loadMonitoredEndpoints();
    },

    destroyed() {
        this.requestController.abort();
        clearTimeout(this.newEntriesTimeout);
    },

    methods: {
        loadMonitoredEndpoints() {
            const {signal} = this.requestController;

            axios.get(Telescope.basePath + '/telescope-api/monitored-endpoints', {signal}).then((response) => {
                if (signal.aborted) return;

                this.endpoints = response.data.endpoints || [];

                if (!this.endpoints.length) {
                    this.entries = [];
                    this.ready = true;
                    return;
                }

                this.loadEntries((entries) => {
                    this.entries = entries;
                    this.checkForNewEntries();
                    this.ready = true;
                });
            }).catch((error) => {
                if (signal.aborted) return;

                this.ready = true;

                if (this.mayRetry(error, signal)) {
                    this.newEntriesTimeout = setTimeout(() => this.loadMonitoredEndpoints(), this.newEntriesTimer);
                }
            });
        },

        loadEntries(after) {
            const {signal} = this.requestController;

            return axios
                .post(
                    Telescope.basePath +
                        '/telescope-api/requests' +
                        '?endpoint=' +
                        encodeURIComponent(this.endpointFilter) +
                        '&before=' +
                        this.pageBefore +
                        '&take=' +
                        this.entriesPerRequest,
                    null,
                    {signal}
                )
                .then((response) => {
                    if (signal.aborted) return;

                    this.nextBefore = response.data.entries.length
                        ? _.last(response.data.entries).sequence
                        : '';

                    this.hasMoreEntries = response.data.entries.length >= this.entriesPerRequest;

                    if (_.isFunction(after)) {
                        after(response.data.entries);
                    }
                })
                .catch((error) => {
                    if (signal.aborted) return;

                    this.ready = true;
                    this.loadingNewEntries = false;
                    this.loadingPage = false;

                    if (this.mayRetry(error, signal)) {
                        this.checkForNewEntries();
                    }
                });
        },

        checkForNewEntries() {
            if (this.currentPage !== 0) {
                return;
            }

            const {signal} = this.requestController;

            if (!this.endpointFilter) {
                return;
            }

            clearTimeout(this.newEntriesTimeout);

            this.newEntriesTimeout = setTimeout(() => {
                axios
                    .post(
                        Telescope.basePath +
                            '/telescope-api/requests' +
                            '?endpoint=' +
                            encodeURIComponent(this.endpointFilter) +
                            '&take=1',
                        null,
                        {signal}
                    )
                    .then((response) => {
                        if (signal.aborted) return;

                        if (response.data.entries.length && !this.entries.length) {
                            this.loadNewEntries();
                        } else if (
                            response.data.entries.length &&
                            _.first(response.data.entries).id !== _.first(this.entries).id
                        ) {
                            if (this.$root.autoLoadsNewEntries) {
                                this.loadNewEntries();
                            } else {
                                this.hasNewEntries = true;
                            }
                        } else {
                            this.checkForNewEntries();
                        }
                    })
                    .catch((error) => {
                        if (this.mayRetry(error, signal)) this.checkForNewEntries();
                    });
            }, this.newEntriesTimer);
        },

        resetPagination() {
            this.currentPage = 0;
            this.pageBefore = '';
            this.nextBefore = '';
            this.pageCursors = [''];
            this.hasMoreEntries = true;
            this.loadingPage = false;
        },

        fetchCurrentPage(fromPrevious) {
            this.loadingPage = true;

            this.loadEntries((entries) => {
                this.entries = entries;
                this.loadingPage = false;

                if (fromPrevious) {
                    this.hasMoreEntries = true;
                }

                if (this.currentPage === 0) {
                    this.checkForNewEntries();
                }
            });
        },

        goToNextPage() {
            if (this.loadingPage || !this.hasMoreEntries || this.nextBefore === '') {
                return;
            }

            clearTimeout(this.newEntriesTimeout);
            this.pageCursors.splice(this.currentPage + 1, this.pageCursors.length, this.nextBefore);
            this.currentPage += 1;
            this.pageBefore = this.nextBefore;
            this.fetchCurrentPage(false);
        },

        goToPreviousPage() {
            if (this.loadingPage || this.currentPage === 0) {
                return;
            }

            this.currentPage -= 1;
            this.pageBefore = this.pageCursors[this.currentPage];
            this.fetchCurrentPage(true);
        },

        loadNewEntries() {
            this.hasNewEntries = false;
            this.resetPagination();
            this.loadingNewEntries = true;

            clearTimeout(this.newEntriesTimeout);

            this.loadEntries((entries) => {
                this.entries = entries;
                this.loadingNewEntries = false;
                this.checkForNewEntries();
            });
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

        <div
            v-if="ready && endpoints.length"
            class="px-4 py-2 d-flex flex-wrap align-items-center card-bg-secondary"
            style="gap: 0.4rem"
        >
            <code v-for="endpoint in endpoints" :key="endpoint" class="badge badge-secondary font-weight-normal mb-0">
                {{ truncate(endpoint, 80) }}
            </code>
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

        <div
            v-if="ready && endpoints.length > 0 && entries.length == 0"
            class="d-flex flex-column align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60" class="fill-text-color" style="width: 200px">
                <path
                    fill-rule="evenodd"
                    d="M7 10h41a11 11 0 0 1 0 22h-8a3 3 0 0 0 0 6h6a6 6 0 1 1 0 12H10a4 4 0 1 1 0-8h2a2 2 0 1 0 0-4H7a5 5 0 0 1 0-10h3a3 3 0 0 0 0-6H7a6 6 0 1 1 0-12zm14 19a1 1 0 0 1-1-1 1 1 0 0 0-2 0 1 1 0 0 1-1 1 1 1 0 0 0 0 2 1 1 0 0 1 1 1 1 1 0 0 0 2 0 1 1 0 0 1 1-1 1 1 0 0 0 0-2zm-5.5-11a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm24 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm1 18a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm-14-3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm22-23a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM33 18a1 1 0 0 1-1-1v-1a1 1 0 0 0-2 0v1a1 1 0 0 1-1 1h-1a1 1 0 0 0 0 2h1a1 1 0 0 1 1 1v1a1 1 0 0 0 2 0v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 0-2h-1z"
                ></path>
            </svg>

            <span>We didn't find anything - just empty space.</span>
        </div>

        <table
            class="table table-hover mb-0 penultimate-column-right"
            v-if="ready && entries.length > 0"
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
                <tr v-if="hasNewEntries" class="dontanimate">
                    <td colspan="100" class="text-center card-bg-secondary py-2">
                        <small>
                            <button type="button" class="btn btn-link btn-sm p-0" v-on:click="loadNewEntries" v-if="!loadingNewEntries">Load New Entries</button>
                        </small>

                        <small v-if="loadingNewEntries">Loading...</small>
                    </td>
                </tr>

                <tr v-for="entry in entries" :key="entry.id">
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
                        <router-link
                            :to="{
                                name: 'request-preview',
                                params: { id: entry.id },
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
                </tr>
            </tbody>
        </table>

        <div
            v-if="ready && (entries.length > 0 || currentPage > 0)"
            class="d-flex align-items-center justify-content-between border-top px-3 py-2"
        >
            <button
                type="button"
                class="btn btn-link btn-sm p-0"
                :disabled="currentPage === 0 || loadingPage"
                v-on:click="goToPreviousPage"
            >
                Anterior
            </button>

            <small v-if="loadingPage">Loading...</small>
            <small v-else>&nbsp;</small>

            <button
                type="button"
                class="btn btn-link btn-sm p-0"
                :disabled="!hasMoreEntries || loadingPage"
                v-on:click="goToNextPage"
            >
                Próximo
            </button>
        </div>
    </div>
</template>
