(function () {
    var PANEL_ID = 'page-agent-runtime_agent-panel';
    var ENHANCED_ATTR = 'data-pa-ui-enhanced';
    var LAUNCHER_ID = 'page-agent-launcher';
    var HIDDEN_CLASS = 'page-agent-hidden-by-launcher';
    var STATE_KEY = 'page-agent-ui-state-v1';
    var CHAT_STATE_KEY = 'page-agent-chat-state-v1';
    var CHECKLIST_MAX_ITEMS = 8;
    var chatRestoredOnce = false;
    var resumeLoopActive = false;
    var resumeCompletedOnUrl = '';
    var launcherOpenRequested = false;
    var MAX_HISTORY_ENTRIES = 80;
    var MAX_HISTORY_FOR_CONTEXT = 20;
    var MAX_TEXT_LEN = 700;
    var HISTORY_COMPRESS_THRESHOLD = 40;
    var appStorage = getPreferredStorage();
    var cachedPanel = null;
    var inMemoryState = null;
    var inMemoryChatState = null;
    var runtimeScriptLoading = false;
    var runtimeScriptRetryCount = 0;
    var runtimeScriptMaxRetries = 3;
    var runtimeScriptLastAttemptAt = 0;

    function safeTextPreview(value, maxLen) {
        var text = typeof value === 'string' ? value : '';
        var limit = typeof maxLen === 'number' && maxLen > 0 ? maxLen : 140;
        if (text.length <= limit) {
            return text;
        }
        return text.slice(0, limit) + '...';
    }

    function debugLog() {
        // Debug logging removed.
    }

    function ensureRuntimeScriptLoaded(reason) {
        if (window.pageAgent) {
            return;
        }

        if (runtimeScriptLoading) {
            return;
        }

        if (runtimeScriptRetryCount >= runtimeScriptMaxRetries) {
            debugLog('runtime_reload_give_up', {
                reason: reason || 'unknown',
                retryCount: runtimeScriptRetryCount,
            });
            return;
        }

        var src = typeof window.__PAGE_AGENT_SCRIPT_SRC__ === 'string' ? window.__PAGE_AGENT_SCRIPT_SRC__ : '';
        if (!src) {
            debugLog('runtime_reload_no_src', { reason: reason || 'unknown' });
            return;
        }

        var now = Date.now();
        if (runtimeScriptLastAttemptAt && (now - runtimeScriptLastAttemptAt) < 1200) {
            return;
        }

        runtimeScriptLastAttemptAt = now;

        var scripts = document.getElementsByTagName('script');
        var hasRuntimeTag = false;
        for (var i = 0; i < scripts.length; i++) {
            var existingSrc = scripts[i].getAttribute('src') || '';
            if (existingSrc === src || existingSrc.indexOf('page-agent.demo.js') !== -1) {
                hasRuntimeTag = true;
                break;
            }
        }

        runtimeScriptLoading = true;
        runtimeScriptRetryCount += 1;

        var tag = document.createElement('script');
        var separator = src.indexOf('?') === -1 ? '?' : '&';
        var bust = 'pa_rt_retry=' + runtimeScriptRetryCount + '_' + now;
        tag.src = hasRuntimeTag ? (src + separator + bust) : src;
        tag.crossOrigin = 'true';
        tag.async = true;
        tag.onload = function () {
            runtimeScriptLoading = false;
            debugLog('runtime_reload_loaded', {
                reason: reason || 'unknown',
                retryCount: runtimeScriptRetryCount,
            });
        };
        tag.onerror = function () {
            runtimeScriptLoading = false;
            debugLog('runtime_reload_error', {
                reason: reason || 'unknown',
                retryCount: runtimeScriptRetryCount,
            });
            setTimeout(function () {
                ensureRuntimeScriptLoaded('retry_after_error');
            }, 700 * runtimeScriptRetryCount);
        };

        document.head.appendChild(tag);
        debugLog('runtime_reload_injected', {
            reason: reason || 'unknown',
            retryCount: runtimeScriptRetryCount,
            hadExistingTag: hasRuntimeTag,
            injectedSrc: tag.src,
        });
    }

    function getPreferredStorage() {
        function canUseStorage(storage) {
            if (!storage || typeof storage.setItem !== 'function') {
                return false;
            }
            try {
                var key = '__pa_probe__';
                storage.setItem(key, '1');
                storage.removeItem(key);
                return true;
            } catch (error) {
                return false;
            }
        }

        if (canUseStorage(window.localStorage)) {
            return window.localStorage;
        }
        if (canUseStorage(window.sessionStorage)) {
            return window.sessionStorage;
        }
        return {
            getItem: function () { return null; },
            setItem: function () {},
            removeItem: function () {},
        };
    }

    function readJsonFromStorage(storage, key, fallback) {
        try {
            var raw = storage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function readState() {
        if (inMemoryState) return inMemoryState;
        inMemoryState = readJsonFromStorage(appStorage, STATE_KEY, {});
        return inMemoryState;
    }

    function writeState(nextState) {
        inMemoryState = nextState;
        try {
            appStorage.setItem(STATE_KEY, JSON.stringify(nextState));
        } catch (error) {
            // Ignore storage errors (e.g. private mode quota).
        }
    }

    function readChatState() {
        if (inMemoryChatState) return inMemoryChatState;
        inMemoryChatState = readJsonFromStorage(appStorage, CHAT_STATE_KEY, null);
        return inMemoryChatState;
    }

    function writeChatState(nextState) {
        inMemoryChatState = nextState;
        try {
            appStorage.setItem(CHAT_STATE_KEY, JSON.stringify(nextState));
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function stripMarkedBlock(text, startTag, endTag) {
        if (typeof text !== 'string' || !text) {
            return '';
        }
        var start = text.indexOf(startTag);
        if (start === -1) {
            return text;
        }
        var end = text.indexOf(endTag, start);
        if (end === -1) {
            return text.slice(0, start).trim();
        }
        return (text.slice(0, start) + text.slice(end + endTag.length)).trim();
    }

    function normalizeTaskText(text) {
        var clean = typeof text === 'string' ? text : '';
        clean = stripMarkedBlock(clean, '<session_context>', '</session_context>');
        clean = stripMarkedBlock(clean, '<resume_after_navigation>', '</resume_after_navigation>');
        clean = stripMarkedBlock(clean, '<task_timeline>', '</task_timeline>');
        clean = stripMarkedBlock(clean, '<task_completed>', '</task_completed>');
        clean = stripMarkedBlock(clean, '<navigation_timeline_context>', '</navigation_timeline_context>');
        clean = stripMarkedBlock(clean, '<progress_reached_stop>', '</progress_reached_stop>');
        
        // Backwards compatibility during transition
        clean = stripMarkedBlock(clean, '[SESSION_CONTEXT]', '[/SESSION_CONTEXT]');
        clean = stripMarkedBlock(clean, '[RESUME_AFTER_NAVIGATION]', '[/RESUME_AFTER_NAVIGATION]');
        clean = stripMarkedBlock(clean, '[TASK_TIMELINE]', '[/TASK_TIMELINE]');
        clean = stripMarkedBlock(clean, '[TASK_COMPLETED]', '[/TASK_COMPLETED]');
        clean = stripMarkedBlock(clean, '[NAVIGATION_TIMELINE_CONTEXT]', '[/NAVIGATION_TIMELINE_CONTEXT]');
        clean = stripMarkedBlock(clean, '[PROGRESS_REACHED_STOP]', '[/PROGRESS_REACHED_STOP]');
        
        return clean.trim();
    }

    function hasSystemMarkers(text) {
        if (typeof text !== 'string') {
            return false;
        }
        return text.indexOf('<session_context>') !== -1 ||
            text.indexOf('<resume_after_navigation>') !== -1 ||
            text.indexOf('<task_timeline>') !== -1 ||
            text.indexOf('<task_completed>') !== -1 ||
            text.indexOf('<navigation_timeline_context>') !== -1 ||
            text.indexOf('<progress_reached_stop>') !== -1 ||
            text.indexOf('[SESSION_CONTEXT]') !== -1 ||
            text.indexOf('[RESUME_AFTER_NAVIGATION]') !== -1 ||
            text.indexOf('[TASK_TIMELINE]') !== -1 ||
            text.indexOf('[TASK_COMPLETED]') !== -1 ||
            text.indexOf('[NAVIGATION_TIMELINE_CONTEXT]') !== -1 ||
            text.indexOf('[PROGRESS_REACHED_STOP]') !== -1;
    }

    function normalizeChatState(raw) {
        var state = raw && typeof raw === 'object' ? raw : {};
        var fallbackResumeRequestedAt =
            (typeof state.resumeRequestedAt === 'number' && state.resumeRequestedAt > 0) ? state.resumeRequestedAt :
            (typeof state.taskStartedAt === 'number' && state.taskStartedAt > 0) ? state.taskStartedAt :
            (typeof state.updatedAt === 'number' && state.updatedAt > 0) ? state.updatedAt :
            Date.now();
        return {
            task: typeof state.task === 'string' ? state.task : '',
            history: Array.isArray(state.history) ? state.history : [],
            status: typeof state.status === 'string' ? state.status : 'idle',
            updatedAt: typeof state.updatedAt === 'number' ? state.updatedAt : Date.now(),
            pendingTask: typeof state.pendingTask === 'string' ? state.pendingTask : '',
            shouldResume: !!state.shouldResume,
            startUrl: typeof state.startUrl === 'string' ? state.startUrl : '',
            currentUrl: typeof state.currentUrl === 'string' ? state.currentUrl : '',
            navTimeline: Array.isArray(state.navTimeline) ? state.navTimeline : [],
            checklist: Array.isArray(state.checklist) ? state.checklist : [],
            taskStartedAt: typeof state.taskStartedAt === 'number' ? state.taskStartedAt : 0,
            resumeRequestedAt: fallbackResumeRequestedAt,
            taskCompleted: !!state.taskCompleted,
        };
    }

    function urlPath(url) {
        var normalized = normalizeUrl(url);
        if (!normalized) {
            return '';
        }

        try {
            return new URL(normalized).pathname.toLowerCase();
        } catch (error) {
            return normalized.toLowerCase();
        }
    }

    function isPath(path, fileName) {
        return path.indexOf('/' + fileName) !== -1 || path === fileName;
    }

    function normalizeUrl(url) {
        if (typeof url !== 'string' || !url) {
            return '';
        }

        try {
            var parsed = new URL(url, window.location.href);
            return parsed.href;
        } catch (error) {
            return url;
        }
    }

    function createNavEntry(url, action, source) {
        return {
            url: normalizeUrl(url || window.location.href),
            action: typeof action === 'string' && action ? action : 'navigate',
            source: typeof source === 'string' && source ? source : 'runtime',
            at: Date.now(),
        };
    }

    function sanitizeTimeline(timeline) {
        var list = Array.isArray(timeline) ? timeline : [];
        var out = [];

        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (!item || typeof item !== 'object') {
                continue;
            }

            var normalized = {
                url: normalizeUrl(typeof item.url === 'string' ? item.url : ''),
                action: typeof item.action === 'string' && item.action ? item.action : 'navigate',
                source: typeof item.source === 'string' && item.source ? item.source : 'runtime',
                at: typeof item.at === 'number' ? item.at : Date.now(),
            };

            if (!normalized.url) {
                continue;
            }

            out.push(normalized);
        }

        if (out.length > 24) {
            out = out.slice(out.length - 24);
        }

        return out;
    }

    function appendTimelineEntry(timeline, entry) {
        var list = sanitizeTimeline(timeline);
        if (!entry || !entry.url) {
            return list;
        }

        var last = list.length ? list[list.length - 1] : null;
        if (last && last.url === entry.url && last.action === entry.action) {
            return list;
        }

        list.push(entry);
        if (list.length > 24) {
            list = list.slice(list.length - 24);
        }
        return list;
    }

    function isChecklistComplete(checklist) {
        var list = Array.isArray(checklist) ? checklist : [];
        if (list.length === 0) {
            return false;
        }
        for (var i = 0; i < list.length; i++) {
            if (!list[i] || !list[i].done) {
                return false;
            }
        }
        return true;
    }

    function detectProgressCompletion(taskText, history, timeline) {
        var task = (typeof taskText === 'string' ? taskText : '').toLowerCase();
        var urls = sanitizeTimeline(timeline);
        var movedToAnotherPage = false;
        if (urls.length >= 2) {
            movedToAnotherPage = urls[0].url !== urls[urls.length - 1].url;
        }

        // We rely primarily on checklist completion now.
        // Return false for general progress detection unless checklist is totally done, 
        // to prevent premature halting when looping tasks.
        return {
            reached: false,
            reason: 'Task progress is bound to exact checklist completion.',
        };
    }

    function formatTimelineLines(timeline) {
        var list = sanitizeTimeline(timeline);
        if (list.length === 0) {
            return '- (no navigation recorded yet)';
        }

        var lines = [];
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            lines.push('- ' + item.action + ' | ' + item.url);
        }
        return lines.join('\n');
    }

    function splitTaskIntoSteps(taskText) {
        if (typeof taskText !== 'string') {
            return [];
        }

        var normalized = taskText
            .replace(/[\r\n]+/g, ' | ')
            .replace(/\s*(?:->|=>|\|)\s*/g, ', ')
            .replace(/\s+(?:then|and then|sau do|roi|rồi|tiep theo)\s+/gi, ', ')
            .trim();

        var parts = normalized.split(/\s*,\s*/);
        var out = [];

        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim();
            if (!part) {
                continue;
            }
            if (/^(task|yeu cau|request):\s*/i.test(part)) {
                part = part.replace(/^(task|yeu cau|request):\s*/i, '').trim();
            }
            if (!part) {
                continue;
            }
            out.push(part);
        }

        if (out.length === 0 && normalized) {
            out.push(normalized);
        }

        if (out.length > CHECKLIST_MAX_ITEMS) {
            out = out.slice(0, CHECKLIST_MAX_ITEMS);
        }

        return out;
    }

    function inferStepType(text) {
        var value = (typeof text === 'string' ? text : '').toLowerCase();
        if (/(dang ky|đăng ký|register|sign up|tao acc|tạo acc|create account)/.test(value)) {
            return 'register';
        }
        if (/(dang xuat|đăng xuất|logout|log out|sign out)/.test(value)) {
            return 'logout';
        }
        if (/(dang nhap|đăng nhập|relogin|re-login|login lai|login lại|login|log in|sign in)/.test(value)) {
            return 'login';
        }
        return 'generic';
    }

    function normalizeChecklist(rawChecklist, taskText) {
        var list = Array.isArray(rawChecklist) ? rawChecklist : [];
        var normalized = [];

        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (!item || typeof item !== 'object') {
                continue;
            }

            var label = typeof item.label === 'string' ? item.label.trim() : '';
            if (!label) {
                continue;
            }

            normalized.push({
                label: label,
                done: !!item.done,
                type: inferStepType(item.type || label),
            });
        }

        if (normalized.length > 0) {
            return normalized.slice(0, CHECKLIST_MAX_ITEMS);
        }

        var steps = splitTaskIntoSteps(taskText);
        var created = [];
        for (var j = 0; j < steps.length; j++) {
            created.push({
                label: steps[j],
                done: false,
                type: inferStepType(steps[j]),
            });
        }

        return created;
    }

    function updateChecklistProgress(checklist, history, timeline) {
        var list = normalizeChecklist(checklist, '');
        if (list.length === 0) {
            return list;
        }

        var urls = sanitizeTimeline(timeline);
        var paths = [];
        for (var p = 0; p < urls.length; p++) {
            var pathStr = urlPath(urls[p].url);
            // Build strictly paired arrays with timestamps
            paths.push({
                path: pathStr,
                at: urls[p].at || Date.now()
            });
        }

        var timelineCursor = 0;
        var historyCursor = 0;
        var previousDone = true;

        for (var j = 0; j < list.length; j++) {
            var step = list[j];
            
            if (step.done) {
                previousDone = true;
                // Khôi phục con trỏ thời gian dựa trên timestamp thay vì index (chống lỗi khi mảng bị cắt bớt)
                if (typeof step.completedAt === 'number') {
                    var foundStart = false;
                    for (var t = 0; t < paths.length; t++) {
                        if (paths[t].at > step.completedAt) {
                            timelineCursor = Math.max(timelineCursor, t);
                            foundStart = true;
                            break;
                        }
                    }
                    if (!foundStart) {
                        // Nếu toàn bộ URL đều cũ hơn completedAt, chứng tỏ chưa có event mới
                        timelineCursor = paths.length;
                    }
                }
                
                // Cập nhật historyCursor để chặn việc text cũ kích hoạt nhầm ở các bước sau
                historyCursor = Array.isArray(history) ? history.length : 0;
                continue;
            }

            if (!previousDone) {
                continue;
            }

            var labelRef = (step.label || '').toLowerCase();
            var stepCompleted = false;
            var foundIndex = -1;
            var matchByHistory = false;

            // Dò tìm hành động chuyển trang kể từ timelineCursor
            for (var t = timelineCursor; t < paths.length; t++) {
                var prevPath = t > 0 ? paths[t - 1].path : '';
                var currPath = paths[t].path;

                if (step.type === 'register' && isPath(prevPath, 'register.php') && isPath(currPath, 'dashboard.php')) {
                    stepCompleted = true;
                    foundIndex = t;
                    break;
                }
                if (step.type === 'logout' && isPath(prevPath, 'dashboard.php') && isPath(currPath, 'login.php')) {
                    stepCompleted = true;
                    foundIndex = t;
                    break;
                }
                if (step.type === 'login' && isPath(prevPath, 'login.php') && isPath(currPath, 'dashboard.php')) {
                    stepCompleted = true;
                    foundIndex = t;
                    break;
                }
            }

            // Fallback: Quét lịch sử hội thoại (chỉ tính từ historyCursor để tránh lặp lại sự kiện cũ)
            if (!stepCompleted && Array.isArray(history) && historyCursor < history.length) {
                var recentHistoryText = '';
                // Quét 10 dòng gần nhất nhưng không vượt qua historyCursor
                var scanStart = Math.max(historyCursor, history.length - 10);
                for (var i = scanStart; i < history.length; i++) {
                    var row = historyLineFromEntry(history[i]);
                    if (row) recentHistoryText += ' ' + row.toLowerCase();
                }
                var lastPathObj = paths.length > 0 ? paths[paths.length - 1].path : '';

                var regexMatched = false;
                if (step.type === 'register' && /(register|create)/.test(recentHistoryText) && /dashboard/.test(lastPathObj)) {
                    regexMatched = true;
                } else if (step.type === 'logout' && /(logout|logged out|dang xuat|đăng xuất|sign out)/.test(recentHistoryText) && isPath(lastPathObj, 'login.php')) {
                    regexMatched = true;
                } else if (step.type === 'login' && /(login success|logged in|dang nhap thanh cong|đăng nhập thành công|sign in success)/.test(recentHistoryText) && isPath(lastPathObj, 'dashboard.php')) {
                    regexMatched = true;
                } else {
                    var genericWords = labelRef.split(/\s+/).filter(function(w) { return w.length > 2; });
                    for (var w = 0; w < genericWords.length; w++) {
                        if (recentHistoryText.indexOf(genericWords[w]) !== -1 && /(thanh cong|hoan tat|done|success|completed)/.test(recentHistoryText)) {
                            regexMatched = true;
                            break;
                        }
                    }
                }

                if (regexMatched) {
                    stepCompleted = true;
                    foundIndex = paths.length - 1;
                    matchByHistory = true;
                }
            }

            if (stepCompleted) {
                step.done = true;
                // Gắn mốc thời gian hoàn thành tuyệt đối
                step.completedAt = (foundIndex >= 0 && foundIndex < paths.length) ? paths[foundIndex].at : Date.now();
                
                // Đẩy con trỏ đi tiếp
                timelineCursor = Math.max(timelineCursor, foundIndex + 1);
                if (matchByHistory) {
                    historyCursor = history.length; // Chặn bước sau dùng lại đoạn text này
                }
                
                previousDone = true;
            } else {
                previousDone = false;
            }
        }

        return list;
    }

    function formatChecklistLines(checklist) {
        var list = Array.isArray(checklist) ? checklist : [];
        if (list.length === 0) {
            return '- [ ] No explicit steps parsed';
        }

        var lines = [];
        for (var i = 0; i < list.length; i++) {
            lines.push('- [' + (list[i].done ? 'x' : ' ') + '] ' + list[i].label);
        }
        return lines.join('\n');
    }

    function buildNavigationContext(taskText) {
        var cached = normalizeChatState(readChatState());
        var nowUrl = normalizeUrl(window.location.href);
        var startUrl = cached.startUrl || nowUrl;
        var timeline = appendTimelineEntry(cached.navTimeline, createNavEntry(nowUrl, 'arrive_current_page', 'runtime'));
        var checklist = updateChecklistProgress(normalizeChecklist(cached.checklist, taskText || cached.task), cached.history, timeline);
        var allChecklistDone = false;
        if (checklist.length > 0) {
            allChecklistDone = true;
            for (var x = 0; x < checklist.length; x++) {
                if (!checklist[x].done) {
                    allChecklistDone = false;
                    break;
                }
            }
        }

        var progress = detectProgressCompletion(taskText || cached.task, cached.history, timeline);
        if (checklist.length > 0) {
            if (!allChecklistDone) {
                progress = {
                    reached: false,
                    reason: 'Checklist still has unfinished steps.',
                };
            } else {
                progress = {
                    reached: true,
                    reason: 'All checklist steps are completed.',
                };
            }
        }

        var navSummary = formatTimelineLines(timeline.slice(-8));
        var checklistSummary = formatChecklistLines(checklist);

        return {
            context: '' +
                '<agent_core_directive>\n' +
                'You are an autonomous web automation agent executing a multi-step task across multiple page loads.\n' +
                'You must persist your progress conceptually and sequentially.\n' +
                '</agent_core_directive>\n\n' +
                '<task_state>\n' +
                'Origin URL: ' + startUrl + '\n' +
                'Current Active URL: ' + nowUrl + '\n' +
                'Is the overarching Task completed tracking?: ' + (progress.reached ? 'YES' : 'NO') + '\n' +
                'Progress Analytical Note: ' + progress.reason + '\n' +
                '</task_state>\n\n' +
                '<checklist_execution_rules>\n' +
                'Current Checklist (Dynamically updated. Note: [x] means verified completed by state machine):\n' +
                checklistSummary + '\n\n' +
                'RULES OF ENGAGEMENT:\n' +
                '1. Look at the FIRST pending [ ] step. That is your ONLY current objective.\n' +
                '2. Observe the current URL and DOM. If the current page isn\'t right for the step, your next action must be to navigate or click to get there.\n' +
                '3. If you are on the right page for the step, execute the necessary form submission or clicks.\n' +
                '4. VERY IMPORTANT: If your action causes a navigation or form submit, you MUST STOP and wait. The system will auto-resume you on the next page load. Do NOT try to complete multiple checklist steps in a single response.\n' +
                '</checklist_execution_rules>\n\n' +
                '<navigation_trace>\n' +
                navSummary + '\n' +
                '</navigation_trace>',
            startUrl: startUrl,
            currentUrl: nowUrl,
            navTimeline: timeline,
            progressReached: progress.reached,
            checklist: checklist,
        };
    }

    function clearChatState() {
        inMemoryChatState = null;
        try {
            appStorage.removeItem(CHAT_STATE_KEY);
        } catch (error) {
            // Ignore storage errors.
        }
    }

    function clipText(value) {
        if (typeof value !== 'string') {
            return value;
        }
        return value.length > MAX_TEXT_LEN ? value.slice(0, MAX_TEXT_LEN) + '...' : value;
    }

    function sanitizeAction(action) {
        if (!action || typeof action !== 'object') {
            return null;
        }
        return {
            name: clipText(typeof action.name === 'string' ? action.name : ''),
            input: action.input || null,
            output: clipText(typeof action.output === 'string' ? action.output : ''),
        };
    }

    function sanitizeReflection(reflection) {
        if (!reflection || typeof reflection !== 'object') {
            return null;
        }
        return {
            evaluation_previous_goal: clipText(typeof reflection.evaluation_previous_goal === 'string' ? reflection.evaluation_previous_goal : ''),
            memory: clipText(typeof reflection.memory === 'string' ? reflection.memory : ''),
            next_goal: clipText(typeof reflection.next_goal === 'string' ? reflection.next_goal : ''),
        };
    }

    function sanitizeHistoryEntry(entry) {
        if (!entry || typeof entry !== 'object' || typeof entry.type !== 'string') {
            return null;
        }

        var base = { type: entry.type };

        if (entry.type === 'step') {
            base.stepIndex = typeof entry.stepIndex === 'number' ? entry.stepIndex : 0;
            base.reflection = sanitizeReflection(entry.reflection);
            base.action = sanitizeAction(entry.action);
            return base;
        }

        if (entry.type === 'observation') {
            base.content = clipText(typeof entry.content === 'string' ? entry.content : '');
            return base;
        }

        if (entry.type === 'error') {
            base.message = clipText(typeof entry.message === 'string' ? entry.message : '');
            return base;
        }

        if (entry.type === 'retry') {
            base.message = clipText(typeof entry.message === 'string' ? entry.message : '');
            base.attempt = typeof entry.attempt === 'number' ? entry.attempt : 0;
            base.maxAttempts = typeof entry.maxAttempts === 'number' ? entry.maxAttempts : 0;
            return base;
        }

        if (entry.type === 'user_takeover') {
            return base;
        }

        return null;
    }

    function sanitizeHistoryList(history) {
        var list = Array.isArray(history) ? history : [];
        var out = [];
        for (var i = 0; i < list.length; i++) {
            var sanitized = sanitizeHistoryEntry(list[i]);
            if (sanitized) {
                out.push(sanitized);
            }
        }
        if (out.length > MAX_HISTORY_ENTRIES) {
            out = out.slice(out.length - MAX_HISTORY_ENTRIES);
        }
        return out;
    }

    function entrySignature(entry) {
        try {
            return JSON.stringify(entry);
        } catch (error) {
            return String(entry && entry.type ? entry.type : 'unknown');
        }
    }

    function mergeHistory(existing, incoming) {
        var base = Array.isArray(existing) ? existing.slice() : [];
        var list = Array.isArray(incoming) ? incoming : [];

        if (list.length === 0) {
            return base;
        }

        var seen = new Set();
        for (var i = 0; i < base.length; i++) {
            seen.add(entrySignature(base[i]));
        }

        for (var j = 0; j < list.length; j++) {
            var sig = entrySignature(list[j]);
            if (!seen.has(sig)) {
                seen.add(sig);
                base.push(list[j]);
            }
        }

        return base;
    }

    function persistNavigationCheckpoint(source) {
        var cached = normalizeChatState(readChatState());
        var currentUrl = normalizeUrl(window.location.href);
        var runtimeTask = '';
        var runtimeStatus = '';

        if (window.pageAgent) {
            runtimeTask = normalizeTaskText(typeof window.pageAgent.task === 'string' ? window.pageAgent.task : '');
            runtimeStatus = typeof window.pageAgent.status === 'string' ? window.pageAgent.status : '';
        }

        // Always prefer the original user task from state, never runtime expanded prompt.
        var bestTask = normalizeTaskText(cached.pendingTask || cached.task || runtimeTask);
        var done = !!cached.taskCompleted;

        // Force a navigation handoff snapshot so new page can continue reliably.
        if (bestTask && !done) {
            cached.task = bestTask;
            cached.pendingTask = bestTask;
            cached.shouldResume = true;
            cached.taskCompleted = false;
            cached.resumeRequestedAt = Date.now();
            if (!cached.taskStartedAt) {
                cached.taskStartedAt = Date.now();
            }
            if (!cached.startUrl) {
                cached.startUrl = currentUrl;
            }
        }

        if (runtimeStatus) {
            cached.status = runtimeStatus;
        }

        cached.currentUrl = currentUrl;
        cached.navTimeline = appendTimelineEntry(
            cached.navTimeline,
            createNavEntry(currentUrl, 'navigation_checkpoint', source || 'checkpoint')
        );
        cached.updatedAt = Date.now();
        writeChatState(cached);
        debugLog('navigation_checkpoint_saved', {
            source: source || 'checkpoint',
            shouldResume: cached.shouldResume,
            pendingTaskLen: cached.pendingTask ? cached.pendingTask.length : 0,
            taskCompleted: !!cached.taskCompleted,
        });
    }

    function persistAgentState() {
        // === VS Code Agent Pattern: Keep conversation history forever ===
        // Merge agent history into state, but NEVER delete entries.
        // Only maintain MAX_HISTORY_ENTRIES as practical limit, not for cleanup (just keep recent).
        
        var panel = findAgentPanel();
        if (panel) {
            savePanelPosition(panel);
        }

        if (!window.pageAgent) {
            ensureRuntimeScriptLoaded('persist_without_runtime');
            persistNavigationCheckpoint('persist_without_runtime');
            return;
        }

        var cached = normalizeChatState(readChatState());
        var history = sanitizeHistoryList(window.pageAgent.history);
        var task = normalizeTaskText(typeof window.pageAgent.task === 'string' ? window.pageAgent.task : '');
        var status = typeof window.pageAgent.status === 'string' ? window.pageAgent.status : 'idle';
        var mergedHistory = mergeHistory(cached.history, history);
        if (mergedHistory.length > MAX_HISTORY_ENTRIES) {
            mergedHistory = mergedHistory.slice(mergedHistory.length - MAX_HISTORY_ENTRIES);
        }

        var pendingTask = cached.pendingTask;
        var shouldResume = cached.shouldResume;
        var currentUrl = normalizeUrl(window.location.href);
        var startUrl = cached.startUrl || currentUrl;
        var navTimeline = appendTimelineEntry(cached.navTimeline, createNavEntry(currentUrl, status === 'running' ? 'step_runtime_tick' : 'observe_page', 'persist'));
        var checklist = updateChecklistProgress(normalizeChecklist(cached.checklist, task || cached.task), mergedHistory, navTimeline);
        var allChecklistDone = isChecklistComplete(checklist);

        if (status === 'running') {
            pendingTask = pendingTask || task;
            shouldResume = !!pendingTask;
        } else if (status === 'completed') {
            pendingTask = '';
            shouldResume = false;
        } else if (status === 'error') {
            shouldResume = !!pendingTask && !!cached.shouldResume;
        }

        if (!pendingTask) {
            shouldResume = false;
        }

        writeChatState({
            task: task || cached.task,
            history: mergedHistory,
            status: status,
            updatedAt: Date.now(),
            pendingTask: pendingTask,
            shouldResume: shouldResume,
            startUrl: startUrl,
            currentUrl: currentUrl,
            navTimeline: navTimeline,
            checklist: checklist,
            taskStartedAt: cached.taskStartedAt,
            resumeRequestedAt: cached.resumeRequestedAt,
            taskCompleted: status === 'completed',
        });
    }

    function markTaskForContinuation(taskText) {
        var state = normalizeChatState(readChatState());
        var nowUrl = normalizeUrl(window.location.href);
        var normalizedTask = normalizeTaskText(taskText);
        var isNewTask = !state.task || state.task !== normalizedTask;

        // New task should always reset runtime resume markers.
        resumeLoopActive = false;
        resumeCompletedOnUrl = '';
        
        state.pendingTask = normalizeTaskText(taskText);
        state.shouldResume = true;
        state.status = 'running';
        state.task = normalizedTask;
        state.startUrl = nowUrl;
        state.currentUrl = nowUrl;
        
        if (isNewTask) {
            state.navTimeline = [];
            state.checklist = normalizeChecklist([], state.task);
            state.taskCompleted = false;
        }
        
        state.navTimeline = appendTimelineEntry(state.navTimeline, createNavEntry(nowUrl, 'task_started', 'user'));
        state.taskStartedAt = Date.now();
        state.resumeRequestedAt = Date.now();
        state.updatedAt = Date.now();
        writeChatState(state);
        debugLog('task_marked_for_continuation', {
            isNewTask: isNewTask,
            taskLen: state.pendingTask.length,
            taskPreview: safeTextPreview(state.pendingTask, 140),
        });
    }

    function maybeResumePendingTask() {
        if (resumeLoopActive || !window.pageAgent || typeof window.pageAgent.execute !== 'function') {
            if (!window.pageAgent || typeof window.pageAgent.execute !== 'function') {
                ensureRuntimeScriptLoaded('resume_not_ready');
                debugLog('resume_skip_runtime_not_ready', {});
            }
            return;
        }

        var cached = normalizeChatState(readChatState());
        if (cached.taskCompleted) {
            cached.pendingTask = '';
            cached.shouldResume = false;
            cached.updatedAt = Date.now();
            writeChatState(cached);
            debugLog('resume_skip_task_completed', {});
            return;
        }

        if (!cached.shouldResume || !cached.pendingTask) {
            debugLog('resume_skip_no_pending', {
                shouldResume: cached.shouldResume,
                pendingTaskLen: cached.pendingTask ? cached.pendingTask.length : 0,
            });
            return;
        }

        var nowUrl = normalizeUrl(window.location.href);

        // Already resumed on this page: skip duplicate resume on the same URL.
        if (resumeCompletedOnUrl === nowUrl) {
            resumeCompletedOnUrl = nowUrl;
            debugLog('resume_skip_already_on_url', { url: nowUrl });
            return;
        }

        // Do not block resume based on start URL; tasks may legitimately return to the origin page.

        var resumeTask = cached.pendingTask +
            '\n\n<resume_after_navigation>\n' +
            'Continue the same task seamlessly after page navigation. ' +
            'Do not restart from scratch if previous steps are already completed.\n' +
            '</resume_after_navigation>';

        resumeLoopActive = true;
        var retries = 0;
        var maxRetries = 160;
        debugLog('resume_loop_started', {
            maxRetries: maxRetries,
            pendingTaskLen: cached.pendingTask.length,
        });

        function markResumeSuccess(source) {
            var latest = normalizeChatState(readChatState());
            // Keep shouldResume=true while the task is still active so future navigations can auto-resume.
            latest.shouldResume = !!latest.pendingTask;
            latest.currentUrl = normalizeUrl(window.location.href);
            latest.navTimeline = appendTimelineEntry(
                latest.navTimeline,
                createNavEntry(latest.currentUrl, 'resume_after_navigation', source || 'runtime')
            );
            latest.updatedAt = Date.now();
            writeChatState(latest);
            resumeCompletedOnUrl = latest.currentUrl;
            debugLog('resume_marked_success', {
                source: source || 'runtime',
                currentUrl: latest.currentUrl,
            });
        }

        var timer = setInterval(function () {
            retries += 1;

            if (retries >= maxRetries) {
                clearInterval(timer);
                resumeLoopActive = false;
                debugLog('resume_loop_timeout', {
                    retries: retries,
                    maxRetries: maxRetries,
                });
                return;
            }

            if (!window.pageAgent || typeof window.pageAgent.execute !== 'function') {
                if (retries % 20 === 0) {
                    debugLog('resume_wait_runtime_ready', { retries: retries });
                }
                return;
            }

            var runningTask = normalizeTaskText(typeof window.pageAgent.task === 'string' ? window.pageAgent.task : '');
            var latest = normalizeChatState(readChatState());
            var pending = latest.pendingTask || cached.pendingTask;
            var alreadyRunningSameTask =
                window.pageAgent.status === 'running' &&
                runningTask &&
                pending &&
                runningTask.indexOf(pending) !== -1;

            if (!alreadyRunningSameTask) {
                // Retry execute at controlled cadence until runtime reflects running same task.
                if (retries === 1 || retries % 6 === 0) {
                    try {
                        debugLog('resume_execute_attempt', {
                            retries: retries,
                            pendingTaskLen: pending ? pending.length : 0,
                        });
                        window.pageAgent.execute(resumeTask);
                    } catch (error) {
                        debugLog('resume_execute_error', {
                            retries: retries,
                            message: error && error.message ? error.message : String(error),
                        });
                        return;
                    }
                }
                return;
            }

            markResumeSuccess(alreadyRunningSameTask ? 'runtime_running_detected' : 'runtime_execute');
            clearInterval(timer);
            resumeLoopActive = false;
        }, 250);
    }

    function historyLineFromEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return '';
        }

        if (entry.type === 'step') {
            var name = entry.action && entry.action.name ? entry.action.name : '';
            var out = entry.action && entry.action.output ? entry.action.output : '';
            var memory = entry.reflection && entry.reflection.memory ? entry.reflection.memory : '';
            if (name || out || memory) {
                return '- ' + [name, out, memory].filter(Boolean).join(' | ');
            }
            return '';
        }

        if (entry.type === 'observation' && entry.content) {
            return '- obs: ' + entry.content;
        }

        if (entry.type === 'error' && entry.message) {
            return '- error: ' + entry.message;
        }

        return '';
    }

    function compressHistoryEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return null;
        }

        if (entry.type === 'step') {
            var name = entry.action && entry.action.name ? entry.action.name : '';
            var out = entry.action && entry.action.output ? entry.action.output : '';
            return {
                type: 'compressed_step',
                summary: (name || 'step') + ' | ' + (out || 'no output'),
            };
        }

        if (entry.type === 'observation') {
            return {
                type: 'compressed_obs',
                summary: clipText(typeof entry.content === 'string' ? entry.content : ''),
            };
        }

        if (entry.type === 'error') {
            return {
                type: 'compressed_error',
                summary: clipText(typeof entry.message === 'string' ? entry.message : ''),
            };
        }

        return null;
    }

    function compressHistory(history) {
        // === VS Code Agent Pattern: Compress for context only, NEVER delete ===
        // Keep full history in memory. When sending to LLM: compress old entries into summary if > threshold.
        // This prevents token overflow while maintaining conversation context.
        var list = Array.isArray(history) ? history : [];
        if (list.length <= HISTORY_COMPRESS_THRESHOLD) {
            return list;
        }

        var compressedCount = list.length - MAX_HISTORY_FOR_CONTEXT;
        var compressed = [];

        for (var i = 0; i < compressedCount; i++) {
            var c = compressHistoryEntry(list[i]);
            if (c) {
                compressed.push(c);
            }
        }

        var out = [];
        if (compressed.length > 0) {
            out.push({
                type: 'history_summary',
                count: compressed.length,
                summary: '... (previous ' + compressed.length + ' steps summarized)',
            });
        }

        for (var j = Math.max(0, list.length - MAX_HISTORY_FOR_CONTEXT); j < list.length; j++) {
            out.push(list[j]);
        }

        return out;
    }

    function buildSessionContext() {
        var cached = readChatState();
        if (!cached || !Array.isArray(cached.history) || cached.history.length === 0) {
            return '';
        }

        // === VS Code Agent Pattern ===
        // Keep full history in pageAgent.history (never delete).
        // When sending context: compress history for token limit, keep recent entries + summarize old ones.
        var compressed = compressHistory(cached.history);
        var list = compressed.slice(-MAX_HISTORY_FOR_CONTEXT);
        var lines = [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].type === 'history_summary') {
                lines.push('- ' + list[i].summary);
                continue;
            }

            if (list[i].type === 'compressed_step' && list[i].summary) {
                lines.push('- [compressed] ' + list[i].summary);
                continue;
            }

            if (list[i].type === 'compressed_obs' && list[i].summary) {
                lines.push('- [compressed_obs] ' + list[i].summary);
                continue;
            }

            if (list[i].type === 'compressed_error' && list[i].summary) {
                lines.push('- [compressed_error] ' + list[i].summary);
                continue;
            }

            var line = historyLineFromEntry(list[i]);
            if (line) {
                lines.push(line);
            }
        }

        if (lines.length === 0) {
            return '';
        }

        return lines.join('\n');
    }

    function buildTaskCompletionMarker(checklist, progressReached) {
        // === VS Code Agent Pattern: Mark completion, never delete history ===
        // Signal agent to STOP without looping. Marker stays in context so agent knows task is done.
        if (!progressReached || !Array.isArray(checklist) || checklist.length === 0) {
            return '';
        }

        var allDone = true;
        for (var i = 0; i < checklist.length; i++) {
            if (!checklist[i].done) {
                allDone = false;
                break;
            }
        }

        if (!allDone) {
            return '';
        }

        return '\n<task_completed>\n' +
            'All task steps are complete. Task is FINISHED.\n' +
            'Do NOT attempt to continue. Verify completion once and return the final result.\n' +
            '</task_completed>';
    }

    function waitMs(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    async function refreshIndexSafely(controller) {
        if (!controller || typeof controller.updateTree !== 'function') {
            return;
        }
        try {
            await controller.updateTree();
        } catch (error) {
            // Ignore index refresh failures.
        }
    }

    function getElementFromController(controller, index) {
        if (!controller || !controller.selectorMap || typeof controller.selectorMap.get !== 'function') {
            return null;
        }
        var entry = controller.selectorMap.get(index);
        return entry && entry.ref ? entry.ref : null;
    }

    function isMenuStateOpenFromTrigger(trigger) {
        if (!trigger || !trigger.getAttribute) {
            return false;
        }
        var expanded = trigger.getAttribute('aria-expanded');
        return expanded === 'true';
    }

    function forceRevealNearbyMenu(trigger) {
        if (!trigger || !trigger.closest) {
            return false;
        }

        var group = trigger.closest('.group');
        if (!group) {
            return false;
        }

        var nodes = group.querySelectorAll('*');
        var opened = false;

        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var className = typeof node.className === 'string' ? node.className : '';
            if (!className) {
                continue;
            }

            var looksLikeMenu =
                className.indexOf('group-hover:') !== -1 ||
                className.indexOf('dropdown') !== -1 ||
                className.indexOf('menu') !== -1 ||
                node.getAttribute('role') === 'menu' ||
                node.getAttribute('role') === 'listbox';

            if (!looksLikeMenu) {
                continue;
            }

            node.classList.remove('hidden', 'invisible', 'opacity-0', 'translate-y-2', '-translate-y-2', 'pointer-events-none');
            node.classList.add('visible', 'opacity-100', 'translate-y-0');
            node.style.display = 'block';
            node.style.visibility = 'visible';
            node.style.opacity = '1';
            opened = true;
        }

        return opened;
    }

    function ensureMenuToolRegistered() {
        if (!window.pageAgent || !window.pageAgent.tools || typeof window.pageAgent.tools.get !== 'function') {
            return;
        }

        if (window.pageAgent.tools.has('open_menu_by_index')) {
            return;
        }

        var clickTool = window.pageAgent.tools.get('click_element_by_index');
        if (!clickTool || !clickTool.inputSchema || typeof clickTool.execute !== 'function') {
            return;
        }

        window.pageAgent.tools.set('open_menu_by_index', {
            description: 'Mo menu/dropdown bang click + keyboard fallback, khong phu thuoc hover.',
            inputSchema: clickTool.inputSchema,
            execute: async function (input) {
                try {
                    var controller = this.pageController;
                    if (!controller || typeof controller.assertIndexed !== 'function') {
                        return await clickTool.execute.call(this, input);
                    }

                    controller.assertIndexed();
                    var index = input && typeof input.index === 'number' ? input.index : null;
                    if (index === null) {
                        return '❌ Open menu failed: index is required.';
                    }

                    var beforeCount = controller.selectorMap && typeof controller.selectorMap.size === 'number' ? controller.selectorMap.size : 0;
                    var clickResult = await clickTool.execute.call(this, input);
                    await waitMs(80);
                    await refreshIndexSafely(controller);

                    var element = getElementFromController(controller, index);
                    var trigger = element && element.closest
                        ? (element.closest('[aria-haspopup], [aria-expanded], [data-toggle], [data-dropdown]') || element)
                        : element;

                    var expanded = isMenuStateOpenFromTrigger(trigger);
                    var afterFirst = controller.selectorMap && typeof controller.selectorMap.size === 'number' ? controller.selectorMap.size : beforeCount;
                    if (expanded || afterFirst > beforeCount) {
                        return '✅ Menu opened via click at element [' + index + '].';
                    }

                    if (trigger && trigger.focus) {
                        trigger.focus();
                    }

                    var keys = ['Enter', ' ', 'ArrowDown'];
                    if (trigger && trigger.dispatchEvent) {
                        for (var k = 0; k < keys.length; k++) {
                            var key = keys[k];
                            trigger.dispatchEvent(new KeyboardEvent('keydown', { key: key, code: key, bubbles: true, cancelable: true }));
                            trigger.dispatchEvent(new KeyboardEvent('keyup', { key: key, code: key, bubbles: true, cancelable: true }));
                        }
                    }

                    if (trigger && typeof trigger.click === 'function') {
                        trigger.click();
                    }

                    await waitMs(120);
                    await refreshIndexSafely(controller);

                    expanded = isMenuStateOpenFromTrigger(trigger);
                    var afterSecond = controller.selectorMap && typeof controller.selectorMap.size === 'number' ? controller.selectorMap.size : beforeCount;
                    if (expanded || afterSecond > beforeCount) {
                        return '✅ Menu opened via keyboard/click fallback at element [' + index + '].';
                    }

                    var forced = forceRevealNearbyMenu(trigger || element);
                    if (forced) {
                        await waitMs(50);
                        await refreshIndexSafely(controller);
                        return '⚠️ Menu was force-opened by resilient fallback at element [' + index + '].';
                    }

                    if (typeof clickResult === 'string') {
                        return clickResult + ' (menu state could not be confirmed)';
                    }

                    return '⚠️ Click executed but menu state could not be confirmed at element [' + index + '].';
                } catch (error) {
                    return '❌ Open menu failed: ' + (error && error.message ? error.message : String(error));
                }
            },
        });
    }

    function ensureHoverActsAsClick() {
        if (!window.pageAgent || !window.pageAgent.tools || typeof window.pageAgent.tools.get !== 'function') {
            return;
        }

        ensureMenuToolRegistered();

        var clickTool = window.pageAgent.tools.get('click_element_by_index');
        if (!clickTool || !clickTool.inputSchema || typeof clickTool.execute !== 'function') {
            return;
        }

        var menuTool = window.pageAgent.tools.get('open_menu_by_index');
        var toolToUse = menuTool && typeof menuTool.execute === 'function' ? menuTool : clickTool;

        // Force hover action to use click semantics so the agent no longer depends on CSS :hover.
        window.pageAgent.tools.set('hover_element_by_index', {
            description: 'Hover da duoc chuyen thanh open-menu/click de mo menu/dropdown on dinh hon.',
            inputSchema: toolToUse.inputSchema,
            execute: async function (input) {
                var result = await toolToUse.execute.call(this, input);
                if (typeof result === 'string') {
                    return result + ' (converted from hover to click)';
                }
                return '✅ Converted hover to click and executed successfully.';
            },
        });
    }

    function patchExecuteWithSessionContext() {
        if (!window.pageAgent || typeof window.pageAgent.execute !== 'function') {
            ensureRuntimeScriptLoaded('patch_not_ready');
            debugLog('patch_skip_runtime_not_ready', {});
            return;
        }

        ensureHoverActsAsClick();

        if (window.pageAgent.execute.__paSessionPatched === true) {
            return;
        }

        var originalExecute = window.pageAgent.execute.bind(window.pageAgent);
        var patchedExecute = function (task) {
            var userTask = typeof task === 'string' ? task : '';
            if (!userTask) {
                return originalExecute(task);
            }

             debugLog('execute_intercepted', {
                hasSystemMarkers: hasSystemMarkers(userTask),
                taskLen: userTask.length,
                taskPreview: safeTextPreview(userTask, 140),
            });

            // Only user-originated tasks should be marked as pending continuation.
            if (!hasSystemMarkers(userTask)) {
                markTaskForContinuation(userTask);
            }

            if (userTask.indexOf('<session_context>') !== -1 || userTask.indexOf('[SESSION_CONTEXT]') !== -1) {
                return originalExecute(userTask);
            }

            var nav = buildNavigationContext(userTask);
            var completionMarker = buildTaskCompletionMarker(nav.checklist, nav.progressReached);

            var cached = normalizeChatState(readChatState());
            if (completionMarker) {
                cached.taskCompleted = true;
            }

            writeChatState({
                task: cached.task,
                history: sanitizeHistoryList(cached.history),
                status: cached.status,
                updatedAt: Date.now(),
                pendingTask: cached.pendingTask,
                shouldResume: cached.shouldResume,
                startUrl: nav.startUrl,
                currentUrl: nav.currentUrl,
                navTimeline: nav.navTimeline,
                checklist: nav.checklist,
                taskStartedAt: cached.taskStartedAt,
                resumeRequestedAt: cached.resumeRequestedAt,
                taskCompleted: cached.taskCompleted,
            });

            if (nav.progressReached && userTask.indexOf('<progress_reached_stop>') === -1) {
                userTask += '\n\n<progress_reached_stop>\n' +
                    'Progress appears completed from navigation timeline and history. ' +
                    'Verify outcome once, then stop and return result.\n' +
                    '</progress_reached_stop>';
            }

            var context = buildSessionContext();
            var enhancedTask = userTask +
                '\n\n' +
                nav.context +
                completionMarker;

            if (context) {
                enhancedTask +=
                    '\n\n<session_context>\n' +
                    'Use this previous-session context if relevant to the request:\n' +
                    context +
                    '\n</session_context>';
            }

            return originalExecute(enhancedTask);
        };
        patchedExecute.__paSessionPatched = true;
        window.pageAgent.execute = patchedExecute;
        debugLog('execute_patch_applied', {});
    }

    function clearAgentHistoryNow() {
        clearChatState();
        chatRestoredOnce = true;

        if (!window.pageAgent) {
            return;
        }

        try {
            window.pageAgent.history = [];
            window.pageAgent.task = '';
            window.pageAgent.dispatchEvent(new Event('historychange'));
        } catch (error) {
            // Ignore runtime errors from external lib internals.
        }

        try {
            if (window.pageAgent.panel && typeof window.pageAgent.panel.reset === 'function') {
                window.pageAgent.panel.reset();
            }
        } catch (error) {
            // Ignore UI reset errors.
        }
    }

    function restoreAgentStateIfNeeded() {
        if (chatRestoredOnce || !window.pageAgent) {
            return;
        }

        var cached = readChatState();
        if (!cached || !Array.isArray(cached.history) || cached.history.length === 0) {
            chatRestoredOnce = true;
            return;
        }

        try {
            window.pageAgent.task = typeof cached.task === 'string' ? cached.task : '';
            window.pageAgent.history = sanitizeHistoryList(cached.history);
            window.pageAgent.dispatchEvent(new Event('historychange'));
            chatRestoredOnce = true;
        } catch (error) {
            // Keep trying on next mutation until restored.
        }
    }

    function updateState(patch) {
        var current = readState();
        var next = {
            collapsed: typeof current.collapsed === 'boolean' ? current.collapsed : true,
            position: current.position || null,
            historyExpanded: !!current.historyExpanded,
        };

        if (Object.prototype.hasOwnProperty.call(patch, 'collapsed')) {
            next.collapsed = !!patch.collapsed;
        }

        if (Object.prototype.hasOwnProperty.call(patch, 'position')) {
            next.position = patch.position;
        }

        if (Object.prototype.hasOwnProperty.call(patch, 'historyExpanded')) {
            next.historyExpanded = !!patch.historyExpanded;
        }

        writeState(next);
    }

    function getHistoryToggleButton(panel) {
        if (!panel) return null;
        var buttons = panel.querySelectorAll('button');
        for (var i = 0; i < buttons.length; i++) {
            var text = buttons[i].textContent.trim();
            if (text === '▲' || text === '▼') {
                return buttons[i];
            }
        }
        return null;
    }

    function isHistoryExpanded(panel) {
        if (!panel) return false;
        var btn = getHistoryToggleButton(panel);
        if (btn) {
            return btn.textContent.trim() === '▲';
        }
        return /(^|\s)_expanded_[^\s]+/.test(panel.className || '');
    }

    function applyHistoryExpandedState(panel) {
        if (!panel || panel.classList.contains(HIDDEN_CLASS)) return;
        var state = readState();
        var wantExpanded = !!state.historyExpanded;
        
        var btn = getHistoryToggleButton(panel);
        if (btn) {
            // Only click if it's currently not matching our desired state
            var currentlyExpanded = btn.textContent.trim() === '▲';
            if (currentlyExpanded !== wantExpanded) {
                try {
                    btn.click();
                } catch (e) {
                    // Ignore
                }
            }
        }
    }

    function getCollapsedPreference() {
        var state = readState();
        return typeof state.collapsed === 'boolean' ? state.collapsed : true;
    }

    function savePanelPosition(panel) {
        if (!panel || panel.classList.contains(HIDDEN_CLASS)) {
            return;
        }

        var rect = panel.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            return;
        }

        var left = Math.round(rect.left);
        var top = Math.round(rect.top);

        updateState({
            position: {
                left: left,
                top: top,
            },
            historyExpanded: isHistoryExpanded(panel)
        });
    }

    function forceApplyPosition(panel, left, top) {
        if (!panel) {
            return;
        }

        var panelWidth = panel.offsetWidth || 360;
        var panelHeight = panel.offsetHeight || 40;
        var x = clamp(left, 8, window.innerWidth - panelWidth - 8);
        var y = clamp(top, 8, window.innerHeight - panelHeight - 8);

        panel.style.left = x + 'px';
        panel.style.top = y + 'px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.style.transform = 'none';
        panel.style.transition = 'none';
        panel.setAttribute('data-pa-positioned', '1');
    }

    function applySavedPosition(panel) {
        if (!panel) {
            return;
        }

        var state = readState();
        var pos = state.position;
        if (!pos || typeof pos.left !== 'number' || typeof pos.top !== 'number') {
            return;
        }

        forceApplyPosition(panel, pos.left, pos.top);
    }

    function lockPositionObserver(panel) {
        if (!panel || panel.getAttribute('data-pa-position-guard') === '1') {
            return;
        }

        panel.setAttribute('data-pa-position-guard', '1');
        var ticking = false;

        function enforce() {
            ticking = false;
            if (panel.classList.contains(HIDDEN_CLASS)) {
                return;
            }
            if (panel.getAttribute('data-pa-dragging') === '1') {
                return;
            }
            if (panel.getAttribute('data-pa-positioned') !== '1') {
                return;
            }
            applySavedPosition(panel);
        }

        var obs = new MutationObserver(function () {
            if (ticking) {
                return;
            }
            ticking = true;
            requestAnimationFrame(enforce);
        });

        obs.observe(panel, {
            attributes: true,
            attributeFilter: ['style', 'class'],
        });

        window.addEventListener('resize', function () {
            applySavedPosition(panel);
        });
    }

    function injectStyles() {
        if (document.getElementById('page-agent-ui-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'page-agent-ui-style';
        style.textContent = '' +
            '#' + PANEL_ID + ' {' +
            '  z-index: 2147483646 !important;' +
            '  transition: none !important;' +
            '  background: rgba(43, 40, 68, 0.82) !important;' +
            '  backdrop-filter: blur(20px) saturate(1.4) !important;' +
            '  -webkit-backdrop-filter: blur(20px) saturate(1.4) !important;' +
            '  border: 1px solid rgba(255, 255, 255, 0.12) !important;' +
            '  border-radius: 16px !important;' +
            '  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35), 0 0 40px rgba(139, 92, 246, 0.18) !important;' +
            '  padding: 4px 6px !important;' +
            '}' +
            '#' + PANEL_ID + '[data-pa-dragging="1"] {' +
            '  transition: none !important;' +
            '  cursor: grabbing !important;' +
            '}' +
            '#' + PANEL_ID + ' [class*="_controls_"] button {' +
            '  background: rgba(255, 255, 255, 0.08) !important;' +
            '  color: rgba(255, 255, 255, 0.85) !important;' +
            '  border-radius: 8px !important;' +
            '  border: 1px solid rgba(255, 255, 255, 0.05) !important;' +
            '  margin-left: 5px !important;' +
            '  padding: 2px 8px !important;' +
            '  font-family: inherit !important;' +
            '  font-size: 11px !important;' +
            '  transition: all 0.2s ease !important;' +
            '}' +
            '#' + PANEL_ID + ' [class*="_controls_"] button:hover {' +
            '  background: rgba(255, 255, 255, 0.16) !important;' +
            '  transform: translateY(-1px) !important;' +
            '}' +
            '#' + PANEL_ID + ' [class*="_controls_"] button.page-agent-collapse-btn {' +
            '  min-width: 28px !important;' +
            '}' +
            '#' + PANEL_ID + ' .page-agent-clear-btn {' +
            '  color: #fb7185 !important;' +
            '}' +
            '#' + PANEL_ID + ' .page-agent-clear-btn:hover {' +
            '  background: rgba(251, 113, 133, 0.15) !important;' +
            '  color: #fda4af !important;' +
            '}' +
            '#' + PANEL_ID + ' [class*="_status_"] {' +
            '  color: #fff !important;' +
            '  font-weight: 700 !important;' +
            '  font-size: 12px !important;' +
            '  letter-spacing: 0.02em !important;' +
            '  margin-right: 12px !important;' +
            '  text-shadow: 0 0 12px rgba(96, 165, 250, 0.4) !important;' +
            '}' +
            '#' + PANEL_ID + ' input[type="text"] {' +
            '  background: rgba(255, 255, 255, 0.05) !important;' +
            '  border: 1px solid rgba(255, 255, 255, 0.1) !important;' +
            '  border-radius: 10px !important;' +
            '  color: #fff !important;' +
            '  padding: 8px 14px !important;' +
            '  outline: none !important;' +
            '  transition: border-color 0.2s ease !important;' +
            '}' +
            '#' + PANEL_ID + ' input[type="text"]:focus {' +
            '  border-color: rgba(139, 92, 246, 0.5) !important;' +
            '  background: rgba(255, 255, 255, 0.08) !important;' +
            '}' +
            '#' + PANEL_ID + '.' + HIDDEN_CLASS + ' {' +
            '  display: none !important;' +
            '  opacity: 0 !important;' +
            '  pointer-events: none !important;' +
            '}' +
            '#' + LAUNCHER_ID + ' {' +
            '  position: fixed;' +
            '  right: 25px;' +
            '  bottom: 25px;' +
            '  width: 58px;' +
            '  height: 58px;' +
            '  border: 3px solid #fff;' +
            '  border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;' +
            '  background: linear-gradient(135deg, #7c3aed, #db2777);' +
            '  color: #fff;' +
            '  cursor: pointer;' +
            '  box-shadow: 0 10px 30px rgba(124, 58, 237, 0.45);' +
            '  display: flex;' +
            '  align-items: center;' +
            '  justify-content: center;' +
            '  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), border-radius 0.4s ease;' +
            '  z-index: 2147483647;' +
            '  animation: pa-blob-float 6s ease-in-out infinite;' +
            '}' +
            '#' + LAUNCHER_ID + '.page-agent-hidden {' +
            '  opacity: 0;' +
            '  pointer-events: none;' +
            '  transform: scale(0.6);' +
            '}' +
            '@keyframes pa-blob-float {' +
            '  0% { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; transform: translateY(0) rotate(0deg); }' +
            '  50% { border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; transform: translateY(-10px) rotate(5deg); }' +
            '  100% { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; transform: translateY(0) rotate(0deg); }' +
            '}' +
            '#' + LAUNCHER_ID + '::after {' +
            '  content: "AI Agent";' +
            '  position: absolute;' +
            '  right: 110%;' +
            '  top: 50%;' +
            '  transform: translateY(-50%);' +
            '  background: rgba(15, 23, 42, 0.8);' +
            '  backdrop-filter: blur(8px);' +
            '  color: white;' +
            '  padding: 6px 12px;' +
            '  border-radius: 8px;' +
            '  font-size: 11px;' +
            '  font-weight: 700;' +
            '  white-space: nowrap;' +
            '  opacity: 0;' +
            '  transition: opacity 0.3s ease, right 0.3s ease;' +
            '  pointer-events: none;' +
            '}' +
            '#' + LAUNCHER_ID + ':hover::after {' +
            '  opacity: 1;' +
            '  right: 120%;' +
            '}' +
            '#' + LAUNCHER_ID + ' svg {' +
            '  width: 26px;' +
            '  height: 26px;' +
            '  transition: transform 0.3s ease;' +
            '}' +
            '#' + LAUNCHER_ID + ':hover {' +
            '  transform: scale(1.1);' +
            '  border-radius: 50%;' +
            '}' +
            '#' + LAUNCHER_ID + ':active {' +
            '  transform: scale(0.95);' +
            '}' +
            '#' + LAUNCHER_ID + ':hover svg {' +
            '  transform: scale(1.15) rotate(-5deg);' +
            '}' +
            '#' + LAUNCHER_ID + ':active {' +
            '  transform: translateY(-2px) scale(0.95);' +
            '}' +
            '#' + LAUNCHER_ID + '.page-agent-hidden {' +
            '  display: none !important;' +
            '}' +
            '.page-agent-collapse-btn {' +
            '  width: 24px;' +
            '  height: 24px;' +
            '  border: none;' +
            '  border-radius: 6px;' +
            '  color: #fff;' +
            '  cursor: pointer;' +
            '  background: linear-gradient(135deg, rgba(217, 119, 6, 0.96), rgba(180, 83, 9, 0.96));' +
            '  box-shadow: 0 6px 12px rgba(163, 74, 11, 0.3);' +
            '}' +
            '.page-agent-clear-btn {' +
            '  width: 24px;' +
            '  height: 24px;' +
            '  border: none;' +
            '  border-radius: 6px;' +
            '  color: #fff;' +
            '  cursor: pointer;' +
            '  background: linear-gradient(135deg, rgba(95, 84, 66, 0.96), rgba(66, 56, 38, 0.96));' +
            '  box-shadow: 0 6px 12px rgba(55, 48, 38, 0.25);' +
            '}';

        document.head.appendChild(style);
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(value, max));
    }

    function makeDraggable(panel) {
        var header = panel.querySelector('[class*="_header_"]');
        if (!header || header.getAttribute('data-pa-draggable') === '1') {
            return;
        }

        header.setAttribute('data-pa-draggable', '1');
        header.style.cursor = 'move';

        var isDragging = false;
        var offsetX = 0;
        var offsetY = 0;
        var moved = false;
        var startX = 0;
        var startY = 0;
        var rAFId = null;

        function onMouseMove(event) {
            if (!isDragging) {
                return;
            }

            var dx = Math.abs(event.clientX - startX);
            var dy = Math.abs(event.clientY - startY);
            if (!moved && (dx > 3 || dy > 3)) {
                moved = true;
            }

            if (rAFId) {
                cancelAnimationFrame(rAFId);
            }

            var clientX = event.clientX;
            var clientY = event.clientY;

            rAFId = requestAnimationFrame(function () {
                var panelWidth = panel.offsetWidth || 360;
                var panelHeight = panel.offsetHeight || 40;
                var nextLeft = clamp(clientX - offsetX, 8, window.innerWidth - panelWidth - 8);
                var nextTop = clamp(clientY - offsetY, 8, window.innerHeight - panelHeight - 8);

                panel.style.left = nextLeft + 'px';
                panel.style.top = nextTop + 'px';
                panel.style.right = 'auto';
                panel.style.bottom = 'auto';
                panel.style.transform = 'none';
                panel.style.transition = 'none';
                panel.setAttribute('data-pa-positioned', '1');
            });
        }

        function stopDragging() {
            if (rAFId) {
                cancelAnimationFrame(rAFId);
                rAFId = null;
            }
            panel.setAttribute('data-pa-dragging', '0');
            isDragging = false;
            savePanelPosition(panel);
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', stopDragging);
        }

        header.addEventListener('mousedown', function (event) {
            var target = event.target;
            if (target && target.closest && target.closest('[class*="_controlButton_"]')) {
                return;
            }

            event.preventDefault();
            var rect = panel.getBoundingClientRect();
            isDragging = true;
            moved = false;
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            startX = event.clientX;
            startY = event.clientY;

            panel.setAttribute('data-pa-dragging', '1');
            panel.style.left = rect.left + 'px';
            panel.style.top = rect.top + 'px';
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
            panel.style.transform = 'none';
            panel.style.transition = 'none';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', stopDragging);
        });

        header.addEventListener('click', function (event) {
            if (moved) {
                event.preventDefault();
                event.stopPropagation();
                moved = false;
            }
        }, true);
    }

    function getOrCreateLauncher() {
        var launcher = document.getElementById(LAUNCHER_ID);
        if (launcher) {
            return launcher;
        }

        launcher = document.createElement('button');
        launcher.id = LAUNCHER_ID;
        launcher.type = 'button';
        launcher.title = 'Mở Trợ lý Ýn Uống';
        launcher.setAttribute('aria-label', 'Open Food AI');
        launcher.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-concierge-bell"><path d="M2 18h20"/><path d="M12 2v4"/><path d="M12 22v-4"/><path d="M16 9h.01"/><path d="M8 9h.01"/><path d="M12 18a10 10 0 0 0 10-10H2a10 10 0 0 0 10 10Z"/><path d="M21 2l1.5 3.5L26 7l-3.5 1.5L21 12l-1.5-3.5L16 7l3.5-1.5z" transform="translate(-5, -4) scale(0.6)" fill="currentColor"/></svg>';
        document.body.appendChild(launcher);
        return launcher;
    }

    function findClosestFixedContainer(node) {
        var current = node;
        var depth = 0;
        while (current && current !== document.body && depth < 12) {
            if (current.nodeType === 1) {
                var style = window.getComputedStyle(current);
                if (style && (style.position === 'fixed' || style.position === 'sticky')) {
                    return current;
                }
            }
            current = current.parentElement;
            depth += 1;
        }
        return null;
    }

    function findAgentPanelByTaskInput() {
        var fields = document.querySelectorAll('textarea, input[type="text"]');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var placeholder = (field.getAttribute('placeholder') || '').toLowerCase();
            if (!placeholder) {
                continue;
            }
            if (placeholder.indexOf('enter new task') === -1 && placeholder.indexOf('new task') === -1) {
                continue;
            }

            var fixedContainer = findClosestFixedContainer(field);
            if (fixedContainer) {
                return fixedContainer;
            }

            if (field.parentElement) {
                return field.parentElement;
            }
        }
        return null;
    }

    function findAgentPanel() {
        if (cachedPanel && document.body.contains(cachedPanel)) {
            return cachedPanel;
        }
        var panel = document.getElementById(PANEL_ID);
        if (panel) {
            cachedPanel = panel;
            return panel;
        }
        panel = document.querySelector('[id*="runtime_agent-panel"]');
        if (panel) {
            cachedPanel = panel;
            return panel;
        }

        panel = document.querySelector('[id*="agent-panel"], [id*="agent_panel"], [class*="agent-panel"], [class*="agent_panel"]');
        if (panel) {
            cachedPanel = panel;
            return panel;
        }

        panel = findAgentPanelByTaskInput();
        if (panel) {
            debugLog('panel_found_by_task_input', {});
        }
        cachedPanel = panel;
        return panel;
    }

    function waitAndShowPanel() {
        var retries = 0;
        var maxRetries = 80;
        var timer = setInterval(function () {
            retries += 1;

            ensureHoverActsAsClick();

            if (window.pageAgent && window.pageAgent.panel && typeof window.pageAgent.panel.show === 'function') {
                window.pageAgent.panel.show();
            }

            patchExecuteWithSessionContext();
            maybeResumePendingTask();

            var panel = findAgentPanel();
            if (panel) {
                launcherOpenRequested = false;
                enhancePanel(panel);
                if (window.pageAgent && window.pageAgent.panel && typeof window.pageAgent.panel.show === 'function') {
                    window.pageAgent.panel.show();
                }
                showPanelFromLauncher(panel);
                clearInterval(timer);
                return;
            }

            if (retries >= maxRetries) {
                clearInterval(timer);
            }
        }, 250);
    }

    function bindLauncher(launcher) {
        if (!launcher || launcher.getAttribute('data-pa-launcher-bound') === '1') {
            return;
        }

        launcher.setAttribute('data-pa-launcher-bound', '1');
        launcher.addEventListener('click', function () {
            launcherOpenRequested = true;
            ensureHoverActsAsClick();
            var panel = findAgentPanel();
            if (window.pageAgent && window.pageAgent.panel && typeof window.pageAgent.panel.show === 'function') {
                window.pageAgent.panel.show();
            }

            patchExecuteWithSessionContext();
            maybeResumePendingTask();

            if (panel) {
                launcherOpenRequested = false;
                enhancePanel(panel);
                showPanelFromLauncher(panel);
                return;
            }

            waitAndShowPanel();
        });
    }

    function hidePanelToLauncher(panel) {
        if (!panel) {
            return;
        }

        panel.classList.add(HIDDEN_CLASS);
        updateState({ collapsed: true });
        var launcher = getOrCreateLauncher();
        launcher.classList.remove('page-agent-hidden');
        persistAgentState();
    }

    function showPanelFromLauncher(panel) {
        if (!panel) {
            return;
        }

        panel.classList.remove(HIDDEN_CLASS);
        panel.style.opacity = '1';
        panel.style.display = 'block';
        panel.style.transition = 'none';

        if (panel.getAttribute('data-pa-positioned') === '1') {
            applySavedPosition(panel);
        } else {
            // First open: lock panel to a concrete absolute position immediately
            // so internal agent re-renders (e.g. after pressing Enter) cannot shift it.
            var panelWidth = panel.offsetWidth || 360;
            var panelHeight = panel.offsetHeight || 40;
            var defaultLeft = window.innerWidth - panelWidth - 20;
            var defaultTop = window.innerHeight - panelHeight - 95;
            forceApplyPosition(panel, defaultLeft, defaultTop);
            savePanelPosition(panel);
        }

        var launcher = getOrCreateLauncher();
        launcher.classList.add('page-agent-hidden');
        updateState({ collapsed: false });
        persistAgentState();
        
        applyHistoryExpandedState(panel);
    }

    function makeIconToggle(panel) {
        var controls = panel.querySelector('[class*="_controls_"]');
        if (!controls || controls.querySelector('[data-pa-collapse-btn="1"]')) {
            return;
        }

        var clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.textContent = 'C';
        clearButton.title = 'Xoa lich su chat';
        clearButton.className = 'page-agent-clear-btn';
        clearButton.setAttribute('data-pa-clear-btn', '1');

        clearButton.addEventListener('click', function (event) {
            event.stopPropagation();
            clearAgentHistoryNow();
        });

        controls.insertBefore(clearButton, controls.firstChild);

        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = '-';
        button.title = 'Thu nhỏ thành icon';
        button.className = 'page-agent-collapse-btn';
        button.setAttribute('data-pa-collapse-btn', '1');

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            hidePanelToLauncher(panel);
        });

        controls.insertBefore(button, controls.firstChild);

        var launcher = getOrCreateLauncher();
        bindLauncher(launcher);

        launcher.classList.add('page-agent-hidden');
    }

    function enhancePanel(panel) {
        if (!panel || panel.getAttribute(ENHANCED_ATTR) === '1') {
            return;
        }

        panel.setAttribute(ENHANCED_ATTR, '1');
        panel.style.zIndex = '2147483646';
        panel.style.transition = 'none';

        injectStyles();
        applySavedPosition(panel);
        lockPositionObserver(panel);
        makeDraggable(panel);
        makeIconToggle(panel);
        restoreAgentStateIfNeeded();
        patchExecuteWithSessionContext();
        maybeResumePendingTask();

        var state = normalizeChatState(readChatState());
        var alwaysShowRunning = false; // Do not force open if user intentionally collapsed it
        
        if (alwaysShowRunning && (state.status === 'running' || state.shouldResume)) {
            showPanelFromLauncher(panel);
        } else if (getCollapsedPreference()) {
            hidePanelToLauncher(panel);
        } else {
            showPanelFromLauncher(panel);
        }
    }

    function init() {
        injectStyles();
        ensureRuntimeScriptLoaded('init');
        ensureHoverActsAsClick();

        var launcher = getOrCreateLauncher();
        bindLauncher(launcher);
        launcher.classList.remove('page-agent-hidden');

        var panel = findAgentPanel();
        if (panel) {
            enhancePanel(panel);
        }

        // Patch execute with session context immediately.
        patchExecuteWithSessionContext();

        // Resume pending task if one exists.
        maybeResumePendingTask();

        setInterval(persistAgentState, 1500);

        setInterval(function () {
            patchExecuteWithSessionContext();
            maybeResumePendingTask();

            if (!launcherOpenRequested) {
                return;
            }

            if (window.pageAgent && window.pageAgent.panel && typeof window.pageAgent.panel.show === 'function') {
                window.pageAgent.panel.show();
            }

            var pendingPanel = findAgentPanel();
            if (pendingPanel) {
                launcherOpenRequested = false;
                enhancePanel(pendingPanel);
                showPanelFromLauncher(pendingPanel);
            }
        }, 800);

        window.addEventListener('beforeunload', function () {
            persistNavigationCheckpoint('beforeunload');
            persistAgentState();
        });
        window.addEventListener('pagehide', function () {
            persistNavigationCheckpoint('pagehide');
            persistAgentState();
        });

        // Immediate state flush on navigation/form submission
        document.addEventListener('submit', function () {
            persistNavigationCheckpoint('submit');
            persistAgentState();
            var panel = findAgentPanel();
            if (panel) savePanelPosition(panel);
        });

        document.addEventListener('click', function (e) {
            var target = e.target;
            while (target && target !== document && target.tagName !== 'A') {
                target = target.parentNode;
            }
            if (target && target.tagName === 'A') {
                persistNavigationCheckpoint('anchor_click');
                persistAgentState();
                var panel = findAgentPanel();
                if (panel) savePanelPosition(panel);
            }
        });

        window.addEventListener('storage', function (e) {
            if (e.key === STATE_KEY) {
                inMemoryState = null; // force reload
                var panel = findAgentPanel();
                if (panel) {
                    applySavedPosition(panel);
                    if (getCollapsedPreference()) {
                        hidePanelToLauncher(panel);
                    } else {
                        showPanelFromLauncher(panel);
                    }
                }
            } else if (e.key === CHAT_STATE_KEY) {
                inMemoryChatState = null; // force reload
                chatRestoredOnce = false; // allow restore again
                restoreAgentStateIfNeeded();
                maybeResumePendingTask();
                
                var panel2 = findAgentPanel();
                if (panel2) {
                    var st = normalizeChatState(readChatState());
                    var alwaysShowRunning = false;
                    if (alwaysShowRunning && (st.status === 'running' || st.shouldResume)) {
                        showPanelFromLauncher(panel2);
                    }
                }
            }
        });

        var observerTimer = null;
        var observer = new MutationObserver(function () {
            var p = findAgentPanel();
            if (p && p.getAttribute(ENHANCED_ATTR) !== '1') {
                enhancePanel(p);
            }

            if (observerTimer) clearTimeout(observerTimer);
            observerTimer = setTimeout(function () {
                var p2 = findAgentPanel();
                if (p2) {
                    launcherOpenRequested = false;
                    enhancePanel(p2);
                    restoreAgentStateIfNeeded();
                    patchExecuteWithSessionContext();
                    maybeResumePendingTask();
                    persistAgentState();
                    if (!p2.classList.contains(HIDDEN_CLASS)) {
                        var launcher = document.getElementById(LAUNCHER_ID);
                        if (launcher) {
                            launcher.classList.add('page-agent-hidden');
                        }
                    }
                } else {
                    var onlyLauncher = document.getElementById(LAUNCHER_ID);
                    if (onlyLauncher) {
                        onlyLauncher.classList.remove('page-agent-hidden');
                    }
                }
            }, 250);
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
