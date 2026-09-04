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

    var HW_KEY = 'tfp_week_homework_questions';

    var TYPE_LABELS = {
        multiple_choice: __('Multiple Choice', 'tfp-dashboard'),
        written: __('Written', 'tfp-dashboard'),
        both: __('Yes / No + Explanation', 'tfp-dashboard')
    };
    var TYPE_ORDER = ['multiple_choice', 'written', 'both'];

    var FIELDS = [
        { key: 'tfp_week_video_url', label: __('Video URL', 'tfp-dashboard'), placeholder: 'https://...' },
        { key: 'tfp_week_meeting_date', label: __('Meeting Date', 'tfp-dashboard'), placeholder: 'e.g. June 12, 2026' },
        { key: 'tfp_week_meeting_time', label: __('Meeting Time', 'tfp-dashboard'), placeholder: 'e.g. 6:00 PM PT' },
        { key: 'tfp_week_facilitator_name', label: __('Facilitator Name', 'tfp-dashboard'), placeholder: 'e.g. Chris Soloc' },
    ];

    /* ------------------------------------------------------------------ *
     * Pure helpers — all question editing funnels through these, so the
     * stored JSON always stays canonical and compatible with the existing
     * front-end homework renderer / answer AJAX (see includes/page-week.php
     * and includes/week/homework-helpers.php).
     * ------------------------------------------------------------------ */

    /**
     * Turn a single question object into its canonical stored shape.
     * Multiple-choice keeps options (trimmed, empty rows dropped) plus an
     * optional correct_index. Written / both keep only id/type/prompt; any
     * stray yes_no or correct_index keys are intentionally dropped (the
     * student renderer always shows both Yes and No radios for "both").
     */
    function toSavedQuestion(q) {
        var prompt = (typeof q.prompt === 'string') ? q.prompt.trim() : '';
        var out = {
            id: (typeof q.id === 'string' && q.id.trim()) ? q.id.trim() : '',
            type: (q.type === 'multiple_choice' || q.type === 'written' || q.type === 'both') ? q.type : 'written',
            prompt: prompt
        };

        if (out.type === 'multiple_choice') {
            var rawOptions = (Array.isArray(q.options) ? q.options : [])
                .map(function (o) { return (typeof o === 'string') ? o.trim() : ''; });
            var options = rawOptions.filter(function (o) { return o !== ''; });
            out.options = options;

            // correct_index is written against the ORIGINAL (pre-trim) rows, so
            // remap it to the index within the filtered options actually saved.
            if (typeof q.correct_index === 'number' && q.correct_index >= 0 &&
                    rawOptions[q.correct_index] && rawOptions[q.correct_index] !== '') {
                var kept = 0;
                for (var i = 0; i < q.correct_index; i++) {
                    if (rawOptions[i] && rawOptions[i] !== '') {
                        kept++;
                    }
                }
                if (options[kept]) {
                    out.correct_index = kept;
                }
            }
        }

        return out;
    }

    function serializeQuestions(list) {
        if (!Array.isArray(list) || !list.length) {
            return '';
        }
        return JSON.stringify(list.map(toSavedQuestion));
    }

    /**
     * Deep-ish copy used when opening a question in the editor, so edits do
     * not touch the stored list until "Done" validates and commits.
     */
    function draftFrom(q) {
        var d = { id: q.id, type: q.type, prompt: q.prompt };
        if (q.type === 'multiple_choice') {
            d.options = Array.isArray(q.options) ? q.options.slice() : [];
            d.correct_index = (typeof q.correct_index === 'number') ? q.correct_index : null;
        }
        return d;
    }

    function defaultDraft(type) {
        var d = { id: '', type: type, prompt: '' };
        if (type === 'multiple_choice') {
            d.options = ['', ''];
            d.correct_index = null;
        }
        return d;
    }

    /**
     * Normalize a stored question for the working list. Guarantees shape and
     * gives a multiple-choice question at least two editable option rows.
     */
    function normalizeQuestion(q, index) {
        if (!q || typeof q !== 'object') {
            return null;
        }
        var id = (typeof q.id === 'string' && q.id.trim()) ? q.id.trim() : ('q' + (index + 1));
        var type = (q.type === 'multiple_choice' || q.type === 'written' || q.type === 'both') ? q.type : 'written';
        var out = { id: id, type: type, prompt: (typeof q.prompt === 'string') ? q.prompt : '' };

        if (type === 'multiple_choice') {
            var options = Array.isArray(q.options) ? q.options.map(String) : [];
            while (options.length < 2) {
                options.push('');
            }
            out.options = options;
            out.correct_index = (typeof q.correct_index === 'number' && q.correct_index >= 0 && q.correct_index < options.length)
                ? q.correct_index
                : null;
        }

        return out;
    }

    /**
     * Auto-migration: read the raw stored JSON string into the working list.
     * Empty/invalid JSON safely becomes an empty list (never a crash), and
     * duplicate ids are re-keyed so the front-end DOM stays valid.
     */
    function parseQuestions(json) {
        if (!json) {
            return [];
        }

        var decoded;
        try {
            decoded = JSON.parse(json);
        } catch (e) {
            return [];
        }
        if (!Array.isArray(decoded)) {
            return [];
        }

        var out = [];
        for (var i = 0; i < decoded.length; i++) {
            var n = normalizeQuestion(decoded[i], i);
            if (!n) {
                continue;
            }
            var taken = {};
            out.forEach(function (q) { taken[q.id] = 1; });
            if (taken[n.id]) {
                n.id = makeId(out);
            }
            out.push(n);
        }
        return out;
    }

    function makeId(list) {
        var used = {};
        (list || []).forEach(function (q) { used[q.id] = 1; });
        var n = 1;
        while (used['q' + n]) {
            n++;
        }
        return 'q' + n;
    }

    function buildMeta(meta, key, value) {
        var updated = {};
        for (var k in meta) {
            if (Object.prototype.hasOwnProperty.call(meta, k)) {
                updated[k] = meta[k];
            }
        }
        updated[key] = value;
        return updated;
    }

    function validateDraft(d) {
        if (!d.prompt || !d.prompt.trim()) {
            return __('Please write the question first.', 'tfp-dashboard');
        }
        if (d.type === 'multiple_choice') {
            var filled = 0;
            (d.options || []).forEach(function (o) {
                if (o && o.trim()) {
                    filled++;
                }
            });
            if (filled < 2) {
                return __('Multiple choice needs at least 2 answer options.', 'tfp-dashboard');
            }
        }
        return '';
    }

    function trimPreview(text) {
        var s = (typeof text === 'string') ? text : '';
        if (!s.trim()) {
            return __('(No question yet)', 'tfp-dashboard');
        }
        return s.length > 42 ? s.slice(0, 42) + '…' : s;
    }

    /* ------------------------------------------------------------------ *
     * Panel component
     * ------------------------------------------------------------------ */

    var TfpWeekPanel = compose(
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

        // Working list of questions (always canonical — this is what persists).
        var questionsState = useState(function () {
            return parseQuestions(meta[HW_KEY]);
        });
        var questions = questionsState[0];
        var setQuestions = questionsState[1];

        // Open editor: null when collapsed, else { isNew, draft, error }.
        var editingState = useState(null);
        var editing = editingState[0];
        var setEditing = editingState[1];

        // "Add Question" type picker visibility.
        var addingState = useState(false);
        var adding = addingState[0];
        var setAdding = addingState[1];

        // Two-step delete confirm.
        var pendingDeleteState = useState(null);
        var pendingDelete = pendingDeleteState[0];
        var setPendingDelete = pendingDeleteState[1];

        // Adopt external changes (e.g. Gutenberg undo/redo) back into the list.
        useEffect(function () {
            var stored = meta[HW_KEY] || '';
            var current = serializeQuestions(questions);
            if (stored !== current) {
                setQuestions(parseQuestions(stored));
            }
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [meta[HW_KEY]]);

        function persist(list) {
            setQuestions(list);
            props.setMeta(buildMeta(meta, HW_KEY, serializeQuestions(list)));
        }

        function openNew(type) {
            setAdding(false);
            setEditing({ isNew: true, draft: defaultDraft(type), error: '' });
        }

        function openEdit(qid) {
            setAdding(false);
            for (var i = 0; i < questions.length; i++) {
                if (questions[i].id === qid) {
                    setEditing({ isNew: false, draft: draftFrom(questions[i]), error: '' });
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

        function changeType(newType) {
            setEditing(function (prev) {
                var d = Object.assign({}, prev.draft);
                if (newType === 'multiple_choice') {
                    d.options = (Array.isArray(d.options) && d.options.length) ? d.options : ['', ''];
                    d.correct_index = (typeof d.correct_index === 'number') ? d.correct_index : null;
                } else {
                    delete d.options;
                    delete d.correct_index;
                }
                d.type = newType;
                return { isNew: prev.isNew, draft: d, error: '' };
            });
        }

        function patchOption(idx, value) {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).slice();
                options[idx] = value;
                var d = Object.assign({}, prev.draft, { options: options });
                return { isNew: prev.isNew, draft: d, error: '' };
            });
        }

        function addOption() {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).concat(['']);
                var d = Object.assign({}, prev.draft, { options: options });
                return { isNew: prev.isNew, draft: d, error: '' };
            });
        }

        function removeOption(idx) {
            setEditing(function (prev) {
                var options = (prev.draft.options || []).slice();
                options.splice(idx, 1);
                var d = Object.assign({}, prev.draft, { options: options, correct_index: null });
                return { isNew: prev.isNew, draft: d, error: '' };
            });
        }

        function commitEdit() {
            if (!editing) {
                return;
            }
            var error = validateDraft(editing.draft);
            if (error) {
                setEditing({ isNew: editing.isNew, draft: editing.draft, error: error });
                return;
            }

            var saved = toSavedQuestion(editing.draft);
            if (!saved.id) {
                saved.id = makeId(questions);
            }

            var list;
            if (editing.isNew) {
                list = questions.concat([saved]);
            } else {
                list = questions.map(function (q) {
                    return q.id === saved.id ? saved : q;
                });
            }
            persist(list);
            setEditing(null);
        }

        function cancelEdit() {
            setEditing(null);
        }

        function moveQuestion(index, dir) {
            var to = index + dir;
            if (to < 0 || to >= questions.length) {
                return;
            }
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

        // 1. Plain week fields (video URL, meeting date/time, facilitator).
        var elements = FIELDS.map(function (field) {
            return el(TextControl, {
                key: field.key,
                label: field.label,
                placeholder: field.placeholder,
                value: meta[field.key] || '',
                onChange: function (value) {
                    props.setMeta(buildMeta(meta, field.key, value));
                },
            });
        });

        // 2. Homework Questions editor section.
        var hwChildren = [];

        hwChildren.push(
            el('strong', { key: 'hw-title', className: 'tfp-hw-title' }, __('Homework Questions', 'tfp-dashboard'))
        );
        hwChildren.push(
            el('p', { key: 'hw-help', className: 'tfp-hw-help' },
                __('Questions your student answers for this week’s homework.', 'tfp-dashboard')
            )
        );

        var editingLocked = editing !== null;
        var anyQuestions = questions.length > 0;

        if (anyQuestions) {
            hwChildren.push(
                el('p', { key: 'hw-count', className: 'tfp-hw-count' },
                    sprintf('%d question(s)', questions.length)
                )
            );
        }

        // + Add Question
        if (!editingLocked && !adding) {
            hwChildren.push(
                el(Button, {
                    key: 'hw-add',
                    className: 'tfp-hw-add-btn',
                    onClick: function () { setAdding(true); }
                }, '+ ' + __('Add Question', 'tfp-dashboard'))
            );
        }

        // Type picker (shown after clicking Add).
        if (!editingLocked && adding) {
            hwChildren.push(
                el('div', { key: 'hw-picker', className: 'tfp-hw-picker' },
                    el('p', { className: 'tfp-hw-picker-label' }, __('What kind of question?', 'tfp-dashboard')),
                    el(Button, { onClick: function () { openNew('multiple_choice'); }, className: 'tfp-hw-type-btn' },
                        el('span', { className: 'tfp-hw-type-btn-title' }, TYPE_LABELS.multiple_choice),
                        el('span', { className: 'tfp-hw-type-btn-desc' }, __('Student picks one of several options.', 'tfp-dashboard'))
                    ),
                    el(Button, { onClick: function () { openNew('written'); }, className: 'tfp-hw-type-btn' },
                        el('span', { className: 'tfp-hw-type-btn-title' }, TYPE_LABELS.written),
                        el('span', { className: 'tfp-hw-type-btn-desc' }, __('Student writes a paragraph answer.', 'tfp-dashboard'))
                    ),
                    el(Button, { onClick: function () { openNew('both'); }, className: 'tfp-hw-type-btn' },
                        el('span', { className: 'tfp-hw-type-btn-title' }, TYPE_LABELS.both),
                        el('span', { className: 'tfp-hw-type-btn-desc' }, __('Student answers Yes/No and explains why.', 'tfp-dashboard'))
                    ),
                    el(Button, { onClick: function () { setAdding(false); }, className: 'tfp-hw-cancel-link' },
                        __('Cancel', 'tfp-dashboard')
                    )
                )
            );
        }

        // A brand-new question being edited (before it has a card in the list).
        if (editing && editing.isNew) {
            hwChildren.push(buildEditorCard());
        }

        // Question cards.
        if (anyQuestions) {
            var cardNodes = [];
            questions.forEach(function (q, index) {
                var isOpenForThis = !!(editing && !editing.isNew && editing.draft.id === q.id);
                if (isOpenForThis) {
                    cardNodes.push(buildEditorCard());
                } else {
                    cardNodes.push(buildCollapsedCard(q, index));
                }
            });
            hwChildren.push(el('div', { key: 'hw-list', className: 'tfp-hw-list' }, cardNodes));
        }

        // Empty-state copy (only when nothing is being added/edited).
        if (!anyQuestions && !editingLocked && !adding) {
            hwChildren.push(
                el('p', { key: 'hw-empty', className: 'tfp-hw-empty' },
                    __('No homework questions yet — click “Add Question” to create the first one.', 'tfp-dashboard')
                )
            );
        }

        elements.push(el('div', { key: 'hw-section', className: 'tfp-hw-section' }, hwChildren));

        return el(
            PluginDocumentSettingPanel,
            { name: 'tfp-week-details', title: __('TFP Week Details', 'tfp-dashboard') },
            elements
        );

        /* ------------------------- sub-render helpers ------------------------- */

        function buildEditorCard() {
            var d = editing.draft;
            var typeOptions = TYPE_ORDER.map(function (t) {
                return { value: t, label: TYPE_LABELS[t] };
            });

            var rows = [];
            rows.push(el('div', { key: 'ec-head' },
                el('span', { className: 'tfp-hw-badge tfp-hw-badge--' + d.type }, TYPE_LABELS[d.type]),
                editing.isNew
                    ? el('span', { className: 'tfp-hw-new-label' }, __('New question', 'tfp-dashboard'))
                    : null
            ));

            rows.push(el(SelectControl, {
                key: 'ec-type',
                label: __('Question type', 'tfp-dashboard'),
                value: d.type,
                options: typeOptions,
                onChange: changeType
            }));

            rows.push(el(TextareaControl, {
                key: 'ec-prompt',
                label: __('Question', 'tfp-dashboard'),
                help: __('The question your student will read and answer.', 'tfp-dashboard'),
                value: d.prompt,
                rows: 3,
                onChange: function (value) { patchDraft({ prompt: value }); }
            }));

            if (d.type === 'multiple_choice') {
                var optionRows = (d.options || []).map(function (opt, i) {
                    var controls = [];
                    controls.push(el(TextControl, {
                        key: 'oc-input-' + i,
                        label: __('Option', 'tfp-dashboard') + ' ' + (i + 1),
                        value: opt,
                        onChange: function (value) { patchOption(i, value); }
                    }));
                    if ((d.options || []).length > 2) {
                        controls.push(el(Button, {
                            key: 'oc-remove-' + i,
                            className: 'tfp-hw-remove-opt',
                            onClick: function () { removeOption(i); }
                        }, '×'));
                    }
                    return el('div', { key: 'oc-' + i, className: 'tfp-hw-option-row' }, controls);
                });

                var correctOptions = [
                    { value: '-1', label: __('— Not marked (optional) —', 'tfp-dashboard') }
                ];
                (d.options || []).forEach(function (opt, i) {
                    var label = __('Option', 'tfp-dashboard') + ' ' + (i + 1);
                    if (opt && opt.trim()) {
                        label += ': ' + (opt.length > 18 ? opt.slice(0, 18) + '…' : opt);
                    } else {
                        label += ' (' + __('empty', 'tfp-dashboard') + ')';
                    }
                    correctOptions.push({ value: String(i), label: label });
                });

                rows.push(el('div', { key: 'ec-options' },
                    el('span', { className: 'tfp-hw-options-label' }, __('Answer options', 'tfp-dashboard')),
                    el('p', { className: 'tfp-hw-options-hint' },
                        __('Give at least two options. A correct answer is optional — homework is reviewed by a facilitator, not auto-graded.', 'tfp-dashboard')
                    ),
                    optionRows,
                    el(Button, { className: 'tfp-hw-add-opt', onClick: addOption },
                        '+ ' + __('Add option', 'tfp-dashboard')
                    )
                ));

                rows.push(el(SelectControl, {
                    key: 'ec-correct',
                    label: __('Correct option (optional)', 'tfp-dashboard'),
                    help: __('Only used if this is ever auto-checked later.', 'tfp-dashboard'),
                    value: (typeof d.correct_index === 'number') ? String(d.correct_index) : '-1',
                    options: correctOptions,
                    onChange: function (value) {
                        patchDraft({ correct_index: value === '-1' ? null : parseInt(value, 10) });
                    }
                }));
            }

            if (editing.error) {
                rows.push(el('div', { key: 'ec-error', className: 'tfp-hw-error' }, editing.error));
            }

            rows.push(el('div', { key: 'ec-actions', className: 'tfp-hw-editor-actions' },
                el(Button, { isPrimary: true, onClick: commitEdit }, __('Done', 'tfp-dashboard')),
                el(Button, { onClick: cancelEdit }, __('Cancel', 'tfp-dashboard'))
            ));

            return el('div', { key: 'editor-card', className: 'tfp-hw-card tfp-hw-card--open' }, rows);
        }

        function buildCollapsedCard(q, index) {
            var cardActions = [];

            // Reorder + delete are locked while an editor is open (prevents
            // indexes shifting under an in-progress edit).
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
                    el(Button, {
                        className: 'tfp-hw-del-yes',
                        onClick: function (e) { e.stopPropagation(); confirmDelete(q.id); }
                    }, __('Yes', 'tfp-dashboard')),
                    el(Button, {
                        className: 'tfp-hw-del-no',
                        onClick: function (e) { e.stopPropagation(); setPendingDelete(null); }
                    }, __('No', 'tfp-dashboard'))
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

            var optionMeta = '';
            if (q.type === 'multiple_choice') {
                optionMeta = ' · ' + sprintf('%d %s', (q.options || []).length, __('options', 'tfp-dashboard'));
            } else if (q.type === 'written') {
                optionMeta = ' · ' + __('paragraph', 'tfp-dashboard');
            } else if (q.type === 'both') {
                optionMeta = ' · ' + __('Yes/No + explanation', 'tfp-dashboard');
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
                        el('span', { className: 'tfp-hw-badge tfp-hw-badge--' + q.type }, TYPE_LABELS[q.type]),
                        el('span', { className: 'tfp-hw-card-actions' }, cardActions)
                    ),
                    el('div', { className: 'tfp-hw-card-preview' }, trimPreview(q.prompt)),
                    el('div', { className: 'tfp-hw-card-meta' },
                        __('Question', 'tfp-dashboard') + ' ' + (index + 1) + optionMeta
                    )
                )
            );
        }
    });

    registerPlugin('tfp-week-details-panel', { render: TfpWeekPanel });
})(window.wp);
