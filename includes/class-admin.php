<?php
defined( 'ABSPATH' ) || exit;

class ASP_Freight_Admin {

    const OPTION_API_KEY    = 'asp_freight_neshan_api_key';
    const OPTION_CACHE_DAYS = 'asp_freight_cache_days';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'data_file_notice' ] );
    }

    public function add_menu(): void {
        add_options_page(
            'تنظیمات محاسبه‌گر کرایه',
            'محاسبه‌گر کرایه',
            'manage_options',
            'asp-freight-calculator',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'asp_freight_group', self::OPTION_API_KEY, [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'asp_freight_group', self::OPTION_CACHE_DAYS, [
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ] );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $data_ok    = file_exists( ASP_FREIGHT_DATA );
        $cities_ok  = false;
        $missing    = 0;

        if ( $data_ok ) {
            $raw = json_decode( file_get_contents( ASP_FREIGHT_DATA ), true );
            if ( isset( $raw['destinations'] ) ) {
                $missing = count( array_filter( $raw['destinations'], fn($d) => empty($d['lat']) || empty($d['lng']) ) );
                $cities_ok = $missing === 0;
            }
        }
        ?>
        <div class="wrap" dir="rtl">
            <h1>تنظیمات محاسبه‌گر کرایه</h1>

            <div class="notice <?= $data_ok && $cities_ok ? 'notice-success' : 'notice-warning' ?>" style="padding:10px;">
                <?php if ( ! $data_ok ): ?>
                    <p>⚠️ فایل داده یافت نشد. لطفاً <code>calculator-data.json</code> را در پوشه <code>data/</code> پلاگین قرار دهید.</p>
                <?php elseif ( $missing > 0 ): ?>
                    <p>⚠️ <strong><?= $missing ?></strong> شهر فاقد مختصات (<code>lat/lng</code>) هستند. اسکریپت geocoder را اجرا کنید.</p>
                <?php else: ?>
                    <p>✅ فایل داده سالم است. <?= count($raw['destinations']) ?> شهر با مختصات کامل.</p>
                <?php endif; ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'asp_freight_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="<?= self::OPTION_API_KEY ?>">کلید API نشان</label></th>
                        <td>
                            <input
                                type="password"
                                id="<?= self::OPTION_API_KEY ?>"
                                name="<?= self::OPTION_API_KEY ?>"
                                value="<?= esc_attr( get_option( self::OPTION_API_KEY ) ) ?>"
                                class="regular-text"
                                autocomplete="off"
                            />
                            <p class="description">کلید از <a href="https://platform.neshan.org" target="_blank">platform.neshan.org</a> دریافت می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="<?= self::OPTION_CACHE_DAYS ?>">مدت کش فاصله‌ها (روز)</label></th>
                        <td>
                            <input
                                type="number"
                                id="<?= self::OPTION_CACHE_DAYS ?>"
                                name="<?= self::OPTION_CACHE_DAYS ?>"
                                value="<?= esc_attr( get_option( self::OPTION_CACHE_DAYS, 30 ) ) ?>"
                                min="1" max="365"
                                style="width:80px"
                            />
                            <p class="description">فاصله‌های دریافت‌شده از نشان برای این مدت کش می‌شوند تا مصرف API کاهش یابد.</p>
                        </td>
                    </tr>
                </table>

                <h2>شورت‌کد</h2>
                <p>برای نمایش ماشین‌حساب از شورت‌کد زیر استفاده کنید:</p>
                <code style="font-size:15px;padding:6px 12px;background:#f0f0f0;display:inline-block;border-radius:4px;">
                    [asp_freight_calculator]
                </code>

                <h2>راهنمای فایل داده</h2>
                <ol>
                    <li>فایل <code>calculator-data.json</code> خود را در پوشه <code><?= ASP_FREIGHT_DIR ?>data/</code> قرار دهید.</li>
                    <li>اسکریپت <code>geocode_cities.py</code> را اجرا کنید تا مختصات شهرها تکمیل شود.</li>
                    <li>فایل خروجی <code>calculator-data-geocoded.json</code> را جایگزین فایل اصلی کنید.</li>
                </ol>

                <p>
                    <button type="button" class="button" onclick="aspFreightClearCache()">پاک‌کردن کش فاصله‌ها</button>
                    <span id="asp-cache-result" style="margin-right:10px;color:green;display:none">✓ کش پاک شد</span>
                </p>

                <?php submit_button( 'ذخیره تنظیمات' ); ?>
            </form>
        </div>

        <script>
        function aspFreightClearCache() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=asp_freight_clear_cache&nonce=<?= wp_create_nonce('asp_freight_admin') ?>'
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('asp-cache-result').style.display = 'inline';
                }
            });
        }
        </script>
        <?php
    }

    public function data_file_notice(): void {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'settings_page_asp-freight-calculator' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! get_option( self::OPTION_API_KEY ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p>محاسبه‌گر کرایه: کلید API نشان تنظیم نشده. <a href="' . admin_url('options-general.php?page=asp-freight-calculator') . '">تنظیمات</a></p></div>';
        }
    }

    public static function get_api_key(): string {
        return (string) get_option( self::OPTION_API_KEY, '' );
    }

    public static function get_cache_days(): int {
        return (int) get_option( self::OPTION_CACHE_DAYS, 30 );
    }
}
