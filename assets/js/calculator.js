(function () {
  'use strict';

  var data     = window.aspFreightData;
  var cities   = data.cities;   // [{id, label}, ...]
  var vehicles = data.vehicles; // [{id, label}, ...]

  var originInput  = document.getElementById('bc-origin-input');
  var originList   = document.getElementById('bc-origin-list');
  var originCombo  = document.getElementById('bc-origin-combo');
  var destInput    = document.getElementById('bc-dest-input');
  var destList     = document.getElementById('bc-dest-list');
  var destCombo    = document.getElementById('bc-dest-combo');
  var vehicleSel   = document.getElementById('bc-vehicle');
  var resultValue  = document.getElementById('bc-result-value');
  var resultMeta   = document.getElementById('bc-result-meta');
  var loadingEl    = document.getElementById('bc-loading');
  var headTitle    = document.getElementById('bc-head-title');

  var selectedOrigin = null; // {id, label}
  var selectedDest   = null; // {id, label}
  var fmt = new Intl.NumberFormat('fa-IR');
  var debounceTimer  = null;
  var currentRequest = null; // AbortController

  // ── پر کردن لیست خودروها ─────────────────────────────────────────────────
  vehicles.forEach(function (v) {
    var opt = document.createElement('option');
    opt.value       = v.id;
    opt.textContent = v.label;
    vehicleSel.appendChild(opt);
  });

  // ── combobox عمومی ────────────────────────────────────────────────────────

  function buildCombo(input, list, combo, onSelect, onClear, otherSelectedId) {
    var filtered    = [];
    var activeIndex = -1;

    function highlight(text, query) {
      if (!query) return text;
      var idx = text.indexOf(query);
      if (idx === -1) return text;
      return (
        text.slice(0, idx) +
        '<mark>' + text.slice(idx, idx + query.length) + '</mark>' +
        text.slice(idx + query.length)
      );
    }

    function render(query) {
      var q = (query || '').trim();
      filtered = q
        ? cities.filter(function (c) {
            return c.label.indexOf(q) !== -1 && c.id !== otherSelectedId();
          })
        : cities.filter(function (c) { return c.id !== otherSelectedId(); });

      activeIndex = -1;
      list.innerHTML = '';

      if (filtered.length === 0) {
        var empty = document.createElement('li');
        empty.className   = 'bc-combo-empty';
        empty.textContent = 'شهری پیدا نشد';
        list.appendChild(empty);
        return;
      }

      filtered.forEach(function (city, i) {
        var li = document.createElement('li');
        li.className          = 'bc-combo-option';
        li.setAttribute('role', 'option');
        li.setAttribute('data-index', i);
        li.innerHTML          = highlight(city.label, q);
        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
          select(city);
        });
        list.appendChild(li);
      });
    }

    function open() {
      list.classList.add('bc-combo-list-open');
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      list.classList.remove('bc-combo-list-open');
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
    }

    function setActive(index) {
      var options = list.querySelectorAll('.bc-combo-option');
      options.forEach(function (o) { o.classList.remove('bc-combo-option-active'); });
      if (index >= 0 && index < options.length) {
        options[index].classList.add('bc-combo-option-active');
        options[index].scrollIntoView({ block: 'nearest' });
      }
      activeIndex = index;
    }

    function select(city) {
      input.value = city.label;
      close();
      onSelect(city);
    }

    input.addEventListener('focus', function () {
      render(input.value);
      open();
    });

    input.addEventListener('input', function () {
      onClear();
      render(input.value);
      open();
    });

    input.addEventListener('keydown', function (e) {
      var options = list.querySelectorAll('.bc-combo-option');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!list.classList.contains('bc-combo-list-open')) { open(); return; }
        if (options.length) setActive((activeIndex + 1) % options.length);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (options.length) setActive((activeIndex - 1 + options.length) % options.length);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex >= 0 && filtered[activeIndex]) select(filtered[activeIndex]);
      } else if (e.key === 'Escape') {
        close();
        input.blur();
      }
    });

    document.addEventListener('click', function (e) {
      if (!combo.contains(e.target)) close();
    });
  }

  // ── راه‌اندازی دو combobox ────────────────────────────────────────────────

  buildCombo(
    originInput, originList, originCombo,
    function (city) { selectedOrigin = city; updateTitle(); calculate(); },
    function ()     { selectedOrigin = null; resetResult(); },
    function ()     { return selectedDest ? selectedDest.id : null; }
  );

  buildCombo(
    destInput, destList, destCombo,
    function (city) { selectedDest = city; calculate(); },
    function ()     { selectedDest = null; resetResult(); },
    function ()     { return selectedOrigin ? selectedOrigin.id : null; }
  );

  vehicleSel.addEventListener('change', calculate);

  // ── به‌روزرسانی عنوان ─────────────────────────────────────────────────────
  function updateTitle() {
    if (selectedOrigin) {
      headTitle.textContent = 'مبدا : ' + selectedOrigin.label;
    } else {
      headTitle.textContent = 'محاسبه‌گر کرایه حمل';
    }
  }

  // ── ریست نتیجه ───────────────────────────────────────────────────────────
  function resetResult() {
    resultValue.textContent = '— تومان';
    resultMeta.textContent  = '';
  }

  // ── محاسبه (AJAX) ─────────────────────────────────────────────────────────
  function calculate() {
    if (!selectedOrigin || !selectedDest || !vehicleSel.value) {
      resetResult();
      return;
    }

    // لغو درخواست قبلی در صورت وجود
    if (currentRequest) currentRequest.abort();

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      var controller   = new AbortController();
      currentRequest   = controller;

      loadingEl.style.display   = 'flex';
      resultValue.textContent   = '— تومان';
      resultMeta.textContent    = '';

      var hpField = document.getElementById('asp_hp_field');
      var tsField = document.getElementById('asp-ts-field');

      var body = new URLSearchParams({
        action       : 'asp_freight_calculate',
        nonce        : data.nonce,
        origin       : selectedOrigin.id,
        dest         : selectedDest.id,
        vehicle      : vehicleSel.value,
        asp_hp_field : hpField ? hpField.value : '',
        asp_ts       : tsField ? tsField.value : '',
      });

      fetch(data.ajaxUrl, {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : body.toString(),
        signal : controller.signal,
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          loadingEl.style.display = 'none';
          currentRequest = null;

          if (!res.success) {
            resultValue.textContent = res.data.message || 'خطا در محاسبه';
            resultMeta.textContent  = '';
            return;
          }

          var d = res.data;
          resultValue.textContent = fmt.format(d.price) + ' ' + d.currency;

          var meta = [];
          meta.push(d.distance_km + ' کیلومتر');
          if (d.dest_note)    meta.push(d.dest_note);
          if (d.min_applied)  meta.push('کف قیمت اعمال شد');
          resultMeta.textContent = meta.join(' · ');
        })
        .catch(function (err) {
          if (err.name === 'AbortError') return;
          loadingEl.style.display = 'none';
          resultValue.textContent = 'خطا در ارتباط با سرور';
          resultMeta.textContent  = '';
        });
    }, 300);
  }

})();
