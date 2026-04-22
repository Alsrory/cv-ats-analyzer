<?php

/**
 * process.php - نقطة معالجة طلبات تحليل السيرة الذاتية
 * 
 * يستقبل ملف PDF + وصف وظيفي، يستخرج النص، يرسله للتحليل،
 * ويعيد النتائج بتنسيق JSON.
 */

// منع الوصول المباشر عبر GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'طريقة الطلب غير مسموحة. استخدم POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    // تحميل Composer autoload
    require_once __DIR__ . '/vendor/autoload.php';

    // تحميل متغيرات البيئة (safeLoad لدعم Docker --env-file)
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    $apiKeysEnv = $_ENV['GEMINI_API_KEYS'] ?? $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEYS') ?? getenv('GEMINI_API_KEY');
    if (empty($apiKeysEnv)) {
        throw new \RuntimeException('مفاتيح GEMINI_API_KEYS غير موجودة. تأكد من إعداد ملف .env أو تمرير المتغير عبر البيئة.');
    }

    // فصل المفاتيح إذا كانت مفصولة بفاصلة
    $apiKeys = array_filter(array_map('trim', explode(',', $apiKeysEnv)));

    // ===== التحقق من المدخلات =====

    // التحقق من وجود ملف PDF
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف يتجاوز الحد المسموح في إعدادات PHP.',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المسموح في النموذج.',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط.',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف.',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير موجود.',
            UPLOAD_ERR_CANT_WRITE => 'فشل كتابة الملف على القرص.',
        ];
        $errorCode = $_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMsg = $uploadErrors[$errorCode] ?? 'خطأ غير معروف في رفع الملف.';
        throw new \RuntimeException("خطأ في رفع الملف: {$errorMsg}");
    }

    $file = $_FILES['resume'];

    // التحقق من نوع الملف
    $allowedMimeTypes = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimeTypes)) {
        throw new \RuntimeException('نوع الملف غير مدعوم. يرجى رفع ملف PDF فقط.');
    }

    // التحقق من حجم الملف (حد أقصى 10 ميجابايت)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new \RuntimeException('حجم الملف يتجاوز الحد المسموح (10 ميجابايت).');
    }

    // التحقق من الوصف الوظيفي
    $jobDescription = trim($_POST['job_description'] ?? '');
    if (empty($jobDescription)) {
        throw new \RuntimeException('الوصف الوظيفي مطلوب. يرجى إدخال وصف الوظيفة المستهدفة.');
    }

    if (mb_strlen($jobDescription) < 20) {
        throw new \RuntimeException('الوصف الوظيفي قصير جداً. يرجى إدخال وصف أكثر تفصيلاً.');
    }

    // ===== استخراج النص من PDF =====
    $parser = new \Smalot\PdfParser\Parser();

    try {
        $pdf = $parser->parseFile($file['tmp_name']);
        $resumeText = $pdf->getText();
    } catch (\Exception $e) {
        throw new \RuntimeException('فشل قراءة ملف PDF. تأكد من أن الملف غير تالف وغير محمي بكلمة مرور.');
    }

    if (empty(trim($resumeText))) {
        throw new \RuntimeException('لم يتم استخراج أي نص من ملف PDF. قد يكون الملف عبارة عن صورة ممسوحة ضوئياً (Scanned). يرجى استخدام ملف PDF نصي.');
    }

    // ===== تحليل السيرة الذاتية =====
    $analyzer = new \Hp\CvAnalyz\GeminiAnalyzer($apiKeys);
    $result = $analyzer->analyze($resumeText, $jobDescription);

    // إرسال النتيجة
    echo json_encode([
        'success' => true,
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'validation',
    ], JSON_UNESCAPED_UNICODE);

} catch (\RuntimeException $e) {
    error_log("Validation Error: " . $e->getMessage()); // سيظهر هذا في سجلات Render
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => 'processing',
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ داخلي غير متوقع. يرجى المحاولة لاحقاً.',
        'type' => 'internal',
    ], JSON_UNESCAPED_UNICODE);
}
