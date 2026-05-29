(function () {
    'use strict';

    var editModal   = document.getElementById('editModal');
    if (!editModal) return;

    var editBackdrop = document.getElementById('editModalBackdrop');
    var editClose    = document.getElementById('editModalClose');

    window.openEditModal = function (classId) {
        var cls = classId ? SC.classes[classId] : null;
        document.getElementById('editModalLabel').textContent = cls ? 'Edit Class' : 'New Class';
        document.getElementById('editAction').value      = cls ? 'update' : 'create';
        document.getElementById('editTargetId').value    = cls ? classId : '';
        document.getElementById('editClassType').value   = cls ? cls.class_type_id : '';
        document.getElementById('editGymId').value       = cls ? cls.gym_id : '';
        var trainerIdEl = document.getElementById('editTrainerId');
        if (trainerIdEl) trainerIdEl.value = cls ? (cls.trainer_id || '') : '';
        document.getElementById('editDuration').value    = cls ? cls.duration_min : '';
        document.getElementById('editCapacity').value    = cls ? cls.capacity : '';
        document.getElementById('editDescription').value = cls ? (cls.description || '') : '';
        document.getElementById('editClassPhoto').value  = '';
        document.getElementById('editPhotoFileName').textContent = 'No file chosen';
        var previewWrap = document.getElementById('editPhotoPreviewWrap');
        var previewImg  = document.getElementById('editPhotoPreview');
        if (cls && cls.photo) {
            previewImg.src = cls.photo;
            previewWrap.style.display = 'block';
        } else {
            previewWrap.style.display = 'none';
        }
        if (cls && cls.schedule) {
            var d = new Date(cls.schedule);
            var pad = function (n) { return String(n).padStart(2, '0'); };
            document.getElementById('editSchedule').value =
                d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        } else {
            document.getElementById('editSchedule').value = '';
        }
        editModal.setAttribute('aria-hidden', 'false');
        editModal.classList.add('sc-modal--open');
        document.body.style.overflow = 'hidden';
    };

    var editPhotoInput = document.getElementById('editClassPhoto');
    if (editPhotoInput) {
        editPhotoInput.addEventListener('change', function () {
            var previewWrap = document.getElementById('editPhotoPreviewWrap');
            var previewImg  = document.getElementById('editPhotoPreview');
            var fileNameEl  = document.getElementById('editPhotoFileName');
            if (this.files && this.files[0]) {
                if (fileNameEl) fileNameEl.textContent = this.files[0].name;
                var reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    previewWrap.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    window.closeEditModal = function () {
        editModal.setAttribute('aria-hidden', 'true');
        editModal.classList.remove('sc-modal--open');
        document.body.style.overflow = '';
    };

    if (editClose)    editClose.addEventListener('click', window.closeEditModal);
    if (editBackdrop) editBackdrop.addEventListener('click', window.closeEditModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeEditModal();
    });
}());
