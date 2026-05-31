const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const main      = document.getElementById('trainersMain');
const isAdmin   = main?.dataset.isAdmin === '1';
const isLogged  = main?.dataset.isLogged === '1';

let allTrainers = [];
let classTypes  = [];
let gymList     = [];

// ── Utilities ─────────────────────────────────────────────────────────────────

function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function tagList(csv, fallback) {
    const items = csv ? csv.split(',').map(s => s.trim()).filter(Boolean) : [];
    return items.length
        ? items.map(s => `<span>${esc(s)}</span>`).join('')
        : `<span>${fallback}</span>`;
}

// ── Render cards ──────────────────────────────────────────────────────────────

function trainerCardHTML(t) {
    const photo  = t.profile_photo || '../images/profile_pic.webp';
    const name   = esc((t.first_name ?? '') + ' ' + (t.last_name ?? ''));
    const gymsData   = (t.gyms        ? t.gyms.split(',').map(s=>s.trim())        : []).join('|');
    const classesData= (t.class_types ? t.class_types.split(',').map(s=>s.trim()) : []).join('|');
    const dataAttrs  = `data-gyms="${esc(gymsData)}" data-classes="${esc(classesData)}"`;

    const inner = `
        <img class="trainer-photo" src="${esc(photo)}" alt="${name}">
        <div class="trainer-info">
            <div class="trainer-name-row">
                <h2>${name}</h2>
                <p class="trainer-username">@${esc(t.username ?? '')}</p>
            </div>
            ${t.bio ? `<p class="trainer-bio">${esc(t.bio)}</p>` : ''}
            <section class="trainer-section">
                <h3>Gyms</h3>
                <div class="trainer-tags">${tagList(t.gyms, 'No gyms assigned')}</div>
            </section>
            <section class="trainer-section">
                <h3>Classes</h3>
                <div class="trainer-tags">${tagList(t.class_types, 'No classes assigned')}</div>
            </section>
        </div>`;

    if (isAdmin) {
        return `
            <div class="trainer-card-admin" ${dataAttrs}>
                <a class="trainer-card" href="/pages/trainer-profile.php?id=${t.id}">${inner}</a>
                <div class="admin-trainer-actions">
                    <button class="btn-admin-ghost btn-admin-ghost--sm" data-edit="${t.id}">
                        <i class="fa fa-pen"></i> Edit
                    </button>
                    <button class="btn-admin-sm btn-admin-sm--danger" data-delete="${t.id}" data-name="${esc(t.first_name ?? '')}">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>`;
    }

    const tag  = isLogged ? 'a' : 'article';
    const href = isLogged ? `href="/pages/trainer-profile.php?id=${t.id}"` : '';
    return `<${tag} class="trainer-card" ${href} ${dataAttrs}>${inner}</${tag}>`;
}

function renderTrainers(trainers) {
    const grid = document.getElementById('trainersGrid');
    if (!grid) return;
    grid.innerHTML = trainers.length
        ? trainers.map(trainerCardHTML).join('')
        : '<p>No trainers found.</p>';

    const countEl = document.getElementById('trainerCount');
    if (countEl) countEl.textContent = `${trainers.length} trainer${trainers.length !== 1 ? 's' : ''} registered`;

    attachActionListeners();
}

// ── Filters ───────────────────────────────────────────────────────────────────

function populateFilters(trainers) {
    const gymSel   = document.getElementById('trainer-filter-gym');
    const classSel = document.getElementById('trainer-filter-class');
    const gymSet   = new Set();
    const classSet = new Set();

    trainers.forEach(t => {
        (t.gyms        ? t.gyms.split(',')        : []).forEach(g => gymSet.add(g.trim()));
        (t.class_types ? t.class_types.split(',') : []).forEach(c => classSet.add(c.trim()));
    });

    [...gymSet].sort().forEach(g =>
        gymSel.insertAdjacentHTML('beforeend', `<option value="${esc(g)}">${esc(g)}</option>`)
    );
    [...classSet].sort().forEach(c =>
        classSel.insertAdjacentHTML('beforeend', `<option value="${esc(c)}">${esc(c)}</option>`)
    );

    gymSel.addEventListener('change', applyFilters);
    classSel.addEventListener('change', applyFilters);
    document.getElementById('trainerSearch')?.addEventListener('input', applyFilters);
    document.getElementById('trainer-filter-clear')?.addEventListener('click', () => {
        gymSel.value = 'all';
        classSel.value = 'all';
        const searchEl = document.getElementById('trainerSearch');
        if (searchEl) searchEl.value = '';
        applyFilters();
    });
}

function applyFilters() {
    const gymVal   = document.getElementById('trainer-filter-gym')?.value   ?? 'all';
    const classVal = document.getElementById('trainer-filter-class')?.value ?? 'all';
    const q        = (document.getElementById('trainerSearch')?.value ?? '').toLowerCase().trim();
    const hasFilter = gymVal !== 'all' || classVal !== 'all' || q !== '';
    let visible = 0;

    document.querySelectorAll('[data-gyms]').forEach(card => {
        const ok = (gymVal   === 'all' || (card.dataset.gyms    || '').split('|').includes(gymVal)) &&
                   (classVal === 'all' || (card.dataset.classes  || '').split('|').includes(classVal)) &&
                   (!q || card.textContent.toLowerCase().includes(q));
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    const count = document.getElementById('trainer-filter-count');
    const clear = document.getElementById('trainer-filter-clear');
    if (count) count.textContent = hasFilter ? `${visible} result${visible === 1 ? '' : 's'}` : '';
    if (clear) clear.hidden = !hasFilter;
}

// ── Admin form ────────────────────────────────────────────────────────────────

function populateCheckboxes(specIds = [], gymIds = []) {
    const specEl = document.getElementById('specializationsCheckboxes');
    const gymEl  = document.getElementById('gymsCheckboxes');

    if (specEl) {
        specEl.innerHTML = classTypes.map(ct => `
            <label class="admin-check-label">
                <input type="checkbox" name="specializations[]" value="${ct.id}"
                       ${specIds.map(String).includes(String(ct.id)) ? 'checked' : ''}>
                ${esc(ct.name)}
            </label>`).join('');
    }

    if (gymEl) {
        gymEl.innerHTML = gymList.map(g => `
            <label class="admin-check-label">
                <input type="checkbox" name="gyms[]" value="${g.id}"
                       ${gymIds.map(String).includes(String(g.id)) ? 'checked' : ''}>
                ${esc(g.city + ' — ' + g.name)}
            </label>`).join('');
    }
}

function openCreateForm() {
    const formCard = document.getElementById('trainerForm');
    if (!formCard) return;

    formCard.hidden = false;
    document.getElementById('formTitle').textContent = 'New Trainer';

    const f = formCard.querySelector('form');
    f.reset();
    f.action = '/api/trainers.php';
    f.querySelector('[name="_method"]')?.remove();

    document.getElementById('usernameField')?.removeAttribute('hidden');
    document.getElementById('passwordField')?.removeAttribute('hidden');
    document.getElementById('promoteSection')?.setAttribute('hidden', '');
    document.getElementById('trainerPhotoPreview').src = '../images/profile_pic.webp';
    document.getElementById('formSubmitBtn').innerHTML = '<i class="fa fa-plus"></i> Create';
    document.getElementById('trainerFormError').hidden = true;

    populateCheckboxes();
    formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeForm() {
    document.getElementById('trainerForm').hidden = true;
}

async function editTrainer(id) {
    let res, t;
    try {
        res = await fetch('/api/trainers.php?id=' + id);
        t   = await res.json();
    } catch {
        alert('Network error.');
        return;
    }
    if (!res.ok) { alert(t.error || 'Trainer not found.'); return; }

    const formCard = document.getElementById('trainerForm');
    formCard.hidden = false;
    document.getElementById('formTitle').textContent = 'Edit Trainer';

    const f = formCard.querySelector('form');
    f.action = '/api/trainers.php?id=' + id;

    let methodInput = f.querySelector('[name="_method"]');
    if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        f.prepend(methodInput);
    }
    methodInput.value = 'PUT';

    f.querySelector('[name="first_name"]').value      = t.first_name      ?? '';
    f.querySelector('[name="last_name"]').value       = t.last_name       ?? '';
    f.querySelector('[name="email"]').value           = t.email           ?? '';
    f.querySelector('[name="bio"]').value             = t.bio             ?? '';
    f.querySelector('[name="certifications"]').value  = t.certifications  ?? '';

    document.getElementById('trainerPhotoPreview').src = t.profile_photo || '../images/profile_pic.webp';
    document.getElementById('usernameField')?.setAttribute('hidden', '');
    document.getElementById('passwordField')?.setAttribute('hidden', '');
    document.getElementById('formSubmitBtn').innerHTML = '<i class="fa fa-save"></i> Save';
    document.getElementById('trainerFormError').hidden = true;

    const promoteSection = document.getElementById('promoteSection');
    if (promoteSection) {
        promoteSection.removeAttribute('hidden');
        promoteSection.querySelector('[name="user_id"]').value = id;
    }

    populateCheckboxes(t.specialization_ids ?? [], t.gym_ids ?? []);
    formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Action listeners ──────────────────────────────────────────────────────────

function attachActionListeners() {
    document.querySelectorAll('[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => editTrainer(btn.dataset.edit));
    });

    document.querySelectorAll('[data-delete]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id   = btn.dataset.delete;
            const name = btn.dataset.name;
            if (!confirm(`Remove ${name}? This cannot be undone.`)) return;

            const fd = new FormData();
            fd.append('_method', 'DELETE');
            fd.append('csrf_token', csrfToken);

            let res;
            try {
                res = await fetch('/api/trainers.php?id=' + id, { method: 'POST', body: fd });
            } catch {
                alert('Network error.');
                return;
            }

            if (res.status === 204 || res.ok) {
                btn.closest('.trainer-card-admin')?.remove();
                allTrainers = allTrainers.filter(t => String(t.id) !== String(id));
                const countEl = document.getElementById('trainerCount');
                if (countEl) countEl.textContent = `${allTrainers.length} trainer${allTrainers.length !== 1 ? 's' : ''} registered`;
            } else {
                const data = await res.json();
                alert(data.error || 'Error removing trainer.');
            }
        });
    });
}

// ── Form submit (create / edit) ───────────────────────────────────────────────

const trainerForm = document.querySelector('#trainerForm form');
if (trainerForm) {
    trainerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const fd     = new FormData(this);
        const method = fd.get('_method') || 'POST';
        if (method === 'POST') fd.delete('_method');

        let res;
        try {
            res = await fetch(this.action, { method: 'POST', body: fd });
        } catch {
            showFormError('Network error. Please try again.');
            return;
        }

        const data = await res.json();
        if (res.ok) {
            window.location.href = '/pages/trainers.php?msg=' + encodeURIComponent(data.msg);
        } else {
            showFormError(data.error || 'An error occurred.');
        }
    });
}

function showFormError(msg) {
    const el = document.getElementById('trainerFormError');
    if (!el) return;
    el.innerHTML = `<i class="fa fa-triangle-exclamation"></i> ${msg}`;
    el.hidden = false;
}

// ── Promote form ──────────────────────────────────────────────────────────────

const promoteForm = document.querySelector('#promoteSection form');
if (promoteForm) {
    promoteForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        let res;
        try {
            res = await fetch('/api/admins.php', { method: 'POST', body: fd });
        } catch {
            alert('Network error.');
            return;
        }
        const data = await res.json();
        if (res.ok) {
            window.location.href = '/pages/trainers.php?msg=' + encodeURIComponent(data.msg);
        } else {
            alert(data.error || 'An error occurred.');
        }
    });
}

// ── Photo preview ─────────────────────────────────────────────────────────────

document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(inp => {
    inp.addEventListener('change', function() {
        const file = this.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        const preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
        reader.onload = e => { preview.src = e.target.result; };
        reader.readAsDataURL(file);
    });
});

// ── Success message auto-dismiss ──────────────────────────────────────────────

const msgEl = document.getElementById('trainerMsg');
if (msgEl) {
    setTimeout(() => {
        msgEl.style.transition = 'opacity .4s';
        msgEl.style.opacity = '0';
        setTimeout(() => msgEl.remove(), 420);
    }, 3500);
}

// ── Init ──────────────────────────────────────────────────────────────────────

async function init() {
    let res, data;
    try {
        res  = await fetch('/api/trainers.php?include=meta');
        data = await res.json();
    } catch {
        document.getElementById('trainersGrid').innerHTML = '<p>Failed to load trainers.</p>';
        return;
    }

    allTrainers = data.trainers ?? [];
    classTypes  = data.classTypes ?? [];
    gymList     = data.gymList ?? [];

    renderTrainers(allTrainers);
    populateFilters(allTrainers);
}

init();
