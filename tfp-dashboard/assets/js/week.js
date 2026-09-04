(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpWeekSettings === 'undefined') return;

        var video = document.querySelector('[data-tfp-week-video]');
        var markedComplete = false;

        function markVideoComplete() {
            if (markedComplete) return;
            markedComplete = true;

            var body = new URLSearchParams({
                action: 'tfp_week_mark_step_complete',
                tfp_week_nonce: tfpWeekSettings.nonce,
                lesson_id: video.dataset.lessonId,
                step: 'video',
            });

            fetch(tfpWeekSettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (!res.success) {
                        markedComplete = false;
                        return;
                    }
                    // Simplest reliable way to reflect the newly-unlocked
                    // Reading tab and updated status badge.
                    window.location.reload();
                })
                .catch(function () {
                    markedComplete = false;
                });
        }

        // Primary signal: video reached the end.
        if (video) {
            if (video.dataset.alreadyComplete === '1') {
                video = null;
            } else {
                video.addEventListener('ended', markVideoComplete);

                // Fallback: some short/looping videos may not fire a clean
                // "ended" event — treat 95%+ watched as complete too.
                video.addEventListener('timeupdate', function () {
                    if (video.duration && video.currentTime / video.duration >= 0.95) {
                        markVideoComplete();
                    }
                });
            }
        }

        // --- Reading Tab Logic ---
        var readingLayout = document.querySelector('.tfp-reading-layout');
        if (readingLayout) {
            var lessonId = readingLayout.dataset.lessonId;
            var sidebarItems = readingLayout.querySelectorAll('.tfp-reading-item');
            var contentPanels = readingLayout.querySelectorAll('.tfp-reading-content-panel:not(.tfp-reading-all-done)');
            var allDonePanel = readingLayout.querySelector('.tfp-reading-all-done');

            function activateReading(id) {
                sidebarItems.forEach(function (item) {
                    item.classList.toggle('is-active', item.dataset.readingId === id);
                });
                contentPanels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.dataset.contentId === id);
                });
                if (allDonePanel) {
                    allDonePanel.classList.remove('is-active');
                }
            }

            // Sidebar clicks
            sidebarItems.forEach(function (item) {
                item.querySelector('.tfp-reading-btn').addEventListener('click', function (e) {
                    e.preventDefault();
                    activateReading(item.dataset.readingId);
                });
            });

            // Start first reading button (default panel)
            var startFirstBtn = readingLayout.querySelector('.tfp-reading-start-first');
            if (startFirstBtn) {
                startFirstBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var firstId = this.dataset.firstReading;
                    if (firstId) activateReading(firstId);
                });
            }

            // Previous button clicks
            readingLayout.querySelectorAll('.tfp-reading-prev').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    activateReading(this.dataset.target);
                });
            });

            // Continue/Mark Complete clicks
            readingLayout.querySelectorAll('.tfp-reading-mark-complete').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var button = this;
                    var readingId = button.dataset.readingId;
                    var isLast = button.dataset.isLast === '1';
                    var nextId = button.dataset.next;

                    if (button.classList.contains('is-loading')) return;
                    button.classList.add('is-loading');
                    button.style.opacity = '0.7';

                    var body = new URLSearchParams({
                        action: 'tfp_week_mark_reading_complete',
                        tfp_week_nonce: tfpWeekSettings.nonce,
                        lesson_id: lessonId,
                        reading_id: readingId,
                        is_last: isLast ? '1' : '0'
                    });

                    fetch(tfpWeekSettings.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (res) {
                            if (res.success) {
                                // Update sidebar status
                                var sidebarItem = readingLayout.querySelector('.tfp-reading-item[data-reading-id="' + readingId + '"]');
                                if (sidebarItem) {
                                    sidebarItem.classList.add('is-completed');
                                    sidebarItem.querySelector('.tfp-reading-item-status span').textContent = 'Completed';
                                    var sidebarBtn = sidebarItem.querySelector('.tfp-reading-btn');
                                    sidebarBtn.textContent = 'Review Reading';
                                    sidebarBtn.className = 'tfp-dash-btn tfp-dash-btn--sm tfp-reded-btn tfp-reading-btn';
                                }

                                // Update progress title
                                var titleEl = readingLayout.querySelector('.tfp-reading-progress-title');
                                if (titleEl && res.data && res.data.completed_count) {
                                    titleEl.textContent = 'Reading Progress — ' + res.data.completed_count + ' of ' + res.data.total + ' Completed';
                                }

                                if (isLast) {
                                    // Show all done panel
                                    contentPanels.forEach(function (p) { p.classList.remove('is-active'); });
                                    sidebarItems.forEach(function (p) { p.classList.remove('is-active'); });
                                    if (allDonePanel) allDonePanel.classList.add('is-active');

                                    // Unlock the homework tab globally (requires reload to update tab bar UI)
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 1500);
                                } else if (nextId) {
                                    activateReading(nextId);
                                }
                            }
                        })
                        .finally(function () {
                            button.classList.remove('is-loading');
                            button.style.opacity = '1';
                        });
                });
            });
        }
    });
})();
