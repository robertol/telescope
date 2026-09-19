<script type="text/ecmascript-6">
import axios from 'axios';

export default {
    data() {
        return {
            recording: Telescope.recording,
            watchers: [],
            accounts: [],
            form: { name: '', email: '', password: '' },
            error: '',
            ready: false,
        };
    },

    mounted() {
        document.title = 'Settings - Telescope';
        this.load();
    },

    methods: {
        load() {
            axios.get(Telescope.basePath + '/telescope-api/settings').then((response) => {
                this.recording = response.data.recording;
                this.watchers = response.data.watchers || [];
            });

            axios.get(Telescope.basePath + '/telescope-api/accounts').then((response) => {
                this.accounts = response.data.users || [];
                this.ready = true;
            });
        },

        toggleRecording() {
            axios.post(Telescope.basePath + '/telescope-api/toggle-recording').then(() => {
                this.recording = !this.recording;
                window.Telescope.recording = this.recording;
                this.$root.recording = this.recording;
            });
        },

        createAccount() {
            this.error = '';

            axios
                .post(Telescope.basePath + '/telescope-api/accounts', this.form)
                .then((response) => {
                    this.accounts.push(response.data.user);
                    this.form = { name: '', email: '', password: '' };
                })
                .catch((error) => {
                    this.error =
                        (error.response && error.response.data && error.response.data.message) ||
                        'Unable to create the Telescope user.';
                });
        },

        deleteAccount(user) {
            if (!window.confirm('Remove ' + user.email + ' from the Telescope dashboard?')) {
                return;
            }

            axios
                .delete(Telescope.basePath + '/telescope-api/accounts/' + user.id)
                .then(() => {
                    this.accounts = this.accounts.filter((item) => item.id !== user.id);
                })
                .catch((error) => {
                    this.error =
                        (error.response && error.response.data && error.response.data.message) ||
                        'Unable to delete the Telescope user.';
                });
        },
    },
};
</script>

<template>
    <div>
        <h1 class="nw-page-title mb-4">Settings</h1>

        <div class="card mb-4">
            <div class="card-header"><h2 class="h6 m-0">Dashboard users</h2></div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    These accounts belong to Telescope only. They are not Laravel application users.
                </p>
                <div v-if="error" class="text-danger small mb-3">{{ error }}</div>
                <form class="form-row align-items-end mb-4" v-on:submit.prevent="createAccount">
                    <div class="col-md-3">
                        <label class="small text-muted">Name</label>
                        <input v-model="form.name" type="text" class="form-control" required />
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Email</label>
                        <input v-model="form.email" type="email" class="form-control" required />
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted">Password</label>
                        <input v-model="form.password" type="password" class="form-control" minlength="8" required />
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-muted btn-block">Add user</button>
                    </div>
                </form>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in accounts" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-muted" v-on:click="deleteAccount(user)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h2 class="h6 m-0">Recording</h2></div>
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="font-weight-bold">{{ recording ? 'Recording' : 'Paused' }}</div>
                    <div class="text-muted small">Pause stops new entries from being stored.</div>
                </div>
                <button type="button" class="btn btn-muted" v-on:click="toggleRecording">
                    {{ recording ? 'Pause' : 'Resume' }}
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="h6 m-0">Watchers</h2></div>
            <div v-if="!ready" class="p-4 text-muted">Fetching...</div>
            <table v-else class="table mb-0">
                <tbody>
                    <tr v-for="watcher in watchers" :key="watcher.class">
                        <td>{{ watcher.name }}</td>
                        <td class="text-right">
                            <span class="badge" :class="watcher.enabled ? 'badge-success' : 'badge-secondary'">
                                {{ watcher.enabled ? 'enabled' : 'off' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
