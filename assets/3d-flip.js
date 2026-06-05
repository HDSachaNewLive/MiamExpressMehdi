// 3d-flip.js
document.addEventListener('DOMContentLoaded', () => {
  const currentPage = document.getElementById('current-page');
  if (!currentPage) return;

  // lancer le flip-in au chargement
  window.addEventListener('load', () => {
    const page = document.getElementById('current-page');
    if (!page) return;
    page.classList.add('flip-in');
  });

  // gérer tous les liens en une seule boucle
  document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', e => {
      // exclusions : lien mot de passe oublié, attribut data-no-ajax, classe no-ajax
      if (
        link.id === 'link-forgot' ||
        link.hasAttribute('data-no-ajax') ||
        link.classList.contains('no-ajax')
      ) {
        return;
      }

      const url = link.href;
      const attr = link.getAttribute('href');
      if (!url || attr === '#' || attr === '' || url.startsWith('javascript:') || url.startsWith('mailto:') || link.target === '_blank') {
        return;
      }

      e.preventDefault();

      const page = document.getElementById('current-page');
      if (!page) { window.location = url; return; }

      page.classList.add('flip-out');

      setTimeout(() => {
        window.location = url;
      }, 800); // doit matcher la transition CSS
    });
  });
});
