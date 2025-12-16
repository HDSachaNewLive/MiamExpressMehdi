let surprisePlats = [];

async function getSurprise() {
  const budgetMin = parseFloat(document.getElementById('budget-min').value) || 0;
  const budgetMax = parseFloat(document.getElementById('budget-max').value) || 100;
  
  console.log('Budget min:', budgetMin, 'Budget max:', budgetMax); // Debug pour la logs (et je parle pas de celle que j'ai entre les jambes)
  
  if (budgetMin >= budgetMax) {
    showMessage('⚠️ Le budget minimum doit être inférieur au maximum', 'error');
    return;
  }
  
  const btn = document.querySelector('.btn-surprise');
  if (!btn) {
    console.error('Bouton .btn-surprise introuvable');
    return;
  }
  
  btn.disabled = true;
  btn.textContent = '🎲 Recherche...';
  
  try {
    const formData = new FormData();
    formData.append('budget_min', budgetMin);
    formData.append('budget_max', budgetMax);
    
    console.log('Envoi de la requête...'); // Debug
    
    const response = await fetch('surprise_me.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Réponse reçue:', response.status); // Debug
    
    const data = await response.json();
    console.log('Données:', data); // Debug
    
    if (data.error) { //pas utile car home.php est pas accesible si on est pas connecté
      if (data.error === 'not_logged') {
        showMessage('⚠️ Connecte-toi pour utiliser cette fonctionnalité', 'error');
      } else {
        showMessage('⚠️ ' + (data.message || 'Erreur lors de la recherche'), 'error');
      }
      return;
    }
    
    if (data.success) {
      displaySurpriseResults(data.plats, data.total);
    }
    
  } catch (error) {
    console.error('Erreur complète:', error);
    showMessage('⚠️ Erreur de connexion', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = '🎲 Surprenez-moi !';
  }
}

function displaySurpriseResults(plats, total) {
  surprisePlats = plats;
  
  const resultsDiv = document.getElementById('surprise-results');
  const platsDiv = document.getElementById('surprise-plats');
  const totalSpan = document.getElementById('surprise-total-amount');
  
  platsDiv.innerHTML = '';
  
  plats.forEach(plat => {
    const card = document.createElement('div');
    card.className = 'surprise-plat-card';
    card.innerHTML = `
      <div class="surprise-plat-info">
        <h5>${escapeHtml(plat.nom_plat)}</h5>
        <span class="restaurant-name">📍 ${escapeHtml(plat.nom_restaurant)}</span>
      </div>
      <div class="surprise-plat-price">${parseFloat(plat.prix).toFixed(2)} €</div>
    `;
    platsDiv.appendChild(card);
  });
  
  totalSpan.textContent = total;
  resultsDiv.style.display = 'block';
  
  // Scroll vers les résultats
  resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function addSurpriseToCart() {
  if (surprisePlats.length === 0) return;
  
  let addedCount = 0;
  
  for (const plat of surprisePlats) {
    try {
      const formData = new FormData();
      formData.append('plat_id', plat.plat_id);
      formData.append('quantite', 1);
      
      const response = await fetch('panier.php', {
        method: 'POST',
        body: formData
      });
      
      if (response.ok) {
        addedCount++;
      }
    } catch (error) {
      console.error('Erreur ajout panier:', error);
    }
  }
  
  if (addedCount === surprisePlats.length) {
    showMessage(`✅ ${addedCount} plat(s) ajouté(s) au panier !`, 'success');
    setTimeout(() => {
      window.location.href = 'panier.php';
    }, 1500);
  } else {
    showMessage(`⚠️ ${addedCount}/${surprisePlats.length} plat(s) ajouté(s)`, 'error');
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function showMessage(text, type = 'success') {
  const oldMsg = document.querySelector('.flash-message');
  if (oldMsg) oldMsg.remove();
  
  const msg = document.createElement('div');
  msg.className = `flash-message ${type}`;
  msg.textContent = text;
  document.body.appendChild(msg);
  
  setTimeout(() => {
    msg.classList.add('hide');
    setTimeout(() => msg.remove(), 400);
  }, 3000);
}