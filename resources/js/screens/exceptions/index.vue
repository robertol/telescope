<script type="text/ecmascript-6">
import axios from 'axios';
import BarChart from '../../components/BarChart.vue';
import PeriodSelector from '../../components/PeriodSelector.vue';

export default {
    components: { BarChart, PeriodSelector },

    data() {
        return {
            summary: { handled: 0, unhandled: 0, families: [], timeline: [] },
            search: '',
            status: 'all',
            ready: false,
        };
    },

    computed: {
        families() {
            const term = this.search.trim().toLowerCase();

            if (!term) {
                return this.summary.families;
            }

            return this.summary.families.filter((family) => {
                return [family.class, family.message]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(term));
            });
        },

        titleCount() {
            return this.families.length;
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
        status() {
            this.load();
        },
    },

    mounted() {
        document.title = 'Exceptions - Telescope';
        this.load();
    },

    methods: {
        load() {
            const params = { hours: this.periodHours };

            if (this.status !== 'all') {
                params.status = this.status;
            }

            axios.get(Telescope.basePath + '/telescope-api/exceptions/summary', { params }).then((response) => {
                this.summary = response.data;
                this.ready = true;
            });
        },
    },
};
</script>

<template>
    <div>
        <bar-chart :points="summary.timeline" color="#ef4444" :height="56"></bar-chart>

        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
            <h1 class="nw-page-title mb-0">{{ titleCount }} Exceptions</h1>
            <period-selector></period-selector>
        </div>

        <div class="d-flex align-items-center mb-3">
            <input v-model="search" type="search" class="form-control w-auto mr-3" placeholder="Search class or message" />
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-muted" :class="{ active: status === 'all' }" v-on:click="status = 'all'">
                    View all ({{ summary.handled + summary.unhandled }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-muted"
                    :class="{ active: status === 'handled' }"
                    v-on:click="status = 'handled'"
                >
                    Handled ({{ summary.handled }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-muted"
                    :class="{ active: status === 'unhandled' }"
                    v-on:click="status = 'unhandled'"
                >
                    Unhandled ({{ summary.unhandled }})
                </button>
            </div>
        </div>

        <div class="card">
            <div v-if="!ready" class="p-5 text-center text-muted">Fetching...</div>
            <table v-else class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Last seen</th>
                        <th></th>
                        <th>Exception</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Users</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!families.length">
                        <td colspan="6" class="text-center text-muted p-4">No exceptions in this period.</td>
                    </tr>
                    <tr v-for="family in families" :key="family.family_hash">
                        <td class="table-fit text-muted">{{ timeAgo(family.last_seen) }}</td>
                        <td class="table-fit">
                            <span v-if="!family.handled" class="badge badge-danger">UNHANDLED</span>
                        </td>
                        <td>
                            <div class="font-weight-bold">{{ family.class }}</div>
                            <small class="text-muted">{{ truncate(family.message, 120) }}</small>
                        </td>
                        <td class="text-right">{{ family.count }}</td>
                        <td class="text-right">{{ family.users }}</td>
                        <td class="table-fit">
                            <router-link
                                :to="{ name: 'exception-preview', params: { id: family.latest_id } }"
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
        </div>
    </div>
</template>
