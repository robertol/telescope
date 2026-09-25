<script type="text/ecmascript-6">
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
        };
    },

    methods: {
        toggleExpanded(id) {
            this.expandedId = this.expandedId === id ? null : id;
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
            <router-link :to="{ path: '/requests', query: { hours: periodHours } }" class="nw-shell-action">
                Clear filter
            </router-link>
        </div>

        <monitored-requests></monitored-requests>

        <index-screen title="Requests" resource="requests" endpoint-override="" remember-closed-key="telescopeRequestsCardClosed" :row-clickable="true" v-on:row-click="toggleExpanded($event.id)">
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
