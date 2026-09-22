export const STATUS_SERIES = [
    { key: '123xx', color: '#8b93a7', label: '1/2/3XX' },
    { key: '4xx', color: '#e8a54b', label: '4XX' },
    { key: '5xx', color: '#e85d6c', label: '5XX' },
];

export const DURATION_SERIES = [
    { key: 'avg', color: '#9aa3af', label: 'AVG', width: 1.6 },
    { key: 'p95', color: '#e8a54b', label: 'P95', width: 2.1 },
];

export const JOB_SERIES = [
    { key: 'processed', color: '#8b7cf7', label: 'PROCESSED' },
    { key: 'pending', color: '#e8a54b', label: 'RELEASED' },
    { key: 'failed', color: '#e85d6c', label: 'FAILED' },
];

export const RESOURCE_COLORS = {
    request: '#8b93a7',
    exception: '#e85d6c',
    job: '#8b7cf7',
    query: '#34d399',
    log: '#9aa3af',
};

export const CHART_HEIGHT = {
    sparkline: 72,
    pane: 160,
    dashboard: 188,
};
