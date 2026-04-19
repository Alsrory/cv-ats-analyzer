<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>محلل السيرة الذاتية - ATS Analyzer</title>
    <meta name="description" content="أداة تحليل ذكية للسير الذاتية تحاكي أنظمة تتبع المتقدمين ATS مع تقييم رقمي شامل">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- خلفية متحركة -->
    <div class="bg-animation">
        <div class="bg-orb bg-orb-1"></div>
        <div class="bg-orb bg-orb-2"></div>
        <div class="bg-orb bg-orb-3"></div>
    </div>

    <div class="container">
        <!-- الهيدر -->
        <header class="header" id="app-header">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="40" height="40" rx="8" stroke="currentColor" stroke-width="2.5" />
                        <path d="M14 16h20M14 22h16M14 28h12M14 34h8" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" />
                        <circle cx="36" cy="34" r="6" fill="url(#grad)" stroke="currentColor" stroke-width="2" />
                        <path d="M34 34l1.5 1.5L38 32.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <defs>
                            <linearGradient id="grad" x1="30" y1="28" x2="42" y2="40">
                                <stop stop-color="#6366f1" />
                                <stop offset="1" stop-color="#8b5cf6" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div>
                    <h1>محلل السيرة الذاتية</h1>
                    <p class="subtitle">محاكاة أنظمة ATS مع تدقيق هيكلي شامل</p>
                </div>
            </div>
        </header>

        <!-- نموذج الإدخال -->
        <section class="upload-section" id="upload-section">
            <form id="analyze-form" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- رفع الملف -->
                    <div class="form-group">
                        <label class="form-label">السيرة الذاتية (PDF)</label>
                        <div class="dropzone" id="dropzone">
                            <input type="file" id="resume-input" name="resume" accept=".pdf" required hidden>
                            <div class="dropzone-content" id="dropzone-content">
                                <svg class="dropzone-icon" viewBox="0 0 64 64" fill="none">
                                    <path d="M32 44V20M32 20l-8 8M32 20l8 8" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 40v8a4 4 0 004 4h32a4 4 0 004-4v-8" stroke="currentColor"
                                        stroke-width="2.5" stroke-linecap="round" />
                                </svg>
                                <p class="dropzone-text">اسحب ملف PDF هنا أو <span class="dropzone-link">تصفح
                                        الملفات</span></p>
                                <p class="dropzone-hint">الحد الأقصى: 10 ميجابايت</p>
                            </div>
                            <div class="dropzone-file" id="dropzone-file" style="display:none;">
                                <svg viewBox="0 0 40 40" fill="none" width="36" height="36">
                                    <rect x="6" y="2" width="28" height="36" rx="4" stroke="currentColor"
                                        stroke-width="2" />
                                    <path d="M14 14h12M14 20h10M14 26h8" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                                <div class="file-info">
                                    <span class="file-name" id="file-name"></span>
                                    <span class="file-size" id="file-size"></span>
                                </div>
                                <button type="button" class="file-remove" id="file-remove"
                                    title="إزالة الملف">&times;</button>
                            </div>
                        </div>
                    </div>

                    <!-- الوصف الوظيفي -->
                    <div class="form-group">
                        <label class="form-label" for="job-description">الوصف الوظيفي</label>
                        <textarea id="job-description" name="job_description" class="textarea" required
                            placeholder="الصق هنا الوصف الوظيفي للوظيفة المستهدفة... &#10;&#10;مثال: نبحث عن مطور Full Stack يتقن PHP, Laravel, JavaScript..."
                            rows="8"></textarea>
                        <div class="char-count"><span id="char-count">0</span> حرف</div>
                    </div>
                </div>

                <button type="submit" class="btn-analyze" id="btn-analyze">
                    <span class="btn-text">تحليل السيرة الذاتية</span>
                    <span class="btn-loader" style="display:none;">
                        <span class="spinner"></span>
                        <span>جاري التحليل...</span>
                    </span>
                </button>
            </form>
        </section>

        <!-- قسم النتائج -->
        <section class="results-section" id="results-section" style="display:none;">
            <!-- زر العودة -->
            <button class="btn-back" id="btn-back">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
                    <path d="M11 5l7 7-7 7M18 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
                تحليل جديد
            </button>

            <!-- الدرجة الإجمالية -->
            <div class="score-hero" id="score-hero">
                <div class="score-ring-container">
                    <svg class="score-ring" viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="88" class="score-ring-bg" />
                        <circle cx="100" cy="100" r="88" class="score-ring-fill" id="score-ring-fill" />
                    </svg>
                    <div class="score-value" id="score-value">0</div>
                    <div class="score-label">ATS Score</div>
                </div>
                <div class="score-verdict" id="score-verdict"></div>
                <div class="ats-badge" id="ats-badge"></div>
            </div>

            <!-- درجات فرعية -->
            <div class="sub-scores" id="sub-scores">
                <div class="sub-score-card" data-key="match_score">
                    <div class="sub-score-header">
                        <span class="sub-score-icon">🎯</span>
                        <span>المطابقة الدلالية</span>
                        <span class="sub-score-weight">40%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="prog-match"></div>
                    </div>
                    <div class="sub-score-val" id="val-match">0</div>
                </div>
                <div class="sub-score-card" data-key="structural_score">
                    <div class="sub-score-header">
                        <span class="sub-score-icon">🏗️</span>
                        <span>السلامة الهيكلية</span>
                        <span class="sub-score-weight">30%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="prog-struct"></div>
                    </div>
                    <div class="sub-score-val" id="val-struct">0</div>
                </div>
                <div class="sub-score-card" data-key="readability_score">
                    <div class="sub-score-header">
                        <span class="sub-score-icon">👁️</span>
                        <span>القابلية للقراءة</span>
                        <span class="sub-score-weight">20%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="prog-read"></div>
                    </div>
                    <div class="sub-score-val" id="val-read">0</div>
                </div>
                <div class="sub-score-card" data-key="format_score">
                    <div class="sub-score-header">
                        <span class="sub-score-icon">🎨</span>
                        <span>التنسيق والخطوط</span>
                        <span class="sub-score-weight">10%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="prog-fmt"></div>
                    </div>
                    <div class="sub-score-val" id="val-fmt">0</div>
                </div>
            </div>

            <!-- التفاصيل -->
            <div class="details-grid" id="details-grid">
                <!-- الكلمات المتطابقة -->
                <div class="detail-card card-matched">
                    <h3><span class="card-icon">✅</span> الكلمات المتطابقة</h3>
                    <div class="tags-container" id="matched-keywords"></div>
                </div>
                <!-- الكلمات المفقودة -->
                <div class="detail-card card-missing">
                    <h3><span class="card-icon">❌</span> الكلمات المفقودة</h3>
                    <div class="tags-container" id="missing-keywords"></div>
                </div>
                <!-- الأقسام الموجودة -->
                <div class="detail-card card-sections-found">
                    <h3><span class="card-icon">📋</span> الأقسام الموجودة</h3>
                    <div class="tags-container" id="found-sections"></div>
                </div>
                <!-- الأقسام المفقودة -->
                <div class="detail-card card-sections-missing">
                    <h3><span class="card-icon">📝</span> الأقسام المفقودة</h3>
                    <div class="tags-container" id="missing-sections"></div>
                </div>
            </div>

            <!-- مشاكل القراءة -->
            <div class="detail-card card-issues" id="card-issues" style="display:none;">
                <h3><span class="card-icon">⚠️</span> مشاكل في القراءة</h3>
                <ul class="issues-list" id="parsing-issues"></ul>
            </div>

            <!-- تقييم الخطوط -->
            <div class="detail-card card-typo" id="card-typo">
                <h3><span class="card-icon">🔤</span> تقييم الخطوط والتنسيق</h3>
                <p id="typography-feedback"></p>
            </div>

            <!-- نصائح التحسين -->
            <div class="detail-card card-tips">
                <h3><span class="card-icon">💡</span> نصائح التحسين</h3>
                <ul class="tips-list" id="optimization-tips"></ul>
            </div>

            <!-- الخلاصة -->
            <div class="verdict-card" id="verdict-card">
                <h3><span class="card-icon">📊</span> الخلاصة النهائية</h3>
                <p id="final-verdict"></p>
            </div>
        </section>

        <!-- رسالة الخطأ -->
        <div class="error-toast" id="error-toast" style="display:none;">
            <span class="error-icon">⚠️</span>
            <span class="error-message" id="error-message"></span>
            <button class="error-close" id="error-close">&times;</button>
        </div>
    </div>

    <script src="app.js"></script>
</body>

</html>