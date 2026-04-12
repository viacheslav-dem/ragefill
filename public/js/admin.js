// ============================================
// RAGEFILL Admin Panel v2.0 — Vanilla JS
// ============================================

// --- Theme (dark default, light via attribute) ---
(function initTheme() {
    const saved = localStorage.getItem('ragefill_cms_theme');
    if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');
    // else: dark is default (no attribute needed)
})();

const API_BASE = '/api';
let token = localStorage.getItem('ragefill_token');
let sauces = [];
let categories = [];
let selectedIds = new Set();
let activeCategoryFilter = null;
let currentHeat = 5;
let formDirty = false;
let removeImage = false;
let editingId = null;
let additionalImages = []; // [{file: File|null, url: string, filename: string|null}]
let deletedImages = [];

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
        link.href = '/css/quill.snow.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = '/js/quill.min.js';
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
    quill = initQuillWithHtmlToggle('#form-description-editor', 'Описание соуса…');
    quillComposition = initQuillWithHtmlToggle('#form-composition-editor', 'Перечень ингредиентов…');
    return true;
}

// Quill is loaded lazily after auth — no immediate init

// --- Auth ---
if (token) {
    showAdmin();
} else {
    loginScreen.style.display = '';
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
            const card = document.querySelector('.cms-login__card');
            const input = document.getElementById('login-password');
            input.classList.add('login-error');
            card.classList.add('shake');
            input.select();
            setTimeout(() => { card.classList.remove('shake'); }, 500);
            input.addEventListener('input', () => { input.classList.remove('login-error'); }, { once: true });
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
    siteSettingsLoaded = false;
    loginScreen.style.display = '';
    adminPanel.style.display = 'none';
});

function showAdmin() {
    loginScreen.style.display = 'none';
    adminPanel.style.display = 'flex';
    loadQuillAssets().catch(() => {});
    loadCategories().then(() => loadSauces());
}

// --- Load categories ---
async function loadCategories() {
    try {
        const res = await fetch(`${API_BASE}/admin/categories`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (res.status === 401) return;
        categories = await res.json();
        populateCategoryDropdown();
        renderCategoryFilters();
        const navBadge = document.getElementById('nav-categories-count');
        if (navBadge) navBadge.textContent = categories.length || '';
    } catch (err) { /* silent */ }
}

function populateCategoryDropdown() {
    const sel = document.getElementById('form-category');
    if (!sel) return;
    sel.innerHTML = categories.map(c =>
        `<option value="${escapeHtml(c.slug)}">${escapeHtml(c.emoji ? c.emoji + ' ' : '')}${escapeHtml(c.name)}</option>`
    ).join('');
}

function getCategoryLabel(slug) {
    const cat = categories.find(c => c.slug === slug);
    return cat ? `${cat.emoji || ''} ${cat.name}`.trim() : slug;
}

function getCategoryFormLabel(slug) {
    const cat = categories.find(c => c.slug === slug);
    return cat ? cat.name.toLowerCase() : 'товар';
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
    if (activeCategoryFilter) {
        list = list.filter(s => s.category === activeCategoryFilter);
    }
    if (filter) {
        const q = filter.toLowerCase();
        list = list.filter(s =>
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
    // Update sidebar badge
    const navBadge = document.getElementById('nav-sauces-count');
    if (navBadge) navBadge.textContent = sauces.length || '';

    if (list.length === 0) {
        sauceList.innerHTML = `
            <div class="empty-state">
                <div class="empty-state__icon">${filter ? '🔍' : '<img src="/uploads/pepper.svg" alt="" width="48" height="48">'}</div>
                <div class="empty-state__text">${filter ? 'Ничего не найдено' : 'Соусы ещё не добавлены'}</div>
            </div>
        `;
        return;
    }

    const trashIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';

    const rows = list.map(sauce => {
        const thumb = sauce.image
            ? `<img class="admin-sauce-thumb" src="/uploads/${escapeHtml(sauce.image)}" alt="" loading="lazy" data-fallback="true">`
            : '<div class="admin-sauce-thumb-placeholder"><img src="/uploads/pepper.svg" alt="" width="20" height="20"></div>';
        const catObj = categories.find(c => c.slug === sauce.category);
        const catEmoji = catObj && catObj.emoji ? escapeHtml(catObj.emoji) : '';
        const catName = catObj ? escapeHtml(catObj.name) : escapeHtml(sauce.category);
        const heatDots = Array.from({length: 5}, (_, i) =>
            `<span class="admin-heat-dot${i < sauce.heat_level ? ' filled' : ''}"></span>`
        ).join('');
        const checked = selectedIds.has(sauce.id) ? 'checked' : '';
        const volume = sauce.volume ? escapeHtml(sauce.volume) : '—';

        const visPill = sauce.is_active
            ? '<span class="stbl-pill stbl-pill--on">Актив</span>'
            : '<span class="stbl-pill stbl-pill--off">Скрыт</span>';
        const stockPill = isInStock(sauce)
            ? '<span class="stbl-pill stbl-pill--on">Есть</span>'
            : '<span class="stbl-pill stbl-pill--off">Нет</span>';

        return `<tr class="stbl-row ${sauce.is_active ? '' : 'stbl-row--inactive'}${selectedIds.has(sauce.id) ? ' selected' : ''}" data-edit-id="${sauce.id}" data-sauce-id="${sauce.id}">
            <td class="stbl-cell stbl-cell--drag"><div class="cms-drag-handle"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="11" r="1.5"/><circle cx="15" cy="11" r="1.5"/><circle cx="9" cy="16" r="1.5"/><circle cx="15" cy="16" r="1.5"/></svg></div></td>
            <td class="stbl-cell stbl-cell--check" onclick="event.stopPropagation()"><label class="admin-sauce-checkbox"><input type="checkbox" class="bulk-check" data-id="${sauce.id}" ${checked}><span class="checkmark"></span></label></td>
            <td class="stbl-cell stbl-cell--img">${thumb}</td>
            <td class="stbl-cell stbl-cell--name"><span class="stbl-name">${escapeHtml(sauce.name)}</span></td>
            <td class="stbl-cell stbl-cell--desc">${sauce.subtitle ? escapeHtml(sauce.subtitle) : '<span class="stbl-muted">—</span>'}</td>
            <td class="stbl-cell stbl-cell--cat"><span class="stbl-cat">${catEmoji ? catEmoji + ' ' : ''}${catName}</span></td>
            <td class="stbl-cell stbl-cell--heat"><span class="admin-heat-indicator">${heatDots}</span></td>
            <td class="stbl-cell stbl-cell--vol">${volume}</td>
            <td class="stbl-cell stbl-cell--status"><button class="stbl-pill-btn" data-toggle-field="is_active" data-toggle-id="${sauce.id}" title="${sauce.is_active ? 'Скрыть' : 'Показать'}">${visPill}</button></td>
            <td class="stbl-cell stbl-cell--status"><button class="stbl-pill-btn" data-toggle-field="in_stock" data-toggle-id="${sauce.id}" title="${isInStock(sauce) ? 'Убрать из наличия' : 'В наличии'}">${stockPill}</button></td>
            <td class="stbl-cell stbl-cell--action"><button class="admin-action-btn admin-action-btn--delete" data-delete-id="${sauce.id}" title="Удалить">${trashIcon}</button></td>
        </tr>`;
    }).join('');

    sauceList.innerHTML = `<table class="stbl">
        <thead><tr>
            <th class="stbl-th stbl-cell--drag"></th>
            <th class="stbl-th stbl-cell--check"></th>
            <th class="stbl-th stbl-cell--img"></th>
            <th class="stbl-th stbl-cell--name">Товар</th>
            <th class="stbl-th stbl-cell--desc">Краткое описание</th>
            <th class="stbl-th stbl-cell--cat">Категория</th>
            <th class="stbl-th stbl-cell--heat">Острота</th>
            <th class="stbl-th stbl-cell--vol">Объём</th>
            <th class="stbl-th stbl-cell--status">Статус</th>
            <th class="stbl-th stbl-cell--status">Наличие</th>
            <th class="stbl-th stbl-cell--action"></th>
        </tr></thead>
        <tbody id="sauce-tbody">${rows}</tbody>
    </table>`;

    // Broken image fallback
    sauceList.querySelectorAll('img[data-fallback]').forEach(img => {
        img.addEventListener('error', function () {
            const placeholder = document.createElement('div');
            placeholder.className = 'admin-sauce-thumb-placeholder';
            placeholder.innerHTML = '<img src="/uploads/pepper.svg" alt="" width="20" height="20">';
            this.replaceWith(placeholder);
        });
    });

    // Re-init SortableJS on tbody
    if (typeof initSauceSortable === 'function') initSauceSortable();
}

// Admin search with debounce + clear button
let adminSearchTimer = null;
const searchClearBtn = document.getElementById('admin-search-clear');
adminSearch.addEventListener('input', () => {
    clearTimeout(adminSearchTimer);
    adminSearchTimer = setTimeout(() => renderSauceList(adminSearch.value.trim()), 200);
    searchClearBtn.classList.toggle('visible', adminSearch.value.length > 0);
});
searchClearBtn.addEventListener('click', () => {
    adminSearch.value = '';
    searchClearBtn.classList.remove('visible');
    renderSauceList('');
    adminSearch.focus();
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
document.getElementById('form-category').addEventListener('change', (e) => {
    const label = getCategoryFormLabel(e.target.value);
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
    const catName = getCategoryFormLabel(catVal);
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
    document.getElementById('form-is-new').checked = sauce ? !!sauce.is_new : false;
    document.getElementById('form-is-low-stock').checked = sauce ? !!sauce.is_low_stock : false;

    // SEO fields
    const metaTitleEl = document.getElementById('form-meta-title');
    const metaDescEl = document.getElementById('form-meta-description');
    metaTitleEl.value = sauce ? (sauce.meta_title || '') : '';
    metaDescEl.value = sauce ? (sauce.meta_description || '') : '';
    updateSeoCounters();

    // Show/hide danger zone (delete) in edit mode
    const dangerZone = document.getElementById('form-danger-zone');
    if (sauce) {
        dangerZone.style.display = '';
    } else {
        dangerZone.style.display = 'none';
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

    // Additional images
    additionalImages = [];
    deletedImages = [];
    if (sauce && sauce.images) {
        const imgs = typeof sauce.images === 'string' ? JSON.parse(sauce.images || '[]') : (sauce.images || []);
        additionalImages = imgs.map(fn => ({ file: null, url: '/uploads/' + fn, filename: fn }));
    }
    renderAdditionalImages();

    formOverlay.classList.add('active');

    // Reset dirty state AFTER Quill content is set (Quill fires text-change on programmatic updates)
    setTimeout(() => {
        formDirty = false;
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
function closeFormPanel() {
    formOverlay.classList.remove('active');
}
function tryCloseForm() {
    if (formDirty) {
        unsavedOverlay.classList.add('active');
    } else {
        closeFormPanel();
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
    closeFormPanel();
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
        dot.setAttribute('aria-checked', val === currentHeat ? 'true' : 'false');
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
        const qlContainer = editorEl ? editorEl.querySelector('.ql-container') || editorEl : null;
        if (qlContainer) qlContainer.classList.add('error');
        document.getElementById('form-description-error').classList.add('visible');
        valid = false;
    } else {
        const qlContainerOk = editorEl ? editorEl.querySelector('.ql-container') || editorEl : null;
        if (qlContainerOk) qlContainerOk.classList.remove('error');
        document.getElementById('form-description-error').classList.remove('visible');
    }

    if (!valid) {
        const firstError = sauceForm.querySelector('.form-error.visible');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Заполните обязательные поля', 'error');
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
    formData.append('is_new', document.getElementById('form-is-new').checked ? '1' : '0');
    formData.append('is_low_stock', document.getElementById('form-is-low-stock').checked ? '1' : '0');
    formData.append('meta_title', document.getElementById('form-meta-title').value.trim());
    formData.append('meta_description', document.getElementById('form-meta-description').value.trim());
    formData.append('remove_image', removeImage ? '1' : '0');

    const imageFile = document.getElementById('form-image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    // Additional images
    const existingImgs = additionalImages.filter(img => img.filename).map(img => img.filename);
    formData.append('existing_images', JSON.stringify(existingImgs));
    formData.append('delete_images', JSON.stringify(deletedImages));
    additionalImages.filter(img => img.file).forEach(img => {
        formData.append('additional_images[]', img.file);
    });

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
            closeFormPanel();
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
            closeFormPanel();
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
    const icon = type === 'error' ? '✕' : '✓';
    toast.innerHTML = `<span class="toast__icon toast__icon--${type}">${icon}</span> ${escapeHtml(message)}`;
    toast.className = `toast ${type} show`;
    if (type === 'error') toast.setAttribute('aria-live', 'assertive');
    else toast.setAttribute('aria-live', 'polite');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function setLoading(btn, loading) {
    btn.classList.toggle('loading', loading);
    btn.disabled = loading;
}

// Ctrl+S shortcut to save (window + capture + code-based to handle all layouts)
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.code === 'KeyS' || e.key === 's' || e.key === 'S' || e.key === 'ы' || e.key === 'Ы')) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        if (formOverlay.classList.contains('active')) {
            document.getElementById('form-submit-btn').click();
        } else if (document.getElementById('tab-site').classList.contains('active')) {
            document.getElementById('ss-save-btn').click();
        }
    }
}, true);

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
    // Inline toggle buttons (active / in_stock)
    const toggleBtn = e.target.closest('.stbl-pill-btn[data-toggle-id]');
    if (toggleBtn) {
        e.stopPropagation();
        const id = parseInt(toggleBtn.dataset.toggleId);
        const field = toggleBtn.dataset.toggleField;
        const sauce = sauces.find(s => s.id == id);
        if (!sauce) return;
        const newVal = field === 'is_active' ? (sauce.is_active ? 0 : 1) : (isInStock(sauce) ? 0 : 1);
        sauce[field] = newVal;
        renderSauceList(adminSearch.value.trim());
        const formData = new FormData();
        formData.append(field, String(newVal));
        fetch(`${API_BASE}/admin/sauces/${id}`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        }).then(res => {
            if (!res.ok) { sauce[field] = newVal ? 0 : 1; renderSauceList(adminSearch.value.trim()); showToast('Ошибка', 'error'); }
        }).catch(() => {
            sauce[field] = newVal ? 0 : 1; renderSauceList(adminSearch.value.trim()); showToast('Ошибка соединения', 'error');
        });
        return;
    }
    const deleteBtn = e.target.closest('[data-delete-id]');
    if (deleteBtn) { confirmDelete(parseInt(deleteBtn.dataset.deleteId)); return; }
    // Don't open edit when clicking drag handle
    if (e.target.closest('.cms-drag-handle')) return;
    const row = e.target.closest('.stbl-row[data-edit-id]');
    if (row) { editSauce(parseInt(row.dataset.editId)); return; }
});
sauceList.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        const row = e.target.closest('.stbl-row[data-edit-id]');
        if (row) { e.preventDefault(); editSauce(parseInt(row.dataset.editId)); }
    }
});

// === Additional Images ===
function renderAdditionalImages() {
    const list = document.getElementById('additional-images-list');
    if (!list) return;
    if (additionalImages.length === 0) { list.innerHTML = ''; return; }
    list.innerHTML = additionalImages.map((img, i) => `
        <div class="additional-image-item" draggable="true" data-index="${i}">
            <img src="${img.url}" alt="Фото ${i + 1}">
            <button type="button" class="additional-image-item__remove" data-rm="${i}" title="Удалить">&times;</button>
            <span class="additional-image-item__order">${i + 1}</span>
        </div>
    `).join('');
    setupDragReorder(list);
}

document.getElementById('additional-images-list').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-rm]');
    if (!btn) return;
    const idx = parseInt(btn.dataset.rm);
    const removed = additionalImages.splice(idx, 1)[0];
    if (removed.filename) deletedImages.push(removed.filename);
    if (removed.file) URL.revokeObjectURL(removed.url);
    renderAdditionalImages();
    formDirty = true;
});

document.getElementById('form-additional-images').addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    const remaining = 10 - additionalImages.length;
    if (remaining <= 0) { e.target.value = ''; return; }
    files.slice(0, remaining).forEach(file => {
        const url = URL.createObjectURL(file);
        additionalImages.push({ file, url, filename: null });
    });
    renderAdditionalImages();
    e.target.value = '';
    formDirty = true;
});

function setupDragReorder(container) {
    let dragIdx = null;
    container.querySelectorAll('.additional-image-item').forEach(item => {
        item.addEventListener('dragstart', (e) => {
            dragIdx = parseInt(item.dataset.index);
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            container.querySelectorAll('.additional-image-item').forEach(x => x.classList.remove('drag-over'));
            dragIdx = null;
        });
        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            item.classList.add('drag-over');
        });
        item.addEventListener('dragleave', () => { item.classList.remove('drag-over'); });
        item.addEventListener('drop', (e) => {
            e.preventDefault();
            item.classList.remove('drag-over');
            const dropIdx = parseInt(item.dataset.index);
            if (dragIdx === null || dragIdx === dropIdx) return;
            const [moved] = additionalImages.splice(dragIdx, 1);
            additionalImages.splice(dropIdx, 0, moved);
            renderAdditionalImages();
            formDirty = true;
        });
    });
}


// ============================================
// SITE SETTINGS MANAGEMENT
// ============================================

let siteSettingsLoaded = false;
let quillAbout = null;

// --- CMS Navigation (sidebar) ---
const PAGE_TITLES = { sauces: 'Товары', categories: 'Категории', site: 'Главная страница' };

document.querySelectorAll('.cms-nav__item[data-tab]').forEach(navItem => {
    navItem.addEventListener('click', () => {
        // Switch active nav
        document.querySelectorAll('.cms-nav__item').forEach(n => n.classList.remove('active'));
        navItem.classList.add('active');

        // Switch page
        document.querySelectorAll('.cms-page').forEach(p => { p.classList.remove('active'); p.style.display = 'none'; });
        const target = document.getElementById('tab-' + navItem.dataset.tab);
        if (target) { target.classList.add('active'); target.style.display = ''; }

        // Update mobile topbar title
        const topTitle = document.getElementById('cms-topbar-title');
        if (topTitle) topTitle.textContent = PAGE_TITLES[navItem.dataset.tab] || '';

        // Close mobile sidebar
        const sidebar = document.getElementById('cms-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');

        // Load site settings on first visit
        if (navItem.dataset.tab === 'site' && !siteSettingsLoaded) {
            loadSiteSettings();
        }
        // Load categories list on first visit
        if (navItem.dataset.tab === 'categories' && !categoriesLoaded) {
            categoriesLoaded = true;
            renderCategoriesList();
        }
    });
});

// --- Mobile sidebar toggle ---
const sidebarToggle = document.getElementById('sidebar-toggle');
const cmsSidebar = document.getElementById('cms-sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

if (sidebarToggle && cmsSidebar) {
    sidebarToggle.addEventListener('click', () => {
        cmsSidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('active');
    });
    sidebarOverlay.addEventListener('click', () => {
        cmsSidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
    });
}

// --- Default data (mirrors PHP defaults) ---
const DEFAULT_BENEFITS = [
    { icon: 'pepper.svg', title: 'Собственные перцы', text: 'Выращиваем острые перцы сами: Carolina Reaper, Apocalypse Scorpion, Habanero, Bhut Jolokia и другие.' },
    { icon: 'branch.svg', title: 'Натуральный состав', text: 'Готовим по авторским рецептам из натуральных ингредиентов. Без консервантов и красителей.' },
    { icon: 'gift.svg', title: 'Идея для подарка', text: 'Подарочные наборы на любой праздник — День рождения, 23 февраля, 8 марта, юбилей.' },
    { icon: 'fire.svg', title: 'Только честная острота', text: 'Готовим соусы из натуральных сверхострых перцев без добавления экстракта капсаицина!' },
    { icon: 'box.svg', title: 'Доставка по Беларуси', text: 'Ускоренная отправка на следующий день после заказа. Белпочта, Европочта.' },
    { icon: 'pizza.svg', title: 'Запоминающийся вкус', text: 'Соусы, которые действительно жгут и запоминаются. Яркий вкус для мяса, пиццы, бургеров.' },
];

const DEFAULT_TESTIMONIALS = [
    { author: 'Anton Kavaliou', text: 'Попробовал ROWAN. Интересный такой вкус. Понравилось, что очень насыщенный.' },
    { author: 'Наталья Голик', text: 'Пробовали ваши соусы) все ооочень вкусные и интересные!) Но! Agonix это ад адище 🔥🔥🔥' },
    { author: 'Света Комарова', text: 'Решила я попробовать Cheron. Грамулечку. Это просто 🔥🔥🔥 Язык пылал. Муж в восторге!' },
];

const DEFAULT_FAQ = [
    { question: 'Как сделать заказ?', answer: 'Напишите нам в Telegram @rage_fill — поможем выбрать соус и оформим заказ.' },
    { question: 'Какие способы доставки доступны?', answer: 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту.' },
    { question: 'Какой срок годности у соусов?', answer: 'Срок годности — 12 месяцев с даты изготовления.' },
    { question: 'Из чего делают соусы RAGE FILL?', answer: 'Только натуральные ингредиенты: свежие острые перцы, овощи, специи и уксус.' },
    { question: 'Какой соус выбрать, если я не пробовал острое?', answer: 'Начните с соусов с уровнем остроты 1–2 из 5.' },
    { question: 'Можно ли заказать соус в подарок?', answer: 'Да! У нас есть готовые подарочные наборы.' },
];

// --- Load site settings ---
async function loadSiteSettings() {
    const loader = document.getElementById('site-settings-loader');
    const form = document.getElementById('site-settings-form');
    try {
        const res = await fetch(`${API_BASE}/admin/site-settings`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (res.status === 401) { document.getElementById('logout-btn').click(); return; }
        const data = await res.json();
        populateSiteSettings(data);
        siteSettingsLoaded = true;
        if (loader) loader.style.display = 'none';
        if (form) form.style.display = '';
    } catch (err) {
        showToast('Ошибка загрузки настроек', 'error');
        if (loader) loader.querySelector('.cms-loader__text').textContent = 'Ошибка загрузки. Обновите страницу.';
    }
}

function populateSiteSettings(data) {
    // Hero
    document.getElementById('ss-hero-tagline').value = data.hero_tagline || '';
    document.getElementById('ss-hero-description').value = data.hero_description || '';
    document.getElementById('ss-hero-btn-primary').value = data.hero_btn_primary || '';
    document.getElementById('ss-hero-btn-secondary').value = data.hero_btn_secondary || '';

    // Contact
    document.getElementById('ss-contact-telegram').value = data.contact_telegram || '';
    document.getElementById('ss-instagram-reviews-url').value = data.instagram_reviews_url || '';

    // Benefits
    const benefits = Array.isArray(data.benefits) ? data.benefits : DEFAULT_BENEFITS;
    renderBenefitsRepeater(benefits);

    // Testimonials
    const testimonials = Array.isArray(data.testimonials) ? data.testimonials : DEFAULT_TESTIMONIALS;
    renderTestimonialsRepeater(testimonials);

    // FAQ
    const faq = Array.isArray(data.faq) ? data.faq : DEFAULT_FAQ;
    renderFaqRepeater(faq);

    // About (Quill)
    initQuillAbout(data.about_text || '');

    // Section titles
    document.getElementById('ss-section-title-benefits').value = data.section_title_benefits || '';
    document.getElementById('ss-section-title-reviews').value = data.section_title_reviews || '';
    document.getElementById('ss-section-title-faq').value = data.section_title_faq || '';

    // Featured products
    document.getElementById('ss-featured-title').value = data.featured_title || '';
    featuredProductIds = Array.isArray(data.featured_product_ids) ? data.featured_product_ids : [];
    populateFeaturedSelect();
    renderFeaturedPicker();

    // Footer
    document.getElementById('ss-footer-tagline').value = data.footer_tagline || '';
    document.getElementById('ss-footer-about').value = data.footer_about || '';

    // Review images
    loadReviewImages();

    // Apply collapsible state and build TOC
    applySectionCollapse();
    buildToc();
}

// --- Quill for About ---
function initQuillAbout(html) {
    if (quillAbout) {
        quillAbout.root.innerHTML = html;
        return;
    }
    loadQuillAssets().then(() => {
        quillAbout = initQuillWithHtmlToggle('#ss-about-editor', 'Текст раздела "О нас"…');
        quillAbout.root.innerHTML = html;
    }).catch(() => {});
}

// --- Repeater: Benefits ---
function renderBenefitsRepeater(items) {
    const cnt = document.getElementById('benefits-count');
    if (cnt) cnt.textContent = items.length || '';
    const container = document.getElementById('ss-benefits-list');
    container.innerHTML = `<table class="rtbl"><tbody>${items.map((b, i) => `
        <tr class="rtbl-row" data-index="${i}">
            <td class="rtbl-cell rtbl-cell--drag"><div class="site-repeater__drag-handle"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="7" r="1.5"/><circle cx="15" cy="7" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="17" r="1.5"/><circle cx="15" cy="17" r="1.5"/></svg></div></td>
            <td class="rtbl-cell rtbl-cell--num">${i + 1}</td>
            <td class="rtbl-cell rtbl-cell--icon"><input type="text" class="form-input ss-benefit-icon" value="${escapeHtml(b.icon || '')}" placeholder="svg" title="Файл иконки"></td>
            <td class="rtbl-cell rtbl-cell--main"><input type="text" class="form-input ss-benefit-title" value="${escapeHtml(b.title || '')}" placeholder="Заголовок"></td>
            <td class="rtbl-cell rtbl-cell--wide"><input type="text" class="form-input ss-benefit-text" value="${escapeHtml(b.text || '')}" placeholder="Описание"></td>
            <td class="rtbl-cell rtbl-cell--rm"><button type="button" class="site-repeater__remove" data-remove="benefit" data-index="${i}" title="Удалить">&times;</button></td>
        </tr>`).join('')}</tbody></table>`;
    bindRemoveButtons(container, 'benefit', renderBenefitsRepeater);
    initRepeaterSortable(container.querySelector('tbody'));
}

// --- Repeater: Testimonials ---
function renderTestimonialsRepeater(items) {
    const cnt = document.getElementById('testimonials-count');
    if (cnt) cnt.textContent = items.length || '';
    const container = document.getElementById('ss-testimonials-list');
    container.innerHTML = `<table class="rtbl"><tbody>${items.map((t, i) => `
        <tr class="rtbl-row" data-index="${i}">
            <td class="rtbl-cell rtbl-cell--drag"><div class="site-repeater__drag-handle"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="7" r="1.5"/><circle cx="15" cy="7" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="17" r="1.5"/><circle cx="15" cy="17" r="1.5"/></svg></div></td>
            <td class="rtbl-cell rtbl-cell--num">${i + 1}</td>
            <td class="rtbl-cell rtbl-cell--main"><input type="text" class="form-input ss-testimonial-author" value="${escapeHtml(t.author || '')}" placeholder="Автор"></td>
            <td class="rtbl-cell rtbl-cell--wide"><input type="text" class="form-input ss-testimonial-text" value="${escapeHtml(t.text || '')}" placeholder="Текст отзыва"></td>
            <td class="rtbl-cell rtbl-cell--rm"><button type="button" class="site-repeater__remove" data-remove="testimonial" data-index="${i}" title="Удалить">&times;</button></td>
        </tr>`).join('')}</tbody></table>`;
    bindRemoveButtons(container, 'testimonial', renderTestimonialsRepeater);
    initRepeaterSortable(container.querySelector('tbody'));
}

// --- Repeater: FAQ ---
function renderFaqRepeater(items) {
    const cnt = document.getElementById('faq-count');
    if (cnt) cnt.textContent = items.length || '';
    const container = document.getElementById('ss-faq-list');
    container.innerHTML = `<table class="rtbl"><tbody>${items.map((f, i) => `
        <tr class="rtbl-row" data-index="${i}">
            <td class="rtbl-cell rtbl-cell--drag"><div class="site-repeater__drag-handle"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="7" r="1.5"/><circle cx="15" cy="7" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="17" r="1.5"/><circle cx="15" cy="17" r="1.5"/></svg></div></td>
            <td class="rtbl-cell rtbl-cell--num">${i + 1}</td>
            <td class="rtbl-cell rtbl-cell--main"><input type="text" class="form-input ss-faq-question" value="${escapeHtml(f.question || '')}" placeholder="Вопрос"></td>
            <td class="rtbl-cell rtbl-cell--wide"><textarea class="form-input ss-faq-answer" placeholder="Ответ (HTML)" rows="2">${escapeHtml(f.answer || '')}</textarea></td>
            <td class="rtbl-cell rtbl-cell--rm"><button type="button" class="site-repeater__remove" data-remove="faq" data-index="${i}" title="Удалить">&times;</button></td>
        </tr>`).join('')}</tbody></table>`;
    bindRemoveButtons(container, 'faq', renderFaqRepeater);
    initRepeaterSortable(container.querySelector('tbody'));
}

function bindRemoveButtons(container, type, renderFn) {
    container.querySelectorAll(`.site-repeater__remove[data-remove="${type}"]`).forEach(btn => {
        btn.addEventListener('click', () => {
            const items = collectRepeaterData(type);
            items.splice(parseInt(btn.dataset.index), 1);
            renderFn(items);
        });
    });
}

// --- Add buttons ---
document.getElementById('ss-benefits-add').addEventListener('click', () => {
    const items = collectRepeaterData('benefit');
    items.push({ icon: '', title: '', text: '' });
    renderBenefitsRepeater(items);
});

document.getElementById('ss-testimonials-add').addEventListener('click', () => {
    const items = collectRepeaterData('testimonial');
    items.push({ author: '', text: '' });
    renderTestimonialsRepeater(items);
});

document.getElementById('ss-faq-add').addEventListener('click', () => {
    const items = collectRepeaterData('faq');
    items.push({ question: '', answer: '' });
    renderFaqRepeater(items);
});

// --- Collect repeater data ---
function collectRepeaterData(type) {
    if (type === 'benefit') {
        const icons = document.querySelectorAll('.ss-benefit-icon');
        const titles = document.querySelectorAll('.ss-benefit-title');
        const texts = document.querySelectorAll('.ss-benefit-text');
        return Array.from(icons).map((_, i) => ({
            icon: icons[i].value.trim(),
            title: titles[i].value.trim(),
            text: texts[i].value.trim(),
        }));
    }
    if (type === 'testimonial') {
        const authors = document.querySelectorAll('.ss-testimonial-author');
        const texts = document.querySelectorAll('.ss-testimonial-text');
        return Array.from(authors).map((_, i) => ({
            author: authors[i].value.trim(),
            text: texts[i].value.trim(),
        }));
    }
    if (type === 'faq') {
        const questions = document.querySelectorAll('.ss-faq-question');
        const answers = document.querySelectorAll('.ss-faq-answer');
        return Array.from(questions).map((_, i) => ({
            question: questions[i].value.trim(),
            answer: answers[i].value.trim(),
        }));
    }
    return [];
}

// --- Warn before leaving with unsaved form data ---
window.addEventListener('beforeunload', (e) => {
    const formOpen = formOverlay.classList.contains('active');
    if (formOpen && formDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// --- Save site settings ---
document.getElementById('site-settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('ss-save-btn');
    setLoading(btn, true);

    const payload = {
        hero_tagline: document.getElementById('ss-hero-tagline').value.trim(),
        hero_description: document.getElementById('ss-hero-description').value.trim(),
        hero_btn_primary: document.getElementById('ss-hero-btn-primary').value.trim(),
        hero_btn_secondary: document.getElementById('ss-hero-btn-secondary').value.trim(),
        contact_telegram: document.getElementById('ss-contact-telegram').value.trim(),
        instagram_reviews_url: document.getElementById('ss-instagram-reviews-url').value.trim(),
        benefits: collectRepeaterData('benefit'),
        testimonials: collectRepeaterData('testimonial'),
        faq: collectRepeaterData('faq'),
        about_text: quillAbout ? quillAbout._getHtml() : '',
        section_title_benefits: document.getElementById('ss-section-title-benefits').value.trim(),
        section_title_reviews: document.getElementById('ss-section-title-reviews').value.trim(),
        section_title_faq: document.getElementById('ss-section-title-faq').value.trim(),
        featured_title: document.getElementById('ss-featured-title').value.trim(),
        featured_product_ids: featuredProductIds,
        footer_tagline: document.getElementById('ss-footer-tagline').value.trim(),
        footer_about: document.getElementById('ss-footer-about').value.trim(),
    };

    try {
        const res = await fetch(`${API_BASE}/admin/site-settings`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (res.status === 401) { document.getElementById('logout-btn').click(); return; }

        const data = await res.json();
        if (data.success) {
            showToast('Настройки сохранены');
        } else {
            showToast(data.error || 'Ошибка сохранения', 'error');
        }
    } catch (err) {
        showToast('Ошибка соединения', 'error');
    } finally {
        setLoading(btn, false);
    }
});

// --- Theme toggle (dark is default, light via attribute) ---
const themeToggle = document.getElementById('cms-theme-toggle');
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const isLight = document.documentElement.getAttribute('data-theme') === 'light';
        if (isLight) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('ragefill_cms_theme', 'dark');
            themeToggle.querySelector('.cms-theme-label').textContent = 'Светлая тема';
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('ragefill_cms_theme', 'light');
            themeToggle.querySelector('.cms-theme-label').textContent = 'Тёмная тема';
        }
    });
    // Set initial label (dark is default)
    if (document.documentElement.getAttribute('data-theme') !== 'light') {
        themeToggle.querySelector('.cms-theme-label').textContent = 'Светлая тема';
    }
}

// ============================================
// SEO FIELD COUNTERS
// ============================================

function updateSeoCounters() {
    const t = document.getElementById('form-meta-title');
    const d = document.getElementById('form-meta-description');
    document.getElementById('meta-title-count').textContent = `${(t.value || '').length}/70`;
    document.getElementById('meta-desc-count').textContent = `${(d.value || '').length}/160`;
}
document.getElementById('form-meta-title').addEventListener('input', updateSeoCounters);
document.getElementById('form-meta-description').addEventListener('input', updateSeoCounters);

// ============================================
// BULK OPERATIONS
// ============================================

function updateBulkBar() {
    const bar = document.getElementById('bulk-bar');
    const countEl = document.getElementById('bulk-count');
    if (selectedIds.size > 0) {
        bar.style.display = '';
        countEl.textContent = `${selectedIds.size} выбрано`;
    } else {
        bar.style.display = 'none';
    }
}

sauceList.addEventListener('change', (e) => {
    if (!e.target.classList.contains('bulk-check')) return;
    const id = parseInt(e.target.dataset.id);
    if (e.target.checked) {
        selectedIds.add(id);
    } else {
        selectedIds.delete(id);
    }
    const row = e.target.closest('.stbl-row');
    if (row) row.classList.toggle('selected', e.target.checked);
    updateBulkBar();
});

document.getElementById('bulk-bar').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-bulk]');
    if (!btn) return;
    const action = btn.dataset.bulk;
    if (selectedIds.size === 0) return;

    if (action === 'delete') {
        if (!confirm(`Удалить ${selectedIds.size} товаров? Это действие необратимо.`)) return;
    }

    btn.disabled = true;
    try {
        const res = await fetch(`${API_BASE}/admin/sauces/bulk`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: Array.from(selectedIds), action })
        });
        if (res.ok) {
            selectedIds.clear();
            updateBulkBar();
            showToast('Готово', 'success');
            loadSauces();
        } else {
            const data = await res.json();
            showToast(data.error || 'Ошибка', 'error');
        }
    } catch (err) {
        showToast('Ошибка соединения', 'error');
    } finally {
        btn.disabled = false;
    }
});

// ============================================
// CATEGORIES MANAGEMENT
// ============================================

let categoriesLoaded = false;

function renderCategoriesList() {
    const container = document.getElementById('categories-list');
    if (!container) return;
    if (categories.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state__text">Нет категорий</div></div>';
        return;
    }
    container.innerHTML = categories.map((cat, i) => `
        <div class="cms-category-item" data-cat-id="${cat.id}">
            <div class="cms-drag-handle" title="Перетащить"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="10" r="1.5"/><circle cx="15" cy="10" r="1.5"/><circle cx="9" cy="15" r="1.5"/><circle cx="15" cy="15" r="1.5"/><circle cx="9" cy="20" r="1.5"/><circle cx="15" cy="20" r="1.5"/></svg></div>
            <span class="cms-category-item__order">${i + 1}</span>
            <div class="cms-emoji-picker-wrap">
                <input type="text" class="form-input cms-category-item__emoji" value="${escapeHtml(cat.emoji || '')}" placeholder="🔥" style="width:50px;text-align:center" readonly>
            </div>
            <input type="text" class="form-input cms-category-item__name" value="${escapeHtml(cat.name)}" placeholder="Название">
            <input type="text" class="form-input cms-category-item__slug" value="${escapeHtml(cat.slug)}" placeholder="slug" style="width:140px">
            <button class="cms-btn cms-btn--sm cms-category-save" data-cat-id="${cat.id}" title="Сохранить">✓</button>
            <button class="admin-action-btn admin-action-btn--delete cms-category-delete" data-cat-id="${cat.id}" aria-label="Удалить">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </button>
        </div>
    `).join('');
    initCategorySortable();
}

document.getElementById('categories-list').addEventListener('click', async (e) => {
    const saveBtn = e.target.closest('.cms-category-save');
    if (saveBtn) {
        const id = parseInt(saveBtn.dataset.catId);
        const row = saveBtn.closest('.cms-category-item');
        const data = {
            emoji: row.querySelector('.cms-category-item__emoji').value.trim(),
            name: row.querySelector('.cms-category-item__name').value.trim(),
            slug: row.querySelector('.cms-category-item__slug').value.trim(),
        };
        if (!data.name) { showToast('Название обязательно', 'error'); return; }
        try {
            const res = await fetch(`${API_BASE}/admin/categories/${id}`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (res.ok) {
                showToast('Категория обновлена');
                await loadCategories();
                renderCategoriesList();
            } else {
                const d = await res.json();
                showToast(d.error || 'Ошибка', 'error');
            }
        } catch (err) { showToast('Ошибка соединения', 'error'); }
        return;
    }

    const delBtn = e.target.closest('.cms-category-delete');
    if (delBtn) {
        const id = parseInt(delBtn.dataset.catId);
        if (!confirm('Удалить категорию?')) return;
        try {
            const res = await fetch(`${API_BASE}/admin/categories/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const d = await res.json();
            if (d.success) {
                showToast('Категория удалена');
                await loadCategories();
                renderCategoriesList();
            } else {
                showToast(d.error || 'Ошибка', 'error');
            }
        } catch (err) { showToast('Ошибка соединения', 'error'); }
    }
});

document.getElementById('add-category-btn').addEventListener('click', () => {
    const container = document.getElementById('categories-list');
    if (container.querySelector('.cms-category-item--new')) return; // already adding
    const newRow = document.createElement('div');
    newRow.className = 'cms-category-item cms-category-item--new';
    newRow.innerHTML = `
        <span class="cms-category-item__order">+</span>
        <div class="cms-emoji-picker-wrap">
            <input type="text" class="form-input cms-category-item__emoji" placeholder="🔥" style="width:50px;text-align:center" readonly>
        </div>
        <input type="text" class="form-input cms-category-item__name" placeholder="Название категории">
        <input type="text" class="form-input cms-category-item__slug" placeholder="slug (авто)" style="width:140px">
        <button class="cms-btn cms-btn--sm cms-btn--primary cms-category-create" title="Создать">✓</button>
        <button class="admin-action-btn admin-action-btn--delete cms-category-cancel-new" title="Отмена">✕</button>
    `;
    container.appendChild(newRow);
    newRow.querySelector('.cms-category-item__name').focus();

    newRow.querySelector('.cms-category-cancel-new').addEventListener('click', () => newRow.remove());
    newRow.querySelector('.cms-category-create').addEventListener('click', async () => {
        const name = newRow.querySelector('.cms-category-item__name').value.trim();
        const emoji = newRow.querySelector('.cms-category-item__emoji').value.trim();
        if (!name) { showToast('Название обязательно', 'error'); return; }
        try {
            const res = await fetch(`${API_BASE}/admin/categories`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, emoji })
            });
            if (res.ok) {
                showToast('Категория добавлена');
                await loadCategories();
                renderCategoriesList();
            } else {
                const d = await res.json();
                showToast(d.error || 'Ошибка', 'error');
            }
        } catch (err) { showToast('Ошибка соединения', 'error'); }
    });
});

// ============================================
// REVIEW IMAGES MANAGEMENT
// ============================================

let reviewImages = [];

async function loadReviewImages() {
    try {
        const res = await fetch(`${API_BASE}/admin/reviews`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (res.ok) {
            reviewImages = await res.json();
            renderReviewImages();
        }
    } catch (err) { /* silent */ }
}

function renderReviewImages() {
    const list = document.getElementById('ss-reviews-list');
    const countEl = document.getElementById('reviews-img-count');
    if (countEl) countEl.textContent = reviewImages.length || '';
    if (!list) return;
    if (reviewImages.length === 0) { list.innerHTML = ''; return; }
    list.innerHTML = reviewImages.map((img, i) => `
        <div class="additional-image-item" draggable="true" data-index="${i}">
            <img src="/uploads/reviews/${escapeHtml(img)}" alt="Отзыв ${i + 1}" loading="lazy">
            <button type="button" class="additional-image-item__remove" data-review-rm="${escapeHtml(img)}" title="Удалить">&times;</button>
            <span class="additional-image-item__order">${i + 1}</span>
        </div>
    `).join('');
    setupReviewDragReorder(list);
}

function setupReviewDragReorder(container) {
    let dragIdx = null;
    container.querySelectorAll('.additional-image-item').forEach(item => {
        item.addEventListener('dragstart', (e) => {
            dragIdx = parseInt(item.dataset.index);
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            container.querySelectorAll('.additional-image-item').forEach(x => x.classList.remove('drag-over'));
            dragIdx = null;
        });
        item.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; item.classList.add('drag-over'); });
        item.addEventListener('dragleave', () => { item.classList.remove('drag-over'); });
        item.addEventListener('drop', async (e) => {
            e.preventDefault();
            item.classList.remove('drag-over');
            const dropIdx = parseInt(item.dataset.index);
            if (dragIdx === null || dragIdx === dropIdx) return;
            const [moved] = reviewImages.splice(dragIdx, 1);
            reviewImages.splice(dropIdx, 0, moved);
            renderReviewImages();
            // Save new order
            await fetch(`${API_BASE}/admin/reviews/reorder`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: reviewImages })
            });
        });
    });
}

document.getElementById('ss-reviews-list').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-review-rm]');
    if (!btn) return;
    const filename = btn.dataset.reviewRm;
    if (!confirm('Удалить фото?')) return;
    try {
        const res = await fetch(`${API_BASE}/admin/reviews/${encodeURIComponent(filename)}`, {
            method: 'DELETE',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (res.ok) {
            const data = await res.json();
            reviewImages = data.images || [];
            renderReviewImages();
            showToast('Фото удалено');
        }
    } catch (err) { showToast('Ошибка', 'error'); }
});

document.getElementById('ss-reviews-upload').addEventListener('change', async (e) => {
    const files = e.target.files;
    if (!files.length) return;

    const formData = new FormData();
    Array.from(files).forEach(f => formData.append('images[]', f));

    try {
        const res = await fetch(`${API_BASE}/admin/reviews`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });
        if (res.ok) {
            const data = await res.json();
            reviewImages = data.images || [];
            renderReviewImages();
            showToast('Фото загружены');
        }
    } catch (err) { showToast('Ошибка загрузки', 'error'); }
    e.target.value = '';
});

// ============================================
// FEATURED PRODUCTS PICKER
// ============================================

let featuredProductIds = [];

function populateFeaturedSelect() {
    const sel = document.getElementById('ss-featured-select');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Выберите товар —</option>' +
        sauces.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
}

function renderFeaturedPicker() {
    const container = document.getElementById('ss-featured-products');
    if (!container) return;
    if (featuredProductIds.length === 0) {
        container.innerHTML = '<div class="form-hint">Авто-выбор по бейджу ХИТ</div>';
        return;
    }
    container.innerHTML = featuredProductIds.map((id, i) => {
        const sauce = sauces.find(s => s.id == id);
        const name = sauce ? escapeHtml(sauce.name) : `ID ${id}`;
        return `<div class="cms-featured-chip">
            <span>${i + 1}. ${name}</span>
            <button type="button" class="cms-featured-chip__remove" data-featured-rm="${id}">&times;</button>
        </div>`;
    }).join('');
}

document.getElementById('ss-featured-add').addEventListener('click', () => {
    const sel = document.getElementById('ss-featured-select');
    const id = parseInt(sel.value);
    if (!id || featuredProductIds.includes(id)) return;
    if (featuredProductIds.length >= 6) { showToast('Максимум 6 товаров', 'error'); return; }
    featuredProductIds.push(id);
    renderFeaturedPicker();
    sel.value = '';
});

document.getElementById('ss-featured-products').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-featured-rm]');
    if (!btn) return;
    const id = parseInt(btn.dataset.featuredRm);
    featuredProductIds = featuredProductIds.filter(x => x !== id);
    renderFeaturedPicker();
});

// ============================================
// EXTENDED SITE SETTINGS (section titles, footer, featured)
// ============================================

// ============================================
// UX ENHANCEMENTS
// ============================================

// --- Category Filter Chips ---
function renderCategoryFilters() {
    const container = document.getElementById('category-filters');
    if (!container) return;
    const allChip = `<button class="cms-filter-chip${!activeCategoryFilter ? ' active' : ''}" data-cat-filter="">Все</button>`;
    const chips = categories.map(c =>
        `<button class="cms-filter-chip${activeCategoryFilter === c.slug ? ' active' : ''}" data-cat-filter="${escapeHtml(c.slug)}">${c.emoji ? escapeHtml(c.emoji) + ' ' : ''}${escapeHtml(c.name)}</button>`
    ).join('');
    container.innerHTML = allChip + chips;
}

document.getElementById('category-filters').addEventListener('click', (e) => {
    const chip = e.target.closest('.cms-filter-chip');
    if (!chip) return;
    activeCategoryFilter = chip.dataset.catFilter || null;
    renderCategoryFilters();
    renderSauceList(adminSearch.value.trim());
});

// --- Sauce List Drag Reorder (SortableJS) ---
let sauceSortableInstance = null;

function initSauceSortable() {
    if (sauceSortableInstance) sauceSortableInstance.destroy();
    if (typeof Sortable === 'undefined') return;
    const tbody = document.getElementById('sauce-tbody');
    if (!tbody) return;
    sauceSortableInstance = Sortable.create(tbody, {
        handle: '.cms-drag-handle',
        animation: 200,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: async function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            // Get new DOM order of visible items
            const items = tbody.querySelectorAll('.stbl-row[data-sauce-id]');
            const newOrder = Array.from(items).map(el => parseInt(el.dataset.sauceId));
            const visibleIds = new Set(newOrder);

            // Rebuild: reordered visible items first, then non-visible in original order
            const result = [];
            for (const s of sauces) {
                if (visibleIds.has(s.id)) {
                    // Skip — will be inserted from newOrder
                } else {
                    result.push(s);
                }
            }
            // Now interleave: place reordered visible items at the front (they were sorted by sort_order)
            const sauceMap = new Map(sauces.map(s => [s.id, s]));
            const reordered = newOrder.map(id => sauceMap.get(id)).filter(Boolean);
            sauces = [...reordered, ...result];
            sauces.forEach((s, i) => s.sort_order = i);

            // Save to backend
            try {
                await fetch(`${API_BASE}/admin/sauces/reorder`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: sauces.map(s => s.id) })
                });
            } catch (err) { showToast('Ошибка сохранения порядка', 'error'); }
        }
    });
}

// --- Category Drag Reorder (SortableJS) ---
let categorySortableInstance = null;

function initCategorySortable() {
    if (categorySortableInstance) categorySortableInstance.destroy();
    if (typeof Sortable === 'undefined') return;
    const container = document.getElementById('categories-list');
    categorySortableInstance = Sortable.create(container, {
        handle: '.cms-drag-handle',
        animation: 200,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: async function (evt) {
            if (evt.oldIndex === evt.newIndex) return;
            // Reorder categories array
            const [moved] = categories.splice(evt.oldIndex, 1);
            categories.splice(evt.newIndex, 0, moved);
            renderCategoriesList();
            // Save to backend
            try {
                await fetch(`${API_BASE}/admin/categories/reorder`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: categories.map(c => c.id) })
                });
                showToast('Порядок сохранён');
            } catch (err) { showToast('Ошибка сохранения порядка', 'error'); }
        }
    });
}

// --- Emoji Picker for Categories ---
const EMOJI_PALETTE = ['🔥','🌶️','🫙','🎁','🥜','🌿','⭐','💀','🍕','🍔','🥩','🧄','🧅','🍋','🫑','🌽','🍯','🥫','📦','🎯','💪','❤️','🏆','✨','🆕','👑','🔴','🟠','🟡','🟢','🫘','🥄','🍅','🥒'];

document.getElementById('categories-list').addEventListener('click', (e) => {
    const emojiInput = e.target.closest('.cms-category-item__emoji');
    if (!emojiInput) return;
    // Close existing picker
    const existing = document.querySelector('.cms-emoji-picker');
    if (existing) { existing.remove(); return; }
    const wrap = emojiInput.closest('.cms-emoji-picker-wrap');
    const picker = document.createElement('div');
    picker.className = 'cms-emoji-picker';
    picker.innerHTML = EMOJI_PALETTE.map(em =>
        `<button type="button" class="cms-emoji-picker__item">${em}</button>`
    ).join('');
    wrap.appendChild(picker);
    picker.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.cms-emoji-picker__item');
        if (btn) { emojiInput.value = btn.textContent; picker.remove(); }
    });
    setTimeout(() => {
        document.addEventListener('click', function closePicker(ev) {
            if (!picker.contains(ev.target) && ev.target !== emojiInput) {
                picker.remove();
                document.removeEventListener('click', closePicker);
            }
        });
    }, 0);
});

// --- Repeater Drag Reorder (SortableJS) ---
function initRepeaterSortable(container) {
    if (typeof Sortable === 'undefined' || !container) return;
    Sortable.create(container, {
        handle: '.site-repeater__drag-handle',
        animation: 200,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: function () {
            // Update numbering after reorder
            container.querySelectorAll('.rtbl-row').forEach((row, i) => {
                row.dataset.index = i;
                const num = row.querySelector('.rtbl-cell--num');
                if (num) num.textContent = i + 1;
                const removeBtn = row.querySelector('.site-repeater__remove');
                if (removeBtn) removeBtn.dataset.index = i;
            });
        }
    });
}

// --- CMS Collapsible Cards ---
const COLLAPSED_KEY = 'ragefill_cms_collapsed';
let collapsedSections = new Set(JSON.parse(localStorage.getItem(COLLAPSED_KEY) || '[]'));

function applySectionCollapse() {
    document.querySelectorAll('#site-settings-form .cms-card[data-section]').forEach(card => {
        const section = card.dataset.section;
        if (collapsedSections.has(section)) {
            card.classList.add('collapsed');
        } else {
            card.classList.remove('collapsed');
        }
    });
}

function toggleSection(card) {
    const section = card.dataset.section;
    if (!section) return;
    if (collapsedSections.has(section)) {
        collapsedSections.delete(section);
        card.classList.remove('collapsed');
    } else {
        collapsedSections.add(section);
        card.classList.add('collapsed');
    }
    localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...collapsedSections]));
}

// Delegate click on card headers in site settings
document.getElementById('site-settings-form').addEventListener('click', (e) => {
    const header = e.target.closest('.cms-card__header');
    if (!header) return;
    // Don't toggle if clicking on a count badge or other interactive element inside header
    if (e.target.closest('.cms-card__count')) return;
    const card = header.closest('.cms-card[data-section]');
    if (card) toggleSection(card);
});

// Expand All / Collapse All (delegated — buttons are recreated by buildToc)
document.addEventListener('click', (e) => {
    if (e.target.closest('#cms-expand-all')) {
        collapsedSections.clear();
        localStorage.setItem(COLLAPSED_KEY, '[]');
        applySectionCollapse();
    }
    if (e.target.closest('#cms-collapse-all')) {
        document.querySelectorAll('#site-settings-form .cms-card[data-section]').forEach(card => {
            collapsedSections.add(card.dataset.section);
        });
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify([...collapsedSections]));
        applySectionCollapse();
    }
});

// --- Section TOC ---
let tocObserver = null;

function buildToc() {
    const toc = document.getElementById('cms-toc');
    if (!toc) return;

    // Disconnect previous observer to avoid leaks
    if (tocObserver) { tocObserver.disconnect(); tocObserver = null; }

    const sections = document.querySelectorAll('#site-settings-form .cms-card[data-section]');
    if (sections.length === 0) return;
    // Preserve controls block, rebuild only toc items
    const controls = toc.querySelector('.cms-toc__controls');
    const controlsHtml = controls ? controls.outerHTML : '';
    toc.innerHTML = controlsHtml + Array.from(sections).map(card => {
        const title = card.querySelector('.cms-card__title').textContent;
        return `<button type="button" class="cms-toc__item" data-toc-section="${card.dataset.section}">${title}</button>`;
    }).join('');

    // Use event delegation on toc (innerHTML replaced, so old listeners are GC'd)
    toc.onclick = (e) => {
        const item = e.target.closest('[data-toc-section]');
        if (!item) return;
        const section = item.dataset.tocSection;
        const card = document.querySelector(`.cms-card[data-section="${section}"]`);
        if (!card) return;
        if (collapsedSections.has(section)) {
            toggleSection(card);
        }
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // IntersectionObserver for active state — only one active at a time
    const visibleSections = new Set();
    tocObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const s = entry.target.dataset.section;
            if (entry.isIntersecting) visibleSections.add(s);
            else visibleSections.delete(s);
        });
        // Pick the topmost visible section
        const allItems = toc.querySelectorAll('[data-toc-section]');
        let found = false;
        allItems.forEach(item => {
            if (!found && visibleSections.has(item.dataset.tocSection)) {
                item.classList.add('active');
                found = true;
            } else {
                item.classList.remove('active');
            }
        });
    }, { rootMargin: '-10% 0px -60% 0px' });
    sections.forEach(s => tocObserver.observe(s));
}

