document.addEventListener('DOMContentLoaded', () => {
    const counters = document.querySelectorAll('.counter');
    const statsSection = document.querySelector('.stats');

    let hasAnimated = false;

    function animateCounters() {
        counters.forEach(counter => {
            const target = parseFloat(counter.dataset.target);
            const duration = 1800; 
            const startTime = performance.now();

            function updateCounter(currentTime) {
                const elapsedTime = currentTime - startTime;
                const progress = Math.min(elapsedTime / duration, 1);

                const currentValue = target * progress;

                if (target % 1 !== 0) {
                    counter.textContent = currentValue.toFixed(1);
                } else {
                    counter.textContent = Math.floor(currentValue);
                }

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target % 1 !== 0 ? target.toFixed(1) : target;
                }
            }

            requestAnimationFrame(updateCounter);
        });
    }

    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && !hasAnimated) {
            hasAnimated = true;
            animateCounters();
        }
    }, {
        threshold: 0.5
    });

    observer.observe(statsSection);
});