// ==========================================
// 云盘前端逻辑
// ==========================================

const API = '../server/api/drive.php';

const dom = {
    loginCard: document.getElementById('login-card'),
    drivePanel: document.getElementById('drive-panel'),
    loginPassword: document.getElementById('login-password'),
    loginBtn: document.getElementById('login-btn'),
    loginError: document.getElementById('login-error'),
    logoutBtn: document.getElementById('logout-btn'),
    uploadZone: document.getElementById('upload-zone'),
    fileInput: document.getElementById('file-input'),
    uploadProgress: document.getElementById('upload-progress'),
    uploadStatus: document.getElementById('upload-status'),
    progressFill: document.getElementById('progress-fill'),
    fileGrid: document.getElementById('file-grid'),
    fileCount: document.getElementById('file-count'),
    refreshBtn: document.getElementById('refresh-btn'),
    storageUsage: document.getElementById('storage-usage'),
};

let currentFiles = [];

// ─── Toast 消息 ────────────────────────────

function showToast(message, type = 'success') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('fadeout');
        setTimeout(() => toast.remove(), 400);
    }, 2500);
}

// ─── 检查登录状态 ──────────────────────────

async function checkAuth() {
    try {
        const res = await fetch(API + '?action=check', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.authed) {
            showDrive();
            loadFiles();
        } else {
            showLogin();
        }
    } catch (e) {
        showLogin();
    }
}

function showLogin() {
    dom.loginCard.classList.remove('hidden');
    dom.drivePanel.classList.remove('active');
}

function showDrive() {
    dom.loginCard.classList.add('hidden');
    dom.drivePanel.classList.add('active');
}

// ─── 登录 ─────────────────────────────────

dom.loginBtn.addEventListener('click', doLogin);
dom.loginPassword.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') doLogin();
});

async function doLogin() {
    const password = dom.loginPassword.value.trim();
    if (!password) {
        dom.loginError.textContent = '请输入密码';
        return;
    }
    dom.loginBtn.disabled = true;
    dom.loginError.textContent = '';

    try {
        const res = await fetch(API + '?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password }),
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            showDrive();
            loadFiles();
        } else {
            dom.loginError.textContent = data.error || '密码错误';
        }
    } catch (e) {
        dom.loginError.textContent = '网络错误，请稍后重试';
    } finally {
        dom.loginBtn.disabled = false;
    }
}

// ─── 登出 ─────────────────────────────────

dom.logoutBtn.addEventListener('click', async () => {
    try {
        await fetch(API + '?action=logout', { credentials: 'same-origin' });
    } catch (e) { /* ignore */ }
    showLogin();
    dom.loginPassword.value = '';
});

// ─── 文件列表 ──────────────────────────────

async function loadFiles() {
    try {
        const res = await fetch(API + '?action=list', { credentials: 'same-origin' });
        if (res.status === 401) {
            showLogin();
            return;
        }
        const data = await res.json();
        currentFiles = data.files || [];
        renderFiles();
    } catch (e) {
        showToast('加载文件列表失败', 'error');
    }
}

function renderFiles() {
    dom.fileGrid.innerHTML = '';
    const files = currentFiles;
    dom.fileCount.textContent = '共 ' + files.length + ' 个文件';

    // 计算总大小
    let totalSize = 0;
    files.forEach(f => { totalSize += f.size; });
    dom.storageUsage.textContent = '已用 ' + formatSize(totalSize);

    if (files.length === 0) {
        dom.fileGrid.innerHTML = `
          <div class="empty-state">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>这里还没有文件，上传一个开始吧 🚀</p>
          </div>`;
        return;
    }

    files.forEach((file) => {
        const card = document.createElement('div');
        card.className = 'file-card glass';
        card.innerHTML = `
          <div class="file-icon">
            <i class="fas ${file.icon}"></i>
          </div>
          <div class="file-info">
            <div class="file-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
            <div class="file-meta">
              <span>${file.sizeDisplay}</span>
              <span>${file.date}</span>
            </div>
          </div>
          <div class="file-actions">
            <button class="btn-icon" title="下载" data-download="${escapeHtml(file.name)}">
              <i class="fas fa-download"></i>
            </button>
            <button class="btn-icon danger" title="删除" data-delete="${escapeHtml(file.name)}">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>`;
        dom.fileGrid.appendChild(card);
    });

    // 绑定下载
    dom.fileGrid.querySelectorAll('[data-download]').forEach(btn => {
        btn.addEventListener('click', () => downloadFile(btn.dataset.download));
    });

    // 绑定删除
    dom.fileGrid.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', () => deleteFile(btn.dataset.delete));
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

// ─── 下载 ─────────────────────────────────

function downloadFile(filename) {
    const url = API + '?action=download&file=' + encodeURIComponent(filename);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ─── 删除 ─────────────────────────────────

async function deleteFile(filename) {
    if (!confirm('确定要删除 "' + filename + '" 吗？此操作不可恢复。')) return;

    try {
        const res = await fetch(API + '?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename }),
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (res.ok && data.ok) {
            showToast('已删除: ' + filename);
            loadFiles();
        } else {
            showToast(data.error || '删除失败', 'error');
        }
    } catch (e) {
        showToast('网络错误', 'error');
    }
}

// ─── 上传 ─────────────────────────────────

dom.uploadZone.addEventListener('click', () => dom.fileInput.click());
dom.fileInput.addEventListener('change', () => {
    if (dom.fileInput.files.length > 0) {
        uploadFiles(dom.fileInput.files);
        dom.fileInput.value = '';
    }
});

// 拖拽上传
dom.uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dom.uploadZone.classList.add('drag-over');
});
dom.uploadZone.addEventListener('dragleave', () => {
    dom.uploadZone.classList.remove('drag-over');
});
dom.uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dom.uploadZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length > 0) {
        uploadFiles(e.dataTransfer.files);
    }
});

async function uploadFiles(fileList) {
    for (const file of fileList) {
        await uploadSingle(file);
    }
    loadFiles();
}

async function uploadSingle(file) {
    // 500MB 检查
    if (file.size > 524288000) {
        showToast('文件 "' + file.name + '" 超过 500MB 限制', 'error');
        return;
    }

    dom.uploadProgress.classList.add('active');
    dom.uploadStatus.textContent = '上传中: ' + file.name;
    dom.progressFill.style.width = '0%';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const xhr = new XMLHttpRequest();
        xhr.withCredentials = true;

        const result = await new Promise((resolve, reject) => {
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    dom.progressFill.style.width = pct + '%';
                    dom.uploadStatus.textContent = '上传中: ' + file.name + ' (' + pct + '%)';
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const data = JSON.parse(xhr.responseText);
                    resolve({ ok: xhr.status >= 200 && xhr.status < 300, data });
                } catch (e) {
                    reject(new Error('解析响应失败'));
                }
            });

            xhr.addEventListener('error', () => reject(new Error('网络错误')));
            xhr.addEventListener('abort', () => reject(new Error('上传取消')));

            xhr.open('POST', API + '?action=upload');
            xhr.send(formData);
        });

        if (result.ok && result.data.ok) {
            showToast('上传成功: ' + file.name);
        } else {
            showToast((result.data && result.data.error) || '上传失败', 'error');
        }
    } catch (e) {
        showToast(e.message || '上传失败', 'error');
    }

    dom.uploadProgress.classList.remove('active');
    dom.progressFill.style.width = '0%';
}

// ─── 刷新 ─────────────────────────────────

dom.refreshBtn.addEventListener('click', loadFiles);

// ─── 初始化 ───────────────────────────────

document.addEventListener('DOMContentLoaded', checkAuth);
