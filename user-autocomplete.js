// user-autocomplete.js
// Autocomplétion de nom d'utilisateur — même principe que address-autocomplete.js,
// mais interroge search_users.php et remplit un champ caché data-target avec l'user_id choisi.
(function () {
  const DEBOUNCE_MS = 300;
  const MIN_CHARS   = 2;

  function init(input) {
    const list = document.createElement('ul');
    list.className = 'user-search-dropdown';
    document.body.appendChild(list); // rattaché au body, comme pour l'autocomplete d'adresse

    const targetSelector = input.dataset.target;
    const hiddenField = targetSelector ? document.querySelector(targetSelector) : null;

    let timer = null;
    let active = -1;
    let selectedValue = ''; // texte exact du dernier utilisateur réellement sélectionné

    input.setAttribute('autocomplete', 'off');

    function positionList() {
      const rect = input.getBoundingClientRect();
      list.style.position = 'fixed';
      list.style.top      = (rect.bottom + 2) + 'px';
      list.style.left     = rect.left + 'px';
      list.style.width    = rect.width + 'px';
    }

    // Si l'utilisateur retouche le texte après avoir sélectionné quelqu'un,
    // on invalide la sélection (il doit re-choisir dans la liste)
    function invaliderSiModifie() {
      if (hiddenField && input.value !== selectedValue) {
        hiddenField.value = '';
      }
    }

    input.addEventListener('input', function () {
      invaliderSiModifie();
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

    window.addEventListener('scroll', positionList, true);
    window.addEventListener('resize', positionList);

    document.addEventListener('click', function (e) {
      if (e.target !== input) { list.innerHTML = ''; list.style.display = 'none'; active = -1; }
    });

    function highlight(items) {
      items.forEach((li, i) => li.classList.toggle('active', i === active));
      if (active >= 0) items[active].scrollIntoView({ block: 'nearest' });
    }

    function fetchSuggestions(q) {
      fetch(`search_users.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(results => {
          list.innerHTML = '';
          active = -1;
          if (!results.length) { list.style.display = 'none'; return; }

          results.forEach(u => {
            const li = document.createElement('li');
            li.textContent = u.nom_user;
            li.addEventListener('mousedown', function (e) {
              e.preventDefault(); // empêche le blur de l'input avant le click
              input.value = u.nom_user;
              selectedValue = u.nom_user;
              if (hiddenField) hiddenField.value = u.user_id;

              list.innerHTML = ''; list.style.display = 'none'; active = -1;
              input.dispatchEvent(new Event('user-selected', { bubbles: true }));
            });
            list.appendChild(li);
          });

          positionList();
          list.style.display = 'block';
        })
        .catch(() => { list.innerHTML = ''; list.style.display = 'none'; });
    }
  }

  // CSS (mêmes classes visuelles que le dropdown d'adresse, préfixe différent)
  const style = document.createElement('style');
  style.textContent = `
    .user-search-dropdown {
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
      margin-top: -2px;
    }
    .user-search-dropdown li {
      padding: 9px 14px;
      font-size: 0.9rem;
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
    .user-search-dropdown li:hover,
    .user-search-dropdown li.active {
      background: rgba(255,107,107,0.12) !important;
      color: #c0392b;
    }

    .user-search-dropdown::-webkit-scrollbar {
      width: 7px;
    }

    .user-search-dropdown::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 20px;
    }

    .user-search-dropdown::-webkit-scrollbar-thumb {
      background: rgba(241, 241, 241, 0.6);
      border-radius: 20px;
      transition: all ease 0.2s;
    }

    .user-search-dropdown::-webkit-scrollbar-thumb:hover {
      background:  rgba(223, 223, 223, 0.67);
      transition: all ease 0.2s;
    }
  `;
  document.head.appendChild(style);

  function initAll() {
    document.querySelectorAll('[data-user-autocomplete]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();
