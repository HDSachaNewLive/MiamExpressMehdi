// assets/address-autocomplete.js
(function () {
  const DEBOUNCE_MS = 350;
  const MIN_CHARS   = 3;

  function init(input) {
    // On ne wrape plus l'input — la dropdown est positionnée via getBoundingClientRect
    const list = document.createElement('ul');
    list.className = 'nominatim-dropdown';
    document.body.appendChild(list); // rattaché au body pour éviter tout problème de overflow/z-index

    let timer  = null;
    let active = -1;

    input.setAttribute('autocomplete', 'off');

    function positionList() {
      const rect = input.getBoundingClientRect();
      list.style.position = 'fixed';
      list.style.top      = (rect.bottom + 2) + 'px';
      list.style.left     = rect.left + 'px';
      list.style.width    = rect.width + 'px';
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < MIN_CHARS) { list.innerHTML = ''; list.style.display = 'none'; return; }
      timer = setTimeout(() => fetchSuggestions(q), DEBOUNCE_MS);
    });

    input.addEventListener('keydown', function (e) {
      const items = list.querySelectorAll('li');
      if (!items.length) return;
      if (e.key === 'ArrowDown')  { e.preventDefault(); active = Math.min(active + 1, items.length - 1); highlight(items); }
      else if (e.key === 'ArrowUp')   { e.preventDefault(); active = Math.max(active - 1, 0); highlight(items); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); items[active].click(); }
      else if (e.key === 'Escape')    { list.innerHTML = ''; list.style.display = 'none'; active = -1; }
    });

    // Repositionner si scroll ou resize
    window.addEventListener('scroll',  positionList, true);
    window.addEventListener('resize',  positionList);

    document.addEventListener('click', function (e) {
      if (e.target !== input) { list.innerHTML = ''; list.style.display = 'none'; active = -1; }
    });

    function highlight(items) {
      items.forEach((li, i) => li.classList.toggle('active', i === active));
      if (active >= 0) items[active].scrollIntoView({ block: 'nearest' });
    }

    function fetchSuggestions(q) {
      const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&q=${encodeURIComponent(q)}`;
      fetch(url, { headers: { 'Accept-Language': 'fr' } })
        .then(r => r.json())
        .then(results => {
          list.innerHTML = '';
          active = -1;
          if (!results.length) { list.style.display = 'none'; return; }

          results.forEach(r => {
            const li = document.createElement('li');
            li.textContent = r.display_name;
            li.addEventListener('mousedown', function (e) {
              e.preventDefault(); // empêche le blur de l'input avant le click
              input.value = r.display_name;

              // Remplir lat/lng si des champs jumeaux existent
              const latField = input.dataset.lat ? document.querySelector(input.dataset.lat) : null;
              const lngField = input.dataset.lng ? document.querySelector(input.dataset.lng) : null;
              if (latField) latField.value = r.lat;
              if (lngField) lngField.value = r.lon;

              list.innerHTML = ''; list.style.display = 'none'; active = -1;
              input.dispatchEvent(new Event('address-selected', { bubbles: true }));
            });
            list.appendChild(li);
          });

          positionList();
          list.style.display = 'block';
        })
        .catch(() => { list.innerHTML = ''; list.style.display = 'none'; });
    }
  }

  // CSS
  const style = document.createElement('style');
  style.textContent = `
    .nominatim-dropdown {
      display: none;
      position: fixed;
      background: transparent;
      backdrop-filter: blur(12px);
      border: 1px solid rgba(0,0,0,0.1);
      border-radius: 0 0 12px 12px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.18);
      list-style: none;
      margin: 0;
      padding: 4px 0;
      z-index: 99999;
      max-height: 240px;
      overflow-y: auto;
    }
    .nominatim-dropdown li {
      padding: 9px 14px;
      font-size: 0.88rem;
      font-family: 'HSR', sans-serif;
      color: #222;
      cursor: pointer;
      transition: background 0.15s;
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      transform: none !important;
      display: block !important;
      margin: 0 !important;
      text-align: left;
    }
    .nominatim-dropdown li:hover,
    .nominatim-dropdown li.active {
      background: rgba(255,107,107,0.12) !important;
      color: #c0392b;
    }
  `;
  document.head.appendChild(style);

  function initAll() {
    document.querySelectorAll('[data-address-autocomplete]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();