(function () {
    function dismissAlert(el, delay) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.addEventListener('transitionend', function () {
                el.remove();
            }, { once: true });
        }, delay);
    }

    document.querySelectorAll('.admin-alert:not([hidden])').forEach(function (el) {
        dismissAlert(el, 3500);
    });
}());
