<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta Information -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAERlWElmTU0AKgAAAAgAAYdpAAQAAAABAAAAGgAAAAAAA6ABAAMAAAABAAEAAKACAAQAAAABAAAAIKADAAQAAAABAAAAIAAAAACshmLzAAADQ0lEQVRYCbVXXXLaMBCWaGfIW+kNdIPCS9v0BeUEhRMEThByAuAEDSconCDkBJiX/jxBTxDfoDwm04mVb72WbbBkTIGdEZJ3V7ufdlc/SFGRtF41hHjqCCHbaE0hjMJU8GLa4HeNFoK/FOJiHgQt4u0luU9D659KiDc3QkQ96FqH+6aRfAow4yC4DOnDR14AvOJ/Qzge+CZX48tREHwa+3SdAHjVcoFJyjfxQH6IaFy5olEAcAbnFqsTRM1KqT/SORXdFC1Ec5FC8S7YRyZOAXDOjwo7Cu5zH05uM/OFUQKCdhRTCkAIKrhjcm4oAqAo6fnL8auEeCZfMcUAOCy+apdrq1zeWwDeFOSnD2wqkgjIFFFeC5XbxxZqgTfZ5he+wiD4MicuV7qZFTQKDDkgVo1zL3r0UaR0Ve92ZAizGRNAbnUCmRJA9FALXZZFtxCEqTAbXJNviVD0oPw94++OKAWmuc2NHrDizjbP/+X3Yfpv4bztn0qSXefEq7VhVNMIhPAXj1uO7BOA1xrQwVFuYuXtH9lGDdDFcjDBKG3ZuD1q/etb3oLWv4eo9L8sN/fuRcQzmgAQ32r5+f8zpqrWNJFXbkY0rkCKdgGF6BSk2AiFvTI1km1YacIGWmElzQOUUISCDFeJArZdfcq5dXmQQ61/wE7t2l1wrjligwjI0CkqMCNUe+krR8E5itG1awrGLCMEgOiP/SrpAwBds5wOoJPRes9BREfx5dTljqv9+RGyKulzmQDP9AGArkavoTmUHvAmXAfBxzgCyQHTAR/zKOQF2oAzR9NoCq2E6u9rSV5nHi04omM6Wtl9DrAr5jmdw4xp8bsgvh8IjI+m5Bs1QGTuuC/9VYnU9h7li9gpL6yswLmWpLWC45RA3NhvRw+5XALsvUOWZ82hM0Havpa8qCeI0oAm5QDEtYDwCkWCM1KI8wRp4i2dpIAeEsQwV3AMhbMRbNPzPDtPUgDkkq/Vly6GUDw5weZLd/fqTlOQd4eKV8jOAjz0J6GQV158N2xFwLpilPE2QjEdTbBBOS86J8vOCORdcjTECKq4ZCoT6knMsOo7n2NraS8Aq5idgELjBPwA4wqyRiKHQ9rzZo22POTv+Suv7yMtDTWEQwAAAABJRU5ErkJggg==">

    <meta name="robots" content="noindex, nofollow">

    <title>Telescope{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>

    <!-- Style sheets-->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />

    {{ Laravel\Telescope\Telescope::css() }}
    {{ Laravel\Telescope\Telescope::js() }}
</head>
<body>
<div id="telescope" v-cloak>
    <alert :message="alert.message"
           :type="alert.type"
           :auto-close="alert.autoClose"
           :confirmation-proceed="alert.confirmationProceed"
           :confirmation-cancel="alert.confirmationCancel"
           v-if="alert.type"></alert>

    <div class="nw-app" :class="{ 'sidebar-open': sidebarOpen }">
        <div class="nw-sidebar-backdrop" v-on:click="sidebarOpen = false"></div>
        <aside class="nw-sidebar">
            <sidebar></sidebar>
        </aside>

        <div class="nw-main">
            <div class="nw-toolbar">
                <button type="button" class="nw-sidebar-toggle btn btn-muted align-items-center py-2" v-on:click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen ? 'true' : 'false'" aria-label="Toggle navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor">
                        <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 5.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10.25zm.75 4.75a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="nw-toolbar-actions">
                <label class="d-flex align-items-center mb-0">
                    <input type="number" min="1" max="8760" class="form-control form-control-sm" style="width: 4.5rem;" v-model.number="pruneHours" :disabled="pruning" title="Horas a manter">
                    <button type="button" class="btn btn-muted ml-2 d-flex align-items-center py-2" v-on:click="pruneEntries" :disabled="pruning || pruneAllowed !== true" :title="pruneTitle">
                        @{{ pruneLabel }}
                    </button>
                </label>

                <button class="btn btn-muted d-flex align-items-center py-2" v-on:click.prevent="toggleRecording" :title="recording ? 'Pause recording' : 'Resume recording'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor" v-if="recording">
                        <path d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zM12.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor" v-else>
                        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                    </svg>
                </button>

                <div class="dropdown">
                    <button class="btn btn-muted d-flex align-items-center py-2" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Clear entries">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <button type="button" class="dropdown-item" v-on:click="clearEntries(false, false)">Delete all</button>
                        <button type="button" class="dropdown-item" v-on:click="clearEntries(false, true)">Preserve Only Monitoring</button>
                    </div>
                </div>

                <button class="btn btn-muted d-flex align-items-center py-2" :class="{active: autoLoadsNewEntries}" v-on:click.prevent="autoLoadNewEntries" title="Auto load entries">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor">
                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                    </svg>
                </button>

                <router-link to="/monitored-tags" class="btn btn-muted d-flex align-items-center py-2" title="Monitoring">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="icon" fill="currentColor">
                        <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                        <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                    </svg>
                </router-link>
                </div>
            </div>

            <router-view></router-view>
        </div>
    </div>
</div>
</body>
</html>
