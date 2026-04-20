<?php

namespace Hp\CvAnalyz;

/**
 * GeminiAnalyzer -  tool to analysis CV by artificial intelligence
 * 
 *  Google Gemini API using cURL with model switching system
 * and retry to ensure connection stability.
 */
class GeminiAnalyzer
{
    /** @var array Google Gemini API keys */
    private array $apiKeys;

    /** @var int The current index of the key used */
    private int $currentKeyIndex = 0;

    /** @var array list of models in order of priority */
    private array $models = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
    ];

    /** @var string The base API URL */
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1/models';

    /** @var int The maximum number of attempts per model */
    private int $maxRetries = 3;

    /** @var int The waiting time between attempts (in seconds) */
    private int $retryDelay = 1;

    /** @var int The connection timeout (in seconds) */
    private int $timeout = 120;

    /** @var array The error log */
    private array $errorLog = [];

    /**
     * @param string|array $apiKeys Google Gemini API key or array of keys
     */
    public function __construct(string|array $apiKeys)
    {
        $keys = is_array($apiKeys) ? $apiKeys : [$apiKeys];
        $this->apiKeys = array_filter(array_map('trim', $keys));

        if (empty($this->apiKeys)) {
            throw new \InvalidArgumentException('API key is required.');
        }

        // select random key
        $this->currentKeyIndex = array_rand($this->apiKeys);
    }

    /**
     * rotate API key when quota is exceeded or max attempts reached
     */
    private function rotateApiKey(): void
    {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
        $this->logInfo("Switching to a new API key.");
    }

    /**
     * analyze CV against job description
     *
     * @param string $resumeText extracted CV text
     * @param string $jobDescription job description
     * @return array analysis results in JSON format
     * @throws \RuntimeException in case of failure of all attempts
     */
    public function analyze(string $resumeText, string $jobDescription): array
    {
        $resumeText = $this->sanitizeText($resumeText);
        $jobDescription = $this->sanitizeText($jobDescription);

        if (empty($resumeText)) {
            throw new \InvalidArgumentException('نص السيرة الذاتية فارغ. تأكد من أن ملف PDF يحتوي على نص قابل للقراءة.');
        }

        if (empty($jobDescription)) {
            throw new \InvalidArgumentException(' الوصف الوظيفي مطلوب.');
        }

        $prompt = $this->buildPrompt($resumeText, $jobDescription);

        // attempt to connect to each model available
        foreach ($this->models as $modelIndex => $model) {
            $this->logInfo("Attempting to connect to model: {$model}");

            for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
                try {
                    $response = $this->callGeminiAPI($model, $prompt);
                    $parsed = $this->parseResponse($response);

                    $this->logInfo("Successfully analyzed using model: {$model} (Attempt {$attempt})");
                    return $parsed;

                } catch (ServiceUnavailableException $e) {
                    $this->logError("503 error from model {$model} (Attempt {$attempt}): {$e->getMessage()}");

                    if ($attempt < $this->maxRetries) {
                        $this->logInfo("Waiting for {$this->retryDelay} seconds before retry...");
                        sleep($this->retryDelay);
                    } elseif ($modelIndex < count($this->models) - 1) {
                        $this->logInfo("Switching to the next model...");
                        break; // next model
                    }

                } catch (ApiException $e) {
                    $this->logError("API error from model {$model}: {$e->getMessage()}");
                    if ($e->getCode() === 429) {
                        // Rate limit / Quota exceeded
                        // switching to the next key if we have more than one
                        if (count($this->apiKeys) > 1) {
                            $this->rotateApiKey();
                        }

                        if ($attempt < $this->maxRetries) {
                            $this->logInfo("Rate limit exceeded. Switching key. Waiting 2 seconds then retry...");
                            sleep(2);
                            continue;
                        }
                        // if attempts are exhausted, move to the next model
                        break;
                    }
                    break; // other errors - move to the next model
                }
            }
        }

        // clear error log
        throw new \RuntimeException('تعذر التحليل حالياً. يرجى الانتظار دقيقة ثم إعادة المحاولة.');
    }

    /**
     * build prompt for the model
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
     * call Gemini API via cURL
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
            CURLOPT_SSL_VERIFYPEER => false, // local development environment
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        // check cURL errors
        if ($curlErrno !== 0) {
            throw new ApiException("Connection error (cURL #{$curlErrno}): {$curlError}", $curlErrno);
        }

        // check HTTP code
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
     * parse API response and extract JSON
     */
    private function parseResponse(string $rawResponse): array
    {
        $responseData = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Failed to parse API response: ' . json_last_error_msg());
        }

        // extract text from Gemini response
        $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (empty($text)) {
            // check for security block
            $blockReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            if ($blockReason === 'SAFETY') {
                throw new ApiException('Content blocked due to security policies.');
            }
            throw new ApiException('No response received from the model.');
        }

        // clean the text from any potential code markers
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        // remove control characters that break JSON (except \n and \t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // second attempt: extract JSON from text
            if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
                $result = json_decode($matches[0], true);
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('Failed to parse model response. Please try again.');
            }
        }

        return $this->validateAndNormalize($result);
    }

    /**
     * validate and normalize data
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

        // ensure scores are integers between 0 and 100
        foreach (['overall_ats_score', 'match_score', 'structural_score', 'readability_score', 'format_score'] as $scoreKey) {
            $result[$scoreKey] = max(0, min(100, intval($result[$scoreKey])));
        }

        // ensure arrays are arrays
        foreach (['parsing_issues', 'matched_keywords', 'missing_keywords', 'optimization_tips'] as $arrayKey) {
            if (!is_array($result[$arrayKey])) {
                $result[$arrayKey] = [];
            }
        }

        // ensure section_analysis structure
        if (!is_array($result['section_analysis'])) {
            $result['section_analysis'] = $defaults['section_analysis'];
        }
        if (!isset($result['section_analysis']['found_sections']) || !is_array($result['section_analysis']['found_sections'])) {
            $result['section_analysis']['found_sections'] = [];
        }
        if (!isset($result['section_analysis']['missing_sections']) || !is_array($result['section_analysis']['missing_sections'])) {
            $result['section_analysis']['missing_sections'] = [];
        }

        // ensure is_ats_friendly is boolean
        $result['is_ats_friendly'] = (bool) $result['is_ats_friendly'];

        return $result;
    }

    /**
     * clean text before sending to API
     * prevents breaking JSON structure and removes dangerous characters
     */
    private function sanitizeText(string $text): string
    {
        // convert text to UTF-8 if not already
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // remove any invalid bytes in UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // remove BOM if exists
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // remove control characters (except newline and tab)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // remove invisible and special Unicode characters
        $text = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}\x{2060}\x{FFFE}\x{FFFF}]/u', '', $text);

        // normalize newlines
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // remove extra spaces
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * log information message
     */
    private function logInfo(string $message): void
    {
        $this->errorLog[] = "[INFO] " . date('H:i:s') . " - {$message}";
    }

    /**
     * log error message
     */
    private function logError(string $message): void
    {
        $this->errorLog[] = "[ERROR] " . date('H:i:s') . " - {$message}";
    }

    /**
     * Get error log
     */
    public function getErrorLog(): array
    {
        return $this->errorLog;
    }
}
