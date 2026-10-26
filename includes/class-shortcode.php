<?php
defined( 'ABSPATH' ) || exit;

class ASP_Freight_Shortcode {

    public function __construct() {
        add_shortcode( 'asp_freight_calculator', [ $this, 'render' ] );
    }

    public function render( $atts ): string {
        $this->enqueue_assets();

        $data = $this->load_data();
        if ( ! $data ) {
            return '<p style="color:red;">خطا: فایل داده محاسبه‌گر یافت نشد.</p>';
        }

        // فقط اسم و id شهرها به مرورگر فرستاده می‌شه — بدون lat/lng
        $cities   = array_map( fn($d) => [ 'id' => $d['id'], 'label' => $d['city'] ], $data['destinations'] );
        $vehicles = array_map( fn($v) => [ 'id' => $v['id'], 'label' => $v['label'] ], $data['vehicles'] );

        wp_localize_script( 'asp-freight-calculator', 'aspFreightData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'asp_freight_nonce' ),
            'cities'   => $cities,
            'vehicles' => $vehicles,
        ] );

        ob_start();
        include ASP_FREIGHT_DIR . 'templates/calculator.php';
        return ob_get_clean();
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'asp-freight-calculator',
            ASP_FREIGHT_URL . 'assets/css/calculator.css',
            [],
            ASP_FREIGHT_VER
        );
        wp_enqueue_script(
            'asp-freight-calculator',
            ASP_FREIGHT_URL . 'assets/js/calculator.js',
            [],
            ASP_FREIGHT_VER,
            true
        );
    }

    private function load_data(): ?array {
        if ( ! file_exists( ASP_FREIGHT_DATA ) ) return null;
        $data = json_decode( file_get_contents( ASP_FREIGHT_DATA ), true );
        return ( json_last_error() === JSON_ERROR_NONE ) ? $data : null;
    }
}
