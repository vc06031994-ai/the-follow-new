/**
 * Week player → Quiz tab.
 *
 * Matches the Figma quiz flow:
 *   start → question (one at a time, Previous / Next / Submit Answers)
 *         → result (text summary + pass/fail badge)
 *         → review (one answer at a time: Your Answer / Correct Answer / Explanation)
 *
 * Answers auto-save silently via AJAX; grading happens server-side on submit.
 * Submit and Retake are direct actions (no confirmation popup, matching Figma).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpWeekSettings === 'undefined') return;

        var quizWrap = document.querySelector('.tfp-week__quiz');
        if (!quizWrap) return;

        var lessonId = quizWrap.getAttribute('data-lesson-id');
        var currentState = quizWrap.getAttribute('data-state') || 'state-start';
        var totalQuestions = parseInt(quizWrap.getAttribute('data-total') || '0', 10);
        var hasResult = quizWrap.getAttribute('data-has-result') === '1';

        var panels = {
            'state-start': quizWrap.querySelector('.tfp-quiz-state-start'),
            'state-question': quizWrap.querySelector('.tfp-quiz-state-question'),
            'state-result': quizWrap.querySelector('.tfp-quiz-state-result'),
            'state-review': quizWrap.querySelector('.tfp-quiz-state-review')
        };

        var qContainers = quizWrap.querySelectorAll('.tfp-quiz-question');
        var reviewItems = quizWrap.querySelectorAll('.tfp-quiz-review-item');
        var activeQuestionIndex = 0;
        var activeReviewIndex = 0;

        function switchState(newState) {
            currentState = newState;
            for (var key in panels) {
                if (panels[key]) panels[key].hidden = (key !== newState);
            }
        }

        function showQuestion(index) {
            activeQuestionIndex = index;
            qContainers.forEach(function (el) {
                el.hidden = (parseInt(el.getAttribute('data-index'), 10) !== index);
            });
        }

        function showReview(index) {
            activeReviewIndex = index;
            reviewItems.forEach(function (el) {
                el.hidden = (parseInt(el.getAttribute('data-index'), 10) !== index);
            });
        }

        function post(action, data, onDone) {
            var body = new URLSearchParams();
            body.append('action', action);
            body.append('tfp_week_nonce', tfpWeekSettings.nonce);
            body.append('lesson_id', lessonId);
            for (var k in data) {
                body.append(k, data[k]);
            }

            fetch(tfpWeekSettings.ajaxUrl, {
                method: 'POST',
                body: body
            })
                .then(function (r) { return r.json(); })
                .then(onDone)
                .catch(function (err) {
                    console.error(err);
                    alert(tfpWeekSettings.networkError || 'A network error occurred.');
                });
        }

        // --- Start Quiz ----------------------------------------------------
        var startBtn = quizWrap.querySelector('.tfp-quiz-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', function () {
                switchState('state-question');
            });
        }

        // --- Question navigation -------------------------------------------
        var prevBtns = quizWrap.querySelectorAll('.tfp-quiz-prev');
        var nextBtns = quizWrap.querySelectorAll('.tfp-quiz-next');

        prevBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { showQuestion(activeQuestionIndex - 1); });
        });

        nextBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { showQuestion(activeQuestionIndex + 1); });
        });

        // --- Auto-save on answer change (silent, no indicator) -------------
        qContainers.forEach(function (container) {
            var qId = container.getAttribute('data-question-id');
            container.querySelectorAll('input[type="radio"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (hasResult) return;
                    post('tfp_week_save_quiz_answer', { question_id: qId, selected_index: input.value }, function () {
                        // Silently persisted — resume-safe.
                    });
                });
            });
        });

        // --- Submit Answers ------------------------------------------------
        var submitBtn = quizWrap.querySelector('.tfp-quiz-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var original = submitBtn.textContent;
                submitBtn.textContent = tfpWeekSettings.submittingText || 'Submitting...';
                submitBtn.disabled = true;

                post('tfp_week_submit_quiz', {}, function (res) {
                    if (res.success) {
                        window.location.reload(); // server-rendered result appears
                    } else {
                        alert(res.message || tfpWeekSettings.submitError || 'Error submitting quiz.');
                        submitBtn.textContent = original;
                        submitBtn.disabled = false;
                    }
                });
            });
        }

        // --- Result → Review Answers ---------------------------------------
        var reviewBtn = quizWrap.querySelector('.tfp-quiz-review-answers-btn');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function () {
                showReview(0);
                switchState('state-review');
            });
        }

        // --- Review navigation ---------------------------------------------
        quizWrap.querySelectorAll('.tfp-quiz-review-prev').forEach(function (btn) {
            btn.addEventListener('click', function () { showReview(activeReviewIndex - 1); });
        });

        quizWrap.querySelectorAll('.tfp-quiz-review-next').forEach(function (btn) {
            btn.addEventListener('click', function () { showReview(activeReviewIndex + 1); });
        });

        // --- Retake Quiz (direct) ------------------------------------------
        quizWrap.querySelectorAll('.tfp-quiz-retake-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var original = btn.textContent;
                btn.textContent = tfpWeekSettings.submittingText || 'Submitting...';
                btn.disabled = true;

                post('tfp_week_reset_quiz', {}, function (res) {
                    if (res.success) {
                        window.location.reload(); // back to the Start screen
                    } else {
                        alert(res.message || tfpWeekSettings.submitError || 'Error.');
                        btn.textContent = original;
                        btn.disabled = false;
                    }
                });
            });
        });

        // Initialise the active question / review view on load.
        if (currentState === 'state-question' && totalQuestions > 0) {
            showQuestion(0);
        }
        if (currentState === 'state-review') {
            showReview(0);
        }
    });
})();
