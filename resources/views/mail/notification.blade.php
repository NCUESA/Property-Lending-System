{{-- 後台填寫的純文字內文。用 e() 轉義後再換行，模板內容一律不當成 HTML 解析。 --}}
<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px;background:#f6f6f6;font-family:system-ui,-apple-system,'Noto Sans TC',sans-serif;">
    <div style="max-width:560px;margin:0 auto;padding:24px;background:#fff;border-radius:8px;line-height:1.7;color:#111;">
        {!! nl2br(e($body)) !!}
        <hr style="margin:24px 0;border:none;border-top:1px solid #e5e5e5;">
        <p style="margin:0;font-size:12px;color:#777;">本信件由{{ config('app.name') }}系統自動發送，請勿直接回覆。</p>
    </div>
</body>
</html>
