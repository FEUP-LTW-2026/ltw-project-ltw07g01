(function () {
    'use strict';

    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var navPopup     = document.getElementById('navPopup');
    var navBackdrop  = document.getElementById('navBackdrop');

    function closeNav() {
        if (!navPopup) return;
        navPopup.classList.remove('open');
        navBackdrop.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = navPopup.classList.contains('open');
            isOpen ? closeNav() : (
                navPopup.classList.add('open'),
                navBackdrop.classList.add('open'),
                document.body.style.overflow = 'hidden'
            );
        });
        navBackdrop.addEventListener('click', closeNav);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNav();
    });
})();
