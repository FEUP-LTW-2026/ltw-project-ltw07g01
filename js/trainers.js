function openCreateForm() {
    var f = document.getElementById('trainerForm');
    f.hidden = false;
    document.getElementById('formTitle').textContent = 'New Trainer';
    f.scrollIntoView({behavior: 'smooth', block: 'start'});
}

document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(function(inp) {
    inp.addEventListener('change', function() {
        var file = this.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        var reader = new FileReader();
        var preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
        reader.onload = function(e) { preview.src = e.target.result; };
        reader.readAsDataURL(file);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    var msgEl = document.getElementById('trainerMsg');
    if (msgEl) {
        setTimeout(function () {
            msgEl.style.transition = 'opacity .4s';
            msgEl.style.opacity = '0';
            setTimeout(function () { msgEl.remove(); }, 420);
        }, 3500);
    }

    const gymFilter = document.getElementById('trainer-filter-gym');
    const classFilter = document.getElementById('trainer-filter-class');
    const clearButton = document.getElementById('trainer-filter-clear');
    const count = document.getElementById('trainer-filter-count');
    const cards = document.querySelectorAll('[data-gyms]');

    function trainerHasValue(card, dataKey, value) {
        if (value === 'all') return true;
        return (card.dataset[dataKey] || '').split('|').includes(value);
    }

    function filterTrainers() {
        const selectedGym = gymFilter.value;
        const selectedClass = classFilter.value;
        const hasFilter = selectedGym !== 'all' || selectedClass !== 'all';
        let visibleTotal = 0;

        cards.forEach(card => {
            const visible =
                trainerHasValue(card, 'gyms', selectedGym) &&
                trainerHasValue(card, 'classes', selectedClass);

            card.style.display = visible ? '' : 'none';
            if (visible) visibleTotal += 1;
        });

        clearButton.hidden = !hasFilter;
        count.textContent = hasFilter
            ? `${visibleTotal} result${visibleTotal === 1 ? '' : 's'}`
            : '';
    }

    gymFilter.addEventListener('change', filterTrainers);
    classFilter.addEventListener('change', filterTrainers);
    clearButton.addEventListener('click', () => {
        gymFilter.value = 'all';
        classFilter.value = 'all';
        filterTrainers();
    });
});
