(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    window.openCreateForm = function () {
        const f = document.getElementById('locForm');
        f.hidden = false;
        document.getElementById('formTitle').textContent = 'Add Location';
        document.getElementById('locPhotoPreview').src = '../images/location-antas.png';
        f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.closeLocForm = function () {
        document.getElementById('locForm').hidden = true;
    };

    // Photo preview
    document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var file = this.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            var reader = new FileReader();
            var preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
            reader.onload = function (e) { preview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    });

    // Create / Edit form
    const locForm = document.querySelector('#locForm form');
    if (locForm) {
        locForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd     = new FormData(this);
            const method = fd.get('_method') || 'POST';
            fd.delete('_method');
            let res;
            try {
                res = await fetch(this.action, { method, body: fd });
            } catch {
                alert('Network error. Please try again.');
                return;
            }
            const data = await res.json();
            if (res.ok) {
                window.location.href = '/pages/locations.php?msg=' + encodeURIComponent(data.msg);
            } else {
                alert(data.error || 'An error occurred.');
            }
        });
    }

    // Delete forms
    document.querySelectorAll('.form-inline').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id    = this.querySelector('[name="target_id"]').value;
            const token = this.querySelector('[name="csrf_token"]').value;
            const fd    = new FormData();
            fd.append('_method', 'DELETE');
            fd.append('csrf_token', token);
            let res;
            try {
                res = await fetch('/api/locations.php?id=' + id, { method: 'POST', body: fd });
            } catch {
                alert('Network error.');
                return;
            }
            if (res.status === 204 || res.ok) {
                this.closest('.loc-admin-card')?.remove();
            } else {
                const data = await res.json();
                alert(data.error || 'Error removing location.');
            }
        });
    });
}());
