document.addEventListener('DOMContentLoaded', function () {
    var hwWrap = document.querySelector('.tfp-week__homework');
    if (!hwWrap) return;

    var lessonId = hwWrap.getAttribute('data-lesson-id');
    var isSubmitted = hwWrap.getAttribute('data-submitted') === '1';
    var currentState = hwWrap.getAttribute('data-state') || 'state-1';
    var completedCount = parseInt(hwWrap.getAttribute('data-completed') || '0', 10);
    var activeIndex = 0;

    var panels = {
        'state-1': document.querySelector('.tfp-week__homework-state-1'),
        'state-2': document.querySelector('.tfp-week__homework-state-2'),
        'state-3': document.querySelector('.tfp-week__homework-state-3'),
        'state-review': document.querySelector('.tfp-week__homework-state-review')
    };

    var qContainers = document.querySelectorAll('.tfp-week__homework-question-container');
    var navItems = document.querySelectorAll('.tfp-week__homework-list-item');
    var totalQuestions = parseInt(hwWrap.getAttribute('data-total') || qContainers.length, 10);

    document.querySelectorAll('.tfp-homework-next-step-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (btn.getAttribute('aria-disabled') === 'true') {
                e.preventDefault();
            }
        });
    });

    function isHomeworkComplete() {
        return totalQuestions > 0 && completedCount === totalQuestions;
    }

    function switchState(newState) {
        currentState = newState;
        for (var key in panels) {
            if (panels[key]) {
                panels[key].style.display = (key === newState) ? 'block' : 'none';
            }
        }



        if (newState === 'state-2') {
            showQuestion(activeIndex);
        }
    }

    function showQuestion(index) {
        activeIndex = index;
        qContainers.forEach(function (el) {
            el.style.display = (parseInt(el.getAttribute('data-index'), 10) === index) ? 'block' : 'none';
        });



        // Update active class on sidebar
        navItems.forEach(function (el) {
            if (parseInt(el.getAttribute('data-index'), 10) === index) {
                el.classList.add('is-active');
            } else {
                el.classList.remove('is-active');
            }
        });

        // Show/hide nav buttons for the active question (they are hidden by default via PHP inline style)
        document.querySelectorAll('.tfp-week__homework-nav').forEach(function (nav) {
            if (parseInt(nav.getAttribute('data-index'), 10) === index) {
                nav.style.display = 'flex';
            } else {
                nav.style.display = 'none';
            }
        });
    }

    // Start Button
    var startBtn = document.querySelector('.tfp-homework-start-btn');
    if (startBtn) {
        startBtn.addEventListener('click', function () {
            switchState('state-2');
        });
    }

    // Nav Buttons (Prev/Next)
    document.querySelectorAll('.tfp-homework-prev, .tfp-homework-next').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var targetId = this.getAttribute('data-target');
            var targetIndex = -1;
            qContainers.forEach(function (el, idx) {
                if (el.getAttribute('data-question-id') === targetId) {
                    targetIndex = idx;
                }
            });
            if (targetIndex !== -1) {
                showQuestion(targetIndex);
            }
        });
    });

    // Custom theme popup
    function showPopup(message, onConfirm) {
        var existing = document.getElementById('tfp-submission-popup');
        if (existing) existing.remove();
        var popup = document.createElement('div');
        popup.id = 'tfp-submission-popup';
        popup.className = 'tfp-submission-popup';

        var isConfirm = typeof onConfirm === 'function';
        var title = isConfirm ? 'Submit Homework' : 'Homework Submitted';
        var buttonsHtml = isConfirm 
            ? '<button class="tfp-dash-btn tfp-reded-btn" id="tfp-popup-cancel">Cancel</button><button class="tfp-dash-btn tfp-dash-btn--primary" id="tfp-popup-ok">Submit</button>'
            : '<button class="tfp-dash-btn tfp-dash-btn--primary" id="tfp-popup-ok">Close</button>';

        popup.innerHTML = '<div class="tfp-submission-popup__box"><h3>' + title + '</h3><p>' + message + '</p><div style="display:flex; gap:12px; justify-content:center;">' + buttonsHtml + '</div></div>';
        document.body.appendChild(popup);

        document.getElementById('tfp-popup-ok').addEventListener('click', function () {
            popup.remove();
            if (isConfirm) onConfirm(true);
        });

        if (isConfirm) {
            document.getElementById('tfp-popup-cancel').addEventListener('click', function () {
                popup.remove();
                onConfirm(false);
            });
        }
    }

    // Sidebar Nav — supports edit from review/state-3 and normal nav from state-2/state-1
    document.querySelectorAll('.tfp-week__homework-list-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            var idx = parseInt(this.getAttribute('data-index'), 10);
            if (isSubmitted) {
                showPopup('You cannot edit now because you have submitted your homework.', null);
                return;
            }
            if (currentState === 'state-review' || currentState === 'state-3') {
                // Edit mode: open question for editing
                currentState = 'state-2';
                for (var key in panels) {
                    if (panels[key]) panels[key].style.display = (key === 'state-2') ? 'block' : 'none';
                }
                showQuestion(idx);
            } else if (currentState === 'state-1') {
                switchState('state-2');
                showQuestion(idx);
            } else {
                showQuestion(idx);
            }
        });
    });

    // Finish Button (from last question)
    var finishBtn = document.querySelector('.tfp-homework-finish');
    if (finishBtn) {
        finishBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!isHomeworkComplete()) {
                alert('Please answer all homework questions before continuing.');
                return;
            }
            switchState('state-3');
        });
    }

    // Review Answers Button - refresh answers via AJAX
    var reviewBtn = document.querySelector('.tfp-homework-review-btn');
    if (reviewBtn) {
        reviewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fetch(tfpWeekSettings.ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({ action: 'tfp_week_get_homework_answers', tfp_week_nonce: tfpWeekSettings.nonce, lesson_id: lessonId })
            })
                .then(r => r.json())
                .then(res => {
                    switchState('state-review');
                })
                .catch(() => {
                    switchState('state-review');
                });
        });
    }

    // Submit for Review Button
    var submitBtns = document.querySelectorAll('.tfp-homework-submit-btn');
    submitBtns.forEach(function (submitBtn) {
        submitBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var btn = submitBtn;
            showPopup('Note: After submit you will not be able to edit the homework questions. Submit?', function (ok) {
                if (!ok) return;
                var originalText = btn.textContent;
                btn.textContent = 'Submitting...';
                btn.disabled = true;

                var data = new URLSearchParams();
                data.append('action', 'tfp_week_submit_homework');
                data.append('tfp_week_nonce', tfpWeekSettings.nonce);
                data.append('lesson_id', lessonId);

                fetch(tfpWeekSettings.ajaxUrl, {
                    method: 'POST',
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            isSubmitted = true;
                            hwWrap.setAttribute('data-submitted', '1');
                            var note = document.getElementById('tfp-submission-note');
                            if (!note) {
                                note = document.createElement('div');
                                note.id = 'tfp-submission-note';
                                note.style.cssText = 'background:#fdf6e3; border-left:4px solid #bfa100; padding:12px 16px; margin-bottom:20px; color:#5a4a00; font-size:14px; border-radius:4px;';
                                note.innerHTML = '<strong>Note:</strong> You will not be able to edit the homework questions';
                                document.querySelector('.tfp-week__homework-state-review').prepend(note);
                            }
                            switchState('state-review');
                            // Hide submit controls and enable the next step.
                            document.querySelectorAll('.tfp-homework-submit-btn').forEach(function (b) {
                                b.style.display = 'none';
                            });
                            document.querySelectorAll('.tfp-homework-next-step-btn').forEach(function (b) {
                                b.classList.remove('is-disabled');
                                b.setAttribute('aria-disabled', 'false');
                                b.removeAttribute('tabindex');
                            });
                        } else {
                            alert(res.message || 'Error submitting homework.');
                            btn.textContent = originalText;
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('A network error occurred.');
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
            });
        });
    });

    // Auto-save logic
    var saveTimeout;

    function saveAnswer(container) {
        var qId = container.getAttribute('data-question-id');
        var indicator = container.querySelector('.tfp-homework-saving-indicator');

        var data = new URLSearchParams();
        data.append('action', 'tfp_week_save_homework_answer');
        data.append('tfp_week_nonce', tfpWeekSettings.nonce);
        data.append('lesson_id', lessonId);
        data.append('question_id', qId);

        // Gather inputs
        var radio = container.querySelector('input[type="radio"][name="hw_' + qId + '"]:checked');
        if (radio) data.append('selected_index', radio.value);

        var yn = container.querySelector('input[type="radio"][name="yn_' + qId + '"]:checked');
        if (yn) data.append('yes_no', yn.value);

        var text = container.querySelector('textarea[name="text_' + qId + '"]');
        if (text) data.append('text', text.value);

        if (indicator) {
            indicator.textContent = 'Saving...';
            indicator.style.display = 'inline-block';
        }

        fetch(tfpWeekSettings.ajaxUrl, {
            method: 'POST',
            body: data
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    if (indicator) {
                        indicator.textContent = 'Saved';
                        setTimeout(() => indicator.style.display = 'none', 2000);
                    }

                    if (res.progress) {
                        completedCount = parseInt(res.progress.completed, 10) || 0;
                        hwWrap.setAttribute('data-completed', completedCount);
                    }

                    // Update sidebar title and statuses
                    var progressTitle = document.querySelector('.tfp-week__homework-progress-title');
                    if (progressTitle && res.progress) {
                        progressTitle.textContent = 'Homework Progress — ' + res.progress.completed + ' of ' + res.progress.total + ' Completed';
                    }

                    // Mark current sidebar item as completed (naive client-side check, 
                    // ideally we'd check actual response data, but doing it heuristically here)
                    var navItem = document.querySelector('.tfp-week__homework-list-item[data-question-id="' + qId + '"]');
                    if (navItem) {
                        navItem.classList.add('is-completed');
                        var statusSpan = navItem.querySelector('.tfp-week__homework-list-item-status span');
                        if (statusSpan) statusSpan.textContent = 'Completed';
                        var btn = navItem.querySelector('.tfp-homework-nav-btn');
                        if (btn) {
                            btn.textContent = 'Edit Answer';
                            btn.classList.remove('tfp-dash-btn--primary');
                            btn.classList.add('tfp-reded-btn');
                        }
                    }

                    // If all completed, enable finish/submit? Handled by state transitions.
                } else {
                    if (indicator) indicator.textContent = 'Error';
                }
            })
            .catch(err => {
                console.error(err);
                if (indicator) indicator.textContent = 'Error';
            });
    }

    qContainers.forEach(function (container) {
        var inputs = container.querySelectorAll('input[type="radio"]');
        var textareas = container.querySelectorAll('textarea');

        inputs.forEach(function (input) {
            input.addEventListener('change', function () {
                saveAnswer(container);
            });
        });

        textareas.forEach(function (textarea) {
            textarea.addEventListener('input', function () {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function () {
                    saveAnswer(container);
                }, 1000);
            });
        });
    });

    // Click right-side question container to edit/open it
    qContainers.forEach(function (container) {
        container.style.cursor = 'pointer';
        container.addEventListener('click', function (e) {
            if (isSubmitted) return; // Disable edit after submit
            // Ignore if clicking inside an interactive element (inputs, buttons)
            if (e.target.closest('input, button, textarea, label')) return;
            var idx = parseInt(this.getAttribute('data-index'), 10);
            if (currentState === 'state-review' || currentState === 'state-3') {
                currentState = 'state-2';
                for (var key in panels) {
                    if (panels[key]) panels[key].style.display = (key === 'state-2') ? 'block' : 'none';
                }
                showQuestion(idx);
            } else if (currentState === 'state-1') {
                switchState('state-2');
                showQuestion(idx);
            } else {
                showQuestion(idx);
            }
        });
    });

    // Initialization
    if (currentState === 'state-2') {
        showQuestion(activeIndex);
    }
});
