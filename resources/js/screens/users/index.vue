<script type="text/ecmascript-6">
import axios from 'axios';
import PeriodSelector from '../../components/PeriodSelector.vue';
import AutoRefresh from '../../mixins/autoRefresh';

export default {
    components: { PeriodSelector },

    mixins: [AutoRefresh],

    data() {
        return {
            users: [],
            search: '',
            ready: false,
        };
    },

    computed: {
        filtered() {
            const term = this.search.trim().toLowerCase();

            if (!term) {
                return this.users;
            }

            return this.users.filter((user) => {
                return [user.name, user.email, user.id]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(term));
            });
        },
    },

    watch: {
        periodHours() {
            this.load();
        },
    },

    mounted() {
        document.title = 'Users - Telescope';
        this.load();
    },

    methods: {
        load() {
            return axios
                .get(Telescope.basePath + '/telescope-api/users', {
                    params: { hours: this.periodHours },
                    signal: this.autoRefreshSignal(),
                })
                .then((response) => {
                    this.users = response.data.users || [];
                    this.ready = true;
                })
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') {
                        return;
                    }

                    this.ready = true;
                });
        },

        initials(user) {
            const source = user.name || user.email || user.id || '?';
            const parts = String(source).trim().split(/\s+/);

            return parts
                .slice(0, 2)
                .map((part) => part.charAt(0).toUpperCase())
                .join('');
        },
    },
};
</script>

<template>
    <div>
        <div class="nw-dashboard-head">
            <h1 class="nw-page-title mb-0">Users</h1>
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.75rem">
                <input v-model="search" type="search" class="form-control w-auto" placeholder="Search" />
                <period-selector></period-selector>
            </div>
        </div>

        <div class="card">
            <div v-if="!ready" class="p-5 text-center text-muted">Fetching...</div>
            <div v-else class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Last seen</th>
                            <th class="text-right">Requests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!filtered.length">
                            <td colspan="5" class="text-center text-muted p-4">No authenticated users in this period.</td>
                        </tr>
                        <tr
                            v-for="user in filtered"
                            :key="user.id"
                            class="cursor-pointer"
                            v-on:click="$router.push({ path: '/requests', query: { tag: 'Auth:' + user.id } })"
                        >
                            <td class="table-fit">
                                <span class="nw-avatar">{{ initials(user) }}</span>
                            </td>
                            <td>{{ user.name || '—' }}</td>
                            <td>{{ user.email || '—' }}</td>
                            <td>{{ timeAgo(user.last_seen) }}</td>
                            <td class="text-right">{{ user.requests }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
