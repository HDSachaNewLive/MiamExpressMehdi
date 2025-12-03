// 3d-flip.js
document.addEventListener('DOMContentLoaded', () => {
  const currentPage = document.getElementById('current-page');
  if (!currentPage) return;

  // lancer le flip-in au chargement
  window.addEventListener('load', () => {
        const currentPage = document.getElementById('current-page');
  if (!currentPage) return;
  currentPage.classList.add('flip-in');
});

  // gérer les liens internes pour flip-out
  document.querySelectorAll('a').forEach(link => {
    if (link.hostname === location.hostname) {
      link.addEventListener('click', e => {
        e.preventDefault();
        const url = link.href;

        currentPage.classList.add('flip-out');


        setTimeout(() => {
          window.location = url;
        }, 800); // doit matcher la transition CSS
      });
    }
  });

  // gérer les liens externes pour flip-out
  document.querySelectorAll('a').forEach(link => {
    if (link.hostname !== location.hostname) {
      link.addEventListener('click', e => {
        e.preventDefault();
        const url = link.href;

        currentPage.classList.add('flip-out');


        setTimeout(() => {
          window.location = url;
        }, 800); // doit matcher la transition CSS
      });
    }
  });

  // gérer les liens marqués (ne pas intercepter)
  document.querySelectorAll('a').forEach(link => {
    if (link.hasAttribute('data-no-ajax') || link.classList.contains('no-ajax')) {
      return; // laisse le navigateur gérer la navigation normalement
    }
  });
});
