(function () {
    var profileData = JSON.parse(document.getElementById('profile-data').textContent);
    var viewId = profileData.viewId;

    function updateChart(days) {
        var url = 'profile.php?ajax=chart&days=' + days + (viewId ? '&id=' + viewId : '');
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var chart = document.getElementById('profileChart');
                chart.innerHTML = '';
                data.minutesByDay.forEach(function (mins, i) {
                    var h     = mins > 0 ? Math.min(320, Math.max(25, mins * 2)) : 0;
                    var group = document.createElement('div');
                    group.className = 'bar-group';
                    var bar = document.createElement('div');
                    bar.className       = 'bar';
                    bar.style.height    = h + 'px';
                    bar.dataset.time    = data.times[i];
                    var label = document.createElement('span');
                    label.className     = 'day-label';
                    label.textContent   = data.labels[i];
                    group.appendChild(bar);
                    group.appendChild(label);
                    chart.appendChild(group);
                });
                document.getElementById('chartPeriodLabel').textContent = data.periodLabel + ' Total: ';
                document.getElementById('chartPeriodTotal').textContent = data.periodTotal;
                document.getElementById('chartAvg').textContent         = data.avg;
            });
    }

    var rangeEl = document.getElementById('stats-range');
    if (rangeEl) rangeEl.addEventListener('change', function () { updateChart(this.value); });

    window.cancelSubscription = function () {
        if (!confirm('Are you sure you want to cancel your membership? Your gym plan will be deactivated immediately.')) return;
        var btn = document.getElementById('btnCancelSub');
        if (btn) {
            btn.disabled  = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Cancelling…';
        }
        fetch('profile.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'ajax=1&action=cancel_subscription',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-ban"></i> Cancel Membership'; }
                    return;
                }
                if (btn) btn.remove();
                var tagEl = document.querySelector('.member-tag');
                if (tagEl) tagEl.textContent = 'NO MEMBERSHIP';
            })
            .catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-ban"></i> Cancel Membership'; }
            });
    };
}());
