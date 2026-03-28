// ============================================
// RAGEFILL Admin Panel v2.0 — Vanilla JS
// ============================================

const API_BASE = '/api';
let token = localStorage.getItem('ragefill_token');
let sauces = [];
let currentHeat = 5;
let formDirty = false;
let removeImage = false;
let editingId = null;

// --- Elements ---
const loginScreen = document.getElementById('login-screen');
const adminPanel = document.getElementById('admin-panel');
const sauceList = document.getElementById('sauce-list');
const formOverlay = document.getElementById('form-overlay');
const confirmOverlay = document.getElementById('confirm-overlay');
const unsavedOverlay = document.getElementById('unsaved-overlay');
const sauceForm = document.getElementById('sauce-form');
const adminSearch = document.getElementById('admin-search');
const activeToggle = document.getElementById('form-active');
const toggleStatusText = document.getElementById('toggle-status-text');

// --- Lazy load Quill ---
let quillLoaded = false;

function loadQuillAssets() {
    return new Promise((resolve, reject) => {
        if (quillLoaded) { resolve(); return; }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.quilljs.com/1.3.7/quill.snow.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://cdn.quilljs.com/1.3.7/quill.min.js';
        script.onload = () => { quillLoaded = true; resolve(); };
        script.onerror = () => reject(new Error('Failed to load Quill'));
        document.body.appendChild(script);
    });
}

// --- Quill WYSIWYG editors ---
const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    ['link'],
    ['clean']
];

function initQuillWithHtmlToggle(selector, placeholder) {
    const container = document.querySelector(selector);
    const q = new Quill(selector, {
        theme: 'snow',
        placeholder: placeholder,
        modules: { toolbar: quillToolbar }
    });
    q.on('text-change', () => { formDirty = true; });

    // Create HTML source textarea (hidden by default)
    const htmlTextarea = document.createElement('textarea');
    htmlTextarea.className = 'quill-html-source';
    htmlTextarea.style.display = 'none';
    container.parentNode.insertBefore(htmlTextarea, container.nextSibling);

    // Add </> toggle button to toolbar
    const toolbar = container.parentNode.querySelector('.ql-toolbar');
    const htmlBtn = document.createElement('button');
    htmlBtn.type = 'button';
    htmlBtn.className = 'ql-html-toggle';
    htmlBtn.innerHTML = '&lt;/&gt;';
    htmlBtn.title = 'HTML-код';
    toolbar.appendChild(htmlBtn);

    let htmlMode = false;
    htmlBtn.addEventListener('click', () => {
        htmlMode = !htmlMode;
        htmlBtn.classList.toggle('active', htmlMode);
        if (htmlMode) {
            htmlTextarea.value = q.root.innerHTML;
            htmlTextarea.style.display = 'block';
            container.style.display = 'none';
        } else {
            q.root.innerHTML = htmlTextarea.value;
            htmlTextarea.style.display = 'none';
            container.style.display = '';
            formDirty = true;
        }
    });

    htmlTextarea.addEventListener('input', () => { formDirty = true; });

    // Expose helper to get HTML regardless of mode
    q._getHtml = () => htmlMode ? htmlTextarea.value : q.root.innerHTML;
    q._exitHtmlMode = () => {
        if (htmlMode) {
            q.root.innerHTML = htmlTextarea.value;
            htmlTextarea.style.display = 'none';
            container.style.display = '';
            htmlBtn.classList.remove('active');
            htmlMode = false;
        }
    };

    return q;
}

let quill, quillComposition;

function ensureQuillInit() {
    if (quill) return true;
    if (typeof Quill === 'undefined') {
        showToast('Редактор не загружен. Обновите страницу.', 'error');
        return false;
    }
    quill = initQuillWithHtmlToggle('#form-description-editor', 'Описание соуса...');
    quillComposition = initQuillWithHtmlToggle('#form-composition-editor', 'Перечень ингредиентов...');
    return true;
}

// Quill is loaded lazily after auth — no immediate init

// --- Auth ---
if (token) {
    showAdmin();
} else {
    loginScreen.style.display = 'block';
    adminPanel.style.display = 'none';
}

document.getElementById('login-btn').addEventListener('click', login);
document.getElementById('login-password').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') login();
});

async function login() {
    const btn = document.getElementById('login-btn');
    const password = document.getElementById('login-password').value;
    if (!password) return;

    setLoading(btn, true);
    try {
        const res = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password })
        });

        const data = await res.json();
        if (data.token) {
            token = data.token;
            localStorage.setItem('ragefill_token', token);
            showAdmin();
        } else {
            showToast('Неверный пароль', 'error');
        }
    } catch (err) {
        showToast('Ошибка соединения', 'error');
    } finally {
        setLoading(btn, false);
    }
}

document.getElementById('logout-btn').addEventListener('click', () => {
    token = null;
    localStorage.removeItem('ragefill_token');
    loginScreen.style.display = 'block';
    adminPanel.style.display = 'none';
});

function showAdmin() {
    loginScreen.style.display = 'none';
    adminPanel.style.display = 'block';
    loadQuillAssets().catch(() => {});
    loadSauces();
}

// --- Load sauces ---
async function loadSauces() {
    sauceList.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    try {
        const res = await fetch(`${API_BASE}/admin/sauces`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (res.status === 401) {
            document.getElementById('logout-btn').click();
            return;
        }

        sauces = await res.json();
        renderSauceList();
    } catch (err) {
        sauceList.innerHTML = '<div class="empty-state"><div class="empty-state__text">Ошибка загрузки</div></div>';
    }
}

function renderSauceList(filter = '') {
    let list = sauces;
    if (filter) {
        const q = filter.toLowerCase();
        list = sauces.filter(s =>
            s.name.toLowerCase().includes(q) ||
            (s.volume && s.volume.toLowerCase().includes(q))
        );
    }

    // Update counter
    const counterEl = document.getElementById('admin-counter');
    if (counterEl) {
        const total = sauces.length;
        const active = sauces.filter(s => s.is_active).length;
        const inStock = sauces.filter(isInStock).length;
        if (total > 0) {
            counterEl.innerHTML = `Всего: <strong>${total}</strong> · Активных: <strong>${active}</strong> · В наличии: <strong>${inStock}</strong>`;
            if (filter) counterEl.innerHTML += ` · Найдено: <strong>${list.length}</strong>`;
        } else {
            counterEl.textContent = '';
        }
    }

    if (list.length === 0) {
        sauceList.innerHTML = `
            <div class="empty-state">
                <div class="empty-state__icon">${filter ? '🔍' : '🌶️'}</div>
                <div class="empty-state__text">${filter ? 'Ничего не найдено' : 'Соусы ещё не добавлены'}</div>
            </div>
        `;
        return;
    }

    sauceList.innerHTML = list.map(sauce => {
        const thumb = sauce.image
            ? `<img class="admin-sauce-thumb" src="/uploads/${escapeHtml(sauce.image)}" alt="${escapeHtml(sauce.name)}" loading="lazy" data-fallback="true">`
            : '<div class="admin-sauce-thumb-placeholder">🌶️</div>';

        const statusText = sauce.is_active ? 'Активен' : 'Скрыт';
        const inStockText = isInStock(sauce) ? '' : ' · Нет в наличии';
        const volumeText = sauce.volume ? ` · ${escapeHtml(sauce.volume)}` : '';
        const categoryLabels = {
            sauce: '🔥 Соус',
            gift_set: '🎁 Набор',
            pickled_pepper: '🫙 Перцы',
            spicy_peanut: '🥜 Арахис',
            spice: '🌿 Специи'
        };
        const categoryLabel = categoryLabels[sauce.category] || '🔥 Соус';

        const subtitleText = sauce.subtitle ? `<div class="admin-sauce-subtitle">${escapeHtml(sauce.subtitle)}</div>` : '';

        return `
            <div class="admin-sauce-item ${sauce.is_active ? '' : 'inactive'}">
                ${thumb}
                <div class="admin-sauce-info">
                    <div class="admin-sauce-name">${escapeHtml(sauce.name)}</div>
                    ${subtitleText}
                    <div class="admin-sauce-meta">${categoryLabel} · Острота: ${sauce.heat_level}/5 · ${statusText}${inStockText}${volumeText}</div>
                </div>
                <div class="admin-sauce-actions">
                    <button class="admin-action-btn admin-action-btn--edit" data-edit-id="${sauce.id}" aria-label="Редактировать ${escapeHtml(sauce.name)}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                    </button>
                    <button class="admin-action-btn admin-action-btn--delete" data-delete-id="${sauce.id}" aria-label="Удалить ${escapeHtml(sauce.name)}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    // Broken image fallback
    sauceList.querySelectorAll('img[data-fallback]').forEach(img => {
        img.addEventListener('error', function () {
            const placeholder = document.createElement('div');
            placeholder.className = 'admin-sauce-thumb-placeholder';
            placeholder.textContent = '🌶️';
            this.replaceWith(placeholder);
        });
    });
}

// Admin search with debounce
let adminSearchTimer = null;
adminSearch.addEventListener('input', () => {
    clearTimeout(adminSearchTimer);
    adminSearchTimer = setTimeout(() => renderSauceList(adminSearch.value.trim()), 200);
});

// --- Toggle switch ---
activeToggle.addEventListener('change', () => {
    toggleStatusText.textContent = activeToggle.checked ? 'Активен' : 'Скрыт';
    formDirty = true;
});

// --- In stock toggle ---
const inStockToggle = document.getElementById('form-in-stock');
const inStockText = document.getElementById('toggle-in-stock-text');
inStockToggle.addEventListener('change', () => {
    inStockText.textContent = inStockToggle.checked ? 'В наличии' : 'Нет в наличии';
    formDirty = true;
});

// --- Category select updates form title ---
const categoryFormLabels = {
    sauce: 'соус',
    gift_set: 'набор',
    pickled_pepper: 'перцы',
    spicy_peanut: 'арахис',
    spice: 'специи'
};
document.getElementById('form-category').addEventListener('change', (e) => {
    const label = categoryFormLabels[e.target.value] || 'товар';
    document.getElementById('form-title').textContent = editingId ? `Редактировать ${label}` : `Добавить ${label}`;
    formDirty = true;
});

// --- Form ---
document.getElementById('add-sauce-btn').addEventListener('click', () => openForm());

function openForm(sauce = null) {
    if (!ensureQuillInit()) return;
    formDirty = false;
    removeImage = false;
    editingId = sauce ? sauce.id : null;

    // Reset HTML mode if active
    quill._exitHtmlMode();
    quillComposition._exitHtmlMode();

    const catVal = sauce ? (sauce.category || 'sauce') : 'sauce';
    const catName = categoryFormLabels[catVal] || 'товар';
    document.getElementById('form-title').textContent = sauce ? `Редактировать ${catName}` : `Добавить ${catName}`;
    document.getElementById('form-id').value = sauce ? sauce.id : '';
    document.getElementById('form-name').value = sauce ? sauce.name : '';
    document.getElementById('form-subtitle').value = sauce ? (sauce.subtitle || '') : '';
    document.getElementById('form-category').value = sauce ? (sauce.category || 'sauce') : 'sauce';

    setQuillContent(quill, sauce?.description);
    setQuillContent(quillComposition, sauce?.composition);
    document.getElementById('form-volume').value = sauce ? sauce.volume : '';
    document.getElementById('form-sort').value = sauce ? sauce.sort_order : 0;
    document.getElementById('form-image').value = '';
    document.getElementById('form-remove-image').value = '0';

    // Toggle switch
    activeToggle.checked = sauce ? !!sauce.is_active : true;
    toggleStatusText.textContent = activeToggle.checked ? 'Активен' : 'Скрыт';

    // In stock toggle
    inStockToggle.checked = sauce ? isInStock(sauce) : true;
    inStockText.textContent = inStockToggle.checked ? 'В наличии' : 'Нет в наличии';

    // Badge toggles
    document.getElementById('form-is-hit').checked = sauce ? !!sauce.is_hit : false;
    document.getElementById('form-is-low-stock').checked = sauce ? !!sauce.is_low_stock : false;

    // Show/hide delete button in edit mode
    const deleteBtn = document.getElementById('form-delete-btn');
    if (sauce) {
        deleteBtn.style.display = 'block';
    } else {
        deleteBtn.style.display = 'none';
    }

    // Reset validation
    document.querySelectorAll('.form-input.error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.form-error').forEach(el => el.classList.remove('visible'));

    // Heat level
    currentHeat = sauce ? sauce.heat_level : 3;
    updateHeatSelector();

    // Image preview
    const previewWrapper = document.getElementById('image-preview-wrapper');
    const preview = document.getElementById('form-image-preview');
    if (sauce && sauce.image) {
        preview.src = `/uploads/${sauce.image}`;
        previewWrapper.classList.add('visible');
    } else {
        previewWrapper.classList.remove('visible');
    }

    formOverlay.classList.add('active');

    // Track dirty state
    setTimeout(() => {
        sauceForm.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(el => {
            el.addEventListener('input', () => { formDirty = true; }, { once: true });
            el.addEventListener('change', () => { formDirty = true; }, { once: true });
        });
    }, 100);
}

function editSauce(id) {
    const sauce = sauces.find(s => s.id == id);
    if (sauce) openForm(sauce);
}

// Close form
function tryCloseForm() {
    if (formDirty) {
        unsavedOverlay.classList.add('active');
    } else {
        formOverlay.classList.remove('active');
    }
}

document.getElementById('form-cancel').addEventListener('click', tryCloseForm);
document.getElementById('form-close-btn').addEventListener('click', tryCloseForm);

formOverlay.addEventListener('click', (e) => {
    if (e.target === formOverlay) tryCloseForm();
});

// Unsaved changes dialog
document.getElementById('unsaved-cancel').addEventListener('click', () => {
    unsavedOverlay.classList.remove('active');
});
document.getElementById('unsaved-ok').addEventListener('click', () => {
    unsavedOverlay.classList.remove('active');
    formOverlay.classList.remove('active');
    formDirty = false;
});

// Delete from edit form
document.getElementById('form-delete-btn').addEventListener('click', () => {
    if (editingId) {
        confirmDelete(editingId);
    }
});

// Heat selector
document.getElementById('heat-selector').addEventListener('click', (e) => {
    const dot = e.target.closest('.heat-selector-dot');
    if (dot) {
        currentHeat = parseInt(dot.dataset.val);
        updateHeatSelector();
        formDirty = true;
    }
});

function updateHeatSelector() {
    document.querySelectorAll('.heat-selector-dot').forEach(dot => {
        const val = parseInt(dot.dataset.val);
        dot.classList.toggle('active', val <= currentHeat);
    });
}

// Image preview
document.getElementById('form-image').addEventListener('change', (e) => {
    const file = e.target.files[0];
    const previewWrapper = document.getElementById('image-preview-wrapper');
    const preview = document.getElementById('form-image-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            preview.src = ev.target.result;
            previewWrapper.classList.add('visible');
        };
        reader.readAsDataURL(file);
        removeImage = false;
        document.getElementById('form-remove-image').value = '0';
        formDirty = true;
    }
});

// Remove image
document.getElementById('image-remove-btn').addEventListener('click', () => {
    const previewWrapper = document.getElementById('image-preview-wrapper');
    previewWrapper.classList.remove('visible');
    document.getElementById('form-image').value = '';
    document.getElementById('form-remove-image').value = '1';
    removeImage = true;
    formDirty = true;
});

// Validate
function validateForm() {
    let valid = true;
    const name = document.getElementById('form-name');
    // Get text for validation — strip HTML if in source mode
    const descHtmlRaw = quill._getHtml();
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = descHtmlRaw;
    const descText = (tempDiv.textContent || '').trim();
    const editorEl = document.querySelector('#form-description-editor');

    if (name.value.trim().length < 2) {
        name.classList.add('error');
        document.getElementById('form-name-error').classList.add('visible');
        valid = false;
    } else {
        name.classList.remove('error');
        document.getElementById('form-name-error').classList.remove('visible');
    }

    if (descText.length < 10) {
        if (editorEl) editorEl.classList.add('error');
        document.getElementById('form-description-error').classList.add('visible');
        valid = false;
    } else {
        if (editorEl) editorEl.classList.remove('error');
        document.getElementById('form-description-error').classList.remove('visible');
    }

    return valid;
}

// Save sauce
sauceForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) return;

    const btn = document.getElementById('form-submit-btn');
    const id = document.getElementById('form-id').value;
    const formData = new FormData();

    formData.append('name', document.getElementById('form-name').value.trim());
    formData.append('subtitle', document.getElementById('form-subtitle').value.trim());
    formData.append('category', document.getElementById('form-category').value);

    formData.append('description', cleanQuillHtml(quill._getHtml()));
    formData.append('composition', cleanQuillHtml(quillComposition._getHtml()));
    formData.append('volume', document.getElementById('form-volume').value.trim());
    formData.append('heat_level', currentHeat);
    formData.append('sort_order', document.getElementById('form-sort').value);
    formData.append('is_active', activeToggle.checked ? '1' : '0');
    formData.append('in_stock', document.getElementById('form-in-stock').checked ? '1' : '0');
    formData.append('is_hit', document.getElementById('form-is-hit').checked ? '1' : '0');
    formData.append('is_low_stock', document.getElementById('form-is-low-stock').checked ? '1' : '0');
    formData.append('remove_image', removeImage ? '1' : '0');

    const imageFile = document.getElementById('form-image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    const url = id ? `${API_BASE}/admin/sauces/${id}` : `${API_BASE}/admin/sauces`;

    setLoading(btn, true);
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });

        if (res.status === 401) {
            document.getElementById('logout-btn').click();
            return;
        }

        if (res.ok) {
            formDirty = false;
            formOverlay.classList.remove('active');
            showToast(id ? 'Соус обновлён' : 'Соус добавлен', 'success');
            loadSauces();
        } else {
            const data = await res.json();
            showToast(data.error || 'Ошибка сохранения', 'error');
        }
    } catch (err) {
        showToast('Ошибка соединения', 'error');
    } finally {
        setLoading(btn, false);
    }
});

// --- Delete ---
let deleteId = null;

function confirmDelete(id) {
    const sauce = sauces.find(s => s.id == id);
    if (!sauce) return;

    deleteId = id;
    document.getElementById('confirm-text').textContent = `"${sauce.name}" будет удалён безвозвратно`;
    confirmOverlay.classList.add('active');
}

document.getElementById('confirm-cancel').addEventListener('click', () => {
    confirmOverlay.classList.remove('active');
    deleteId = null;
});

document.getElementById('confirm-ok').addEventListener('click', async () => {
    if (!deleteId) return;

    const btn = document.getElementById('confirm-ok');
    setLoading(btn, true);

    try {
        const res = await fetch(`${API_BASE}/admin/sauces/${deleteId}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (res.ok) {
            confirmOverlay.classList.remove('active');
            formOverlay.classList.remove('active');
            showToast('Соус удалён', 'success');
            deleteId = null;
            loadSauces();
        } else {
            showToast('Ошибка удаления', 'error');
        }
    } catch (err) {
        showToast('Ошибка соединения', 'error');
    } finally {
        setLoading(btn, false);
    }
});

confirmOverlay.addEventListener('click', (e) => {
    if (e.target === confirmOverlay) {
        confirmOverlay.classList.remove('active');
        deleteId = null;
    }
});

// --- Helpers ---
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function isInStock(sauce) {
    return sauce.in_stock !== 0 && sauce.in_stock !== '0';
}

function isHtmlContent(str) {
    return /<[a-z][\s\S]*>/i.test(str);
}

function cleanQuillHtml(html) {
    let clean = (html || '').trim();
    clean = clean.replace(/<p><br><\/p>/gi, '');
    clean = clean.replace(/<br><\/p>/gi, '</p>');
    clean = clean.replace(/<p>\s*<\/p>/gi, '');
    clean = clean.trim();
    return (!clean || clean === '<p></p>') ? '' : clean;
}

function setQuillContent(editor, content) {
    if (content && isHtmlContent(content)) {
        editor.root.innerHTML = content;
    } else {
        editor.setText(content || '');
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function setLoading(btn, loading) {
    btn.classList.toggle('loading', loading);
    btn.disabled = loading;
}

// Close overlays on Escape + focus trap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (unsavedOverlay.classList.contains('active')) {
            unsavedOverlay.classList.remove('active');
        } else if (confirmOverlay.classList.contains('active')) {
            confirmOverlay.classList.remove('active');
            deleteId = null;
        } else if (formOverlay.classList.contains('active')) {
            tryCloseForm();
        }
        return;
    }
    if (e.key === 'Tab') {
        const activeOverlay = unsavedOverlay.classList.contains('active') ? unsavedOverlay
            : confirmOverlay.classList.contains('active') ? confirmOverlay
            : formOverlay.classList.contains('active') ? formOverlay
            : null;
        if (!activeOverlay) return;
        const focusable = activeOverlay.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"]), .ql-editor');
        if (focusable.length === 0) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (e.shiftKey) { if (document.activeElement === first) { e.preventDefault(); last.focus(); } }
        else { if (document.activeElement === last) { e.preventDefault(); first.focus(); } }
    }
});

// Event delegation for sauce list actions
sauceList.addEventListener('click', (e) => {
    const editBtn = e.target.closest('[data-edit-id]');
    if (editBtn) { editSauce(parseInt(editBtn.dataset.editId)); return; }
    const deleteBtn = e.target.closest('[data-delete-id]');
    if (deleteBtn) { confirmDelete(parseInt(deleteBtn.dataset.deleteId)); return; }
});
