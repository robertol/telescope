<script type="text/ecmascript-6">
import axios from 'axios';

const ACTIVITY_KEY = 'telescopeActivityOpen';

const ACTIVITY_ITEMS = [
    { to: '/requests', label: 'Requests' },
    { to: '/jobs', label: 'Jobs' },
    { to: '/commands', label: 'Commands' },
    { to: '/schedule', label: 'Scheduled Tasks' },
    { to: '/exceptions', label: 'Exceptions' },
    { to: '/queries', label: 'Queries' },
    { to: '/notifications', label: 'Notifications' },
    { to: '/mail', label: 'Mail' },
    { to: '/cache', label: 'Cache' },
    { to: '/outgoing-requests', label: 'Outgoing Requests', aliases: ['/client-requests'] },
    { to: '/batches', label: 'Batches' },
    { to: '/dumps', label: 'Dumps' },
    { to: '/events', label: 'Events' },
    { to: '/gates', label: 'Gates' },
    { to: '/models', label: 'Models' },
    { to: '/redis', label: 'Redis' },
    { to: '/views', label: 'Views' },
];

export default {
    data() {
        return {
            activityOpen: localStorage[ACTIVITY_KEY] !== '0',
            activityItems: ACTIVITY_ITEMS,
            unhandled: 0,
        };
    },

    computed: {
        appName() {
            return Telescope.appName || 'Telescope';
        },

        environment() {
            return Telescope.environment || '';
        },

        currentUser() {
            return Telescope.user || null;
        },

        userInitials() {
            if (! this.currentUser || ! this.currentUser.name) {
                return '?';
            }

            return this.currentUser.name
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part.charAt(0).toUpperCase())
                .join('');
        },

        onActivityRoute() {
            return ACTIVITY_ITEMS.some((item) => this.isActive(item.to, item.aliases || []));
        },
    },

    watch: {
        onActivityRoute(value) {
            if (value) {
                this.activityOpen = true;
            }
        },
    },

    created() {
        if (this.onActivityRoute) {
            this.activityOpen = true;
        }

        this.refreshBadge();
    },

    methods: {
        toggleActivity() {
            this.activityOpen = !this.activityOpen;
            localStorage[ACTIVITY_KEY] = this.activityOpen ? '1' : '0';
        },

        refreshBadge() {
            axios
                .get(Telescope.basePath + '/telescope-api/exceptions/summary', { params: { hours: 336 } })
                .then((response) => {
                    this.unhandled = response.data.unhandled || 0;
                })
                .catch(() => {});
        },

        isActive(path, aliases = []) {
            return [path, ...aliases].some((candidate) => {
                if (candidate === '/') {
                    return this.$route.path === '/';
                }

                return this.$route.path === candidate || this.$route.path.startsWith(candidate + '/');
            });
        },

        logout() {
            axios.post(Telescope.basePath + '/telescope-api/logout').finally(() => {
                window.location = Telescope.basePath + '/login';
            });
        },
    },
};
</script>

<template>
    <nav class="nw-nav">
        <div class="sidebar-brand">
            <div class="font-weight-bold">{{ appName }}</div>
            <small class="text-muted text-uppercase">{{ environment }}</small>
        </div>

        <router-link to="/" exact class="nw-nav-link" :class="{ active: isActive('/') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Zm9.75 0a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 8.25 20.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Zm9.75 0a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 15.75V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
            <span>Dashboard</span>
        </router-link>

        <router-link to="/issues" class="nw-nav-link" :class="{ active: isActive('/issues') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 9h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>Issues</span>
            <span v-if="unhandled" class="nw-nav-badge">{{ unhandled }}</span>
        </router-link>

        <a href="#" class="nw-nav-link" :class="{ open: activityOpen }" v-on:click.prevent="toggleActivity">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M3 12h4l3 8 4-16 3 8h4" />
            </svg>
            <span>Activity</span>
            <svg class="nw-nav-chevron" viewBox="0 0 24 24">
                <path d="M9 5.25 15.75 12 9 18.75" />
            </svg>
        </a>

        <div class="nw-nav-tree" v-show="activityOpen">
            <router-link
                v-for="item in activityItems"
                :key="item.to"
                :to="item.to"
                class="nw-nav-link nw-nav-child"
                :class="{ active: isActive(item.to, item.aliases || []) }"
            >
                {{ item.label }}
            </router-link>
        </div>

        <div class="nw-nav-section">Monitoring</div>

        <router-link to="/users" class="nw-nav-link" :class="{ active: isActive('/users') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            <span>Users</span>
        </router-link>

        <router-link to="/logs" class="nw-nav-link" :class="{ active: isActive('/logs') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M8 6v12M16 3v15" />
            </svg>
            <span>Logs</span>
        </router-link>

        <router-link to="/monitored-tags" class="nw-nav-link" :class="{ active: isActive('/monitored-tags') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                <path d="M6 6h.008v.008H6V6Z" />
            </svg>
            <span>Monitored tags</span>
        </router-link>

        <router-link to="/settings" class="nw-nav-link" :class="{ active: isActive('/settings') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <span>Settings</span>
        </router-link>

        <router-link to="/support" class="nw-nav-link" :class="{ active: isActive('/support') }">
            <svg class="nw-nav-icon" viewBox="0 0 24 24">
                <path d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
            </svg>
            <span>Support</span>
        </router-link>

        <div class="nw-nav-footer">
            <div class="nw-nav-user" v-if="currentUser">
                <div class="nw-nav-user-avatar">{{ userInitials }}</div>
                <div class="nw-nav-user-meta">
                    <div class="nw-nav-user-name">{{ currentUser.name }}</div>
                    <div class="nw-nav-user-email" v-if="currentUser.email">{{ currentUser.email }}</div>
                </div>
            </div>

            <a href="#" class="nw-nav-link" v-on:click.prevent="logout">
                <svg class="nw-nav-icon" viewBox="0 0 24 24">
                    <path d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</template>
