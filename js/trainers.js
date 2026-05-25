document.addEventListener('DOMContentLoaded', () => {
    const gymFilter = document.getElementById('trainer-filter-gym');
    const classFilter = document.getElementById('trainer-filter-class');
    const clearButton = document.getElementById('trainer-filter-clear');
    const count = document.getElementById('trainer-filter-count');
    const cards = document.querySelectorAll('.trainer-card');

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
