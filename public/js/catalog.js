// ============================================
// RAGEFILL Catalog v2.0.0 — Desktop + Mobile
// Dual-layout: catalog site (desktop) + TG app (mobile)
// ============================================

const _tgRaw = window.Telegram?.WebApp;
const tg = (_tgRaw && _tgRaw.initData) ? _tgRaw : null; // only truthy inside real TG WebApp
let manualTheme = null; // null = auto, 'dark' | 'light' = manual

if (tg) {
    tg.ready();
    tg.expand();
    document.body.classList.add('tg-theme', 'tg-mode');
    if (tg.colorScheme === 'dark') document.body.classList.add('tg-dark');
    tg.onEvent('themeChanged', () => {
        if (!manualTheme) document.body.classList.toggle('tg-dark', tg.colorScheme === 'dark');
    });
} else {
    document.body.classList.add('browser-mode');
}

// Theme toggle (works in both browser and TG)
(function initThemeToggle() {
    const saved = localStorage.getItem('ragefill-theme');
    if (saved === 'dark') {
        document.body.classList.add('tg-dark');
        manualTheme = 'dark';
    } else if (saved === 'light') {
        document.body.classList.remove('tg-dark');
        manualTheme = 'light';
    }

    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const isDark = document.body.classList.toggle('tg-dark');
        manualTheme = isDark ? 'dark' : 'light';
        localStorage.setItem('ragefill-theme', manualTheme);
    });
})();

const API_BASE = '/api';
const DESKTOP_BREAKPOINT = 1024;
const FOCUSABLE_SELECTOR = 'button, [href], input, [tabindex]:not([tabindex="-1"])';
const catalogEl = document.getElementById('catalog');

// Prevent SSR link navigation — JS will handle clicks via modals
catalogEl.querySelectorAll('a.sauce-card').forEach(a => {
    a.addEventListener('click', e => e.preventDefault());
});
const modalOverlay = document.getElementById('modal-overlay');
const modal = document.getElementById('modal');
const searchInput = document.getElementById('search-input');
const toolbar = document.getElementById('toolbar');
const filterChipsContainer = document.getElementById('filter-chips');
const filterToggleBtn = document.getElementById('filter-toggle-btn');
const filterDropdown = document.getElementById('filter-dropdown');
const filterBadge = document.getElementById('filter-badge');
const resetBtn = document.getElementById('toolbar-reset-btn');

let allSauces = [];
let contactTelegram = 'rage_fill';
let activeFilter = 'all';
let stockFilter = 'all'; // 'all' | 'in_stock' | 'out_of_stock'
let categoryFilter = 'all'; // 'all' | 'sauce' | 'gift_set' | 'pickled_pepper' | 'spicy_peanut' | 'spice'
let searchDebounceTimer = null;
let modalTriggerEl = null; // element that opened the modal, for focus return

fetch(`${API_BASE}/settings`).then(r => r.json()).then(s => {
    if (s.contact_telegram) contactTelegram = s.contact_telegram;
}).catch(() => {});

// --- Utilities ---
function esc(text) {
    return (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function plural(n, one, few, many) {
    const mod10 = n % 10, mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
    return many;
}

function stripHtml(html) {
    return new DOMParser().parseFromString(html || '', 'text/html').body.textContent || '';
}

function isTruthy(val) {
    return val === 1 || val === '1';
}

function isInStock(sauce) {
    return sauce.in_stock !== 0 && sauce.in_stock !== '0';
}

function isHtmlContent(str) {
    return /<[a-z][\s\S]*>/i.test(str);
}

function cleanQuillHtml(html) {
    let clean = (html || '').trim()
        .replace(/<p><br><\/p>/gi, '')
        .replace(/<br><\/p>/gi, '</p>')
        .replace(/<p>\s*<\/p>/gi, '');
    return DOMPurify.sanitize(clean, {
        ALLOWED_TAGS: ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'a', 'ul', 'ol', 'li', 'span'],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class'],
    });
}

function haptic(type, style) {
    try {
        if (tg?.HapticFeedback) {
            type === 'impact' ? tg.HapticFeedback.impactOccurred(style || 'light') : tg.HapticFeedback.notificationOccurred(style || 'success');
        }
    } catch (_) {}
}

// --- Heat helpers ---
const HEAT_TIERS = [
    { min: 5, id: 'extreme' },
    { min: 4, id: 'fire' },
    { min: 3, id: 'hot' },
    { min: 2, id: 'medium' },
    { min: 0, id: 'mild' },
];

function getHeatTier(level) {
    return HEAT_TIERS.find(t => level >= t.min);
}

function renderPeppers(active, total) {
    total = total || 5;
    let out = '';
    for (let i = 0; i < active; i++) out += '<span class="pepper active">🌶️</span>';
    for (let i = active; i < total; i++) out += '<span class="pepper dim">🌶️</span>';
    return out;
}

// --- Skeleton ---
function showSkeletons() {
    const count = window.innerWidth >= DESKTOP_BREAKPOINT ? 8 : window.innerWidth >= 768 ? 6 : 4;
    catalogEl.innerHTML = Array.from({ length: count }, () => `
        <div class="skeleton-card">
            <div class="skeleton-image"></div>
            <div class="skeleton-body">
                <div class="skeleton-line short"></div>
                <div class="skeleton-line desc"></div>
                <div class="skeleton-line meta"></div>
            </div>
        </div>
    `).join('');
}

// --- Load ---
async function loadCatalog() {
    showSkeletons();
    try {
        const res = await fetch(`${API_BASE}/sauces`);
        allSauces = await res.json();
        // Pre-compute plain-text for search (avoids DOMParser per keystroke)
        allSauces.forEach(s => {
            s._searchText = (s.name + ' ' + stripHtml(s.description) + ' ' + (s.composition || '')).toLowerCase();
        });
        // Animate cards only on load, not on filter changes
        catalogEl.classList.add('catalog--animate');
        applyFilters();
        setTimeout(() => catalogEl.classList.remove('catalog--animate'), 500);
    } catch (err) {
        catalogEl.innerHTML = `
            <div class="empty-state">
                <div class="empty-state__icon">&#9888;&#65039;</div>
                <div class="empty-state__text">Ошибка загрузки</div>
                <div class="empty-state__hint">${tg ? 'Потяните вниз для обновления' : 'Попробуйте обновить страницу'}</div>
                <button class="empty-state__btn" onclick="loadCatalog()">Повторить</button>
            </div>
        `;
    }
}

// --- Render ---
function renderEmptyState() {
    const hasActiveSearch = searchInput.value.trim().length > 0 || getActiveFilterCount() > 0;
    catalogEl.innerHTML = `
        <div class="empty-state">
            <div class="empty-state__icon">${hasActiveSearch ? '&#128269;' : '&#127798;&#65039;'}</div>
            <div class="empty-state__text">${hasActiveSearch ? 'Ничего не найдено' : 'Каталог пока пуст'}</div>
            <div class="empty-state__hint">${hasActiveSearch ? 'Попробуйте изменить запрос или сбросить фильтры' : 'Скоро здесь появятся товары'}</div>
            ${hasActiveSearch ? '<button class="empty-state__btn" onclick="resetFilters()">Сбросить</button>' : ''}
        </div>
    `;
}

function handleCardClick(card) {
    const sauce = allSauces.find(s => s.id == card.dataset.id);
    if (!sauce) return;
    if (!tg && window.innerWidth >= DESKTOP_BREAKPOINT) {
        window.location.href = `/sauce/${card.dataset.slug}`;
        return;
    }
    modalTriggerEl = card;
    haptic('impact', 'light');
    openModal(sauce);
}

function bindCardEvents() {
    catalogEl.querySelectorAll('.sauce-card').forEach(card => {
        card.addEventListener('click', () => handleCardClick(card));
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
        });
    });
    catalogEl.querySelectorAll('.sauce-card__image').forEach(img => {
        img.addEventListener('error', function () {
            const placeholder = document.createElement('div');
            placeholder.className = 'sauce-card__image-placeholder';
            this.replaceWith(placeholder);
        });
    });
}

function renderCatalog(sauces) {
    if (sauces.length === 0) { renderEmptyState(); return; }
    catalogEl.innerHTML = sauces.map((s, i) => renderCard(s, i)).join('');
    bindCardEvents();
}

function renderCard(sauce, index) {
    const heat = sauce.heat_level;
    const tier = getHeatTier(heat);
    const inStock = isInStock(sauce);
    const stockClass = inStock ? '' : ' out-of-stock';
    const stockBadge = inStock
        ? ''
        : '<span class="sauce-card__stock-badge sauce-card__stock-badge--out">Нет в наличии</span>';

    const img = sauce.image
        ? `<img class="sauce-card__image" src="/uploads/${esc(sauce.image)}" alt="${esc(sauce.name)}" loading="lazy">`
        : `<div class="sauce-card__image-placeholder"></div>`;

    const delay = Math.min(index * 60, 400);
    const peppers = renderPeppers(heat, 5);

    const isHit = isTruthy(sauce.is_hit);
    const isLowStock = isTruthy(sauce.is_low_stock);
    const hitBadge = isHit ? '<span class="sauce-card__badge sauce-card__badge--hit">ХИТ</span>' : '';
    const lowStockBadge = isLowStock ? '<span class="sauce-card__badge sauce-card__badge--low">МАЛО</span>' : '';

    return `
        <div class="sauce-card${stockClass}" data-id="${sauce.id}" data-slug="${esc(sauce.slug || sauce.id)}" tabindex="0" role="listitem" aria-label="${esc(sauce.name)}" style="animation-delay:${delay}ms">
            <div class="sauce-card__image-wrap">
                ${img}
                ${stockBadge}
                ${hitBadge}
                ${lowStockBadge}
            </div>
            <div class="sauce-card__content">
                <h3 class="sauce-card__name">${esc(sauce.name)}</h3>
                ${sauce.subtitle ? `<div class="sauce-card__subtitle">${esc(sauce.subtitle)}</div>` : ''}
                <div class="sauce-card__bottom">
                    <div class="sauce-card__peppers">
                        <span class="sauce-card__pepper-icons">${peppers}</span>
                        <span class="sauce-card__pepper-label">${heat}/5</span>
                    </div>
                </div>
            </div>
            <div class="sauce-card__heat-accent sauce-card__heat-accent--${tier.id}"></div>
        </div>
    `;
}

const stockToggle = document.getElementById('stock-toggle');

const categoryChipsContainer = document.getElementById('category-chips');

function syncRadioGroup(container, selector, dataAttr, activeValue) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(el => {
        const isMatch = el.dataset[dataAttr] === activeValue;
        el.classList.toggle('active', isMatch);
        el.setAttribute('aria-checked', String(isMatch));
    });
}

function resetFilters() {
    searchInput.value = '';
    activeFilter = 'all';
    stockFilter = 'all';
    categoryFilter = 'all';
    syncRadioGroup(filterChipsContainer, '.toolbar__chip', 'filter', 'all');
    syncRadioGroup(stockToggle, '.toolbar__chip[data-stock]', 'stock', 'all');
    syncRadioGroup(categoryChipsContainer, '.toolbar__chip', 'category', 'all');
    updateFilterBadge();
    applyFilters();
    syncSidebarFromState();
}

function getActiveFilterCount() {
    return (categoryFilter !== 'all') + (activeFilter !== 'all') + (stockFilter !== 'all');
}

function updateFilterBadge() {
    const count = getActiveFilterCount();
    filterBadge.textContent = count;
    filterBadge.classList.toggle('visible', count > 0);
    filterToggleBtn.classList.toggle('active', count > 0);
    resetBtn.classList.toggle('visible', count > 0);
}

filterToggleBtn.addEventListener('click', () => {
    const isOpen = filterDropdown.classList.toggle('open');
    filterToggleBtn.setAttribute('aria-expanded', String(isOpen));
    haptic('impact', 'light');
});

// Close dropdown on outside click
document.addEventListener('click', (e) => {
    if (!filterDropdown.classList.contains('open')) return;
    if (!e.target.closest('.toolbar')) {
        filterDropdown.classList.remove('open');
        filterToggleBtn.setAttribute('aria-expanded', 'false');
    }
});

resetBtn.addEventListener('click', () => {
    resetFilters();
    haptic('impact', 'light');
});

// --- Modal ---
function setModalImage(sauce) {
    const container = document.getElementById('modal-image-container');
    if (sauce.image) {
        const img = document.createElement('img');
        img.className = 'modal__image';
        img.src = `/uploads/${sauce.image}`;
        img.alt = sauce.name;
        img.addEventListener('error', function () {
            const placeholder = document.createElement('div');
            placeholder.className = 'modal__image-placeholder';
            this.replaceWith(placeholder);
        });
        container.innerHTML = '';
        container.appendChild(img);
    } else {
        container.innerHTML = '<div class="modal__image-placeholder">&#127798;&#65039;</div>';
    }
}

function setRichContent(el, content) {
    if (isHtmlContent(content)) {
        el.innerHTML = cleanQuillHtml(content);
    } else {
        el.textContent = content;
    }
}

function setOptionalBlock(blockId, valueId, content, renderFn) {
    const block = document.getElementById(blockId);
    if (!content) { block.style.display = 'none'; return; }
    if (renderFn) renderFn(document.getElementById(valueId), content);
    else document.getElementById(valueId).textContent = content;
    block.style.display = 'block';
}

function openModal(sauce) {
    const inStock = isInStock(sauce);

    setModalImage(sauce);
    document.getElementById('modal-name').textContent = sauce.name;

    const subtitleEl = document.getElementById('modal-subtitle');
    if (subtitleEl) {
        subtitleEl.textContent = sauce.subtitle || '';
        subtitleEl.style.display = sauce.subtitle ? 'block' : 'none';
    }

    const stockEl = document.getElementById('modal-stock');
    if (stockEl) {
        stockEl.style.display = inStock ? 'none' : '';
        if (!inStock) {
            stockEl.className = 'modal__stock-badge modal__stock-badge--out';
            stockEl.textContent = 'Нет в наличии';
        }
    }

    const modalPeppersEl = document.getElementById('modal-peppers');
    if (modalPeppersEl) {
        const icons = modalPeppersEl.querySelector('.modal__pepper-icons');
        const label = modalPeppersEl.querySelector('.modal__pepper-label');
        if (icons) icons.innerHTML = renderPeppers(sauce.heat_level, 5);
        if (label) label.textContent = `Острота ${sauce.heat_level} из 5`;
    }

    const descEl = document.getElementById('modal-description');
    setRichContent(descEl, sauce.description);
    descEl.classList.add('collapsed');

    const readMoreBtn = document.getElementById('modal-read-more');
    readMoreBtn.textContent = 'Читать далее';
    readMoreBtn.setAttribute('aria-expanded', 'false');
    readMoreBtn.classList.remove('visible');
    requestAnimationFrame(() => {
        if (descEl.scrollHeight > descEl.clientHeight + 2) readMoreBtn.classList.add('visible');
    });

    setOptionalBlock('modal-composition', 'modal-composition-value', sauce.composition,
        (el, val) => setRichContent(el, val));
    setOptionalBlock('modal-volume', 'modal-volume-value', sauce.volume);

    const contactBtn = document.getElementById('modal-contact-btn');
    if (contactBtn) {
        contactBtn.textContent = inStock ? 'Написать продавцу' : 'Узнать о наличии';
        contactBtn.classList.toggle('modal__contact-btn--secondary', !inStock);
    }

    modalOverlay.classList.add('active');
    document.body.classList.add('modal-open');

    requestAnimationFrame(() => {
        const firstFocusable = modal.querySelector(FOCUSABLE_SELECTOR);
        if (firstFocusable) firstFocusable.focus();
    });

    if (tg) { tg.BackButton.show(); tg.BackButton.onClick(closeModal); }
}

function closeModal() {
    modalOverlay.classList.remove('active');
    document.body.classList.remove('modal-open');
    haptic('impact', 'light');
    if (tg) { tg.BackButton.hide(); tg.BackButton.offClick(closeModal); }
    if (modalTriggerEl) { modalTriggerEl.focus(); modalTriggerEl = null; }
}

document.getElementById('modal-read-more').addEventListener('click', () => {
    const desc = document.getElementById('modal-description');
    const btn = document.getElementById('modal-read-more');
    const collapsed = desc.classList.toggle('collapsed');
    btn.textContent = collapsed ? 'Читать далее' : 'Свернуть';
    btn.setAttribute('aria-expanded', String(!collapsed));
});

document.getElementById('modal-close-btn').addEventListener('click', (e) => { e.stopPropagation(); closeModal(); });
modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener('keydown', (e) => {
    if (!modalOverlay.classList.contains('active')) return;
    if (e.key === 'Escape') { closeModal(); return; }
    if (e.key === 'Tab') {
        const focusable = modal.querySelectorAll(FOCUSABLE_SELECTOR);
        if (focusable.length === 0) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (e.shiftKey) { if (document.activeElement === first) { e.preventDefault(); last.focus(); } }
        else { if (document.activeElement === last) { e.preventDefault(); first.focus(); } }
    }
});

let touchStartY = 0;
modal.addEventListener('touchstart', (e) => { touchStartY = e.touches[0].clientY; }, { passive: true });
modal.addEventListener('touchmove', (e) => {
    if (e.touches[0].clientY - touchStartY > 100 && modal.scrollTop <= 0) closeModal();
}, { passive: true });

document.getElementById('modal-contact-btn').addEventListener('click', () => {
    haptic('impact', 'medium');
    const link = `https://t.me/${contactTelegram}`;
    tg ? tg.openTelegramLink(link) : window.open(link, '_blank');
});

// --- Chip group helper ---
function initChipGroup(container, selector, dataAttr, ariaAttr, onSelect) {
    container.addEventListener('click', (e) => {
        const chip = e.target.closest(selector);
        if (!chip || !chip.dataset[dataAttr]) return;
        container.querySelectorAll(selector).forEach(c => { c.classList.remove('active'); c.setAttribute(ariaAttr, 'false'); });
        chip.classList.add('active');
        chip.setAttribute(ariaAttr, 'true');
        haptic('impact', 'light');
        onSelect(chip.dataset[dataAttr]);
    });
}

// --- Mobile filter chips ---
[
    { container: filterChipsContainer, selector: '.toolbar__chip', attr: 'filter', setState: (v) => { activeFilter = v; } },
    { container: categoryChipsContainer, selector: '.toolbar__chip', attr: 'category', setState: (v) => { categoryFilter = v; } },
    { container: stockToggle, selector: '.toolbar__chip[data-stock]', attr: 'stock', setState: (v) => { stockFilter = v; } },
].forEach(({ container, selector, attr, setState }) => {
    initChipGroup(container, selector, attr, 'aria-checked', (val) => {
        setState(val);
        updateFilterBadge();
        applyFilters();
    });
});

function getFilteredSauces() {
    const q = searchInput.value.trim().toLowerCase();
    let list = allSauces;
    if (categoryFilter !== 'all') list = list.filter(s => (s.category || 'sauce') === categoryFilter);
    if (q) list = list.filter(s => s._searchText.includes(q));
    if (activeFilter !== 'all') list = list.filter(s => getHeatTier(s.heat_level).id === activeFilter);
    if (stockFilter === 'in_stock') list = list.filter(isInStock);
    if (stockFilter === 'out_of_stock') list = list.filter(s => !isInStock(s));
    // Out of stock items go to the bottom
    list.sort((a, b) => (isInStock(a) ? 0 : 1) - (isInStock(b) ? 0 : 1));
    return list;
}

const searchHint = document.getElementById('search-hint');

function applyFilters() {
    const filtered = getFilteredSauces();
    const q = searchInput.value.trim();
    if (q.length > 0) {
        const count = filtered.length;
        searchHint.textContent = `${count} ${plural(count, 'результат', 'результата', 'результатов')}`;
    } else {
        searchHint.textContent = '';
    }
    renderCatalog(filtered);
    updateDesktopResults(filtered.length);
    syncSidebarFromState();
}

searchInput.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applyFilters(), 200);
});

window.addEventListener('scroll', () => {
    toolbar.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });

// --- Pull-to-refresh ---
(function initPullToRefresh() {
    const PULL_THRESHOLD = 120;
    const PULL_DAMPING = 0.4;

    const indicator = document.createElement('div');
    indicator.className = 'ptr-indicator';
    indicator.innerHTML = '<svg class="ptr-spinner" viewBox="0 0 24 24" width="24" height="24"><path d="M12 4V1L8 5l4 4V6a6 6 0 016 6 6 6 0 01-6 6 6 6 0 01-6-6H4a8 8 0 008 8 8 8 0 008-8 8 8 0 00-8-8z" fill="currentColor"/></svg>';
    document.body.prepend(indicator);

    let startY = 0, pulling = false;

    function canPull() {
        return window.scrollY === 0 && !modalOverlay.classList.contains('active');
    }

    function resetIndicator() {
        pulling = false;
        indicator.style.transform = '';
        indicator.style.opacity = '';
    }

    document.addEventListener('touchstart', (e) => {
        if (canPull()) { startY = e.touches[0].clientY; pulling = true; }
    }, { passive: true });

    document.addEventListener('touchmove', (e) => {
        if (!pulling) return;
        const dist = e.touches[0].clientY - startY;
        if (dist > 0 && dist <= PULL_THRESHOLD && window.scrollY === 0) {
            const progress = dist / PULL_THRESHOLD;
            indicator.style.transform = `translateX(-50%) translateY(${dist * PULL_DAMPING}px)`;
            indicator.style.opacity = progress;
            indicator.querySelector('.ptr-spinner').style.transform = `rotate(${progress * 360}deg)`;
        }
        if (dist > PULL_THRESHOLD && window.scrollY === 0) {
            pulling = false;
            indicator.classList.add('refreshing');
            haptic('notification', 'success');
            loadCatalog().then(() => {
                indicator.classList.remove('refreshing');
                resetIndicator();
            });
        }
    }, { passive: true });

    document.addEventListener('touchend', () => {
        if (pulling) resetIndicator();
    }, { passive: true });
})();

const footerYear = document.getElementById('footer-year');
if (footerYear) footerYear.textContent = new Date().getFullYear();

// ============================================
// DESKTOP SIDEBAR & SEARCH SYNC
// Desktop has separate filter sidebar + header search bar
// that must sync with mobile toolbar filters/search
// ============================================

const desktopSearchInput = document.getElementById('desktop-search-input');
const sidebarHeat = document.getElementById('sidebar-heat');
const sidebarStock = document.getElementById('sidebar-stock');
const sidebarResetBtn = document.getElementById('sidebar-reset-btn');
const desktopResultsCount = document.getElementById('desktop-results-count');

// Sync desktop search → mobile search + apply filters
if (desktopSearchInput) {
    let desktopSearchTimer = null;
    desktopSearchInput.addEventListener('input', () => {
        searchInput.value = desktopSearchInput.value;
        clearTimeout(desktopSearchTimer);
        desktopSearchTimer = setTimeout(() => applyFilters(), 200);
    });
}

// Sync mobile search → desktop search
if (searchInput && desktopSearchInput) {
    searchInput.addEventListener('input', () => {
        desktopSearchInput.value = searchInput.value;
    });
}

// Desktop sidebar filters — each syncs its state variable + mobile chips
const sidebarCategory = document.getElementById('sidebar-category');

const sidebarFilters = [
    { container: sidebarHeat, dataAttr: 'filter', mobileContainer: filterChipsContainer, mobileSelector: '.toolbar__chip',
      getState: () => activeFilter, setState: (v) => { activeFilter = v; } },
    { container: sidebarStock, dataAttr: 'stock', mobileContainer: stockToggle, mobileSelector: '.toolbar__chip[data-stock]',
      getState: () => stockFilter, setState: (v) => { stockFilter = v; } },
    { container: sidebarCategory, dataAttr: 'category', mobileContainer: categoryChipsContainer, mobileSelector: '.toolbar__chip',
      getState: () => categoryFilter, setState: (v) => { categoryFilter = v; } },
];

sidebarFilters.forEach(({ container, dataAttr, mobileContainer, mobileSelector, setState }) => {
    if (!container) return;
    container.addEventListener('click', (e) => {
        const opt = e.target.closest('.catalog-sidebar__option');
        if (!opt || !opt.dataset[dataAttr]) return;
        syncRadioGroup(container, '.catalog-sidebar__option', dataAttr, opt.dataset[dataAttr]);
        setState(opt.dataset[dataAttr]);
        syncRadioGroup(mobileContainer, mobileSelector, dataAttr, opt.dataset[dataAttr]);
        updateFilterBadge();
        updateSidebarReset();
        applyFilters();
        haptic('impact', 'light');
    });
});

// Sidebar reset button
if (sidebarResetBtn) {
    sidebarResetBtn.addEventListener('click', () => {
        resetFilters();
        syncSidebarFromState();
        haptic('impact', 'light');
    });
}

function syncSidebarFromState() {
    sidebarFilters.forEach(({ container, dataAttr, mobileContainer, mobileSelector, getState }) => {
        syncRadioGroup(container, '.catalog-sidebar__option', dataAttr, getState());
        syncRadioGroup(mobileContainer, mobileSelector, dataAttr, getState());
    });
    if (desktopSearchInput) desktopSearchInput.value = searchInput.value;
    updateSidebarReset();
}

// Show/hide sidebar reset button
function updateSidebarReset() {
    if (!sidebarResetBtn) return;
    sidebarResetBtn.classList.toggle('visible', getActiveFilterCount() > 0);
}

// Update desktop results count
function updateDesktopResults(count) {
    if (!desktopResultsCount) return;
    if (count === undefined) return;
    desktopResultsCount.textContent = `${count} ${plural(count, 'товар', 'товара', 'товаров')}`;
}


// --- Toolbar sticky offset (below header) ---
(function fixToolbarOffset() {
    const header = document.querySelector('.header');
    if (!header || !toolbar) return;
    function update() {
        toolbar.style.top = header.offsetHeight + 'px';
    }
    update();
    window.addEventListener('resize', update);
})();

loadCatalog();
