<script type="text/ecmascript-6">
import RequestDetailsPanel from '../../components/RequestDetailsPanel.vue';

export default {
    components: {
        RequestDetailsPanel,
    },
}
</script>

<template>
    <request-details-panel :id="$route.params.id"></request-details-panel>
</template>
