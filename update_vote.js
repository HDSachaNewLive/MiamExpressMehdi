// assets/update_vote.js
// écoute en délégation sur le document pour attraper tous les .vote-buttons
document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', function (e) {
    // like
    const likeBtn = e.target.closest('.like-btn');
    const dislikeBtn = e.target.closest('.dislike-btn');
    let btn = null;
    let type = null;

    if (likeBtn) {
      btn = likeBtn;
      type = 'like';
    } else if (dislikeBtn) {
      btn = dislikeBtn;
      type = 'dislike';
    }

    if (!btn) return;

    const container = btn.closest('.vote-buttons');
    if (!container) return;
    const avisId = container.dataset.avisId || container.getAttribute('data-avis-id');
    if (!avisId) return console.warn('vote: missing avis id');

    // Optionnel: feedback immédiat (disable while waiting)
    disableButtons(container, true);

    fetch('vote_comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `avis_id=${encodeURIComponent(avisId)}&type=${encodeURIComponent(type)}`
    })
    .then(r => r.json())
    .then(data => {
      disableButtons(container, false);
      if (!data) return;
      if (data.error) {
        if (data.error === 'not_logged') {
          // rediriger ou afficher message
          alert('Connecte-toi pour voter !');
        } else {
          console.error('Vote error', data);
          alert('Erreur lors du vote (voir console).');
        }
        return;
      }
      if (data.success) {
        // Met à jour les compteurs affichés
        const likeSpan = container.querySelector('.like-btn .count');
        const dislikeSpan = container.querySelector('.dislike-btn .count');
        if (likeSpan) likeSpan.textContent = data.likes;
        if (dislikeSpan) dislikeSpan.textContent = data.dislikes;

        // Met à jour l'état visuel (active)
        const likeEl = container.querySelector('.like-btn');
        const disEl = container.querySelector('.dislike-btn');

        // Si server renvoie your_vote null -> toggled off
        const yourVote = data.your_vote ?? null;

        if (yourVote === 'like') {
          likeEl.classList.add('active');
          disEl.classList.remove('active');
          disEl.classList.remove('dislike');
        } else if (yourVote === 'dislike') {
          disEl.classList.add('active');
          disEl.classList.add('dislike');
          likeEl.classList.remove('active');
        } else { // null => user a toggled off
          likeEl.classList.remove('active');
          disEl.classList.remove('active');
          disEl.classList.remove('dislike');
        }

        // pulse feedback
        pulseButton(btn);
      }
    })
    .catch(err => {
      disableButtons(container, false);
      console.error('Fetch vote error', err);
      alert('Erreur réseau lors du vote.');
    });
  });
});

function disableButtons(container, state) {
  const btns = container.querySelectorAll('button');
  btns.forEach(b => b.disabled = !!state);
}

function pulseButton(btn) {
  if (!btn) return;
  btn.animate([
    { transform: 'scale(1)' },
    { transform: 'scale(1.07)' },
    { transform: 'scale(1)' }
  ], { duration: 200, easing: 'ease-out' });
}
