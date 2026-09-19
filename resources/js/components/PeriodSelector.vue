<script type="text/ecmascript-6">
export default {
    computed: {
        options() {
            return [
                { label: '1H', hours: 1 },
                { label: '24H', hours: 24 },
                { label: '7D', hours: 168 },
                { label: '14D', hours: 336 },
                { label: '30D', hours: 720 },
            ];
        },
    },
};
</script>

<template>
    <div class="period-selector" role="group">
        <button
            v-for="option in options"
            :key="option.hours"
            type="button"
            class="btn btn-sm btn-muted"
            :class="{ active: periodHours === option.hours }"
            v-on:click="setPeriod(option.hours)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
