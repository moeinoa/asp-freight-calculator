<?php
defined( 'ABSPATH' ) || exit;

class ASP_Freight_Ajax {

    const NESHAN_ENDPOINT = 'https://api.neshan.org/v1/distance-matrix/no-traffic';

    public function __construct() {
        add_action( 'wp_ajax_asp_freight_calculate',        [ $this, 'handle_calculate' ] );
        add_action( 'wp_ajax_nopriv_asp_freight_calculate', [ $this, 'handle_calculate' ] );
        add_action( 'wp_ajax_asp_freight_clear_cache',      [ $this, 'handle_clear_cache' ] );
    }

    public function handle_calculate(): void {
        // ── ۰. آنتی‌اسپم (honeypot + rate-limit + min-time) ─────────────────
        // این چک قبل از nonce انجام می‌شه چون سبک‌تره و سریع‌تر ربات‌های
        // ساده رو فیلتر می‌کنه، بدون درگیر کردن بقیه‌ی منطق.
        $spam_error = ASP_Freight_Antispam::check();
        if ( $spam_error !== null ) {
            wp_send_json_error( [ 'message' => $spam_error ], 429 );
        }

        // ── ۱. اعتبارسنجی nonce ──────────────────────────────────────────────
        if ( ! check_ajax_referer( 'asp_freight_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است.' ], 403 );
        }

        // ── ۲. دریافت و پاک‌سازی ورودی‌ها ───────────────────────────────────
        $origin_id  = sanitize_text_field( wp_unslash( $_POST['origin']  ?? '' ) );
        $dest_id    = sanitize_text_field( wp_unslash( $_POST['dest']    ?? '' ) );
        $vehicle_id = sanitize_text_field( wp_unslash( $_POST['vehicle'] ?? '' ) );

        if ( ! $origin_id || ! $dest_id || ! $vehicle_id ) {
            wp_send_json_error( [ 'message' => 'پارامترهای ناقص.' ], 400 );
        }

        if ( $origin_id === $dest_id ) {
            wp_send_json_error( [ 'message' => 'مبدا و مقصد یکسان هستند.' ], 400 );
        }

        // ── ۳. بارگذاری داده ─────────────────────────────────────────────────
        $data = $this->load_data();
        if ( ! $data ) {
            wp_send_json_error( [ 'message' => 'فایل داده بارگذاری نشد.' ], 500 );
        }

        // ── ۴. پیدا کردن مبدا و مقصد ─────────────────────────────────────────
        $origin_city = $this->find_city( $data['destinations'], $origin_id );
        $dest_city   = $this->find_city( $data['destinations'], $dest_id );

        if ( ! $origin_city ) {
            wp_send_json_error( [ 'message' => 'شهر مبدا یافت نشد.' ], 404 );
        }
        if ( ! $dest_city ) {
            wp_send_json_error( [ 'message' => 'شهر مقصد یافت نشد.' ], 404 );
        }
        if ( empty( $origin_city['lat'] ) || empty( $dest_city['lat'] ) ) {
            wp_send_json_error( [ 'message' => 'مختصات شهر موجود نیست.' ], 500 );
        }

        // ── ۵. پیدا کردن خودرو ───────────────────────────────────────────────
        $vehicle = $this->find_vehicle( $data['vehicles'], $vehicle_id );
        if ( ! $vehicle ) {
            wp_send_json_error( [ 'message' => 'نوع خودرو یافت نشد.' ], 404 );
        }

        // ── ۶. دریافت فاصله (با کش) ──────────────────────────────────────────
        $distance_km = $this->get_distance( $origin_city, $dest_city );
        if ( $distance_km === null ) {
            wp_send_json_error( [ 'message' => 'خطا در دریافت اطلاعات. کمی دیگر تلاش کنید' ], 502 );
        }

        // ── ۷. محاسبه قیمت ───────────────────────────────────────────────────
        $settings = $data['settings'];
        $price    = ( $vehicle['rate_per_km'] * $distance_km ) + $vehicle['base_fee'];

        if ( ! empty( $vehicle['min_price'] ) && $price < $vehicle['min_price'] ) {
            $min_applied = true;
            $price       = $vehicle['min_price'];
        } else {
            $min_applied = false;
        }

        $round_to = $settings['round_to'] ?? 100000;
        $price    = (int) ceil( $price / $round_to ) * $round_to;

        // ── ۸. پاسخ ──────────────────────────────────────────────────────────
        wp_send_json_success( [
            'price'         => $price,
            'distance_km'   => $distance_km,
            'currency'      => $settings['currency_label'] ?? 'تومان',
            'min_applied'   => $min_applied,
            'origin_note'   => $origin_city['note']  ?? '',
            'dest_note'     => $dest_city['note']    ?? '',
        ] );
    }

    public function handle_clear_cache(): void {
        if ( ! check_ajax_referer( 'asp_freight_admin', 'nonce', false ) ) {
            wp_send_json_error( [], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_asp_freight_dist_%'
                OR option_name LIKE '_transient_timeout_asp_freight_dist_%'"
        );

        wp_send_json_success( [ 'deleted' => $deleted ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function load_data(): ?array {
        static $cache = null;
        if ( $cache !== null ) return $cache;

        if ( ! file_exists( ASP_FREIGHT_DATA ) ) return null;
        $raw = file_get_contents( ASP_FREIGHT_DATA );
        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) return null;

        $cache = $data;
        return $cache;
    }

    private function find_city( array $cities, string $id ): ?array {
        foreach ( $cities as $city ) {
            if ( ( $city['id'] ?? '' ) === $id ) return $city;
        }
        return null;
    }

    private function find_vehicle( array $vehicles, string $id ): ?array {
        foreach ( $vehicles as $v ) {
            if ( $v['id'] === $id ) return $v;
        }
        return null;
    }

    private function get_distance( array $origin, array $dest ): ?float {
        // کلید کش (مستقل از جهت — فاصله A→B = B→A)
        $ids = [ $origin['id'], $dest['id'] ];
        sort( $ids );
        $cache_key = 'asp_freight_dist_' . md5( implode( '|', $ids ) );

        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return (float) $cached;
        }

        $api_key = ASP_Freight_Admin::get_api_key();
        if ( ! $api_key ) return null;

        $url = add_query_arg( [
            'type'         => 'car',
            'origins'      => "{$origin['lat']},{$origin['lng']}",
            'destinations' => "{$dest['lat']},{$dest['lng']}",
        ], self::NESHAN_ENDPOINT );

        $response = wp_remote_get( $url, [
            'headers' => [ 'Api-Key' => $api_key ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) return null;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return null;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $distance_m = $body['rows'][0]['elements'][0]['distance']['value'] ?? null;
        if ( $distance_m === null ) return null;

        $distance_km = round( $distance_m / 1000, 1 );

        $expire = ASP_Freight_Admin::get_cache_days() * DAY_IN_SECONDS;
        set_transient( $cache_key, $distance_km, $expire );

        return $distance_km;
    }
}
