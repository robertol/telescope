<script type="text/ecmascript-6">
import axios from 'axios';
import PeriodSelector from '../../components/PeriodSelector.vue';

export default {
    components: { PeriodSelector },

    data() {
        return {
            hosts: [],
            ready: false,
        };
    },

    watch: {
        periodHours() {
            this.load();
        },
    },

    mounted() {
        document.title = 'Outgoing Requests - Telescope';
        this.load();
    },

    methods: {
        load() {
            axios
                .get(Telescope.basePath + '/telescope-api/outgoing-requests/hosts', {
                    params: { hours: this.periodHours },
                })
                .then((response) => {
                    this.hosts = response.data.hosts || [];
                    this.ready = true;
                });
        },
    },
};
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="nw-page-title mb-0">Outgoing Requests</h1>
            <period-selector></period-selector>
        </div>

        <div class="card">
            <div v-if="!ready" class="p-5 text-center text-muted">Fetching...</div>
            <table v-else class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th class="text-right">Requests</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!hosts.length">
                        <td colspan="3" class="text-center text-muted p-4">No outgoing requests in this period.</td>
                    </tr>
                    <tr
                        v-for="row in hosts"
                        :key="row.host"
                        class="cursor-pointer"
                        v-on:click="$router.push({ name: 'outgoing-request-host', params: { host: row.host }, query: { hours: periodHours } })"
                    >
                        <td>{{ row.host }}</td>
                        <td class="text-right">{{ row.total }}</td>
                        <td class="table-fit">
                            <span class="control-action">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 001.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
