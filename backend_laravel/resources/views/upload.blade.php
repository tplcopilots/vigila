<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Chunk Upload Resume</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f8fc; margin: 0; padding: 24px; }
        .page { max-width: 1280px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 20px; border: 1px solid #e6e8f5; box-shadow: 0 8px 24px rgba(41, 38, 98, 0.08); }
        h1, h2, h3 { margin-top: 0; color: #292662; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        input, select, button { padding: 10px 12px; border-radius: 8px; border: 1px solid #ccd0e4; }
        button { background: #292662; color: #fff; border: none; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .progress-wrap { margin-top: 16px; }
        progress { width: 100%; height: 20px; }
        .muted { color: #5d647f; font-size: 14px; }
        .ok { color: #0c7a43; }
        .err { color: #b42318; }
        pre { background: #f5f6fb; padding: 10px; border-radius: 8px; overflow-x: auto; }
        .panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
        .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
        .metric { background: #f5f6fb; border: 1px solid #e6e8f5; border-radius: 10px; padding: 12px; }
        .metric .label { color: #5d647f; font-size: 12px; }
        .metric .value { color: #292662; font-size: 18px; font-weight: 700; margin-top: 4px; }
        .bars { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
        .bar-row { display: grid; grid-template-columns: 88px 1fr 44px; align-items: center; gap: 8px; font-size: 12px; color: #5d647f; }
        .bar-track { background: #eef0fa; border-radius: 999px; height: 8px; overflow: hidden; }
        .bar-fill { background: #292662; height: 100%; }
        .viewer-wrap { display: grid; grid-template-columns: 250px 1fr; gap: 12px; min-height: 380px; }
        .file-list { border: 1px solid #e6e8f5; border-radius: 10px; overflow: auto; max-height: 420px; }
        .file-item { display: block; width: 100%; text-align: left; padding: 10px; border: none; background: #fff; border-bottom: 1px solid #f0f2fa; cursor: pointer; }
        .file-item:hover, .file-item.active { background: #eef0fa; }
        .file-name { color: #292662; font-weight: 600; font-size: 13px; display: block; }
        .file-meta { color: #5d647f; font-size: 11px; }
        .preview { border: 1px solid #e6e8f5; border-radius: 10px; padding: 12px; background: #fafbff; min-height: 380px; }
        .preview img, .preview video, .preview audio, .preview iframe { width: 100%; max-height: 340px; border-radius: 8px; }
        .preview-empty { color: #5d647f; font-size: 14px; display: flex; align-items: center; justify-content: center; min-height: 320px; text-align: center; }
        .chart-grid { display:grid; grid-template-columns: 1fr; gap: 12px; margin-top: 12px; }
        .chart-card { border: 1px solid #e6e8f5; border-radius: 10px; background: #fafbff; padding: 10px; }
        .chart-title { font-size: 12px; color: #5d647f; margin-bottom: 6px; }
        .chart-card canvas { width: 100% !important; height: 220px !important; }
        @media (max-width: 960px) {
            .panel-grid { grid-template-columns: 1fr; }
            .viewer-wrap { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page">
<div class="card">
    <h1>File Upload with Auto Resume</h1>

    <div class="row">
        <input type="file" id="videoFile" multiple />
        <select id="provider">
            <option value="firebase">firebase</option>
            <option value="oneDrive">oneDrive</option>
            <option value="cloudServer">cloudServer</option>
        </select>
        <button id="startBtn">Start Upload</button>
        <button id="resumeBtn" disabled>Resume Upload</button>
        <button id="pauseBtn" disabled>Pause</button>
        <button id="cancelBtn" disabled>Cancel</button>
        <button id="enableAutoBtn">Enable Auto Resume</button>
    </div>

    <div class="muted" id="meta"></div>

    <div class="progress-wrap">
        <progress id="progress" value="0" max="100"></progress>
        <div id="progressText" class="muted">0%</div>
    </div>

    <p id="status" class="muted">Select a file to begin.</p>
    <pre id="result"></pre>

    <h3 style="color:#292662; margin-top: 20px;">Batch File Progress</h3>
    <div style="overflow-x:auto;">
        <table id="batchTable" style="width:100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background:#f5f6fb;">
                    <th style="text-align:left; padding:8px; border:1px solid #e6e8f5;">File</th>
                    <th style="text-align:left; padding:8px; border:1px solid #e6e8f5; width:150px;">Progress</th>
                    <th style="text-align:left; padding:8px; border:1px solid #e6e8f5; width:220px;">Status</th>
                    <th style="text-align:left; padding:8px; border:1px solid #e6e8f5;">Response</th>
                </tr>
            </thead>
            <tbody id="batchRows">
                <tr>
                    <td colspan="4" class="muted" style="padding:8px; border:1px solid #e6e8f5;">No batch started yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="panel-grid">
    <div class="card">
        <h2>Upload Analytics</h2>
        <div class="metric-grid">
            <div class="metric">
                <div class="label">Total Files</div>
                <div class="value" id="metricFiles">0</div>
            </div>
            <div class="metric">
                <div class="label">Total Size</div>
                <div class="value" id="metricSize">0 B</div>
            </div>
            <div class="metric">
                <div class="label">Latest Upload</div>
                <div class="value" id="metricLatest" style="font-size:12px; font-weight:600;">-</div>
            </div>
        </div>

        <div class="muted">Type Distribution</div>
        <div id="typeBars" class="bars"></div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title">Chunk State (Pie)</div>
                <canvas id="chunkPieChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Pending Uploads by Provider (Bar)</div>
                <canvas id="providerBarChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">File Types (Bar)</div>
                <canvas id="fileTypeBarChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Uploaded File Viewer</h2>
        <div class="viewer-wrap">
            <div id="uploadedList" class="file-list"></div>
            <div class="preview" id="previewPane">
                <div class="preview-empty">Select an uploaded file to preview here.</div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(() => {
    const CHUNK_SIZE = 1024 * 1024;
    const MAX_RETRY = 3;

    const fileInput = document.getElementById('videoFile');
    const providerSelect = document.getElementById('provider');
    const startBtn = document.getElementById('startBtn');
    const resumeBtn = document.getElementById('resumeBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const enableAutoBtn = document.getElementById('enableAutoBtn');
    const progress = document.getElementById('progress');
    const progressText = document.getElementById('progressText');
    const status = document.getElementById('status');
    const result = document.getElementById('result');
    const meta = document.getElementById('meta');
    const batchRows = document.getElementById('batchRows');
    const metricFiles = document.getElementById('metricFiles');
    const metricSize = document.getElementById('metricSize');
    const metricLatest = document.getElementById('metricLatest');
    const typeBars = document.getElementById('typeBars');
    const uploadedList = document.getElementById('uploadedList');
    const previewPane = document.getElementById('previewPane');
    const chunkPieCanvas = document.getElementById('chunkPieChart');
    const providerBarCanvas = document.getElementById('providerBarChart');
    const fileTypeBarCanvas = document.getElementById('fileTypeBarChart');

    let paused = false;
    let cancelled = false;
    let autoContinueMode = false;
    let currentState = null;
    let selectedFile = null;
    let selectedFiles = [];
    let batchUploading = false;
    let dashboardRefreshTimer = null;
    let openedViewerFileName = null;
    let freezeRightViewerRefresh = false;
    let chunkPieChart = null;
    let providerBarChart = null;
    let fileTypeBarChart = null;
    const LAST_UPLOAD_KEY = 'upload_last_state';
    const AUTO_CONTINUE_KEY = 'upload_auto_continue';
    const HANDLE_DB_NAME = 'upload_file_handles';
    const HANDLE_STORE_NAME = 'handles';
    const HANDLE_KEY = 'pending_file_handle';
    const CHUNK_DB_NAME = 'upload_chunk_cache';
    const CHUNK_STORE_NAME = 'chunks';
    const CACHE_META_PREFIX = 'upload_cache_meta:';

    function setStatus(message, kind = 'muted') {
        status.className = kind;
        status.textContent = message;
    }

    function setProgress(uploaded, total) {
        const percent = total === 0 ? 0 : Math.floor((uploaded / total) * 100);
        progress.value = percent;
        progressText.textContent = `${percent}% (${uploaded}/${total} chunks)`;
    }

    function escapeHtml(input) {
        return String(input)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function getBatchRowId(file) {
        return `batch_${btoa(unescape(encodeURIComponent(fileFingerprint(file)))).replaceAll('=', '')}`;
    }

    function initBatchRows(files) {
        if (!files.length) {
            batchRows.innerHTML = `
                <tr>
                    <td colspan="4" class="muted" style="padding:8px; border:1px solid #e6e8f5;">No batch started yet.</td>
                </tr>
            `;
            return;
        }

        batchRows.innerHTML = files.map((file) => {
            const rowId = getBatchRowId(file);
            return `
                <tr id="${rowId}">
                    <td style="padding:8px; border:1px solid #e6e8f5;">${escapeHtml(file.name)}</td>
                    <td data-col="progress" style="padding:8px; border:1px solid #e6e8f5;">0%</td>
                    <td data-col="status" class="muted" style="padding:8px; border:1px solid #e6e8f5;">Pending</td>
                    <td data-col="response" style="padding:8px; border:1px solid #e6e8f5; white-space: pre-wrap;">-</td>
                </tr>
            `;
        }).join('');
    }

    function setBatchRowProgress(file, uploaded, total) {
        const row = document.getElementById(getBatchRowId(file));
        if (!row) return;
        const percent = total === 0 ? 0 : Math.floor((uploaded / total) * 100);
        row.querySelector('[data-col="progress"]').textContent = `${percent}% (${uploaded}/${total})`;
    }

    function setBatchRowStatus(file, message, kind = 'muted') {
        const row = document.getElementById(getBatchRowId(file));
        if (!row) return;
        const cell = row.querySelector('[data-col="status"]');
        cell.className = kind;
        cell.textContent = message;
    }

    function setBatchRowResponse(file, responsePayload) {
        const row = document.getElementById(getBatchRowId(file));
        if (!row) return;
        const value = typeof responsePayload === 'string'
            ? responsePayload
            : JSON.stringify(responsePayload, null, 2);
        row.querySelector('[data-col="response"]').textContent = value;
    }

    function ensureCharts() {
        if (!window.Chart) {
            return;
        }

        if (!chunkPieChart && chunkPieCanvas) {
            chunkPieChart = new Chart(chunkPieCanvas, {
                type: 'pie',
                data: {
                    labels: ['Pending Chunks', 'Running Chunks', 'Completed Files'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: ['#f59e0b', '#292662', '#0c7a43'],
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        }

        if (!providerBarChart && providerBarCanvas) {
            providerBarChart = new Chart(providerBarCanvas, {
                type: 'bar',
                data: {
                    labels: ['firebase', 'oneDrive', 'cloudServer'],
                    datasets: [{
                        label: 'Pending Uploads',
                        data: [0, 0, 0],
                        backgroundColor: '#292662',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } },
                },
            });
        }

        if (!fileTypeBarChart && fileTypeBarCanvas) {
            fileTypeBarChart = new Chart(fileTypeBarCanvas, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Files',
                        data: [],
                        backgroundColor: '#4f46e5',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } },
                },
            });
        }
    }

    function updateCharts(analytics) {
        ensureCharts();
        if (!analytics) {
            return;
        }

        const chunkState = analytics.chunkState || {};
        if (chunkPieChart) {
            chunkPieChart.data.datasets[0].data = [
                Number(chunkState.pendingChunks || 0),
                Number(chunkState.runningChunks || 0),
                Number(chunkState.completedFiles || 0),
            ];
            chunkPieChart.update();
        }

        const provider = analytics.pendingByProvider || {};
        if (providerBarChart) {
            providerBarChart.data.datasets[0].data = [
                Number(provider.firebase || 0),
                Number(provider.oneDrive || 0),
                Number(provider.cloudServer || 0),
            ];
            providerBarChart.update();
        }

        const typeCounts = analytics.fileTypeCounts || {};
        const labels = Object.keys(typeCounts);
        const values = labels.map((key) => Number(typeCounts[key] || 0));
        if (fileTypeBarChart) {
            fileTypeBarChart.data.labels = labels.length ? labels : ['none'];
            fileTypeBarChart.data.datasets[0].data = values.length ? values : [0];
            fileTypeBarChart.update();
        }
    }

    function formatBytes(value) {
        const bytes = Number(value || 0);
        if (bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let idx = 0;
        while (size >= 1024 && idx < units.length - 1) {
            size /= 1024;
            idx += 1;
        }
        return `${size.toFixed(size >= 10 ? 0 : 1)} ${units[idx]}`;
    }

    function fileCategory(extension) {
        const ext = String(extension || '').toLowerCase();
        if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp'].includes(ext)) return 'image';
        if (['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'mpeg'].includes(ext)) return 'audio';
        if (['mp4', 'avi', 'mpeg', 'mov', 'mkv', 'webm'].includes(ext)) return 'video';
        if (['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'rtf'].includes(ext)) return 'document';
        return 'other';
    }

    function renderTypeBars(files) {
        const groups = { image: 0, audio: 0, video: 0, document: 0, other: 0 };
        for (const file of files) {
            groups[fileCategory(file.extension)] += 1;
        }

        const total = Math.max(files.length, 1);
        typeBars.innerHTML = Object.entries(groups)
            .map(([name, count]) => {
                const pct = Math.round((count / total) * 100);
                return `
                    <div class="bar-row">
                        <span>${name.toUpperCase()}</span>
                        <div class="bar-track"><div class="bar-fill" style="width:${pct}%;"></div></div>
                        <span>${count}</span>
                    </div>
                `;
            })
            .join('');
    }

    function renderPreview(file) {
        if (!file) {
            previewPane.innerHTML = '<div class="preview-empty">Select an uploaded file to preview here.</div>';
            return;
        }

        const ext = String(file.extension || '').toLowerCase();
        const encoded = encodeURIComponent(file.name);
        const viewUrl = `/upload/files/${encoded}/view`;
        const downloadUrl = `/upload/files/${encoded}/download`;

        let body = '';
        if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp'].includes(ext)) {
            body = `<img src="${viewUrl}" alt="${escapeHtml(file.name)}" />`;
        } else if (['mp4', 'avi', 'mpeg', 'mov', 'mkv', 'webm'].includes(ext)) {
            body = `<video src="${viewUrl}" controls preload="metadata"></video>`;
        } else if (['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a', 'mpeg'].includes(ext)) {
            body = `<audio src="${viewUrl}" controls preload="metadata"></audio>`;
        } else if (['pdf', 'txt', 'html', 'csv'].includes(ext)) {
            body = `<iframe src="${viewUrl}"></iframe>`;
        } else {
            body = `<div class="preview-empty">Preview not available for this file type.<br/>Use download to open locally.</div>`;
        }

        previewPane.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:8px;">
                <div>
                    <div style="color:#292662; font-weight:700;">${escapeHtml(file.name)}</div>
                    <div class="muted">${(file.extension || 'unknown').toUpperCase()} • ${formatBytes(file.sizeBytes)}</div>
                </div>
                <a href="${downloadUrl}" style="background:#292662;color:#fff;padding:8px 10px;border-radius:8px;text-decoration:none;">Download</a>
            </div>
            ${body}
        `;
    }

    function renderUploadedList(files, options = {}) {
        const {
            preserveOpenedSelection = false,
            skipPreviewUpdate = false,
        } = options;

        if (!files.length) {
            uploadedList.innerHTML = '<div class="muted" style="padding:10px;">No uploaded files found yet.</div>';
            renderPreview(null);
            openedViewerFileName = null;
            freezeRightViewerRefresh = false;
            return;
        }

        const map = new Map(files.map(file => [file.name, file]));
        const activeFile = preserveOpenedSelection && openedViewerFileName && map.get(openedViewerFileName)
            ? map.get(openedViewerFileName)
            : files[0];

        uploadedList.innerHTML = files.map((file) => `
            <button type="button" class="file-item ${activeFile?.name === file.name ? 'active' : ''}" data-name="${escapeHtml(file.name)}">
                <span class="file-name">${escapeHtml(file.name)}</span>
                <span class="file-meta">${(file.extension || 'unknown').toUpperCase()} • ${formatBytes(file.sizeBytes)}</span>
            </button>
        `).join('');

        uploadedList.querySelectorAll('.file-item').forEach((button) => {
            button.addEventListener('click', () => {
                uploadedList.querySelectorAll('.file-item').forEach((x) => x.classList.remove('active'));
                button.classList.add('active');
                const selected = map.get(button.getAttribute('data-name'));
                openedViewerFileName = selected?.name || null;
                freezeRightViewerRefresh = true;
                renderPreview(selected || null);
            });
        });

        if (!skipPreviewUpdate) {
            renderPreview(activeFile || null);
        }
    }

    async function refreshUploadPanels() {
        try {
            const [fileData, analyticsData] = await Promise.all([
                request('/upload/files'),
                request('/upload/analytics'),
            ]);

            const files = Array.isArray(fileData.files) ? fileData.files : [];
            const analytics = analyticsData.analytics || null;
            const totalSize = files.reduce((sum, file) => sum + Number(file.sizeBytes || 0), 0);

            metricFiles.textContent = String(files.length);
            metricSize.textContent = formatBytes(totalSize);
            metricLatest.textContent = files[0]?.updatedAt
                ? new Date(files[0].updatedAt).toLocaleString()
                : '-';

            renderTypeBars(files);

            const stillExists = files.some((file) => file.name === openedViewerFileName);
            if (freezeRightViewerRefresh && !stillExists) {
                freezeRightViewerRefresh = false;
                openedViewerFileName = null;
            }

            renderUploadedList(files, {
                preserveOpenedSelection: Boolean(openedViewerFileName),
                skipPreviewUpdate: freezeRightViewerRefresh,
            });

            updateCharts(analytics);
        } catch (error) {
            metricFiles.textContent = '0';
            metricSize.textContent = '0 B';
            metricLatest.textContent = '-';
            typeBars.innerHTML = '<div class="muted">Failed to load analytics.</div>';
            if (!freezeRightViewerRefresh) {
                uploadedList.innerHTML = `<div class="err" style="padding:10px;">${escapeHtml(error.message)}</div>`;
                renderPreview(null);
            }
            updateCharts({
                chunkState: { pendingChunks: 0, runningChunks: 0, completedFiles: 0 },
                pendingByProvider: { firebase: 0, oneDrive: 0, cloudServer: 0 },
                fileTypeCounts: {},
            });
        }
    }

    function startDashboardAutoRefresh() {
        stopDashboardAutoRefresh();
        dashboardRefreshTimer = window.setInterval(() => {
            if (document.hidden) {
                return;
            }
            refreshUploadPanels();
        }, 5000);
    }

    function stopDashboardAutoRefresh() {
        if (dashboardRefreshTimer !== null) {
            clearInterval(dashboardRefreshTimer);
            dashboardRefreshTimer = null;
        }
    }

    function fileFingerprint(file) {
        return `${file.name}:${file.size}:${file.lastModified}`;
    }

    function stateKey(file) {
        return `upload_state:${fileFingerprint(file)}`;
    }

    function saveState(file, state) {
        localStorage.setItem(stateKey(file), JSON.stringify(state));
        localStorage.setItem(LAST_UPLOAD_KEY, JSON.stringify({
            fileName: file.name,
            mimeType: file.type || null,
            fileSize: file.size,
            fileLastModified: file.lastModified,
            fileId: state.fileId,
            totalChunks: state.totalChunks,
            provider: state.provider,
            savedAt: Date.now(),
        }));
    }

    function loadState(file) {
        const raw = localStorage.getItem(stateKey(file));
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (_) { return null; }
    }

    function loadLastState() {
        const raw = localStorage.getItem(LAST_UPLOAD_KEY);
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (_) { return null; }
    }

    function clearState(file) {
        localStorage.removeItem(stateKey(file));
        localStorage.removeItem(LAST_UPLOAD_KEY);
        localStorage.removeItem(AUTO_CONTINUE_KEY);
        const fileId = currentState?.fileId;
        if (fileId) {
            clearCachedChunks(fileId).catch(() => {});
        }
        clearStoredHandle().catch(() => {});
        selectedFile = null;
    }

    function setAutoContinue(value) {
        autoContinueMode = value;
        localStorage.setItem(AUTO_CONTINUE_KEY, value ? '1' : '0');
    }

    function loadAutoContinue() {
        return localStorage.getItem(AUTO_CONTINUE_KEY) === '1';
    }

    function pickFileForResume() {
        fileInput.value = '';
        fileInput.click();
    }

    function getCurrentFile() {
        return selectedFile || selectedFiles[0] || fileInput.files[0] || null;
    }

    function getSelectedFiles() {
        if (selectedFiles.length > 0) {
            return selectedFiles;
        }
        return Array.from(fileInput.files || []);
    }

    function openHandleDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(HANDLE_DB_NAME, 1);

            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(HANDLE_STORE_NAME)) {
                    db.createObjectStore(HANDLE_STORE_NAME);
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function saveStoredHandle(handle) {
        const db = await openHandleDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(HANDLE_STORE_NAME, 'readwrite');
            tx.objectStore(HANDLE_STORE_NAME).put(handle, HANDLE_KEY);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        db.close();
    }

    async function loadStoredHandle() {
        const db = await openHandleDb();
        const handle = await new Promise((resolve, reject) => {
            const tx = db.transaction(HANDLE_STORE_NAME, 'readonly');
            const req = tx.objectStore(HANDLE_STORE_NAME).get(HANDLE_KEY);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
        db.close();
        return handle;
    }

    async function clearStoredHandle() {
        const db = await openHandleDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(HANDLE_STORE_NAME, 'readwrite');
            tx.objectStore(HANDLE_STORE_NAME).delete(HANDLE_KEY);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        db.close();
    }

    function chunkCacheKey(fileId, index) {
        return `${fileId}:${index}`;
    }

    function cacheMetaKey(fileId) {
        return `${CACHE_META_PREFIX}${fileId}`;
    }

    function openChunkDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(CHUNK_DB_NAME, 1);

            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(CHUNK_STORE_NAME)) {
                    db.createObjectStore(CHUNK_STORE_NAME);
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function putCachedChunk(fileId, index, bytes) {
        const db = await openChunkDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(CHUNK_STORE_NAME, 'readwrite');
            tx.objectStore(CHUNK_STORE_NAME).put(bytes, chunkCacheKey(fileId, index));
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        db.close();
    }

    async function getCachedChunk(fileId, index) {
        const db = await openChunkDb();
        const value = await new Promise((resolve, reject) => {
            const tx = db.transaction(CHUNK_STORE_NAME, 'readonly');
            const req = tx.objectStore(CHUNK_STORE_NAME).get(chunkCacheKey(fileId, index));
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
        db.close();
        return value;
    }

    async function clearCachedChunks(fileId) {
        const metaRaw = localStorage.getItem(cacheMetaKey(fileId));
        const meta = metaRaw ? JSON.parse(metaRaw) : null;
        const totalChunks = Number(meta?.totalChunks || 0);

        const db = await openChunkDb();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(CHUNK_STORE_NAME, 'readwrite');
            for (let i = 0; i < totalChunks; i++) {
                tx.objectStore(CHUNK_STORE_NAME).delete(chunkCacheKey(fileId, i));
            }
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
        db.close();

        localStorage.removeItem(cacheMetaKey(fileId));
    }

    function setCacheMeta(fileId, meta) {
        localStorage.setItem(cacheMetaKey(fileId), JSON.stringify(meta));
    }

    function getCacheMeta(fileId) {
        const raw = localStorage.getItem(cacheMetaKey(fileId));
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (_) { return null; }
    }

    async function cacheEntireFile(file, fileId, totalChunks) {
        const existingMeta = getCacheMeta(fileId);
        if (existingMeta?.complete === true && Number(existingMeta.totalChunks) === Number(totalChunks)) {
            return;
        }

        setStatus('Preparing local chunk cache for full auto-resume...', 'muted');

        for (let index = 0; index < totalChunks; index++) {
            const start = index * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunkBlob = file.slice(start, end);
            const buffer = await chunkBlob.arrayBuffer();
            await putCachedChunk(fileId, index, buffer);

            setCacheMeta(fileId, {
                totalChunks,
                complete: index === totalChunks - 1,
                cachedChunks: index + 1,
                updatedAt: Date.now(),
            });
        }
    }

    async function getCachedChunkBase64(fileId, index) {
        const buffer = await getCachedChunk(fileId, index);
        if (!buffer) {
            throw new Error(`Missing cached chunk ${index}`);
        }

        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    async function tryFileFromStoredHandle(allowPermissionRequest = false) {
        if (!('showOpenFilePicker' in window)) {
            return null;
        }

        const handle = await loadStoredHandle();
        if (!handle) {
            return null;
        }

        let perm = await handle.queryPermission({ mode: 'read' });
        if (perm !== 'granted' && allowPermissionRequest) {
            perm = await handle.requestPermission({ mode: 'read' });
        }

        if (perm !== 'granted') {
            return null;
        }

        const file = await handle.getFile();
        selectedFile = file;
        return file;
    }

    async function enableAutoResumeAccess() {
        if (!('showOpenFilePicker' in window)) {
            setStatus('This browser does not support persistent file access. Use Chrome/Edge for full auto-resume.', 'err');
            return;
        }

        const [handle] = await window.showOpenFilePicker({
            multiple: false,
            types: [{
                description: 'Supported Files',
                accept: {
                    'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.bmp'],
                    'audio/*': ['.mp3', '.wav', '.ogg', '.aac', '.flac', '.m4a', '.mpeg'],
                    'video/*': ['.mp4', '.avi', '.mpeg', '.mov', '.mkv', '.webm'],
                    'application/pdf': ['.pdf'],
                    'text/plain': ['.txt'],
                    'text/csv': ['.csv'],
                    'application/msword': ['.doc'],
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
                    'application/vnd.ms-excel': ['.xls'],
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
                    'application/vnd.ms-powerpoint': ['.ppt'],
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'],
                },
            }],
        });

        const permission = await handle.requestPermission({ mode: 'read' });
        if (permission !== 'granted') {
            setStatus('Auto-resume permission was not granted.', 'err');
            return;
        }

        await saveStoredHandle(handle);
        selectedFile = await handle.getFile();
        setAutoContinue(true);
        setStatus('Auto-resume enabled. Refresh will continue from last chunk automatically when pending upload exists.', 'ok');
    }

    function isSamePendingFile(file, pending) {
        if (!pending) {
            return false;
        }

        const sameName = (file.name || '') === (pending.fileName || '');
        const sameSize = Number(file.size) === Number(pending.fileSize);

        return sameName && sameSize;
    }

    async function request(url, options = {}) {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const headers = Object.assign({}, options.headers || {}, {
            'X-CSRF-TOKEN': token,
        });

        const response = await fetch(url, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || `Request failed (${response.status})`);
        }
        return data;
    }

    async function toBase64(blob) {
        const buffer = await blob.arrayBuffer();
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    async function uploadChunkWithRetry(payload) {
        let lastError = null;
        for (let attempt = 0; attempt <= MAX_RETRY; attempt++) {
            if (cancelled) {
                throw new Error('Upload cancelled by user');
            }

            try {
                await request('/upload/chunk', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                return;
            } catch (error) {
                if (String(error.message).includes('Chunk already uploaded')) {
                    return;
                }
                lastError = error;
                if (attempt < MAX_RETRY) {
                    await new Promise(resolve => setTimeout(resolve, 600 * (attempt + 1)));
                }
            }
        }
        throw lastError || new Error('Chunk upload failed after retries');
    }

    async function startOrResumeUpload(options = {}) {
        const {
            promptForFile = true,
            fileOverride = null,
            onProgress = null,
            onStatus = null,
            onResponse = null,
        } = options;

        const pushStatus = (message, kind = 'muted') => {
            setStatus(message, kind);
            if (onStatus) onStatus(message, kind);
        };

        const pushProgress = (uploaded, total) => {
            setProgress(uploaded, total);
            if (onProgress) onProgress(uploaded, total);
        };

        const pushResponse = (payload) => {
            result.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2);
            if (onResponse) onResponse(payload);
        };

        let file = fileOverride || getCurrentFile();

        if (!file) {
            file = await tryFileFromStoredHandle(promptForFile);
        }

        if (!file) {
            const lastState = loadLastState();
            if (lastState) {
                try {
                    const statusData = await request(`/upload/status?fileId=${encodeURIComponent(lastState.fileId)}&totalChunks=${lastState.totalChunks}`);
                    const missing = statusData.missingIndexes || [];
                    const uploaded = statusData.uploadedIndexes || [];

                    pushProgress(uploaded.length, Number(lastState.totalChunks) || 1);

                    if (missing.length === 0) {
                        const finalizeData = await request('/upload/finalize', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                fileId: lastState.fileId,
                                totalChunks: lastState.totalChunks,
                                provider: lastState.provider || providerSelect.value,
                                fileName: lastState.fileName || null,
                                mimeType: lastState.mimeType || null,
                                action: 'join_chunks',
                            }),
                        });

                        pushStatus('All chunks already uploaded. Finalized successfully.', 'ok');
                        pushResponse(finalizeData);
                        refreshUploadPanels();
                        localStorage.removeItem(LAST_UPLOAD_KEY);
                        resumeBtn.disabled = true;
                        return { completed: true };
                    }

                    const cacheMeta = getCacheMeta(lastState.fileId);
                    const cacheReady =
                        cacheMeta?.complete === true &&
                        Number(cacheMeta.totalChunks) === Number(lastState.totalChunks);

                    if (cacheReady) {
                        pushStatus('Pending upload found. Auto-resuming from local cache...', 'ok');
                        const provider = lastState.provider || providerSelect.value;
                        for (const index of missing) {
                            if (cancelled) {
                                pushStatus('Upload cancelled.', 'err');
                                return;
                            }

                            const payload = await getCachedChunkBase64(lastState.fileId, index);
                            await uploadChunkWithRetry({
                                fileId: lastState.fileId,
                                chunkIndex: index,
                                totalChunks: Number(lastState.totalChunks),
                                payload,
                                provider,
                            });

                            const uploadedCount = uploaded.length + 1;
                            uploaded.push(index);
                            pushProgress(uploadedCount, Number(lastState.totalChunks) || 1);
                        }

                        const finalizeData = await request('/upload/finalize', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                fileId: lastState.fileId,
                                totalChunks: Number(lastState.totalChunks),
                                provider,
                                fileName: lastState.fileName || null,
                                mimeType: lastState.mimeType || null,
                                action: 'join_chunks',
                            }),
                        });

                        pushStatus('Upload complete from cached chunks.', 'ok');
                        pushResponse(finalizeData);
                        refreshUploadPanels();
                        localStorage.removeItem(LAST_UPLOAD_KEY);
                        await clearCachedChunks(lastState.fileId).catch(() => {});
                        resumeBtn.disabled = true;
                        return { completed: true };
                    }

                    if (promptForFile) {
                        pushStatus(`Pending resume found (${uploaded.length}/${lastState.totalChunks}). Select same file to continue missing chunks.`, 'ok');
                        pickFileForResume();
                        return { completed: false };
                    }

                    pushStatus(`Resume state detected (${uploaded.length}/${lastState.totalChunks}). Waiting for auto-resume file access...`, 'muted');
                    return { completed: false };
                } catch (error) {
                    pushStatus(`Unable to fetch pending status: ${error.message}`, 'err');
                    pushResponse({ error: error.message });
                    return { completed: false, error };
                }
            }

            if (autoContinueMode) {
                if (promptForFile) {
                    pushStatus('Auto-continue is enabled. Select same file once to continue.', 'ok');
                    pickFileForResume();
                } else {
                    pushStatus('Auto-continue enabled. Waiting for browser file access to resume...', 'muted');
                }
                return { completed: false };
            }

            pushStatus('Please select a file.', 'err');
            return { completed: false };
        }

        paused = false;
        cancelled = false;
        startBtn.disabled = true;
        resumeBtn.disabled = true;
        pauseBtn.disabled = false;
        cancelBtn.disabled = false;

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const provider = providerSelect.value;

        const lastState = loadLastState();
        let state = loadState(file);
        if (!state && isSamePendingFile(file, lastState)) {
            state = {
                fileId: lastState.fileId,
                totalChunks: Number(lastState.totalChunks) || totalChunks,
                provider: lastState.provider || provider,
                fileName: lastState.fileName || file.name,
                mimeType: lastState.mimeType || file.type || null,
            };
            saveState(file, state);
        }

        const fileIdFromState = state?.fileId || null;
        const hasPreviousState = Boolean(fileIdFromState);

        meta.textContent = `File: ${file.name} | Size: ${file.size} bytes | Chunks: ${totalChunks}`;

        try {
            const initData = await request('/upload/init', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    totalChunks,
                    provider,
                    fileId: fileIdFromState,
                }),
            });

            state = {
                fileId: initData.fileId,
                totalChunks,
                provider,
                fileName: file.name,
                mimeType: file.type || null,
            };
            currentState = state;
            saveState(file, state);

            await cacheEntireFile(file, state.fileId, totalChunks);

            let statusData = initData;
            if (hasPreviousState) {
                statusData = await request(`/upload/status?fileId=${encodeURIComponent(state.fileId)}&totalChunks=${totalChunks}`);
            }

            const uploadedSet = new Set(statusData.uploadedIndexes || []);
            pushProgress(uploadedSet.size, totalChunks);
            pushStatus('Uploading... network failure will resume from missing chunks.', 'muted');

            for (let index = 0; index < totalChunks; index++) {
                if (cancelled) {
                    setStatus('Upload cancelled.', 'err');
                    break;
                }

                if (paused) {
                    setStatus('Upload paused. Click Resume Upload to continue.', 'muted');
                    break;
                }

                if (uploadedSet.has(index)) {
                    continue;
                }

                const start = index * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunkBlob = file.slice(start, end);
                const payload = await toBase64(chunkBlob);

                await uploadChunkWithRetry({
                    fileId: state.fileId,
                    chunkIndex: index,
                    totalChunks,
                    payload,
                    provider,
                });

                uploadedSet.add(index);
                pushProgress(uploadedSet.size, totalChunks);
                saveState(file, state);
            }

            if (paused) {
                pushStatus('Upload paused. Click Resume Upload to continue.', 'muted');
                return { completed: false, paused: true };
            }

            if (cancelled) {
                pushStatus('Upload cancelled.', 'err');
                return { completed: false, cancelled: true };
            }

            const latestStatus = await request(`/upload/status?fileId=${encodeURIComponent(state.fileId)}&totalChunks=${totalChunks}`);
            if ((latestStatus.missingIndexes || []).length > 0) {
                pushStatus(`Network interruption detected. Missing chunks: ${latestStatus.missingIndexes.join(', ')}`, 'err');
                resumeBtn.disabled = false;
                startBtn.disabled = false;
                pauseBtn.disabled = true;
                return { completed: false };
            }

            const finalizeData = await request('/upload/finalize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    fileId: state.fileId,
                    totalChunks,
                    provider,
                    fileName: state.fileName || file.name,
                    mimeType: state.mimeType || file.type || null,
                    action: 'join_chunks',
                }),
            });

            pushStatus('Upload complete. Final file stored successfully.', 'ok');
            pushResponse(finalizeData);
            refreshUploadPanels();
            await clearCachedChunks(state.fileId).catch(() => {});
            clearState(file);
            currentState = null;
            pushProgress(totalChunks, totalChunks);
            return { completed: true };
        } catch (error) {
            if (cancelled) {
                pushStatus('Upload cancelled by user.', 'err');
                pushResponse({ error: 'Upload cancelled by user' });
                resumeBtn.disabled = false;
                return { completed: false, cancelled: true };
            }

            if (autoContinueMode) {
                pushStatus(`Upload failed: ${error.message}. Auto-retrying in 5 seconds...`, 'err');
                pushResponse({ error: error.message });
                resumeBtn.disabled = false;
                setTimeout(() => {
                    if (!cancelled && autoContinueMode) {
                        startOrResumeUpload({ promptForFile: false });
                    }
                }, 5000);
                return { completed: false, error };
            }

            pushStatus(`Upload failed: ${error.message}`, 'err');
            pushResponse({ error: error.message });
            resumeBtn.disabled = false;
            return { completed: false, error };
        } finally {
            startBtn.disabled = false;
            pauseBtn.disabled = true;
            if (!cancelled) {
                cancelBtn.disabled = false;
            }
        }
    }

    async function startBatchUpload(files) {
        initBatchRows(files);
        batchUploading = true;
        startBtn.disabled = true;
        resumeBtn.disabled = true;
        fileInput.disabled = true;

        try {
            for (let idx = 0; idx < files.length; idx++) {
                if (cancelled) {
                    break;
                }

                const file = files[idx];
                selectedFile = file;
                meta.textContent = `Batch: ${idx + 1}/${files.length} | File: ${file.name} | Size: ${file.size} bytes`;
                setStatus(`Uploading file ${idx + 1} of ${files.length}: ${file.name}`, 'muted');
                setBatchRowStatus(file, 'Uploading', 'muted');
                setBatchRowProgress(file, 0, 1);

                const outcome = await startOrResumeUpload({
                    promptForFile: false,
                    fileOverride: file,
                    onProgress: (uploaded, total) => setBatchRowProgress(file, uploaded, total),
                    onStatus: (message, kind) => setBatchRowStatus(file, message, kind),
                    onResponse: (payload) => setBatchRowResponse(file, payload),
                });

                if (!outcome?.completed) {
                    if (outcome?.cancelled) {
                        setStatus('Batch upload cancelled.', 'err');
                        setBatchRowStatus(file, 'Cancelled', 'err');
                    } else if (outcome?.paused) {
                        setStatus(`Batch paused on file ${idx + 1}. Click Resume Upload to continue this file.`, 'muted');
                        setBatchRowStatus(file, 'Paused', 'muted');
                    } else {
                        setStatus(`Batch stopped on file ${idx + 1}. Resolve issue and click Start Upload again.`, 'err');
                        setBatchRowStatus(file, 'Failed', 'err');
                    }
                    return;
                }

                setBatchRowStatus(file, 'Completed', 'ok');
            }

            if (!cancelled) {
                setStatus(`Batch upload complete. ${files.length} file(s) processed.`, 'ok');
            }
        } finally {
            batchUploading = false;
            selectedFile = null;
            startBtn.disabled = false;
            fileInput.disabled = false;
            if (!cancelled) {
                cancelBtn.disabled = false;
            }
        }
    }

    startBtn.addEventListener('click', async () => {
        const files = getSelectedFiles();
        if (files.length > 1) {
            await startBatchUpload(files);
            return;
        }

        await startOrResumeUpload({ promptForFile: true });
    });
    resumeBtn.addEventListener('click', () => startOrResumeUpload({ promptForFile: true }));

    pauseBtn.addEventListener('click', () => {
        paused = true;
        pauseBtn.disabled = true;
        resumeBtn.disabled = false;
    });

    cancelBtn.addEventListener('click', () => {
        cancelled = true;
        paused = false;
        setAutoContinue(false);
        cancelBtn.disabled = true;
        pauseBtn.disabled = true;
        resumeBtn.disabled = false;
        setStatus('Upload cancelled. You can click Resume Upload to continue later.', 'err');
    });

    window.addEventListener('online', () => {
        if (batchUploading) {
            return;
        }

        if (currentState && fileInput.files[0]) {
            if (autoContinueMode && !cancelled) {
                setStatus('Network restored. Auto-resuming upload...', 'ok');
                startOrResumeUpload({ promptForFile: false });
                return;
            }

            setStatus('Network restored. Click Resume Upload to continue.', 'ok');
            resumeBtn.disabled = false;
        }
    });

    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files || []);
        selectedFiles = files;
        const file = files[0];
        if (!file) return;
        selectedFile = file;

        if (files.length > 1) {
            setStatus(`${files.length} files selected. Click Start Upload to process them sequentially.`, 'ok');
            resumeBtn.disabled = true;
            currentState = null;
            setProgress(0, 1);
            result.textContent = '';
            meta.textContent = `Batch ready: ${files.length} files selected`;
            return;
        }

        const lastState = loadLastState();
        let state = loadState(file);

        if (!state && isSamePendingFile(file, lastState)) {
            state = {
                fileId: lastState.fileId,
                totalChunks: Number(lastState.totalChunks) || Math.ceil(file.size / CHUNK_SIZE),
                provider: lastState.provider || providerSelect.value,
                fileName: lastState.fileName || file.name,
                mimeType: lastState.mimeType || file.type || null,
            };
            saveState(file, state);
        }

        if (state) {
            setStatus('Found previous upload state for this file. Auto-resuming from last uploaded chunk...', 'ok');
            resumeBtn.disabled = false;
            currentState = state;
            startOrResumeUpload();
        } else {
            if (lastState) {
                setStatus('Selected file does not match pending resume file. Starting as new upload.', 'err');
                localStorage.removeItem(LAST_UPLOAD_KEY);
            }
            setStatus('Ready to start upload.', 'muted');
            resumeBtn.disabled = true;
            currentState = null;
            setProgress(0, 1);
            result.textContent = '';
        }
    });

    window.addEventListener('load', () => {
        autoContinueMode = loadAutoContinue();
        refreshUploadPanels();
        startDashboardAutoRefresh();

        const lastState = loadLastState();
        if (!lastState) {
            return;
        }

        providerSelect.value = lastState.provider || providerSelect.value;
        resumeBtn.disabled = false;

        setStatus(
            `Previous upload found for ${lastState.fileName}. Select the same file to auto-resume from last stopped chunk.`,
            'ok',
        );
        meta.textContent = `Pending Resume: ${lastState.fileName} | fileId: ${lastState.fileId}`;

        request(`/upload/status?fileId=${encodeURIComponent(lastState.fileId)}&totalChunks=${lastState.totalChunks}`)
            .then((statusData) => {
                const uploadedCount = (statusData.uploadedIndexes || []).length;
                setProgress(uploadedCount, Number(lastState.totalChunks) || 1);

                if (autoContinueMode) {
                    const selectedNow = getCurrentFile();
                    if (selectedNow && isSamePendingFile(selectedNow, lastState)) {
                        setStatus('Auto-continue enabled. Resuming upload automatically...', 'ok');
                        startOrResumeUpload({ promptForFile: false });
                    } else {
                        tryFileFromStoredHandle(false)
                            .then((storedFile) => {
                                if (storedFile && isSamePendingFile(storedFile, lastState)) {
                                    setStatus('Auto-continue enabled. Resuming upload automatically...', 'ok');
                                    startOrResumeUpload({ promptForFile: false });
                                    return;
                                }
                                setStatus('Auto-continue enabled. Resuming from local cache...', 'ok');
                                startOrResumeUpload({ promptForFile: false });
                            })
                            .catch(() => {
                                setStatus('Auto-continue enabled. Resuming from local cache...', 'ok');
                                startOrResumeUpload({ promptForFile: false });
                            });
                    }
                    return;
                }

                const shouldAutoContinue = window.confirm(
                    `Pending upload found (${uploadedCount}/${lastState.totalChunks} chunks). Continue automatically until complete?`,
                );

                if (shouldAutoContinue) {
                    setAutoContinue(true);
                    const selectedNow = getCurrentFile();
                    if (selectedNow && isSamePendingFile(selectedNow, lastState)) {
                        setStatus('Auto-continue enabled. Resuming upload automatically...', 'ok');
                        startOrResumeUpload({ promptForFile: false });
                    } else {
                        setStatus('Auto-continue enabled. Click Resume once to grant permission if browser asks, then it will auto-continue.', 'ok');
                    }
                } else {
                    setAutoContinue(false);
                }
            })
            .catch(() => {
                setProgress(0, Number(lastState.totalChunks) || 1);
            });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopDashboardAutoRefresh();
            return;
        }
        refreshUploadPanels();
        startDashboardAutoRefresh();
    });

    window.addEventListener('beforeunload', () => {
        stopDashboardAutoRefresh();
    });

    enableAutoBtn.addEventListener('click', async () => {
        try {
            await enableAutoResumeAccess();
        } catch (error) {
            setStatus(`Failed to enable auto-resume: ${error.message}`, 'err');
        }
    });
})();
</script>
</body>
</html>
