function openCreateForm() {
    var f = document.getElementById('equipForm');
    f.hidden = false;
    document.getElementById('formTitle').textContent = 'Add Equipment';
    f.scrollIntoView({behavior: 'smooth', block: 'start'});
}

function toggleEquip(id, btn) {
    var fd = new FormData();
    fd.append('_action', 'toggle');
    fd.append('target_id', id);
    fd.append('ajax', '1');
    fetch('/pages/equipment.php', {method: 'POST', body: fd})
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) return;
            var avail = data.is_available;
            var icon = btn.querySelector('i');
            icon.className = 'fa fa-' + (avail ? 'toggle-on' : 'toggle-off');
            var row = btn.closest('tr');
            var badge = row.querySelector('.admin-badge');
            badge.className = 'admin-badge admin-badge--' + (avail ? 'active' : 'inactive');
            badge.textContent = avail ? 'Available' : 'Unavailable';
        });
}

function openEquipModal(card) {
    var name     = card.dataset.name;
    var gym      = card.dataset.gym;
    var body     = card.dataset.body;
    var status   = card.dataset.status;
    var img      = card.dataset.img;
    var muscles  = card.dataset.muscles;
    var diagrams = card.dataset.diagrams;

    document.getElementById('equipModalName').textContent  = name;
    document.getElementById('equipModalGym').textContent   = gym;
    document.getElementById('equipModalBody').textContent  = body;

    var musclesWrap = document.getElementById('equipModalMusclesWrap');
    var diagramsEl  = document.getElementById('equipModalDiagrams');

    diagramsEl.innerHTML = '';
    if (diagrams) {
        diagrams.split(',').forEach(function(src) {
            var imgEl = document.createElement('img');
            imgEl.src = src.trim();
            imgEl.alt = '';
            diagramsEl.appendChild(imgEl);
        });
    }

    if (muscles || diagrams) {
        document.getElementById('equipModalMuscles').textContent = muscles;
        musclesWrap.hidden = false;
    } else {
        musclesWrap.hidden = true;
    }

    var statusEl = document.getElementById('equipModalStatus');
    if (status === 'available') {
        statusEl.textContent  = 'Available';
        statusEl.className    = 'equip-modal-status status available';
    } else {
        statusEl.textContent  = 'Out of service';
        statusEl.className    = 'equip-modal-status status unavailable';
    }

    var imgWrap = document.getElementById('equipModalImgWrap');
    var imgEl   = document.getElementById('equipModalImg');
    if (img) {
        imgEl.src        = img;
        imgEl.alt        = name;
        imgWrap.hidden   = false;
    } else {
        imgWrap.hidden   = true;
    }

    document.getElementById('equipModal').setAttribute('aria-hidden', 'false');
    document.getElementById('equipModal').classList.add('open');
    document.getElementById('equipModalBackdrop').classList.add('open');
}

function closeEquipModal() {
    document.getElementById('equipModal').setAttribute('aria-hidden', 'true');
    document.getElementById('equipModal').classList.remove('open');
    document.getElementById('equipModalBackdrop').classList.remove('open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEquipModal();
});

document.addEventListener('DOMContentLoaded', () => {
    var msgEl = document.getElementById('equipMsg');
    if (msgEl) {
        setTimeout(function () {
            msgEl.style.transition = 'opacity .4s';
            msgEl.style.opacity = '0';
            setTimeout(function () { msgEl.remove(); }, 420);
        }, 3500);
    }

    const locationFilter = document.getElementById('filter-location');
    const bodyFilter = document.getElementById('filter-body');
    const statusFilter = document.getElementById('filter-status');
    const cards = document.querySelectorAll('.equipment-card');
    const clearButton = document.getElementById('equipment-filter-clear');
    const count = document.getElementById('equipment-filter-count');
    const grids = document.querySelectorAll('.equipment-grid[data-loadmore]');

    const STEP = 3;
    const visibleCount = new WeakMap();
    grids.forEach(grid => visibleCount.set(grid, STEP));

    function cardMatchesFilters(card) {
        if (!locationFilter) return true;
        const selectedLocation = locationFilter.value;
        const selectedBody = bodyFilter.value;
        const selectedStatus = statusFilter.value;
        return (selectedLocation === 'all' || card.dataset.location === selectedLocation)
            && (selectedBody === 'all' || card.dataset.body === selectedBody)
            && (selectedStatus === 'all' || card.dataset.status === selectedStatus);
    }

    function applyGridVisibility(grid) {
        const limit = visibleCount.get(grid) || STEP;
        const allCards = grid.querySelectorAll('.equipment-card');
        let matchedSoFar = 0;
        let matchedTotal = 0;

        allCards.forEach(card => {
            const matches = cardMatchesFilters(card);
            if (matches) {
                matchedTotal += 1;
                if (matchedSoFar < limit) {
                    card.style.display = '';
                    matchedSoFar += 1;
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = 'none';
            }
        });

        const wrap = grid.nextElementSibling;
        if (wrap && wrap.classList.contains('equip-loadmore-wrap')) {
            const btn = wrap.querySelector('[data-loadmore-btn]');
            const hasMore = matchedTotal > limit;
            wrap.hidden = !hasMore;
            if (btn) {
                const remaining = matchedTotal - limit;
                btn.innerHTML = 'Show all (' + remaining + ' more) <i class="fa fa-chevron-down"></i>';
            }
        }
        return matchedTotal;
    }

    function refreshAll() {
        let total = 0;
        grids.forEach(grid => { total += applyGridVisibility(grid); });
        if (count && locationFilter) {
            const hasFilter = locationFilter.value !== 'all'
                || bodyFilter.value !== 'all'
                || statusFilter.value !== 'all';
            clearButton.hidden = !hasFilter;
            count.textContent = hasFilter
                ? `${total} result${total === 1 ? '' : 's'}`
                : '';
        }
    }

    function resetAndRefresh() {
        grids.forEach(grid => visibleCount.set(grid, STEP));
        refreshAll();
    }

    document.querySelectorAll('[data-loadmore-btn]').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrap = btn.closest('.equip-loadmore-wrap');
            const grid = wrap.previousElementSibling;
            if (!grid) return;
            visibleCount.set(grid, Infinity);
            applyGridVisibility(grid);
        });
    });

    if (locationFilter) {
        locationFilter.addEventListener('change', resetAndRefresh);
        bodyFilter.addEventListener('change', resetAndRefresh);
        statusFilter.addEventListener('change', resetAndRefresh);
        clearButton.addEventListener('click', () => {
            locationFilter.value = 'all';
            bodyFilter.value = 'all';
            statusFilter.value = 'all';
            resetAndRefresh();
        });
    }

    refreshAll();
});
