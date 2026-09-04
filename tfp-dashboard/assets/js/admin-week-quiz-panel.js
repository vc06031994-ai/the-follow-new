/**
 * TFP Week Quiz — Gutenberg sidebar panel for LearnDash lessons.
 *
 * Lets the admin author the auto-graded quiz for a week: a list of
 * multiple-choice questions (each with a REQUIRED correct answer) plus a pass
 * percentage. Stored as `tfp_week_quiz_questions` (JSON) and
 * `tfp_week_quiz_pass_percentage` on the sfwd-lessons post, and consumed by
 * includes/week/quiz-helpers.php + the Week player's Quiz tab.
 *
 * Reuses the homework panel's CSS classes (tfp-hw-*) so no separate admin
 * stylesheet is required. Self-contained: it does not touch the homework
 * panel (admin-week-meta-panel.js).
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.editPost) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var withSelect = wp.data.withSelect;
    var withDispatch = wp.data.withDispatch;
    var compose = wp.compose.compose;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;

    var QUIZ_KEY = 'tfp_week_quiz_questions';
    var PASS_KEY = 'tfp_week_quiz_pass_percentage';

    /* ------------------------------------------------------------------ *
     * Pure helpers (quiz is multiple-choice only).
     * ------------------------------------------------------------------ */

    function toSavedQuestion(q) {
        var prompt = (typeof q.prompt === 'string') ? q.prompt.trim() : '';
        var out = {
            id: (typeof q.id === 'string' && q.id.trim()) ? q.id.trim() : '',
            type: 'multiple_choice',
            prompt: prompt,
            explanation: (typeof q.explanation === 'string') ? q.explanation.trim() : ''
        };

        var rawOptions = (Array.isArray(q.options) ? q.options : [])
            .map(function (o) { return (typeof o === 'string') ? o.trim() : ''; });
        var options = rawOptions.filter(function (o) { return o !== ''; });
        out.options = options;

        // correct_index is written against the ORIGINAL rows — remap to the
        // index within the filtered options actually saved.
        if (typeof q.correct_index === 'number' && q.correct_index >= 0 &&
                rawOptions[q.correct_index] && rawOptions[q.correct_index] !== '') {
            var kept = 0;
            for (var i = 0; i < q.correct_index; i++) {
                if (rawOptions[i] && rawOptions[i] !== '') kept++;
            }
            if (options[kept]) {
                out.correct_index = kept;
            }
        }

        return out;
    }

    function serializeQuestions(list) {
        if (!Array.isArray(list) || !list.length) return '';
        return JSON.stringify(list.map(toSavedQuestion));
    }

    function parseQuestions(json) {
        if (!json) return [];
        var decoded;
        try {
            decoded = JSON.parse(json);
        } catch (e) {
            return [];
        }
        if (!Array.isArray(decoded)) return [];

        var out = [];
        for (var i = 0; i < decoded.length; i++) {
            var q = decoded[i];
            if (!q || typeof q !== 'object') continue;

            var id = (typeof q.id === 'string' && q.id.trim()) ? q.id.trim() : ('q' + (i + 1));
            var options = Array.isArray(q.options) ? q.options.map(String) : [];
            while (options.length < 2) options.push('');

            var normalized = {
                id: id,
                type: 'multiple_choice',
                prompt: (typeof q.prompt === 'string') ? q.prompt : '',
                options: options,
                correct_index: (typeof q.correct_index === 'number' && q.correct_index >= 0 && q.correct_index < options.length)
                    ? q.correct_index
                    : null,
                explanation: (typeof q.explanation === 'string') ? q.explanation : ''
            };

            // Re-key duplicates so DOM ids stay valid.
            var taken = {};
            out.forEach(function (x) { taken[x.id] = 1; });
            if (taken[normalized.id]) {
                var n = 1;
                while (taken['q' + n]) n++;
                normalized.id = 'q' + n;
            }
            out.push(normalized);
        }
        return out;
    }

    function makeId(list) {
        var used = {};
        (list || []).forEach(function (q) { used[q.id] = 1; });
        var n = 1;
        while (used['q' + n]) n++;
        return 'q' + n;
    }

    function buildMeta(meta, key, value) {
        var updated = {};
        for (var k in meta) {
            if (Object.prototype.hasOwnProperty.call(meta, k)) updated[k] = meta[k];
        }
        updated[key] = value;
        return updated;
    }

    function validateDraft(d) {
        if (!d.prompt || !d.prompt.trim()) {
            return __('Please write the question first.', 'tfp-dashboard');
        }
        var filled = 0;
        (d.options || []).forEach(function (o) { if (o && o.trim()) filled++; });
        if (filled < 2) {
            return __('A quiz question needs at least 2 answer options.', 'tfp-dashboard');
        }
        if (typeof d.correct_index !== 'number') {
            return __('Please mark the correct answer for this question.', 'tfp-dashboard');
        }
        return '';
    }

    function trimPreview(text) {
        var s = (typeof text === 'string') ? text : '';
        if (!s.trim()) return __('(No question yet)', 'tfp-dashboard');
        return s.length > 42 ? s.slice(0, 42) + '…' : s;
    }

    /* ------------------------------------------------------------------ *
     * Panel component
     * ------------------------------------------------------------------ */

    var TfpQuizPanel = compose(
        withSelect(function (select) {
            return {
                meta: select('core/editor').getEditedPostAttribute('meta') || {},
            };
        }),
        withDispatch(function (dispatch) {
            return {
                setMeta: function (meta) {
                    dispatch('core/editor').editPost({ meta: meta });
                },
            };
        })
    )(function (props) {
        var meta = props.meta || {};

        var questionsState = useState(function () { return parseQuestions(meta[QUIZ_KEY]); });
        var questions = questionsState[0];
        var setQuestions = questionsState[1];

        var editingState = useState(null);
        var editing = editingState[0];
        var setEditing = editingState[1];

        var pendingDeleteState = useState(null);
        var pendingDelete = pendingDeleteState[0];
        var setPendingDelete = pendingDeleteState[1];

        useEffect(function () {
            var stored = meta[QUIZ_KEY] || '';
            var current = serializeQuestions(questions);
            if (stored !== current) {
                setQuestions(parseQuestions(stored));
            }
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [meta[QUIZ_KEY]]);

        function persist(list) {
            setQuestions(list);
            props.setMeta(buildMeta(meta, QUIZ_KEY, serializeQuestions(list)));
        }

        function openNew() {
            setEditing({
                isNew: true,
                draft: { id: '', type: 'multiple_choice', prompt: '', options: ['', ''], correct_index: null, explanation: '' },
                error: ''
            });
        }

        function openEdit(qid) {
            for (var i = 0; i < questions.length; i++) {
                if (questions[i].id === qid) {
                    var q = questions[i];
                    setEditing({
                        isNew: false,
                        draft: {
                            id: q.id,
                            type: 'multiple_choice',
                            prompt: q.prompt,
                            options: q.options.slice(),
                            correct_index: (typeof q.correct_index === 'number') ? q.correct_index : null,
                            explanation: (typeof q.explanation === 'string') ? q.explanation : ''
                        },
                        error: ''
                    });
                    return;
                }
            }
        }

        function patchDraft(patch) {
            setEditing(function (prev) {
                return {
                    isNew: prev.isNew,
                    draft: Object.assign({}, prev.draft, patch),
                    error: ''
                };
            });
        }

        function patchOption(idx, value) {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).slice();
                options[idx] = value;
                return { isNew: prev.isNew, draft: Object.assign({}, prev.draft, { options: options }), error: '' };
            });
        }

        function addOption() {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).concat(['']);
                return { isNew: prev.isNew, draft: Object.assign({}, prev.draft, { options: options }), error: '' };
            });
        }

        function removeOption(idx) {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).slice();
                options.splice(idx, 1);
                return { isNew: prev.isNew, draft: Object.assign({}, prev.draft, { options: options, correct_index: null }), error: '' };
            });
        }

        function commitEdit() {
            if (!editing) return;
            var error = validateDraft(editing.draft);
            if (error) {
                setEditing({ isNew: editing.isNew, draft: editing.draft, error: error });
                return;
            }

            var saved = toSavedQuestion(editing.draft);
            if (!saved.id) saved.id = makeId(questions);

            var list;
            if (editing.isNew) {
                list = questions.concat([saved]);
            } else {
                list = questions.map(function (q) { return q.id === saved.id ? saved : q; });
            }
            persist(list);
            setEditing(null);
        }

        function moveQuestion(index, dir) {
            var to = index + dir;
            if (to < 0 || to >= questions.length) return;
            var list = questions.slice();
            var tmp = list[index];
            list[index] = list[to];
            list[to] = tmp;
            persist(list);
        }

        function confirmDelete(qid) {
            persist(questions.filter(function (q) { return q.id !== qid; }));
            setPendingDelete(null);
        }

        /* ------------------------------ elements ------------------------------ */

        function buildEditorCard() {
            var d = editing.draft;
            var rows = [];

            rows.push(el('div', { key: 'head' },
                el('span', { className: 'tfp-hw-badge tfp-hw-badge--multiple_choice' }, __('Multiple Choice', 'tfp-dashboard')),
                editing.isNew
                    ? el('span', { className: 'tfp-hw-new-label' }, __('New question', 'tfp-dashboard'))
                    : null
            ));

            rows.push(el(TextareaControl, {
                key: 'prompt',
                label: __('Question', 'tfp-dashboard'),
                help: __('The question your student will answer.', 'tfp-dashboard'),
                value: d.prompt,
                rows: 3,
                onChange: function (value) { patchDraft({ prompt: value }); }
            }));

            var optionRows = (d.options || []).map(function (opt, i) {
                var controls = [];
                controls.push(el(TextControl, {
                    key: 'opt-' + i,
                    label: __('Option', 'tfp-dashboard') + ' ' + (i + 1),
                    value: opt,
                    onChange: function (value) { patchOption(i, value); }
                }));
                if ((d.options || []).length > 2) {
                    controls.push(el(Button, {
                        key: 'rm-' + i,
                        className: 'tfp-hw-remove-opt',
                        onClick: function () { removeOption(i); }
                    }, '×'));
                }
                return el('div', { key: 'row-' + i, className: 'tfp-hw-option-row' }, controls);
            });

            var correctOptions = [{ value: '-1', label: __('— Select correct answer —', 'tfp-dashboard') }];
            (d.options || []).forEach(function (opt, i) {
                var label = __('Option', 'tfp-dashboard') + ' ' + (i + 1);
                if (opt && opt.trim()) {
                    label += ': ' + (opt.length > 18 ? opt.slice(0, 18) + '…' : opt);
                } else {
                    label += ' (' + __('empty', 'tfp-dashboard') + ')';
                }
                correctOptions.push({ value: String(i), label: label });
            });

            rows.push(el('div', { key: 'options' },
                el('span', { className: 'tfp-hw-options-label' }, __('Answer options', 'tfp-dashboard')),
                el('p', { className: 'tfp-hw-options-hint' },
                    __('Give at least two options. The correct answer is required — quizzes are auto-graded.', 'tfp-dashboard')
                ),
                optionRows,
                el(Button, { className: 'tfp-hw-add-opt', onClick: addOption }, '+ ' + __('Add option', 'tfp-dashboard'))
            ));

            rows.push(el(SelectControl, {
                key: 'correct',
                label: __('Correct answer', 'tfp-dashboard'),
                help: __('The student must pick this option to get the question right.', 'tfp-dashboard'),
                value: (typeof d.correct_index === 'number') ? String(d.correct_index) : '-1',
                options: correctOptions,
                onChange: function (value) {
                    patchDraft({ correct_index: value === '-1' ? null : parseInt(value, 10) });
                }
            }));

            rows.push(el(TextareaControl, {
                key: 'explanation',
                label: __('Explanation', 'tfp-dashboard'),
                help: __('Shown on the Review Answers screen after grading — explains why the correct answer is right.', 'tfp-dashboard'),
                value: (typeof d.explanation === 'string') ? d.explanation : '',
                rows: 3,
                onChange: function (value) { patchDraft({ explanation: value }); }
            }));

            if (editing.error) {
                rows.push(el('div', { key: 'error', className: 'tfp-hw-error' }, editing.error));
            }

            rows.push(el('div', { key: 'actions', className: 'tfp-hw-editor-actions' },
                el(Button, { isPrimary: true, onClick: commitEdit }, __('Done', 'tfp-dashboard')),
                el(Button, { onClick: function () { setEditing(null); } }, __('Cancel', 'tfp-dashboard'))
            ));

            return el('div', { key: 'editor', className: 'tfp-hw-card tfp-hw-card--open' }, rows);
        }

        function buildCollapsedCard(q, index) {
            var editingLocked = editing !== null;
            var cardActions = [];

            cardActions.push(el(Button, {
                key: 'up',
                className: 'tfp-hw-icon-btn',
                label: __('Move up', 'tfp-dashboard'),
                disabled: editingLocked || index === 0,
                onClick: function (e) { e.stopPropagation(); moveQuestion(index, -1); }
            }, '↑'));

            cardActions.push(el(Button, {
                key: 'down',
                className: 'tfp-hw-icon-btn',
                label: __('Move down', 'tfp-dashboard'),
                disabled: editingLocked || index === questions.length - 1,
                onClick: function (e) { e.stopPropagation(); moveQuestion(index, 1); }
            }, '↓'));

            if (pendingDelete === q.id) {
                cardActions.push(el('span', { key: 'del-confirm', className: 'tfp-hw-del-confirm' },
                    el('span', { className: 'tfp-hw-del-text' }, __('Delete?', 'tfp-dashboard')),
                    el(Button, { className: 'tfp-hw-del-yes', onClick: function (e) { e.stopPropagation(); confirmDelete(q.id); } }, __('Yes', 'tfp-dashboard')),
                    el(Button, { className: 'tfp-hw-del-no', onClick: function (e) { e.stopPropagation(); setPendingDelete(null); } }, __('No', 'tfp-dashboard'))
                ));
            } else {
                cardActions.push(el(Button, {
                    key: 'del',
                    className: 'tfp-hw-icon-btn tfp-hw-del',
                    label: __('Delete question', 'tfp-dashboard'),
                    disabled: editingLocked,
                    onClick: function (e) { e.stopPropagation(); setPendingDelete(q.id); }
                }, '🗑'));
            }

            return el('div', {
                key: 'card-' + q.id,
                className: 'tfp-hw-card' + (editingLocked ? ' tfp-hw-card--locked' : '')
            },
                el('div', {
                    className: 'tfp-hw-card-head',
                    onClick: editingLocked ? null : function () { openEdit(q.id); }
                },
                    el('div', { className: 'tfp-hw-card-top' },
                        el('span', { className: 'tfp-hw-badge tfp-hw-badge--multiple_choice' }, __('Multiple Choice', 'tfp-dashboard')),
                        el('span', { className: 'tfp-hw-card-actions' }, cardActions)
                    ),
                    el('div', { className: 'tfp-hw-card-preview' }, trimPreview(q.prompt)),
                    el('div', { className: 'tfp-hw-card-meta' },
                        __('Question', 'tfp-dashboard') + ' ' + (index + 1) + ' · ' + sprintf('%d %s', (q.options || []).length, __('options', 'tfp-dashboard'))
                    )
                )
            );
        }

        var elements = [];

        elements.push(el('strong', { key: 'title', className: 'tfp-hw-title' }, __('Quiz Questions', 'tfp-dashboard')));
        elements.push(el('p', { key: 'help', className: 'tfp-hw-help' },
            __('Auto-graded multiple-choice quiz for this week. Each question needs a correct answer.', 'tfp-dashboard')
        ));

        // Pass percentage.
        elements.push(el(TextControl, {
            key: 'pass',
            label: __('Pass percentage (%)', 'tfp-dashboard'),
            help: __('A student must score at least this percentage to pass. Leave empty for the default (70%).', 'tfp-dashboard'),
            type: 'number',
            min: 1,
            max: 100,
            value: meta[PASS_KEY] !== undefined && meta[PASS_KEY] !== '' ? String(meta[PASS_KEY]) : '',
            onChange: function (value) {
                var clean = value.replace(/[^0-9]/g, '');
                props.setMeta(buildMeta(meta, PASS_KEY, clean === '' ? '' : String(parseInt(clean, 10) || '')));
            }
        }));

        if (questions.length > 0) {
            elements.push(el('p', { key: 'count', className: 'tfp-hw-count' }, sprintf('%d question(s)', questions.length)));
        }

        var editingLocked = editing !== null;

        if (!editingLocked) {
            elements.push(el(Button, {
                key: 'add',
                className: 'tfp-hw-add-btn',
                onClick: openNew
            }, '+ ' + __('Add Question', 'tfp-dashboard')));
        }

        if (editing && editing.isNew) {
            elements.push(buildEditorCard());
        }

        if (questions.length > 0) {
            var cardNodes = [];
            questions.forEach(function (q, index) {
                var isOpen = !!(editing && !editing.isNew && editing.draft.id === q.id);
                cardNodes.push(isOpen ? buildEditorCard() : buildCollapsedCard(q, index));
            });
            elements.push(el('div', { key: 'list', className: 'tfp-hw-list' }, cardNodes));
        }

        if (!questions.length && !editingLocked) {
            elements.push(el('p', { key: 'empty', className: 'tfp-hw-empty' },
                __('No quiz questions yet — click “Add Question” to create the first one.', 'tfp-dashboard')
            ));
        }

        return el(
            PluginDocumentSettingPanel,
            { name: 'tfp-week-quiz', title: __('TFP Week Quiz', 'tfp-dashboard') },
            elements
        );
    });

    registerPlugin('tfp-week-quiz-panel', { render: TfpQuizPanel });
})(window.wp);
