/**
 * app.js - التطبيق الأمامي لمحلل السيرة الذاتية
 */
(function () {
    'use strict';

    // === DOM Elements ===
    const form = document.getElementById('analyze-form');
    const resumeInput = document.getElementById('resume-input');
    const dropzone = document.getElementById('dropzone');
    const dropzoneContent = document.getElementById('dropzone-content');
    const dropzoneFile = document.getElementById('dropzone-file');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const fileRemove = document.getElementById('file-remove');
    const jobDesc = document.getElementById('job-description');
    const charCount = document.getElementById('char-count');
    const btnAnalyze = document.getElementById('btn-analyze');
    const btnText = btnAnalyze.querySelector('.btn-text');
    const btnLoader = btnAnalyze.querySelector('.btn-loader');
    const uploadSection = document.getElementById('upload-section');
    const resultsSection = document.getElementById('results-section');
    const btnBack = document.getElementById('btn-back');
    const errorToast = document.getElementById('error-toast');
    const errorMessage = document.getElementById('error-message');
    const errorClose = document.getElementById('error-close');

    // === Dropzone Events ===
    dropzone.addEventListener('click', () => resumeInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('drag-over');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0];
            if (file.type === 'application/pdf') {
                resumeInput.files = e.dataTransfer.files;
                showSelectedFile(file);
            } else {
                showError('يرجى رفع ملف PDF فقط.');
            }
        }
    });

    resumeInput.addEventListener('change', () => {
        if (resumeInput.files.length > 0) {
            showSelectedFile(resumeInput.files[0]);
        }
    });

    fileRemove.addEventListener('click', (e) => {
        e.stopPropagation();
        resumeInput.value = '';
        dropzoneContent.style.display = '';
        dropzoneFile.style.display = 'none';
    });

    function showSelectedFile(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatSize(file.size);
        dropzoneContent.style.display = 'none';
        dropzoneFile.style.display = 'flex';
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // === Character Count ===
    jobDesc.addEventListener('input', () => {
        charCount.textContent = jobDesc.value.length;
    });

    // === Form Submit ===
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!resumeInput.files.length) {
            showError('يرجى رفع ملف السيرة الذاتية.');
            return;
        }

        if (jobDesc.value.trim().length < 20) {
            showError('الوصف الوظيفي قصير جداً. يرجى إدخال وصف أكثر تفصيلاً (20 حرف على الأقل).');
            return;
        }

        setLoading(true);

        try {
            const formData = new FormData();
            formData.append('resume', resumeInput.files[0]);
            formData.append('job_description', jobDesc.value.trim());

            const response = await fetch('process.php', {
                method: 'POST',
                body: formData,
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'حدث خطأ غير متوقع.');
            }

            renderResults(result.data);
        } catch (err) {
            showError(err.message || 'فشل الاتصال بالخادم. تأكد من اتصالك بالإنترنت.');
        } finally {
            setLoading(false);
        }
    });

    function setLoading(loading) {
        btnAnalyze.disabled = loading;
        btnText.style.display = loading ? 'none' : '';
        btnLoader.style.display = loading ? 'flex' : 'none';
    }

    // === Back Button ===
    btnBack.addEventListener('click', () => {
        resultsSection.style.display = 'none';
        uploadSection.style.display = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // === Error Toast ===
    function showError(msg) {
        errorMessage.textContent = msg;
        errorToast.style.display = 'flex';
        setTimeout(() => { errorToast.style.display = 'none'; }, 8000);
    }

    errorClose.addEventListener('click', () => { errorToast.style.display = 'none'; });

    // === Render Results ===
    function renderResults(data) {
        uploadSection.style.display = 'none';
        resultsSection.style.display = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });

        const score = data.overall_ats_score || 0;

        // Score ring animation
        const ringFill = document.getElementById('score-ring-fill');
        const circumference = 2 * Math.PI * 88; // r=88
        const offset = circumference - (score / 100) * circumference;
        const color = getScoreColor(score);

        ringFill.style.stroke = color;
        setTimeout(() => { ringFill.style.strokeDashoffset = offset; }, 100);

        // Animate score number
        animateNumber('score-value', score, 1500);

        // Score verdict
        document.getElementById('score-verdict').textContent = data.final_verdict || '';

        // ATS badge
        const badge = document.getElementById('ats-badge');
        if (data.is_ats_friendly) {
            badge.textContent = '✅ متوافق مع أنظمة ATS';
            badge.className = 'ats-badge friendly';
        } else {
            badge.textContent = '❌ غير متوافق مع أنظمة ATS';
            badge.className = 'ats-badge unfriendly';
        }

        // Sub scores
        renderSubScore('prog-match', 'val-match', data.match_score || 0);
        renderSubScore('prog-struct', 'val-struct', data.structural_score || 0);
        renderSubScore('prog-read', 'val-read', data.readability_score || 0);
        renderSubScore('prog-fmt', 'val-fmt', data.format_score || 0);

        // Keywords
        renderTags('matched-keywords', data.matched_keywords, 'tag-green');
        renderTags('missing-keywords', data.missing_keywords, 'tag-red');

        // Sections
        const sa = data.section_analysis || {};
        renderTags('found-sections', sa.found_sections, 'tag-blue');
        renderTags('missing-sections', sa.missing_sections, 'tag-yellow');

        // Parsing issues
        const issuesCard = document.getElementById('card-issues');
        const issuesList = document.getElementById('parsing-issues');
        if (data.parsing_issues && data.parsing_issues.length > 0) {
            issuesCard.style.display = '';
            issuesList.innerHTML = data.parsing_issues.map(i => `<li>${escapeHtml(i)}</li>`).join('');
        } else {
            issuesCard.style.display = 'none';
        }

        // Typography
        document.getElementById('typography-feedback').textContent = data.typography_feedback || 'لا يوجد تقييم';

        // Tips
        const tipsList = document.getElementById('optimization-tips');
        if (data.optimization_tips && data.optimization_tips.length > 0) {
            tipsList.innerHTML = data.optimization_tips.map(t => `<li>${escapeHtml(t)}</li>`).join('');
        } else {
            tipsList.innerHTML = '<li>لا توجد نصائح إضافية.</li>';
        }

        // Final verdict
        document.getElementById('final-verdict').textContent = data.final_verdict || '';
    }

    function renderSubScore(progId, valId, score) {
        const color = getScoreColor(score);
        const progEl = document.getElementById(progId);
        setTimeout(() => {
            progEl.style.width = score + '%';
            progEl.style.background = color;
        }, 200);
        animateNumber(valId, score, 1200);
    }

    function renderTags(containerId, items, tagClass) {
        const container = document.getElementById(containerId);
        if (!items || items.length === 0) {
            container.innerHTML = '<span class="empty-state">لا توجد عناصر</span>';
            return;
        }
        container.innerHTML = items.map(i => `<span class="tag ${tagClass}">${escapeHtml(i)}</span>`).join('');
    }

    function animateNumber(elementId, target, duration) {
        const el = document.getElementById(elementId);
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(start + (target - start) * eased);
            if (progress < 1) requestAnimationFrame(update);
        }

        requestAnimationFrame(update);
    }

    function getScoreColor(score) {
        if (score >= 80) return '#22c55e';
        if (score >= 60) return '#eab308';
        if (score >= 40) return '#f97316';
        return '#ef4444';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
