(function () {
    function dismissAlert(el, delay) {
        setTimeout(function () {
            el.style.opacity = '0';
            var removed = false;
            el.addEventListener('transitionend', function () {
                if (!removed) { removed = true; el.remove(); }
            }, { once: true });
            setTimeout(function () {
                if (!removed) { removed = true; el.remove(); }
            }, 700);
        }, delay);
    }

    document.querySelectorAll('.admin-alert:not([hidden]):not(.admin-alert--persistent)').forEach(function (el) {
        dismissAlert(el, 3500);
    });
}());
