<?php

namespace Hp\CvAnalyz;

/**
 * استثناء خاص بخطأ 503 - الخادم غير متاح
 * يُستخدم لتفعيل نظام التبديل بين النماذج
 */
class ServiceUnavailableException extends ApiException
{
}
