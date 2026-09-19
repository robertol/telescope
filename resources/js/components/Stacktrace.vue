<script type="text/ecmascript-6">
export default {
    props: ['trace'],

    data() {
        return {
            showVendor: false,
        };
    },

    computed: {
        frames() {
            return (this.trace || []).map((line) => {
                const file = line.file || '';
                const vendor = /[\/\\]vendor[\/\\]/.test(file);

                return Object.assign({}, line, { vendor });
            });
        },

        hiddenCount() {
            return this.frames.filter((line) => line.vendor).length;
        },

        lines() {
            if (this.showVendor) {
                return this.frames;
            }

            return this.frames.filter((line) => !line.vendor);
        },
    },
};
</script>

<template>
    <table class="table mb-0">
        <tbody>
            <tr v-for="(line, index) in lines" :key="index">
                <td class="card-bg-secondary">
                    <code>{{ line.file }}:{{ line.line }}</code>
                </td>
            </tr>

            <tr v-if="!showVendor && hiddenCount">
                <td class="card-bg-secondary">
                    <a href="#" v-on:click.prevent="showVendor = true">{{ hiddenCount }} hidden lines</a>
                </td>
            </tr>
        </tbody>
    </table>
</template>
