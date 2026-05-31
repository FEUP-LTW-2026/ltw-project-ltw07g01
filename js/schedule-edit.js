(function () {
    'use strict';

    var editModal     = document.getElementById('editModal');
    if (!editModal) return;

    var editBackdrop  = document.getElementById('editModalBackdrop');
    var editClose     = document.getElementById('editModalClose');
    var editDateInput = document.getElementById('editDate');
    var editTimeInput = document.getElementById('editTime');
    var editForm      = document.getElementById('editForm');

    // auto-format dd/mm/yyyy as the user types digits
    if (editDateInput) {
        editDateInput.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').substring(0, 8);
            var v = digits.substring(0, 2);
            if (digits.length > 2) v += '/' + digits.substring(2, 4);
            if (digits.length > 4) v += '/' + digits.substring(4, 8);
            this.value = v;
        });
    }

    // auto-format HH:mm as the user types digits
    if (editTimeInput) {
        editTimeInput.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').substring(0, 4);
            this.value = digits.length > 2
                ? digits.substring(0, 2) + ':' + digits.substring(2)
                : digits;
        });
    }

    // combine dd/mm/yyyy + HH:mm into hidden schedule field before submit
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            var dateVal = editDateInput ? editDateInput.value.trim() : '';
            var timeVal = editTimeInput ? editTimeInput.value.trim() : '';
            var dp = dateVal.split('/');
            var valid = dp.length === 3
                && dp[0].length === 2 && dp[1].length === 2 && dp[2].length === 4
                && /^\d{2}:\d{2}$/.test(timeVal);
            if (valid) {
                // convert dd/mm/yyyy → yyyy-mm-dd for PHP
                document.getElementById('editSchedule').value = dp[2] + '-' + dp[1] + '-' + dp[0] + 'T' + timeVal;
                var prev = editForm.querySelector('.edit-dt-error');
                if (prev) prev.remove();
            } else {
                e.preventDefault();
                var wrap = editDateInput ? editDateInput.closest('.admin-field') : null;
                if (wrap && !wrap.querySelector('.edit-dt-error')) {
                    var err = document.createElement('p');
                    err.className = 'edit-dt-error';
                    err.textContent = 'Enter date as dd/mm/yyyy and time as HH:mm (e.g. 31/05/2026 and 14:30).';
                    wrap.appendChild(err);
                }
            }
        });
    }

    window.openEditModal = function (classId) {
        var cls = classId ? SC.classes[classId] : null;
        // block editing past classes
        if (cls && cls.schedule && new Date(cls.schedule) < new Date()) return;

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
            if (editDateInput) editDateInput.value = pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
            if (editTimeInput) editTimeInput.value = pad(d.getHours()) + ':' + pad(d.getMinutes());
        } else {
            if (editDateInput) editDateInput.value = '';
            if (editTimeInput) editTimeInput.value = '';
        }
        document.getElementById('editSchedule').value = '';
        var prevErr = editForm ? editForm.querySelector('.edit-dt-error') : null;
        if (prevErr) prevErr.remove();
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
