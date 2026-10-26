<?php
defined( 'ABSPATH' ) || exit;

/**
 * ASP_Freight_Antispam
 * ---------------------
 * سه لایه محافظتی کاملاً سمت سرور، بدون هیچ وابستگی به سرویس خارجی:
 *
 * 1) Honeypot: یک فیلد مخفی که فقط بات‌ها پر می‌کنن.
 * 2) Rate limiting بر اساس IP (per-minute + per-day) با transient.
 * 3) حداقل زمان بین رندر فرم و ارسال درخواست (ضد اسکریپت‌های فوری).
 *
 * هیچ‌کدوم به شبکه/سرور بیرونی نیاز ندارن، پس قطعی اینترنت یا از کار افتادن
 * یک سرویس ثالث هیچ‌وقت باعث از کار افتادن کامل فرم نمی‌شه.
 */
class ASP_Freight_Antispam {

    const HONEYPOT_FIELD   = 'asp_hp_field';   // اسم فیلد honeypot در فرم
    const TIMESTAMP_FIELD  = 'asp_ts';         // تایم‌استمپ رندر فرم (base64 ساده، نه امنیتی، فقط heuristic)
    const MIN_FILL_SECONDS = 1.2;              // کمتر از این یعنی مشکوک به بات

    const LIMIT_PER_MINUTE = 12;   // حداکثر درخواست در هر دقیقه برای هر IP
    const LIMIT_PER_DAY    = 150;  // حداکثر درخواست در هر روز برای هر IP

    /**
     * بررسی کامل — اگه مشکلی بود، پیام خطا رو برمی‌گردونه؛ اگه null برگردوند یعنی مجاز است.
     * پیام‌ها عمداً کلی و غیرفنی‌اند تا به بات/اسپمر اطلاعات لو نده.
     */
    public static function check(): ?string {

        // ── ۱. Honeypot ──────────────────────────────────────────────────
        $honeypot = isset( $_POST[ self::HONEYPOT_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::HONEYPOT_FIELD ] ) ) : '';
        if ( $honeypot !== '' ) {
            self::log_block( 'honeypot' );
            return 'درخواست نامعتبر است.';
        }

        // ── ۲. حداقل زمان از رندر فرم تا ارسال ──────────────────────────
        $ts_raw = isset( $_POST[ self::TIMESTAMP_FIELD ] ) ? (string) wp_unslash( $_POST[ self::TIMESTAMP_FIELD ] ) : '';
        $rendered_at = self::decode_timestamp( $ts_raw );
        if ( $rendered_at === null ) {
            // اگه فیلد نبود/خراب بود، مشکوکه (فرم واقعی همیشه این رو می‌فرسته)
            self::log_block( 'missing_timestamp' );
            return 'درخواست نامعتبر است.';
        }
        $elapsed = microtime( true ) - $rendered_at;
        if ( $elapsed < self::MIN_FILL_SECONDS ) {
            self::log_block( 'too_fast' );
            return 'درخواست بسیار سریع ارسال شد. لطفاً دوباره تلاش کنید.';
        }

        // ── ۳. Rate limiting بر اساس IP ──────────────────────────────────
        $ip = self::get_client_ip();

        $minute_key = 'asp_rl_m_' . md5( $ip );
        $minute_count = (int) get_transient( $minute_key );
        if ( $minute_count >= self::LIMIT_PER_MINUTE ) {
            self::log_block( 'rate_minute', $ip );
            return 'تعداد درخواست‌ها بیش از حد مجاز است. کمی صبر کنید.';
        }

        $day_key = 'asp_rl_d_' . md5( $ip );
        $day_count = (int) get_transient( $day_key );
        if ( $day_count >= self::LIMIT_PER_DAY ) {
            self::log_block( 'rate_day', $ip );
            return 'محدودیت روزانه‌ی درخواست برای این آدرس شبکه به پایان رسیده است.';
        }

        // ── ثبت درخواست فعلی در شمارنده‌ها (بعد از عبور از همه‌ی چک‌ها) ───
        set_transient( $minute_key, $minute_count + 1, MINUTE_IN_SECONDS );
        set_transient( $day_key,    $day_count + 1,    DAY_IN_SECONDS );

        return null; // مجاز
    }

    /**
     * تولید مقدار تایم‌استمپ برای جای‌گذاری در فرم هنگام رندر (سمت PHP).
     * به‌صورت base64 ساده — هدف امنیتی نیست، صرفاً جلوگیری از حدس ساده.
     */
    public static function render_timestamp(): string {
        return base64_encode( (string) microtime( true ) );
    }

    private static function decode_timestamp( string $encoded ): ?float {
        if ( $encoded === '' ) return null;
        $decoded = base64_decode( $encoded, true );
        if ( $decoded === false || ! is_numeric( $decoded ) ) return null;
        return (float) $decoded;
    }

    private static function get_client_ip(): string {
        // توجه: اگه پشت CDN/پروکسی هستید، ممکنه نیاز به خوندن X-Forwarded-For باشه.
        // فعلاً فقط REMOTE_ADDR مستقیم استفاده می‌شه چون سایت پشت CDN نیست.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
    }

    private static function log_block( string $reason, string $ip = '' ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $ip = $ip ?: self::get_client_ip();
            error_log( "[ASP Freight Antispam] Blocked ({$reason}) IP: {$ip}" );
        }
    }
}
