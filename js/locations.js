(function () {
    window.openCreateForm = function () {
        var f = document.getElementById('locForm');
        f.hidden = false;
        document.getElementById('formTitle').textContent = 'Add Location';
        document.getElementById('locPhotoPreview').src = '../images/location-antas.png';
        f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

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
}());
