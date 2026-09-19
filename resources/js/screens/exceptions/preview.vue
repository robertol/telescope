<script type="text/ecmascript-6">
import axios from 'axios';
import ExceptionCodePreview from './../../components/ExceptionCodePreview.vue';
import Stacktrace from './../../components/Stacktrace.vue';

export default {
    components: {
        'code-preview': ExceptionCodePreview,
        'stack-trace': Stacktrace,
    },

    data() {
        return {
            entry: null,
            batch: [],
            impact: null,
            currentTab: 'message',
        };
    },

    computed: {
        httpStatus() {
            const message = (this.entry && this.entry.content && this.entry.content.message) || '';
            const match = message.match(/status code (\d{3})/i);

            return match ? match[1] : null;
        },

        runtimeVersions() {
            const content = (this.entry && this.entry.content) || {};
            const parts = [];

            if (content.laravel_version) {
                parts.push('Laravel ' + content.laravel_version);
            }

            if (content.php_version) {
                parts.push('PHP ' + content.php_version);
            }

            return parts;
        },
    },

    methods: {
        hasContext() {
            return (
                this.entry.content.hasOwnProperty('context') &&
                this.entry.content.context !== null
            );
        },

        markExceptionAsResolved(entry) {
            this.alertConfirm('Are you sure you want to mark this exception as resolved?', () => {
                axios
                    .put(Telescope.basePath + '/telescope-api/exceptions/' + entry.id, {
                        resolved_at: 'now',
                    })
                    .then((response) => {
                        if (this.$route.params.id !== entry.id) return;

                        this.entry = response.data.entry;
                    });
            });
        },
    },
};
</script>

<template>
    <preview-screen title="Exception Details" resource="exceptions" :id="$route.params.id">
        <template slot="table-parameters" slot-scope="slotProps">
            <tr>
                <td class="table-fit text-muted">Type</td>
                <td>{{ slotProps.entry.content.class }}</td>
            </tr>
            <tr>
                <td class="table-fit text-muted">Location</td>
                <td>{{ slotProps.entry.content.file }}:{{ slotProps.entry.content.line }}</td>
            </tr>
            <tr>
                <td class="table-fit text-muted">Occurrences</td>
                <td>
                    <router-link
                        :to="{ name: 'exceptions', query: { family_hash: slotProps.entry.family_hash } }"
                        class="control-action"
                    >
                        View Other Occurrences
                    </router-link>
                </td>
            </tr>
            <tr>
                <td class="table-fit text-muted">Resolved at</td>
                <td>
                    <span v-if="entry.content.resolved_at">
                        {{ localTime(entry.content.resolved_at) }} ({{ timeAgo(entry.content.resolved_at) }})
                    </span>
                    <span v-if="!entry.content.resolved_at">
                        <button class="btn btn-sm btn-success" v-on:click.prevent="markExceptionAsResolved(entry)">
                            Mark as resolved
                        </button>
                    </span>
                </td>
            </tr>
        </template>

        <div slot="after-attributes-card" slot-scope="slotProps" class="mt-5">
            <h2 class="h5 mb-4">{{ slotProps.entry.content.message }}</h2>

            <div class="row mb-4" v-if="impact">
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="h6 m-0">Info</h3></div>
                        <div class="card-body small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Last seen</span>
                                <span>{{ localTime(impact.last_seen) }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">First seen</span>
                                <span>{{ localTime(impact.first_seen) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card h-100">
                        <div class="card-header"><h3 class="h6 m-0">Impact</h3></div>
                        <div class="card-body small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">30d</span><span>{{ impact.events_30d }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">7d</span><span>{{ impact.events_7d }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">24h</span><span>{{ impact.events_24h }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Users</span><span>{{ impact.users }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Servers</span>
                                <span>{{ impact.hostnames.length ? impact.hostnames.join(', ') : '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center mb-4">
                <span class="badge mr-2" :class="entry.content.resolved_at ? 'badge-success' : 'badge-danger'">
                    {{ entry.content.resolved_at ? 'HANDLED' : 'UNHANDLED' }}
                </span>
                <span v-if="httpStatus" class="badge badge-warning mr-2">{{ httpStatus }}</span>
                <span class="text-muted small">{{ slotProps.entry.content.class }}</span>
                <span v-if="runtimeVersions.length" class="text-muted small ml-3">{{ runtimeVersions.join(' · ') }}</span>
            </div>

            <div class="card mt-5 overflow-hidden">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab == 'message' }"
                            href="#"
                            v-on:click.prevent="currentTab = 'message'"
                            >Message</a
                        >
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab == 'location' }"
                            href="#"
                            v-on:click.prevent="currentTab = 'location'"
                            >Location</a
                        >
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab == 'context' }"
                            href="#"
                            v-show="hasContext()"
                            v-on:click.prevent="currentTab = 'context'"
                            >Context</a
                        >
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab == 'trace' }"
                            href="#"
                            v-on:click.prevent="currentTab = 'trace'"
                            >Stacktrace</a
                        >
                    </li>
                </ul>

                <div>
                    <pre class="code-bg p-4 mb-0 text-white" v-show="currentTab == 'message'">{{
                        slotProps.entry.content.message
                    }}</pre>

                    <code-preview
                        v-show="currentTab == 'location'"
                        :lines="slotProps.entry.content.line_preview"
                        :highlighted-line="slotProps.entry.content.line"
                    ></code-preview>

                    <div class="code-bg p-4 mb-0 text-white" v-show="currentTab == 'context'">
                        <copy-clipboard :data="slotProps.entry.content.context">
                            <vue-json-pretty :data="slotProps.entry.content.context"></vue-json-pretty>
                        </copy-clipboard>
                    </div>

                    <stack-trace :trace="slotProps.entry.content.trace" v-show="currentTab == 'trace'"></stack-trace>
                </div>
            </div>
        </div>
    </preview-screen>
</template>
