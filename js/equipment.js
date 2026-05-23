document.addEventListener('DOMContentLoaded', () => {
    const locationFilter = document.getElementById('filter-location');
    const bodyFilter = document.getElementById('filter-body');
    const statusFilter = document.getElementById('filter-status');
    const cards = document.querySelectorAll('.equipment-card');

    function filterEquipment() {
        const selectedLocation = locationFilter.value;
        const selectedBody = bodyFilter.value;
        const selectedStatus = statusFilter.value;

        cards.forEach(card => {
            const matchesLocation =
                selectedLocation === 'all' || card.dataset.location === selectedLocation;

            const matchesBody =
                selectedBody === 'all' || card.dataset.body === selectedBody;

            const matchesStatus =
                selectedStatus === 'all' || card.dataset.status === selectedStatus;

            card.style.display =
                matchesLocation && matchesBody && matchesStatus ? 'block' : 'none';
        });
    }

    locationFilter.addEventListener('change', filterEquipment);
    bodyFilter.addEventListener('change', filterEquipment);
    statusFilter.addEventListener('change', filterEquipment);
});