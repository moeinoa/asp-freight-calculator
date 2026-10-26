<?php defined( 'ABSPATH' ) || exit; ?>

<div class="bc-wrap" id="bc-wrap">
  <div class="bc-card">

    <!-- ضد ربات: فیلد honeypot — کاربر واقعی هرگز این رو نمی‌بیند و پر نمی‌کند -->
    <div class="bc-hp" aria-hidden="true">
      <label for="asp_hp_field">لطفاً این فیلد را خالی بگذارید</label>
      <input type="text" name="asp_hp_field" id="asp_hp_field" tabindex="-1" autocomplete="off" value="" />
    </div>
    <input type="hidden" id="asp-ts-field" value="<?php echo esc_attr( ASP_Freight_Antispam::render_timestamp() ); ?>" />

    <div class="bc-head">
      <div class="bc-head-title" id="bc-head-title">محاسبه‌گر کرایه حمل</div>
    </div>

    <!-- مبدا -->
    <div class="bc-row">
      <label class="bc-label" for="bc-origin-input">شهر مبدا</label>
      <div class="bc-combo" id="bc-origin-combo">
        <input
          class="bc-select bc-combo-input"
          id="bc-origin-input"
          type="text"
          autocomplete="off"
          placeholder="نام شهر مبدا را تایپ کنید..."
          role="combobox"
          aria-expanded="false"
          aria-autocomplete="list"
          aria-controls="bc-origin-list"
        />
        <ul class="bc-combo-list" id="bc-origin-list" role="listbox"></ul>
      </div>
    </div>

    <!-- مقصد -->
    <div class="bc-row">
      <label class="bc-label" for="bc-dest-input">شهر مقصد</label>
      <div class="bc-combo" id="bc-dest-combo">
        <input
          class="bc-select bc-combo-input"
          id="bc-dest-input"
          type="text"
          autocomplete="off"
          placeholder="نام شهر مقصد را تایپ کنید..."
          role="combobox"
          aria-expanded="false"
          aria-autocomplete="list"
          aria-controls="bc-dest-list"
        />
        <ul class="bc-combo-list" id="bc-dest-list" role="listbox"></ul>
      </div>
    </div>

    <!-- نوع خودرو -->
    <div class="bc-row">
      <label class="bc-label" for="bc-vehicle">نوع خودرو</label>
      <select class="bc-select" id="bc-vehicle">
        <option value="">انتخاب کنید...</option>
      </select>
    </div>

    <!-- نتیجه -->
    <div class="bc-result" id="bc-result">
      <div class="bc-result-label">برآورد کرایه</div>
      <div class="bc-result-value" id="bc-result-value">— تومان</div>
      <div class="bc-result-meta" id="bc-result-meta"></div>
    </div>

    <!-- لودینگ -->
    <div class="bc-loading" id="bc-loading">
      <span class="bc-spinner"></span>
      <span>در حال محاسبه...</span>
    </div>

    <!-- تبصره -->
    <div class="bc-disclaimer">
      <ul>
        <li><strong>هزینه‌های جانبی</strong>: کرایه اعلامی آهن‌شهر صرفاً هزینه خالص حمل جاده‌ای است؛ هزینه‌های بارگیری، تخلیه، جرثقیل، باسکول و... به عهده مشتری بوده و باید مجزا پرداخت شود.</li>
        <li><strong>نوسان نرخ کرایه</strong>: نرخ اعلامی حدودی است و بر اساس وزن دقیق باسکول، نوع آهن‌آلات، شرایط آب‌وهوا و روز بارگیری قابل تغییر است.</li>
        <li><strong>نرخ نهایی</strong>: جهت استعلام دقیق قیمت با کارشناسان حمل و نقل آهن شهر، با شماره تماس <a href="tel:+982149986222">02149986222</a> داخلی‌های <strong>106</strong> و <strong>107</strong> تماس بگیرید.</li>
      </ul>
    </div>

  </div>
</div>
