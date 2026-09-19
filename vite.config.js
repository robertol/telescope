import vue2 from '@vitejs/plugin-vue2';

/** @type {import('vite').UserConfig} */
export default {
    plugins: [vue2()],
    build: {
        assetsDir: '',
        chunkSizeWarningLimit: 2000,
        rollupOptions: {
            input: ['resources/js/app.js', 'resources/sass/styles-dark.scss'],
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm.js',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: [
                    'legacy-js-api',
                    'import',
                    'global-builtin',
                    'color-functions',
                    'abs-percent',
                    'mixed-decls',
                ],
            },
        },
    },
};
