document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const UI_TEXT = {
        ku: {
            pageTitle: '🔗 هژمارتنا خزمایەتیێ و هەمی رێکێن گەهشتنێ',
            pageSubtitle: 'دوو کەسان هەلبژێرە بۆ دیتنا هەمی رێکێن پەیوەندیێ دناڤبەرا وان دا (ب رێکا زەلامان، ژنان، یان هەڤژینیێ)',
            person1Label: 'کەسێ ئێکێ:',
            person2Label: 'کەسێ دووێ (بەرامبەر):',
            searchPlaceholder1: '🔍 ل ناڤێ سێ قۆلی بگەڕێ (بۆ نموونە: محمد خالد حسن)...',
            searchPlaceholder2: '🔍 ل ناڤێ سێ قۆلی بگەڕێ (بۆ نموونە: عيسا سعيد حسن)...',
            modeLabel: 'شێوازێ لێگەڕیان و نیشاندانا رێکێن گەهشتنێ:',
            modeAll: '🌟 هەمی خيارێن گەهشتنێ (زەلام + ژن + هەڤژین و خزمایەتی)',
            modeParents: '👨‍👩‍👧‍👦 باب و دایک (رێکا باب و باپیران + رێکا دایک و داپیران پێکڤە)',
            modeMales: '👨 رێکا زەلامان بتنێ (زنجیرا باب و باپیران بتنێ)',
            modeSpouses: '💍 رێکا هەڤژینیێ بتنێ (ژن و مێر)',
            calcBtn: '🔍 نیشاندانا هەمی رێکێن گەهشتنێ',
            noMatches: 'چ کەس ب ڤی ناڤی نەهاتنە دیتن',
            motherPrefix: 'دایک:',
            selectAlert: 'هیڤیە کەسێ ئێکێ و کەسێ دووێ هەلبژێرە!',
            samePersonAlert: 'تە هەمان کەس ل هەردوو جهان هەلبژارتیە! هیڤیە دوو کەسێن جودا هەلبژێرە.',
            noPathsTitle: '⚠️ چ رێکێن گەهشتنێ د ڤی شێوازی دا نەهاتنە دیتن',
            noPathsSub: 'شێوازێ نیشاندانێ بگوهۆرە بۆ "هەمی خيارێن گەهشتنێ (زەلام + ژن + هەڤژین)" دا هەمی رێک ديار ببن.',
            statTotal: 'سەرجەمێ رێکێن هاتینە دیتن:',
            statMale: '👨 ب رێکا زەلامان (باب/باپیر):',
            statFemale: '👩 ب رێکا ژنان (دایک/داپیر):',
            statSpouse: '💍 ب رێکا هەڤژینیێ:',
            showAllPaths: '📋 نیشاندانا هەمی رێکان پێکڤە',
            pathWord: 'رێکا',
            optionWord: 'خيارێ',
            stepsWord: 'گاڤ',
            stepsCountLabel: 'هژمارا گاڤان:',
            meetsAt: '🌳 دگەهنە ئێک ل نک:'
        },
        ar: {
            pageTitle: '🔗 حاسبة صلة القرابة وجميع مسارات التوصيل',
            pageSubtitle: 'اختر شخصين لمعرفة جميع طرق الاتصال بينهما (عبر الذكور، الإناث والجدات، أو الأزواج والمصاهرة)',
            person1Label: 'الشخص الأول:',
            person2Label: 'الشخص المقابل (الثاني):',
            searchPlaceholder1: '🔍 ابحث بالاسم الثلاثي (مثال: محمد خالد حسن)...',
            searchPlaceholder2: '🔍 ابحث بالاسم الثلاثي (مثال: عيسا سعيد حسن)...',
            modeLabel: 'طريقة البحث وعرض مسارات التوصيل:',
            modeAll: '🌟 كل خيارات التوصيل الممكنة (ذكور + إناث + أزواج ومصاهرة)',
            modeParents: '👨‍👩‍👧‍👦 أب وأم (عرض طريق الذكور/الجد + طريق الإناث/الجدة معاً)',
            modeMales: '👨 طريق الذكور فقط (سلسلة الآباء والأجداد فقط)',
            modeSpouses: '💍 طريق الأزواج والمصاهرة فقط',
            calcBtn: '🔍 عرض جميع مسارات التوصيل',
            noMatches: 'لا توجد نتائج مطابقة لهذا الاسم',
            motherPrefix: 'الأم:',
            selectAlert: 'يرجى اختيار الشخص الأول والشخص الثاني أولاً.',
            samePersonAlert: 'لقد اخترت نفس الشخص في الخانتين! يرجى اختيار شخصين مختلفين.',
            noPathsTitle: '⚠️ لم يتم العثور على مسار توصيل في هذا الوضع',
            noPathsSub: 'جرب تغيير طريقة العرض إلى "كل خيارات التوصيل الممكنة" للبحث في جميع المسارات.',
            statTotal: 'إجمالي طرق التوصيل المكتشفة:',
            statMale: '👨 عبر الذكور (الأب/الجد):',
            statFemale: '👩 عبر الإناث (الأم/الجدة):',
            statSpouse: '💍 عبر الأزواج والمصاهرة:',
            showAllPaths: '📋 عرض كل طرق التوصيل معاً',
            pathWord: 'طريق',
            optionWord: 'الخيار',
            stepsWord: 'خطوات',
            stepsCountLabel: 'عدد الخطوات:',
            meetsAt: '🌳 يلتقيان عند:'
        }
    };


    const extra = {
        ar: {
            pageTitle: 'مسارات صلة القرابة', pageSubtitle: 'اختر شخصين لعرض كل طرق النسب إلى الأجداد والجدات المشتركين. نفحص الأب والأم في كل جيل.',
            modeAll: 'كل المسارات: الآباء والأمهات والزواج', modeParents: 'كل طرق الأجداد المشتركين، عبر الأب والأم', modeMales: 'سلسلة الآباء والأجداد',
            modeFemales: 'مسارات تمر بالأم أو الجدة', modeSpouses: 'مسارات تحتوي على زواج مسجّل',
            calcBtn: 'اعرض مسارات التوصيل', depthLabel: 'أقصى عدد روابط في المسار', depthHint: 'زد العدد للبحث عن طرق أبعد. لا يتكرر الشخص داخل المسار الواحد.',
            loading: 'جارٍ تحميل الأسماء وروابط الزواج...', loaded: '{people} شخص · {marriages} رابطة زواج', empty: 'لا يوجد أشخاص مسجلون بعد.',
            loadError: 'تعذّر تحميل البيانات. حاول مجددًا.', authError: 'انتهت جلسة الدخول. سجّل الدخول مجددًا.', login: 'تسجيل الدخول', retry: 'إعادة المحاولة',
            searchError: 'تعذّر إكمال البحث. اضغط عرض المسارات للمحاولة مجددًا.', depthError: 'اختر عدد روابط بين 1 و {max}.',
            searching: 'جارٍ البحث. عُثر على {count} مسار ضمن {depth} روابط.', partial: 'نعرض {count} مسارًا ضمن {depth} روابط. توجد عمليات بحث أو نتائج إضافية.',
            complete: 'اكتمل البحث ضمن {depth} روابط. العدد: {count} مسار.', more: 'ابحث عن مسارات إضافية', waiting: 'جارٍ البحث...',
            noPathsTitle: 'لم يظهر مسار ضمن نطاق البحث الحالي', noPathsSub: 'يمكنك زيادة عدد الروابط أو تغيير طريقة البحث.',
            tooShort: 'أقصر مسار في هذا الوضع يحتاج إلى {count} روابط. زد نطاق البحث.',
            filterEmpty: 'لا توجد نتائج في هذه الفئة حتى الآن.', showAllPaths: 'عرض المسارات معًا', choosePath: 'اختر مسارًا واحدًا',
            pause: 'إيقاف الحركة', play: 'تشغيل الحركة', replay: 'إعادة الحركة', speed: 'السرعة', view: 'العرض', tree: 'شجرة', chain: 'مسار متسلسل',
            pan: 'اسحب المخطط لرؤية بقية الأسماء. استخدم + و − للتكبير.', zoomIn: 'تكبير المخطط', zoomOut: 'تصغير المخطط', fit: 'احتواء المخطط', home: 'الرئيسية', clear: 'مسح الاختيار',
            statTotal: 'كل المسارات', statMale: 'الآباء', statFemale: 'عبر الأم', statSpouse: 'المصاهرة', resultId: 'رقم',
            meetsAt: 'يلتقيان عند:', first: 'الشخص الأول', second: 'الشخص الثاني'
        },
        ku: {
            pageTitle: 'ڕێکێن پەیوەندییا خزماتیێ', pageSubtitle: 'دوو کەسان هەلبژێرە بۆ دیتنا ڕێکێن گەهشتنێ ب ڕێکا باوک، دایک، زارۆک و هەڤژینییا تۆمارکری.',
            modeAll: 'هەمی ڕێک: باوک و دایک و هەڤژین', modeParents: 'هەمی ڕێکێن باپیر و داپیرێن هەڤبەش، ب ڕێکا باوک و دایکێ', modeMales: 'زنجیرا باوک و باپیران',
            modeFemales: 'ڕێکێن ب دایک و داپیرێ دەرباز دبن', modeSpouses: 'ڕێکێن هەڤژینییا تۆمارکری تێدا هەی',
            calcBtn: 'ڕێکێن گەهشتنێ نیشان بدە', depthLabel: 'زۆرترین ژمارەیا پەیوەندیان د ڕێکەکێ دا', depthHint: 'ژمارەیێ زێدە بکە بۆ ڕێکێن دویرتر. کەس د هەمان ڕێکێ دا ناهێتە دووبارەکرن.',
            loading: 'ناڤ و پەیوەندیێن هەڤژینیێ دهێنە بارکرن...', loaded: '{people} کەس · {marriages} پەیوەندیێن هەڤژینیێ', empty: 'هێشتا چ کەس نەهاتینە تۆمارکرن.',
            loadError: 'داتا نەهاتنە بارکرن. دووبارە هەوڵ بدە.', authError: 'دانیشتن ب دوماهیک هات. دووبارە بچۆ ژوور.', login: 'چوونە ژوور', retry: 'دووبارە هەوڵ بدە',
            searchError: 'گەڕان تەمام نەبوو. دووبارە هەوڵ بدە.', depthError: 'ژمارەیەکێ ژ 1 هەتا {max} هەلبژێرە.',
            searching: 'گەڕان بەردەوامە. {count} ڕێک د سنوورێ {depth} پەیوەندیان دا هاتینە دیتن.',
            partial: '{count} ڕێک د سنوورێ {depth} پەیوەندیان دا. هێشتا ئەنجام یان گەڕانا زیاتر هەیە.',
            complete: 'گەڕان د سنوورێ {depth} پەیوەندیان دا تەمام بوو. {count} ڕێک.', more: 'ل ڕێکێن زیاتر بگەڕێ', waiting: 'گەڕان بەردەوامە...',
            noPathsTitle: 'د سنوورێ گەڕانێ یێ نوکە دا چ ڕێک نەهاتە دیتن', noPathsSub: 'ژمارەیا پەیوەندیان زێدە بکە یان شێوازێ گەڕانێ بگوهۆڕە.',
            tooShort: 'کورتترین ڕێک پێدڤی ب {count} پەیوەندیان هەیە. سنوورێ گەڕانێ زێدە بکە.',
            filterEmpty: 'هێشتا د ڤێ بەشێ دا ئەنجام نینن.', showAllPaths: 'ڕێکان پێکڤە نیشان بدە', choosePath: 'ڕێکەکێ هەلبژێرە',
            pause: 'جوولینێ ڕابوەستینە', play: 'جوولینێ پێبکە', replay: 'دووبارە پێبکە', speed: 'خێرایی', view: 'شێواز', tree: 'زنجیر', chain: 'ڕێکا ڕێکخستی',
            pan: 'نەخشەیێ بکێشە بۆ دیتنا ناڤان. + و − بۆ مەزنکرنێ.', zoomIn: 'نەخشەیێ مەزن بکە', zoomOut: 'نەخشەیێ بچووک بکە', fit: 'هەمیێ ببینە', home: 'سەرەکی', clear: 'هەلبژارتنێ پاقژ بکە',
            statTotal: 'هەمی ڕێک', statMale: 'باوک', statFemale: 'ب ڕێکا دایکێ', statSpouse: 'هەڤژینی', resultId: 'ژمارە',
            meetsAt: 'دگەهنە ئێک ل نک:', first: 'کەسێ ئێکێ', second: 'کەسێ دوویێ'
        }
    };
    Object.assign(extra.ar, {
        ancestorsSearching: 'جارٍ فحص جميع الأجيال المسجلة. اكتُشف {count} طريق إلى {ancestors} أصل مشترك.',
        ancestorsPartial: 'اكتُشف {count} طريق إلى {ancestors} أصل مشترك. اضغط متابعة لفحص بقية الاحتمالات.',
        ancestorsComplete: 'اكتمل فحص جميع الأجيال المسجلة: {count} طريق إلى {ancestors} أصل مشترك.',
        ancestorsMore: 'تابع عرض طرق الأجداد', ancestorChoose: 'الجد أو الجدة المشتركة', ancestorAll: 'كل الأجداد والجدات المشتركين',
        ancestorHelp: 'الفئات توضّح جهة البداية عند الأول والثاني. نتابع الأب والأم في كل جيل. كل مجموعة تعرض نقطة التقاء الفرعين دون تكرار الأشخاص.',
        ancestorCount: '{count} طريق مكتشف', ancestorDone: 'اكتمل', ancestorPending: 'الفحص مستمر',
        originFF: 'أب الأول · أب الثاني', originFM: 'أب الأول · أم الثاني', originMF: 'أم الأول · أب الثاني', originMM: 'أم الأول · أم الثاني', originDirect: 'أحدهما أصل للآخر',
        ancestorEmpty: 'لم نجد أصلًا مشتركًا في روابط النسب المسجلة', ancestorEmptyHelp: 'يعتمد البحث على روابط الأب والأم المسجلة، حتى آخر جيل متاح.',
        branchFirst: 'طريق الشخص الأول إلى الأصل', branchSecond: 'طريق الشخص الثاني إلى الأصل',
        allGenerations: 'جميع الأجيال المسجلة', branchesSummary: 'بداية الفرعين:', branchCount: 'عدد الروابط إلى الأصل:'
    });
    Object.assign(extra.ku, {
        ancestorsSearching: 'هەمی نەسلێن تۆمارکری دهێنە پشکنین. {count} ڕێک بۆ {ancestors} ڕەگێن هەڤبەش هاتنە دیتن.',
        ancestorsPartial: '{count} ڕێک بۆ {ancestors} ڕەگێن هەڤبەش. بۆ ڕێکێن مایین بەردەوام بە.',
        ancestorsComplete: 'هەمی نەسلێن تۆمارکری هاتنە پشکنین: {count} ڕێک بۆ {ancestors} ڕەگێن هەڤبەش.',
        ancestorsMore: 'ڕێکێن باپیران بەردەوام بکە', ancestorChoose: 'باپیر یان داپیرا هەڤبەش', ancestorAll: 'هەمی باپیر و داپیرێن هەڤبەش',
        ancestorHelp: 'بەش دەستپێکا ڕێکا کەسێ ئێکێ و دوویێ نیشان ددەن. د هەر نەسلەکێ دا باوک و دایک دهێنە پشکنین. کەس د هەمان ڕێکێ دا ناهێتە دووبارەکرن.',
        ancestorCount: '{count} ڕێک هاتنە دیتن', ancestorDone: 'تەواو بوو', ancestorPending: 'پشکنین بەردەوامە',
        originFF: 'باوکێ ئێکێ · باوکێ دوویێ', originFM: 'باوکێ ئێکێ · دایکا دوویێ', originMF: 'دایکا ئێکێ · باوکێ دوویێ', originMM: 'دایکا ئێکێ · دایکا دوویێ', originDirect: 'ئێک ژ ڕەگێن یێ دی یە',
        ancestorEmpty: 'د پەیوەندیێن تۆمارکری دا ڕەگەکێ هەڤبەش نەهاتە دیتن', ancestorEmptyHelp: 'گەڕان ب ڕێکا باوک و دایکا تۆمارکری هەتا دوماهیک نەسل دچیت.',
        branchFirst: 'ڕێکا کەسێ ئێکێ بۆ ڕەگی', branchSecond: 'ڕێکا کەسێ دوویێ بۆ ڕەگی',
        allGenerations: 'هەمی نەسلێن تۆمارکری', branchesSummary: 'دەستپێکا هەردوو ڕێکان:', branchCount: 'ژمارەیا پەیوەندیان بۆ ڕەگی:'
    });
    for (const language of ['ar', 'ku']) Object.assign(UI_TEXT[language], extra[language]);
    const $ = id => document.getElementById(id);
    const Core = window.RelationshipCore;
    let currentLang = window.DEFAULT_LANG || 'ku';
    const t = (key, values) => Object.entries(values || {}).reduce((text, [name, value]) => text.split('{' + name + '}').join(String(value)), UI_TEXT[currentLang][key] || key);
    const el = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const svgEl = (tag, attrs) => { const node = document.createElementNS('http://www.w3.org/2000/svg', tag); for (const [key, value] of Object.entries(attrs || {})) node.setAttribute(key, value); return node; };
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let rawPeople = [], rawMarriages = [], peopleMap = {}, sortedPeople = [], loaded = false;
    let paths = [], signatures = new Set(), snapshot = null, busy = false, requestId = 0;
    let ancestorFilter = '';
    const ancestorMode = () => $('relationshipMode').value === 'parents';
    const originKey = type => ({ ff: 'originFF', fm: 'originFM', mf: 'originMF', mm: 'originMM', direct: 'originDirect' })[type];
    let category = 'all', chosenPath = '', worker = null, fallback = null, fallbackTimer = null;
    let renderTimer = null, animations = [], diagramObservers = [], paused = false, cardCache = new Map(), activeRequest = null;
    const pickers = [];
    const stateStore = window.RelationshipState.create(window, window.RELATIONSHIP_USER_ID);
    const selectedIds = ['', ''], drafts = ['', ''];
    let stateReady = false, restorePending = null, departedState = null, restoringLayout = false;
    let savedDiagrams = {}, saveTimer = null, resumeTimer = null, dataController = null, dataVersion = 0;

    function captureState() {
        if (restorePending) return restorePending;
        const diagrams = Object.assign({}, savedDiagrams);
        for (const [signature, card] of cardCache) {
            const viewport = card.querySelector('.rel-tree-viewport');
            if (viewport && viewport.dataset.scale) diagrams[signature] = {
                scale: Number(viewport.dataset.scale), left: viewport.scrollLeft, top: viewport.scrollTop
            };
        }
        return {
            first: selection(1), second: selection(2), draftFirst: drafts[0], draftSecond: drafts[1],
            language: currentLang, mode: $('relationshipMode').value, depth: Number($('maxPathDepth').value),
            view: $('routeView').value, speed: Number($('animationSpeed').value),
            category, ancestor: ancestorFilter, route: chosenPath, paused,
            hasResults: $('relationshipResults').style.display === 'block', complete: !!(snapshot && snapshot.complete),
            counts: Object.fromEntries((snapshot && snapshot.categories || []).map(item => [item.type, item.count])),
            diagrams, scroll: window.scrollY
        };
    }
    function saveState() {
        if (saveTimer !== null) clearTimeout(saveTimer);
        saveTimer = null;
        if (!stateReady || restoringLayout) return;
        stateStore.save(captureState());
    }
    function scheduleSave() {
        if (!stateReady || restoringLayout) return;
        if (saveTimer !== null) clearTimeout(saveTimer);
        saveTimer = setTimeout(saveState, 200);
    }
    function restoreTargets(state) {
        return Object.fromEntries(Object.entries(state.counts || {}).map(([key, count]) => [key, Math.max(1, count + (state.complete ? 1 : 0))]));
    }
    function finishRestore() {
        const state = restorePending;
        if (!state) return;
        restorePending = null;
        category = state.category; ancestorFilter = state.ancestor; chosenPath = state.route;
        if (ancestorFilter && !paths.some(path => path.commonAncestor && path.commonAncestor.id === ancestorFilter)) ancestorFilter = '';
        if (ancestorMode() ? !['all', 'ff', 'fm', 'mf', 'mm', 'direct'].includes(category) : !['all', 'male', 'female', 'spouse'].includes(category)) category = 'all';
        paused = state.paused; updateAnimationButton();
        restoringLayout = true;
        clearAnimations(); cardCache.clear(); renderResults();
        const id = requestId;
        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (id !== requestId) return;
            window.scrollTo(0, state.scroll); restoringLayout = false; saveState();
        }));
    }
    function requestMore(targets) {
        if (busy || !snapshot || snapshot.complete) return;
        busy = true; updateStatus();
        if (fallback) requestFallbackPage(targets);
        else if (worker) worker.postMessage({ type: 'more', requestId, targets });
    }
    function syncSelections() {
        for (const index of [1, 2]) {
            const person = peopleMap[selection(index)];
            $('person' + index + 'Id').value = person ? person.id : '';
            $('searchPerson' + index).value = person ? person.triple_name : drafts[index - 1];
            $('searchPerson' + index).classList.toggle('has-selection', !!person);
            $('clearPerson' + index).style.display = person || drafts[index - 1] ? 'flex' : 'none';
            pickers[index - 1].close();
        }
    }


    function message(text) { $('relationshipMessage').textContent = text || ''; $('relationshipMessage').hidden = !text; }
    function selection(index) { return selectedIds[index - 1]; }
    function cancelSearch() {
        requestId++;
        if (resumeTimer !== null) clearTimeout(resumeTimer);
        resumeTimer = null;
        if (worker) worker.postMessage({ type: 'cancel' });
        if (fallbackTimer !== null) clearTimeout(fallbackTimer);
        fallbackTimer = null; fallback = null; busy = false;
    }
    function clearAnimations() {
        animations.forEach(animation => animation.cancel()); animations = [];
        diagramObservers.forEach(observer => observer.disconnect()); diagramObservers = [];
    }
    function invalidate() {
        cancelSearch(); clearAnimations(); cardCache.clear(); paths = []; signatures.clear(); snapshot = null;
        restorePending = null; restoringLayout = false; savedDiagrams = {}; activeRequest = null;
        category = 'all'; chosenPath = ''; ancestorFilter = '';
        if (renderTimer !== null) clearTimeout(renderTimer);
        renderTimer = null;
        $('pathsViewport').replaceChildren(); $('relationshipStatsBar').replaceChildren(); $('pathsTabsContainer').replaceChildren();
        $('relationshipResults').style.display = 'none'; $('ancestorControls').replaceChildren(); $('ancestorControls').hidden = true; message('');
    }
    function applyLanguage(language) {
        currentLang = ['ar', 'ku'].includes(language) ? language : 'ku';
        document.documentElement.lang = currentLang;
        document.title = t('pageTitle');
        document.querySelectorAll('.lang-switch-btn').forEach(button => button.classList.toggle('active', button.dataset.lang === currentLang));
        const ids = { uiPageTitle: 'pageTitle', uiPageSubtitle: 'pageSubtitle', uiPerson1Label: 'person1Label', uiPerson2Label: 'person2Label', uiModeLabel: 'modeLabel', optModeAll: 'modeAll', optModeParents: 'modeParents', optModeMales: 'modeMales', optModeFemales: 'modeFemales', optModeSpouses: 'modeSpouses', calculateBtn: 'calcBtn', uiDepthLabel: 'depthLabel', uiDepthHint: 'depthHint', replayAnimation: 'replay', uiSpeedLabel: 'speed', uiViewLabel: 'view', optViewTree: 'tree', optViewChain: 'chain' };
        for (const [id, key] of Object.entries(ids)) $(id).textContent = t(key);
        $('uiHome').title = t('home'); $('uiHome').setAttribute('aria-label', t('home'));
        for (const index of [1, 2]) {
            $('searchPerson' + index).placeholder = t('searchPlaceholder' + index);
            $('clearPerson' + index).title = t('clear'); $('clearPerson' + index).setAttribute('aria-label', t('clear'));
        }
        $('swapPeopleBtn').title = currentLang === 'ar' ? 'تبديل الشخصين' : 'جهێ کەسان بگوهۆڕە';
        $('swapPeopleBtn').setAttribute('aria-label', $('swapPeopleBtn').title);
        updateAnimationButton(); updateLoadState(); updateScope();

    }
    function updateLoadState() {
        if (loaded) $('relationshipLoadState').textContent = rawPeople.length ? t('loaded', { people: rawPeople.length, marriages: rawMarriages.length }) : t('empty');
    }
    function updateScope() { $('pathDepthControls').hidden = ancestorMode(); }
    function updateUrl() { saveState(); }

    function setPerson(index, id, calculate) {
        const person = peopleMap[id];
        selectedIds[index - 1] = person ? person.id : ''; drafts[index - 1] = '';
        $('person' + index + 'Id').value = person ? person.id : '';
        $('searchPerson' + index).value = person ? person.triple_name : '';
        $('searchPerson' + index).classList.toggle('has-selection', !!person);
        $('clearPerson' + index).style.display = person ? 'flex' : 'none';
        pickers[index - 1].close();
        if (calculate !== false) { invalidate(); if (selection(1) && selection(2)) runCalculation(); else saveState(); }
    }
    function setupPicker(index) {
        const input = $('searchPerson' + index), dropdown = $('dropdownPerson' + index);
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', dropdown.id); input.setAttribute('aria-expanded', 'false');
        dropdown.setAttribute('role', 'listbox');
        let matches = [], active = -1;
        function close() { dropdown.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
        function highlight() {
            dropdown.querySelectorAll('[role=option]').forEach((node, offset) => { node.classList.toggle('active', offset === active); node.setAttribute('aria-selected', offset === active ? 'true' : 'false'); });
            const option = dropdown.children[active];
            if (option) { input.setAttribute('aria-activedescendant', option.id); option.scrollIntoView({ block: 'nearest' }); }
        }
        function open() {
            if (!loaded || !rawPeople.length) return;
            const words = Core.normalizeSearchText(selection(index) ? '' : input.value).split(' ').filter(Boolean);
            matches = sortedPeople.filter(person => words.every(word => person.search_index.includes(word))).slice(0, 60);
            dropdown.replaceChildren(); active = -1;
            for (const person of matches) {
                const option = el('div', 'rel-dropdown-item'); option.dataset.id = person.id; option.id = 'option-' + index + '-' + person.id;
                option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
                const main = el('div', 'item-main');
                main.append(el('span', 'item-icon', person.gender === 'F' ? '👩' : '👨'), el('span', 'item-triple-name', person.triple_name));
                if (person.mother_name) main.append(el('span', 'item-mother', t('motherPrefix') + ' ' + person.mother_name));
                option.append(main, el('span', 'item-lineage-sub', '#' + person.id));
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => setPerson(index, person.id));
                dropdown.append(option);
            }
            if (!matches.length) dropdown.append(el('div', 'rel-dropdown-empty', t('noMatches')));
            dropdown.classList.add('open'); input.setAttribute('aria-expanded', 'true');
        }
        input.addEventListener('focus', open); input.addEventListener('click', open);
        input.addEventListener('input', () => {
            selectedIds[index - 1] = ''; drafts[index - 1] = input.value;
            $('person' + index + 'Id').value = ''; input.classList.remove('has-selection');
            $('clearPerson' + index).style.display = input.value ? 'flex' : 'none'; invalidate(); saveState(); open();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') close();
            else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault(); if (!dropdown.classList.contains('open')) open();
                if (matches.length) { active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length; highlight(); }
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (dropdown.classList.contains('open') && matches[active]) setPerson(index, matches[active].id);
                else if (dropdown.classList.contains('open') && matches.length === 1) setPerson(index, matches[0].id);
                else if (selection(1) && selection(2)) runCalculation();
            }
        });
        input.addEventListener('blur', () => setTimeout(close, 150));
        $('clearPerson' + index).addEventListener('click', () => { setPerson(index, null, false); invalidate(); saveState(); input.focus(); });
        document.addEventListener('pointerdown', event => { if (!input.parentElement.contains(event.target)) close(); });
        pickers.push({ close });
    }

    function launchFallback(startMessage) {
        if (worker) { worker.terminate(); worker = null; }
        fallback = Core.createSearch(startMessage.first, startMessage.second, rawPeople, rawMarriages, startMessage.mode, currentLang, { maxDepth: startMessage.maxDepth });
        requestFallbackPage(startMessage.targets);
    }
    function requestFallbackPage(requestedTargets) {
        const id = requestId, started = Date.now(), before = snapshot ? snapshot.expanded : 0;
        const targets = Object.fromEntries(fallback.categories.map(type => [type, requestedTargets && Number.isSafeInteger(requestedTargets[type]) ? requestedTargets[type] : ((snapshot && snapshot.categories.find(item => item.type === type).count) || 0) + (fallback.pageSize || 4)]));
        function pump() {
            if (id !== requestId || !fallback) return;
            try {
                const result = fallback.advance(targets, 2000, 10);
                const done = result.pageComplete || result.complete || result.expanded - before >= 120000 || Date.now() - started > 1600;
                receive(Object.assign({}, result, { type: done ? 'page' : 'progress', requestId: id }));
                if (!done) fallbackTimer = setTimeout(pump, 0);
            } catch (_) { searchFailed(); }
        }
        fallbackTimer = setTimeout(pump, 0);
    }
    function runCalculation(options) {
        if (!loaded) return;
        const restored = options && options.restore;
        if (!selection(1) || !selection(2)) { message(t('selectAlert')); return; }
        const maximum = Math.max(1, rawPeople.length - 1), depth = ancestorMode() ? maximum : Number($('maxPathDepth').value);
        updateScope();
        if (!Number.isInteger(depth) || depth < 1 || depth > maximum) { message(t('depthError', { max: maximum })); return; }
        invalidate(); restorePending = restored || null; savedDiagrams = restored ? restored.diagrams : {};
        paused = !!(restored && restored.paused); updateAnimationButton();
        $('relationshipResults').style.display = 'block'; busy = true; updateStatus(); updateUrl();
        const data = { type: 'start', requestId, first: selection(1), second: selection(2), people: rawPeople, marriages: rawMarriages, mode: $('relationshipMode').value, lang: currentLang, maxDepth: depth, targets: restored ? restoreTargets(restored) : undefined };
        activeRequest = data;
        if (typeof Worker !== 'undefined') {
            try {
                if (!worker) {
                    worker = new Worker('assets/relationship-worker.js?v=4');
                    worker.onmessage = event => receive(event.data);
                    worker.onerror = event => { event.preventDefault(); if (busy && activeRequest) launchFallback(activeRequest); };
                }
                worker.postMessage(data);
            } catch (_) { launchFallback(data); }
        } else launchFallback(data);
    }
    function searchFailed() { busy = false; message(t('searchError')); updateStatus(); }
    function receive(data) {
        if (data.requestId !== requestId) return;
        if (data.type === 'error') { searchFailed(); return; }
        snapshot = data;
        for (const path of data.paths) if (!signatures.has(path.signature)) { signatures.add(path.signature); paths.push(path); }
        busy = data.type !== 'page';
        if (renderTimer !== null) clearTimeout(renderTimer);
        renderTimer = null;
        if (restorePending && !busy) {
            if (!data.complete && !data.pageComplete) {
                const id = requestId, targets = restoreTargets(restorePending);
                resumeTimer = setTimeout(() => { if (id === requestId) requestMore(targets); }, 0);
            } else finishRestore();
        }
        updateStatus();
        if (!restoringLayout) renderTimer = setTimeout(renderResults, busy ? 60 : 0);
        if (!busy) scheduleSave();
    }
    function updateStatus() {
        const complete = snapshot && snapshot.complete;
        const key = ancestorMode() ? (busy ? 'ancestorsSearching' : complete ? 'ancestorsComplete' : 'ancestorsPartial') : (busy ? 'searching' : complete ? 'complete' : 'partial');
        $('pathSearchStatus').textContent = t(key, { count: paths.length, depth: $('maxPathDepth').value, ancestors: new Set(paths.map(path => path.commonAncestor && path.commonAncestor.id).filter(Boolean)).size });
        $('pathSearchStatus').dataset.complete = complete ? 'true' : 'false';
        $('relationshipResults').setAttribute('aria-busy', busy ? 'true' : 'false');
        $('loadMorePaths').hidden = !!complete;
        $('loadMorePaths').disabled = busy;
        $('loadMorePaths').textContent = t(busy ? 'waiting' : ancestorMode() ? 'ancestorsMore' : 'more');
        $('calculateBtn').setAttribute('aria-busy', busy ? 'true' : 'false');
    }
    function filteredPaths() {
        let list = paths.filter(path => (category === 'all' || (ancestorMode() ? path.originCategory : path.pathType) === category) && (!ancestorMode() || !ancestorFilter || path.commonAncestor.id === ancestorFilter));
        list.sort((a, b) => a.stepsCount - b.stepsCount || a.signature.localeCompare(b.signature));
        if (category === 'all' && !ancestorMode()) {
            const groups = ['male', 'female', 'spouse'].map(type => list.filter(path => path.pathType === type));
            list = [];
            for (let index = 0; groups.some(group => group[index]); index++) for (const group of groups) if (group[index]) list.push(group[index]);
        }
        return list;
    }
    function renderResults() {
        renderTimer = null; scheduleSave();
        const stats = el('div', 'rel-stats-summary');
        const statTypes = ancestorMode() ? [['all', 'statTotal'], ['ff', 'originFF'], ['fm', 'originFM'], ['mf', 'originMF'], ['mm', 'originMM'], ...(paths.some(path => path.originCategory === 'direct') ? [['direct', 'originDirect']] : [])] : [['all', 'statTotal'], ['male', 'statMale'], ['female', 'statFemale'], ['spouse', 'statSpouse']];
        stats.classList.toggle('rel-origin-stats', ancestorMode());
        const scoped = paths.filter(path => !ancestorMode() || !ancestorFilter || path.commonAncestor.id === ancestorFilter);
        for (const [type, key] of statTypes) {
            const count = type === 'all' ? scoped.length : scoped.filter(path => (ancestorMode() ? path.originCategory : path.pathType) === type).length;
            const button = el('button', 'stat-pill stat-' + (type === 'all' ? 'total' : type) + (category === type ? ' active' : ''));
            button.type = 'button'; button.dataset.category = type;
            button.setAttribute('aria-pressed', category === type ? 'true' : 'false');
            button.append(el('span', '', t(key)), el('strong', '', count));
            button.addEventListener('click', () => { category = type; chosenPath = ''; renderResults(); });
            stats.append(button);
        }
        $('relationshipStatsBar').replaceChildren(stats);
        renderAncestorControls();
        const list = filteredPaths();
        const allButton = el('button', 'rel-tab-btn' + (!chosenPath ? ' active' : ''), t('showAllPaths') + ' (' + list.length + ')');
        allButton.type = 'button'; allButton.addEventListener('click', () => { chosenPath = ''; renderResults(); });
        const select = el('select', 'rel-route-select'); select.id = 'selectedPathSelect'; select.setAttribute('aria-label', t('choosePath'));
        select.append(el('option', '', t('choosePath'))); select.firstChild.value = '';
        list.forEach(path => { const option = el('option', '', '#' + (paths.indexOf(path) + 1) + ' · ' + (path.ancestorRoute ? path.commonAncestor.name + ' · ' + t(originKey(path.originCategory)) : path.badgeText) + ' · ' + path.stepsCount + ' ' + t('stepsWord')); option.value = path.signature; select.append(option); });
        if (!list.some(path => path.signature === chosenPath)) chosenPath = '';
        select.value = chosenPath;
        select.addEventListener('change', () => { chosenPath = select.value; renderResults(); });
        $('pathsTabsContainer').replaceChildren(allButton, select);
        const visible = chosenPath ? list.filter(path => path.signature === chosenPath) : list;
        $('pathAnimationControls').hidden = !visible.length;
        if (!visible.length) {
            const empty = el('div', 'rel-empty-box');
            empty.append(el('h3', '', busy ? t('waiting') : paths.length ? t('filterEmpty') : t(ancestorMode() ? 'ancestorEmpty' : 'noPathsTitle')));
            let hint = t(ancestorMode() ? 'ancestorEmptyHelp' : 'noPathsSub');
            if (snapshot && !paths.length && !ancestorMode()) {
                const minima = snapshot.categories.map(item => item.minimum).filter(value => value !== null);
                if (minima.length && Math.min(...minima) > Number($('maxPathDepth').value)) hint = t('tooShort', { count: Math.min(...minima) });
            }
            empty.append(el('p', '', hint)); $('pathsViewport').replaceChildren(empty); return;
        }
        const cards = visible.map(path => {
            if (!cardCache.has(path.signature)) cardCache.set(path.signature, makeCard(path, paths.indexOf(path) + 1));
            return cardCache.get(path.signature);
        });
        if (ancestorMode()) {
            const groups = new Map();
            visible.forEach((path, index) => {
                const id = path.commonAncestor.id;
                if (!groups.has(id)) {
                    const group = el('section', 'rel-ancestor-group'); group.dataset.ancestorId = id;
                    const meta = snapshot && snapshot.ancestors.find(item => item.person.id === id);
                    const heading = el('h2', 'rel-ancestor-heading', t('meetsAt') + ' ' + path.commonAncestor.triple_name);
                    heading.append(el('span', '', (meta ? meta.count : 0) + ' ' + t('pathWord') + ' · ' + t(meta && meta.complete ? 'ancestorDone' : 'ancestorPending')));
                    group.append(heading); groups.set(id, group);
                }
                groups.get(id).append(cards[index]);
            });
            $('pathsViewport').replaceChildren(...groups.values());
        } else $('pathsViewport').replaceChildren(...cards);
    }

    function renderAncestorControls() {
        const container = $('ancestorControls'); container.hidden = !ancestorMode();
        if (!ancestorMode()) { container.replaceChildren(); return; }
        const label = el('label', '', t('ancestorChoose')); label.htmlFor = 'ancestorSelect';
        const select = el('select', 'rel-route-select'); select.id = 'ancestorSelect';
        const all = el('option', '', t('ancestorAll')); all.value = ''; select.append(all);
        for (const item of (snapshot && snapshot.ancestors || [])) {
            if (!item.count) continue;
            const option = el('option', '', item.person.triple_name + ' · #' + item.person.id + ' · ' + item.count + (item.complete ? '' : '+'));
            option.value = item.person.id; select.append(option);
        }
        select.value = ancestorFilter;
        select.addEventListener('change', () => { ancestorFilter = select.value; chosenPath = ''; renderResults(); });
        container.replaceChildren(label, select, el('p', '', t('ancestorHelp')));
    }

    function animateNode(node, index, lineLength) {
        if (reducedMotion.matches || typeof node.animate !== 'function') return;
        const frames = lineLength ? [{ strokeDashoffset: lineLength }, { strokeDashoffset: 0 }] : [{ opacity: 0, transform: 'translateY(9px)' }, { opacity: 1, transform: 'translateY(0)' }];
        const animation = node.animate(frames, { duration: lineLength ? 480 : 350, delay: index * 460, fill: 'both', easing: 'cubic-bezier(.2,.7,.2,1)' });
        animation.id = 'relationship-path';
        animation.playbackRate = Number($('animationSpeed').value);
        if (restoringLayout) animation.finish();
        if (paused) animation.pause();
        animations.push(animation);
    }
    function personNode(step, endpoint, common) {
        const person = step.person;
        const node = el('a', 'rel-person-node ' + (person.gender === 'F' ? 'node-female' : 'node-male') + (endpoint ? ' node-endpoint' : '') + (common ? ' node-common' : ''));
        node.href = 'tree.php?id=' + step.id; node.dataset.personId = step.id; node.title = person.triple_name;
        const info = el('div', 'node-info');
        info.append(el('div', 'node-name', person.name), el('div', 'node-sub', person.triple_name), el('div', 'node-id', '#' + person.id));
        node.append(el('span', 'node-icon', Array.from(person.name.trim())[0] || '?'), info);
        return node;
    }
    function makeCard(path, number) {
        const card = el('article', 'rel-path-card card-' + path.pathType); card.dataset.signature = path.signature; card.dataset.pathType = path.pathType;
        const header = el('div', 'rel-card-header'), title = el('div', 'rel-card-title');
        title.append(el('span', 'rel-path-number', t('optionWord') + ' #' + number), el('span', 'rel-badge ' + path.badgeClass, path.ancestorRoute ? t(originKey(path.originCategory)) : path.badgeText), el('strong', 'rel-kinship-title', path.relationshipTitle));
        header.append(title, el('div', 'rel-steps-count', t('stepsCountLabel') + ' ' + path.stepsCount)); card.append(header);
        if (path.commonAncestor) {
            const common = el('div', 'rel-common-ancestor'); common.append(t('meetsAt') + ' ', el('strong', '', path.commonAncestor.triple_name)); card.append(common);
        }
        if (path.ancestorRoute && path.steps.length > 1) {
            const branches = el('div', 'rel-lineage-branches');
            const first = path.steps.slice(0, path.ancestorIndex + 1);
            const second = path.steps.slice(path.ancestorIndex).reverse();
            for (const [key, steps, fromSecond] of [['branchFirst', first, false], ['branchSecond', second, true]]) {
                const branch = el('div', 'rel-lineage-branch'); branch.append(el('strong', '', t(key)));
                const line = el('p');
                steps.forEach((step, index) => {
                    if (index) {
                        const kind = fromSecond ? steps[index - 1].parentKind : step.parentKind;
                        line.append(el('span', 'rel-branch-link', ' ← ' + Core.I18N[currentLang][kind] + ': '));
                    }
                    line.append(el('span', 'rel-branch-person', step.person.name));
                });
                branch.append(line); branches.append(branch);
            }
            card.append(branches);
        }
        if ($('routeView').value === 'chain') {
            const chain = el('div', 'rel-visual-chain');
            path.steps.forEach((step, index) => {
                if (index) {
                    const arrow = el('div', 'rel-arrow edge-' + step.edgeCategory);
                    arrow.append(el('span', 'rel-edge-label', step.edgeLabel), el('span', 'rel-arrow-icon', '←')); chain.append(arrow); animateNode(arrow, index - .5);
                }
                const node = personNode(step, !index || index === path.steps.length - 1, index === path.ancestorIndex);
                chain.append(node); animateNode(node, index);
            }); card.append(chain);
        } else drawTree(card, path);
        return card;
    }

    function drawTree(card, path) {
        const count = path.steps.length, pivot = path.ancestorIndex;
        const bloodTree = pivot !== null;
        const positions = [];
        let width = 500, height = 180;
        if (bloodTree) {
            if (pivot === 0 || pivot === count - 1) width = 280;
            for (let i = 0; i < count; i++) {
                const x = i === pivot || pivot === 0 || pivot === count - 1 ? width / 2 : i < pivot ? width / 2 + 110 : width / 2 - 110;
                positions.push({ x, y: 45 + Math.abs(i - pivot) * 142 });
            }
        } else {
            let level = 0; const levels = [0];
            for (let i = 1; i < count; i++) { if (['father', 'mother'].includes(path.steps[i].edgeType)) level--; else if (['son', 'daughter'].includes(path.steps[i].edgeType)) level++; levels.push(level); }
            const minimum = Math.min(...levels);
            const rows = new Map();
            levels.forEach((row, i) => { if (!rows.has(row)) rows.set(row, []); rows.get(row).push(i); });
            width = Math.max(280, Math.max(...Array.from(rows.values(), row => row.length)) * 220 + 60);
            for (let i = 0; i < count; i++) {
                const row = rows.get(levels[i]);
                positions.push({ x: width / 2 + ((row.length - 1) / 2 - row.indexOf(i)) * 220, y: 45 + (levels[i] - minimum) * 142 });
            }
        }
        height = Math.max(...positions.map(point => point.y)) + 130;
        const controls = el('div', 'rel-route-tools');
        const minus = el('button', 'rel-zoom-button', '−'), plus = el('button', 'rel-zoom-button', '+'), fit = el('button', 'rel-zoom-button', '⤢'), output = el('output', '', '85%');
        minus.type = plus.type = fit.type = 'button'; minus.setAttribute('aria-label', t('zoomOut')); plus.setAttribute('aria-label', t('zoomIn')); fit.setAttribute('aria-label', t('fit'));
        controls.append(plus, output, minus, fit, el('span', 'rel-pan-hint', t('pan')));
        const viewport = el('div', 'rel-tree-viewport'); viewport.tabIndex = 0;
        const spacer = el('div', 'rel-scene-spacer'), scene = el('div', 'rel-route-scene');
        scene.style.width = width + 'px'; scene.style.height = height + 'px';
        const svg = svgEl('svg', { width, height, 'aria-hidden': 'true' }); scene.append(svg);
        for (let i = 1; i < count; i++) {
            const first = positions[i - 1], second = positions[i], step = path.steps[i];
            let start, end;
            if (first.y === second.y) {
                start = { x: first.x - 94, y: first.y + 39 }; end = { x: second.x + 94, y: second.y + 39 };
            } else if (first.y > second.y) {
                start = { x: first.x, y: first.y }; end = { x: second.x, y: second.y + 78 };
            } else {
                start = { x: first.x, y: first.y + 78 }; end = { x: second.x, y: second.y };
            }
            if (path.ancestorRoute && start.y < end.y) [start, end] = [end, start];
            const d = 'M' + start.x + ',' + start.y + ' C' + start.x + ',' + ((start.y + end.y) / 2) + ' ' + end.x + ',' + ((start.y + end.y) / 2) + ' ' + end.x + ',' + end.y;
            const color = step.edgeCategory === 'spouse' ? '#fbbf24' : step.parentKind === 'mother' ? '#f472b6' : '#60a5fa';
            const base = svgEl('path', { d, stroke: color, fill: 'none', 'stroke-width': 2, opacity: .22 });
            if (step.parentKind === 'mother') base.setAttribute('stroke-dasharray', '6 5');
            svg.append(base);
            const glow = svgEl('path', { d, stroke: color, fill: 'none', 'stroke-width': 2.5, class: 'rel-flow-line' });
            svg.append(glow); const length = glow.getTotalLength(); glow.setAttribute('stroke-dasharray', String(length));
            animateNode(glow, bloodTree ? Math.min(i - 1, count - 1 - i) : i - .6, length);
            const middle = glow.getPointAtLength(length / 2);
            const label = svgEl('g'); label.append(svgEl('rect', { x: middle.x - 29, y: middle.y - 12, width: 58, height: 24, rx: 10, fill: '#15263d', stroke: color, 'stroke-width': .7 }));
            const text = svgEl('text', { x: middle.x, y: middle.y + 4, 'text-anchor': 'middle', fill: color, 'font-size': 11 }); text.textContent = path.ancestorRoute ? Core.I18N[currentLang][step.parentKind] : step.edgeLabel; label.append(text); svg.append(label);
        }
        path.steps.forEach((step, index) => {
            const point = positions[index], node = personNode(step, index === 0 || index === count - 1, index === pivot);
            node.style.left = point.x - 94 + 'px'; node.style.top = point.y + 'px'; scene.append(node);
            const delay = bloodTree ? index === pivot ? Math.max(pivot, count - 1 - pivot) : index < pivot ? index : count - 1 - index : index;
            animateNode(node, delay);
        });
        let scale = .85, lastWidth = 0;
        function resize() { viewport.dataset.scale = String(scale); scene.style.transform = 'scale(' + scale + ')'; spacer.style.width = width * scale + 'px'; spacer.style.height = height * scale + 'px'; output.textContent = Math.round(scale * 100) + '%'; }
        function fitScene() {
            if (!viewport.clientWidth) return;
            lastWidth = viewport.clientWidth;
            const availableHeight = parseFloat(getComputedStyle(viewport).maxHeight) || 520;
            scale = Math.min(.85, Math.max(.1, (viewport.clientWidth - 18) / width), Math.max(.1, (availableHeight - 18) / height)); resize();
            viewport.scrollLeft = Math.max(0, (viewport.scrollWidth - viewport.clientWidth) / 2);
        }
        minus.addEventListener('click', () => { scale = Math.max(.1, scale / 1.2); resize(); scheduleSave(); });
        plus.addEventListener('click', () => { scale = Math.min(1.7, scale * 1.2); resize(); scheduleSave(); });
        fit.addEventListener('click', () => { fitScene(); scheduleSave(); });
        viewport.addEventListener('scroll', scheduleSave, { passive: true });
        resize(); spacer.append(scene); viewport.append(spacer); card.append(controls, viewport);
        let initialLayout = true;
        requestAnimationFrame(() => {
            const stored = savedDiagrams[path.signature];
            if (stored && viewport.clientWidth) {
                lastWidth = viewport.clientWidth; scale = stored.scale; resize();
                viewport.scrollLeft = stored.left; viewport.scrollTop = stored.top;
            } else fitScene();
            initialLayout = false;
        });
        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(() => { if (!initialLayout && viewport.clientWidth && viewport.clientWidth !== lastWidth) fitScene(); });
            observer.observe(viewport); diagramObservers.push(observer);
        }
    }

    function updateAnimationButton() { $('toggleAnimation').textContent = t(paused ? 'play' : 'pause'); $('toggleAnimation').setAttribute('aria-pressed', paused ? 'true' : 'false'); }
    $('toggleAnimation').addEventListener('click', () => { paused = !paused; animations.forEach(animation => paused ? animation.pause() : animation.play()); updateAnimationButton(); scheduleSave(); });
    $('replayAnimation').addEventListener('click', () => { paused = false; animations.forEach(animation => { animation.currentTime = 0; animation.play(); }); updateAnimationButton(); scheduleSave(); });
    $('animationSpeed').addEventListener('change', () => { animations.forEach(animation => { animation.playbackRate = Number($('animationSpeed').value); }); saveState(); });
    $('routeView').addEventListener('change', () => { savedDiagrams = captureState().diagrams; clearAnimations(); cardCache.clear(); renderResults(); saveState(); });
    $('loadMorePaths').addEventListener('click', () => requestMore());
    $('calculateBtn').addEventListener('click', runCalculation);
    $('relationshipMode').addEventListener('change', () => { updateScope(); if (selection(1) && selection(2)) runCalculation(); else saveState(); });
    $('maxPathDepth').addEventListener('change', () => { if (selection(1) && selection(2)) runCalculation(); else saveState(); });
    $('swapPeopleBtn').addEventListener('click', () => { const first = selection(1), second = selection(2); setPerson(1, second, false); setPerson(2, first, false); invalidate(); if (first && second) runCalculation(); else saveState(); });
    document.querySelectorAll('.lang-switch-btn').forEach(button => button.addEventListener('click', () => { applyLanguage(button.dataset.lang); if (selection(1) && selection(2)) runCalculation(); else saveState(); }));
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) animations.forEach(animation => animation.finish()); });
    setupPicker(1); setupPicker(2); applyLanguage(currentLang);

    async function loadData(state) {
        state = state || stateStore.read(currentLang);
        const version = ++dataVersion;
        if (dataController) dataController.abort();
        stateReady = false; loaded = false; invalidate();
        for (const id of ['searchPerson1', 'searchPerson2', 'calculateBtn', 'swapPeopleBtn']) $(id).disabled = true;
        message(''); $('relationshipLoadState').textContent = t('loading');
        const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 20000);
        dataController = controller;
        try {
            const response = await fetch('relationship_data.php', { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
            if (response.status === 401) throw new Error('auth');
            if (!response.ok) throw new Error('load');
            const data = await response.json();
            if (version !== dataVersion) return;
            if (data.viewer_id !== undefined && String(data.viewer_id) !== stateStore.owner) { location.reload(); return; }
            if (!Array.isArray(data.people) || !Array.isArray(data.marriages)) throw new Error('format');
            rawPeople = data.people; rawMarriages = data.marriages; peopleMap = Core.preparePeopleMap(rawPeople, rawMarriages);
            sortedPeople = Object.values(peopleMap).sort((a, b) => a.triple_name.localeCompare(b.triple_name, 'ar') || Number(a.id) - Number(b.id));
            loaded = true; updateLoadState();
            for (const id of ['searchPerson1', 'searchPerson2', 'calculateBtn', 'swapPeopleBtn']) $(id).disabled = !rawPeople.length;
            $('maxPathDepth').max = Math.max(1, rawPeople.length - 1);
            $('maxPathDepth').value = Math.min(state.depth, Number($('maxPathDepth').max));
            $('relationshipMode').value = state.mode; $('routeView').value = state.view; $('animationSpeed').value = String(state.speed);
            applyLanguage(state.language);
            setPerson(1, state.first, false); setPerson(2, state.second, false);
            drafts[0] = state.first ? '' : state.draftFirst; drafts[1] = state.second ? '' : state.draftSecond;
            syncSelections(); stateReady = true;
            if (selection(1) && selection(2) && state.hasResults) runCalculation({ restore: state });
            else { paused = state.paused; updateAnimationButton(); saveState(); }

        } catch (error) {
            if (version !== dataVersion) return;
            const text = t(error.message === 'auth' ? 'authError' : 'loadError');
            $('relationshipLoadState').textContent = text; message(text);
            if (error.message === 'auth') { const link = el('a', 'rel-retry', t('login')); link.href = 'login.php'; $('relationshipLoadState').append(' ', link); }
            else { const retry = el('button', 'rel-retry', t('retry')); retry.type = 'button'; retry.addEventListener('click', () => loadData(state)); $('relationshipLoadState').append(' ', retry); }
        } finally { clearTimeout(timer); }
    }
    // pagehide works with the back/forward cache and mobile navigation.
    // Do not add unload/beforeunload handlers: they disable that cache.
    window.addEventListener('pagehide', () => {
        if (stateReady) { departedState = captureState(); saveState(); }
        if (dataController) dataController.abort();
        dataVersion++;
        cancelSearch();
        if (worker) { worker.terminate(); worker = null; }
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) loadData(departedState || stateStore.read(currentLang));
        else if (stateReady) syncSelections();
    });
    window.addEventListener('popstate', () => loadData(stateStore.read(currentLang)));
    window.addEventListener('scroll', scheduleSave, { passive: true });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') saveState(); });
    document.addEventListener('click', event => { if (event.target.closest('a[href]')) saveState(); }, true);
    loadData(stateStore.read(currentLang));
});
