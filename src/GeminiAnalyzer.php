<?php

namespace Hp\CvAnalyz;

/**
 * GeminiAnalyzer - محرك تحليل السيرة الذاتية بالذكاء الاصطناعي
 * 
 * يستخدم Google Gemini API عبر cURL مع نظام تبديل النماذج
 * والمحاولة المتكررة لضمان استقرار الاتصال.
 */
class GeminiAnalyzer
{
    /** @var array مصفوفة مفاتيح API */
    private array $apiKeys;

    /** @var int الفهرس الحالي للمفتاح المستخدم */
    private int $currentKeyIndex = 0;

    /** @var array قائمة النماذج بترتيب الأولوية */
    private array $models = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
    ];

    /** @var string عنوان API الأساسي */
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1/models';

    /** @var int الحد الأقصى لعدد المحاولات لكل نموذج */
    private int $maxRetries = 3;

    /** @var int مدة الانتظار بين المحاولات (بالثواني) */
    private int $retryDelay = 1;

    /** @var int مهلة الاتصال (بالثواني) */
    private int $timeout = 120;

    /** @var array سجل الأخطاء */
    private array $errorLog = [];

    /**
     * @param string|array $apiKeys مفتاح أو مصفوفة مفاتيح Google Gemini API
     */
    public function __construct(string|array $apiKeys)
    {
        $keys = is_array($apiKeys) ? $apiKeys : [$apiKeys];
        $this->apiKeys = array_filter(array_map('trim', $keys));

        if (empty($this->apiKeys)) {
            throw new \InvalidArgumentException('مفتاح API مطلوب ولا يمكن أن يكون فارغاً.');
        }

        // اختيار مفتاح عشوائي للبدء
        $this->currentKeyIndex = array_rand($this->apiKeys);
    }

    /**
     * تدوير مفتاح API عند استنفاد الحصة أو الوصول للحد الأقصى
     */
    private function rotateApiKey(): void
    {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
        $this->logInfo("تم التبديل إلى مفتاح API جديد.");
    }

    /**
     * تحليل السيرة الذاتية مقابل الوصف الوظيفي
     *
     * @param string $resumeText نص السيرة الذاتية المستخرج
     * @param string $jobDescription الوصف الوظيفي
     * @return array نتائج التحليل بتنسيق JSON
     * @throws \RuntimeException في حال فشل جميع المحاولات
     */
    public function analyze(string $resumeText, string $jobDescription): array
    {
        $resumeText = $this->sanitizeText($resumeText);
        $jobDescription = $this->sanitizeText($jobDescription);

        if (empty($resumeText)) {
            throw new \InvalidArgumentException('نص السيرة الذاتية فارغ. تأكد من أن ملف PDF يحتوي على نص قابل للقراءة.');
        }

        if (empty($jobDescription)) {
            throw new \InvalidArgumentException('الوصف الوظيفي مطلوب.');
        }

        $prompt = $this->buildPrompt($resumeText, $jobDescription);

        // محاولة الاتصال بكل نموذج متاح
        foreach ($this->models as $modelIndex => $model) {
            $this->logInfo("محاولة الاتصال بالنموذج: {$model}");

            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                try {
                    $response = $this->callGeminiAPI($model, $prompt);
                    $parsed = $this->parseResponse($response);

                    $this->logInfo("نجح التحليل باستخدام النموذج: {$model} (المحاولة {$attempt})");
                    return $parsed;

                } catch (ServiceUnavailableException $e) {
                    $this->logError("خطأ 503 من النموذج {$model} (المحاولة {$attempt}): {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $this->logInfo("الانتظار {$this->retryDelay} ثانية قبل إعادة المحاولة...");
                        sleep($this->retryDelay);
                    } elseif ($modelIndex < count($this->models) - 1) {
                        $this->logInfo("التبديل إلى النموذج التالي...");
                        break; // الانتقال للنموذج التالي
                    }

                } catch (ApiException $e) {
                    $this->logError("خطأ API من النموذج {$model}: {$e->getMessage()}");
                    if ($e->getCode() === 429) {
                        // Rate limit / Quota exceeded
                        // تبديل المفتاح إذا كان لدينا أكثر من مفتاح
                        if (count($this->apiKeys) > 1) {
                            $this->rotateApiKey();
                        }

                        if ($attempt < $this->maxRetries) {
                            $this->logInfo("تجاوز حد الطلبات. تم تبديل المفتاح. الانتظار 2 ثانية ثم إعادة المحاولة...");
                            sleep(2);
                            continue;
                        }
                        // إذا استنفدت المحاولات، ننتقل للنموذج التالي
                        break;
                    }
                    break; // أخطاء أخرى - الانتقال للنموذج التالي
                }
            }
        }

        // رسالة خطأ واضحة للمستخدم
        throw new \RuntimeException('تعذر التحليل حالياً. يرجى الانتظار دقيقة ثم إعادة المحاولة.');
    }

    /**
     * بناء الأمر (Prompt) للنموذج
     */
    private function buildPrompt(string $resumeText, string $jobDescription): string
    {
        $schema = json_encode([
            'overall_ats_score' => 0,
            'match_score' => 0,
            'structural_score' => 0,
            'readability_score' => 0,
            'format_score' => 0,
            'is_ats_friendly' => true,
            'parsing_issues' => ['مثال على مشكلة'],
            'matched_keywords' => ['مهارة متطابقة'],
            'missing_keywords' => ['مهارة مفقودة'],
            'section_analysis' => [
                'found_sections' => ['قسم موجود'],
                'missing_sections' => ['قسم مفقود'],
            ],
            'typography_feedback' => 'تقييم الخطوط والتنسيق',
            'optimization_tips' => ['نصيحة للتحسين'],
            'final_verdict' => 'خلاصة التقييم النهائية',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
أنت "Senior Technical ATS Auditor" - خبير أول في أنظمة تتبع المتقدمين (ATS) مثل Greenhouse و Workday و Lever.

## مهمتك:
قم بتحليل السيرة الذاتية التالية مقابل الوصف الوظيفي المرفق. أجرِ تدقيقاً هيكلياً شاملاً يتضمن:

### معايير التقييم والأوزان:
1. **المطابقة الدلالية (40%)** - `match_score`: توافق المهارات والكلمات المفتاحية والخبرات مع الوصف الوظيفي. قارن المهارات التقنية والناعمة، وسنوات الخبرة، والمؤهلات المطلوبة.
2. **السلامة الهيكلية (30%)** - `structural_score`: وجود وتنسيق الأقسام الأساسية (الخبرة العملية، التعليم، المهارات، الملخص المهني، معلومات الاتصال). تحقق من الترتيب المنطقي وعناوين الأقسام الواضحة.
3. **القابلية للقراءة التقنية (20%)** - `readability_score`: خلو الملف من "قاتلات الـ ATS" مثل الجداول المعقدة، الصور المدمجة، الرموز غير القياسية، الأعمدة المتعددة، الرؤوس والتذييلات.
4. **التنسيق والخطوط (10%)** - `format_score`: جودة الخطوط المستخدمة (هل هي خطوط قياسية مقروءة مثل Arial, Calibri, Times New Roman)، الترتيب الزمني العكسي، استخدام النقاط التعداد بدلاً من الفقرات.

### الدرجة الإجمالية:
`overall_ats_score` = (match_score × 0.4) + (structural_score × 0.3) + (readability_score × 0.2) + (format_score × 0.1)

### السيرة الذاتية:
---
{$resumeText}
---

### الوصف الوظيفي:
---
{$jobDescription}
---

## تعليمات الإخراج:
- يجب أن يكون ردك بتنسيق JSON حصرياً بدون أي نص إضافي.
- جميع النصوص والتقييمات يجب أن تكون باللغة العربية.
- الدرجات يجب أن تكون أرقام صحيحة من 0 إلى 100.
- `is_ats_friendly` يجب أن تكون true إذا كانت الدرجة الإجمالية >= 70.
- قدم نصائح عملية وقابلة للتنفيذ في `optimization_tips`.
- كن دقيقاً ومحدداً في `final_verdict`.

## هيكل JSON المطلوب:
```json
{$schema}
```

أعد JSON فقط بدون أي نص قبله أو بعده.
PROMPT;
    }

    /**
     * استدعاء Gemini API عبر cURL
     */
    private function callGeminiAPI(string $model, string $prompt): string
    {
        $currentApiKey = $this->apiKeys[$this->currentKeyIndex];
        $url = "{$this->baseUrl}/{$model}:generateContent?key={$currentApiKey}";

        $payloadArray = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topP' => 0.8,
                'topK' => 40,
                'maxOutputTokens' => 4096,
            ]
        ];

        $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($payload === false) {
            throw new ApiException('فشل بناء طلب JSON: ' . json_last_error_msg() . ' - تأكد من أن ملف PDF يحتوي على نص صالح.');
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // بيئة التطوير المحلية
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // التحقق من أخطاء cURL
        if ($curlErrno !== 0) {
            throw new ApiException("خطأ في الاتصال (cURL #{$curlErrno}): {$curlError}", $curlErrno);
        }

        // التحقق من كود HTTP
        if ($httpCode === 503) {
            throw new ServiceUnavailableException("الخادم غير متاح (503). النموذج: {$model}");
        }

        if ($httpCode === 429) {
            throw new ApiException("تم تجاوز حد الطلبات (429). حاول لاحقاً.", 429);
        }

        if ($httpCode !== 200) {
            $errorBody = json_decode($response, true);
            $errorMsg = $errorBody['error']['message'] ?? 'خطأ غير معروف';
            throw new ApiException("خطأ API ({$httpCode}): {$errorMsg}", $httpCode);
        }

        return $response;
    }

    /**
     * تحليل استجابة API واستخراج JSON
     */
    private function parseResponse(string $rawResponse): array
    {
        $responseData = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('فشل تحليل استجابة API: ' . json_last_error_msg());
        }

        // استخراج النص من استجابة Gemini
        $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($text)) {
            // التحقق من وجود حظر أمني
            $blockReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            if ($blockReason === 'SAFETY') {
                throw new ApiException('تم حظر المحتوى بسبب سياسات الأمان.');
            }
            throw new ApiException('لم يتم الحصول على رد من النموذج.');
        }

        // تنظيف النص من أي علامات كود محتملة
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        // إزالة أحرف التحكم التي تكسر JSON (ما عدا \n و \t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // محاولة ثانية: استخراج JSON من النص
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
                $result = json_decode($matches[0], true);
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('فشل تحليل رد النموذج. يرجى المحاولة مرة أخرى.');
            }
        }

        return $this->validateAndNormalize($result);
    }

    /**
     * التحقق من صحة البيانات وتطبيعها
     */
    private function validateAndNormalize(array $data): array
    {
        $defaults = [
            'overall_ats_score' => 0,
            'match_score' => 0,
            'structural_score' => 0,
            'readability_score' => 0,
            'format_score' => 0,
            'is_ats_friendly' => false,
            'parsing_issues' => [],
            'matched_keywords' => [],
            'missing_keywords' => [],
            'section_analysis' => [
                'found_sections' => [],
                'missing_sections' => [],
            ],
            'typography_feedback' => 'لا يوجد تقييم',
            'optimization_tips' => [],
            'final_verdict' => 'لا يوجد تقييم',
        ];

        $result = array_merge($defaults, $data);

        // ضمان أن الدرجات أرقام صحيحة بين 0 و 100
        foreach (['overall_ats_score', 'match_score', 'structural_score', 'readability_score', 'format_score'] as $scoreKey) {
            $result[$scoreKey] = max(0, min(100, intval($result[$scoreKey])));
        }

        // ضمان أن المصفوفات فعلاً مصفوفات
        foreach (['parsing_issues', 'matched_keywords', 'missing_keywords', 'optimization_tips'] as $arrayKey) {
            if (!is_array($result[$arrayKey])) {
                $result[$arrayKey] = [];
            }
        }

        // ضمان هيكل section_analysis
        if (!is_array($result['section_analysis'])) {
            $result['section_analysis'] = $defaults['section_analysis'];
        }
        if (!isset($result['section_analysis']['found_sections']) || !is_array($result['section_analysis']['found_sections'])) {
            $result['section_analysis']['found_sections'] = [];
        }
        if (!isset($result['section_analysis']['missing_sections']) || !is_array($result['section_analysis']['missing_sections'])) {
            $result['section_analysis']['missing_sections'] = [];
        }

        // ضمان أن is_ats_friendly قيمة منطقية
        $result['is_ats_friendly'] = (bool) $result['is_ats_friendly'];

        return $result;
    }

    /**
     * تنظيف النص قبل إرساله للـ API
     * يمنع كسر هيكل JSON ويزيل الأحرف الخطرة
     */
    private function sanitizeText(string $text): string
    {
        // تحويل النص إلى UTF-8 إذا لم يكن كذلك
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // إزالة أي بايتات غير صالحة في UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // إزالة BOM إن وُجد
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // إزالة أحرف التحكم (ما عدا السطر الجديد والتبويب)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // إزالة أحرف Unicode غير المرئية والخاصة
        $text = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\x{2060}\x{FFFE}\x{FFFF}]/u', '', $text);

        // تطبيع الأسطر الجديدة
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // إزالة المسافات الزائدة
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * تسجيل رسالة معلوماتية
     */
    private function logInfo(string $message): void
    {
        $this->errorLog[] = "[INFO] " . date('H:i:s') . " - {$message}";
    }

    /**
     * تسجيل رسالة خطأ
     */
    private function logError(string $message): void
    {
        $this->errorLog[] = "[ERROR] " . date('H:i:s') . " - {$message}";
    }

    /**
     * الحصول على سجل الأخطاء
     */
    public function getErrorLog(): array
    {
        return $this->errorLog;
    }
}
