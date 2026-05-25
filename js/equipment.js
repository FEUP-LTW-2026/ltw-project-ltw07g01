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
