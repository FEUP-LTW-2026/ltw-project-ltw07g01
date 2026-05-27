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
    const locationFilter = document.getElementById('filter-location');
    const bodyFilter = document.getElementById('filter-body');
    const statusFilter = document.getElementById('filter-status');
    const cards = document.querySelectorAll('.equipment-card');
    const clearButton = document.getElementById('equipment-filter-clear');
    const count = document.getElementById('equipment-filter-count');

    function filterEquipment() {
        const selectedLocation = locationFilter.value;
        const selectedBody = bodyFilter.value;
        const selectedStatus = statusFilter.value;
        const hasFilter =
            selectedLocation !== 'all' || selectedBody !== 'all' || selectedStatus !== 'all';
        let visibleTotal = 0;

        cards.forEach(card => {
            const matchesLocation =
                selectedLocation === 'all' || card.dataset.location === selectedLocation;

            const matchesBody =
                selectedBody === 'all' || card.dataset.body === selectedBody;

            const matchesStatus =
                selectedStatus === 'all' || card.dataset.status === selectedStatus;

            const visible = matchesLocation && matchesBody && matchesStatus;
            card.style.display = visible ? 'block' : 'none';
            if (visible) visibleTotal += 1;
        });

        clearButton.hidden = !hasFilter;
        count.textContent = hasFilter
            ? `${visibleTotal} result${visibleTotal === 1 ? '' : 's'}`
            : '';
    }

    locationFilter.addEventListener('change', filterEquipment);
    bodyFilter.addEventListener('change', filterEquipment);
    statusFilter.addEventListener('change', filterEquipment);
    clearButton.addEventListener('click', () => {
        locationFilter.value = 'all';
        bodyFilter.value = 'all';
        statusFilter.value = 'all';
        filterEquipment();
    });
});
