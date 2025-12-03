<?php
// admin_annonces.php
session_start();
require_once 'db/config.php';

/* Vérification admin */
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: index.php");
    exit;
}

/* Gestion des modifications via AJAX */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $id = (int) $_POST['annonce_id'];
    $field = $_POST['field']; // 'titre' ou 'message'
    $value = trim($_POST['value']);

    if (empty($value)) {
        echo json_encode(['success' => false, 'error' => 'Le champ ne peut pas être vide']);
        exit;
    }

    if ($field === 'titre' || $field === 'message') {
        $stmt = $conn->prepare("UPDATE annonces SET `$field` = ? WHERE annonce_id = ?");
        if ($stmt->execute([$value, $id])) {
            echo json_encode(['success' => true, 'message' => 'Mise à jour réussie']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur de mise à jour']);
        }
        exit;
    }
}

/* Suppression d'une annonce */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM annonces WHERE annonce_id = ?");
    $stmt->execute([$id]);
    $_SESSION['deleted_message'] = true;
    header("Location: admin_annonces.php");
    exit;
}

/* Création d'une annonce */
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $texte = trim($_POST['message']);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];

    // Correction format datetime-local → MySQL
    $date_debut = str_replace('T', ' ', $date_debut) . ':00';
    $date_fin = str_replace('T', ' ', $date_fin) . ':00';

    if (empty($titre) || empty($texte)) {
        $error = "❌ Le titre et le message sont requis.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO annonces (titre, message, date_debut, date_fin)
            VALUES (?, ?, ?, ?)
        ");

        try {
            $stmt->execute([$titre, $texte, $date_debut, $date_fin]);
            $_SESSION['created_message'] = true;
            header("Location: admin_annonces.php");
            exit;
        } catch (PDOException $e) {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

/* Charger annonces */
$stmt = $conn->query("SELECT * FROM annonces ORDER BY date_debut DESC");
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($error && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = "";
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Admin - Gestion des annonces</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<?php include 'sidebar.php'; ?>
<audio id="player" autoplay loop> 
    <source src="assets/Mairie - Animal Crossing New Horizons OST.mp3" type="audio/mp3"> 
</audio>
<?php include 'slider_son.php'; ?>

<main class="container">

    <h2 class="admin-title">📢 Gestion des annonces</h2>

    <?php if (isset($_SESSION['created_message'])): ?>
        <div class="success">✅ Annonce créée avec succès !</div>
        <?php unset($_SESSION['created_message']); ?>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['deleted_message'])): ?>
        <div class="success">🗑️ Annonce supprimée avec succès !</div>
        <?php unset($_SESSION['deleted_message']); ?>
    <?php endif; ?>

    <div class="admin-form">
        <h3>Créer une nouvelle annonce</h3>

        <form method="POST">

            <label>Titre de l'annonce :</label>
            <input type="text" name="titre" required placeholder="ex: Nouvelle fonctionnalité !">

            <label>Message :</label>
            <textarea name="message" rows="4" required placeholder="Décrivez l'annonce..."></textarea>

            <label>Date de début :</label>
            <input type="datetime-local" name="date_debut" required>

            <label>Date de fin :</label>
            <input type="datetime-local" name="date_fin" required>

            <button class="btn" type="submit">Créer l'annonce</button>
        </form>
    </div>

    <div class="admin-table">
        <h3>Annonces existantes</h3>

        <?php if (empty($annonces)): ?>
            <p>Aucune annonce pour le moment.</p>
        <?php else: ?>

        <table>
            <tr>
                <th>Titre</th>
                <th>Message</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>

            <?php foreach ($annonces as $a): ?>
            <?php 
                $now = date('Y-m-d H:i:s');
                $is_expired = $a['date_fin'] < $now;
                $is_pending = $a['date_debut'] > $now;
                $is_active = $a['actif'] && !$is_expired && !$is_pending;
            ?>
            <tr class="<?= $is_active ? '' : 'inactive-annonce' ?>">
                <td>
                    <strong><?= htmlspecialchars($a['titre']) ?></strong>
                </td>
                <td class="editable" data-id="<?= $a['annonce_id'] ?>" data-field="message" title="Cliquez pour modifier" data-original="<?= htmlspecialchars($a['message']) ?>">
                    <?= nl2br(htmlspecialchars($a['message'])) ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($a['date_debut'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($a['date_fin'])) ?></td>
                <td>
                    <?php if (!$a['actif']): ?>
                        <span class="status-badge inactive">Désactivé</span>
                    <?php elseif ($is_expired): ?>
                        <span class="status-badge expired">Expiré</span>
                    <?php elseif ($is_pending): ?>
                        <span class="status-badge pending">À venir</span>
                    <?php else: ?>
                        <span class="status-badge active">✅ Actif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="delete" href="?delete=<?= $a['annonce_id'] ?>" onclick="return confirm('Supprimer cette annonce ?');">
                        Supprimer
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php endif; ?>
    </div>
    
    <p><a href="home.php" class="back-link">⬅ Retour à l'accueil</a></p>

</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 1205.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xf6b26b,
  shininess: 60,
  waveHeight: 22,
  waveSpeed: 0.7,
  zoom: 1.1
})
</script>

<style>
.container {
    backdrop-filter: blur(15px);
    background: linear-gradient(135deg, rgba(255, 235, 205, 0.3), rgba(255, 200, 200, 0.3));
    padding: 2rem;
    border-radius: 1.5rem;
    max-width: 1200px;
    margin: 100px auto;
    box-shadow: 0 8px 30px rgba(255, 107, 107, 0.2);
}

.admin-title {
    color: #ff6b6b;
    margin-bottom: 2rem;
    text-align: center;
    font-size: 2rem;
    text-shadow: 2px 2px 4px rgba(255, 107, 107, 0.3);
}

.success {
    background: linear-gradient(135deg, rgba(0, 255, 127, 0.25), rgba(76, 175, 80, 0.3));
    padding: 12px 20px;
    border-radius: 12px;
    margin-top: 15px;
    margin-bottom: 20px;
    border-left: 5px solid #00ff7f;
    color: #006837;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(0, 255, 127, 0.2);
}

.error {
    background: linear-gradient(135deg, rgba(255, 77, 77, 0.25), rgba(244, 67, 54, 0.3));
    padding: 12px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 5px solid #ff4d4d;
    color: #8b0000;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(255, 77, 77, 0.2);
}

.admin-form,
.admin-table {
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px);
    border-radius: 1.5rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 20px rgba(255, 140, 66, 0.15);
    border: 2px solid rgba(255, 107, 107, 0.2);
}

.admin-form h3,
.admin-table h3 {
    margin-top: 0;
    color: #ff6b6b;
    margin-bottom: 1.5rem;
    font-size: 1.6rem;
    text-shadow: 1px 1px 3px rgba(255, 107, 107, 0.2);
}

.admin-form label {
    margin-top: 1rem;
    display: block;
    font-weight: bold;
    color: #333;
    margin-bottom: 0.5rem;
}

.admin-form input,
.admin-form textarea {
    width: 100%;
    padding: 0.8rem;
    margin-top: 0.4rem;
    border-radius: 0.8rem;
    border: 2px solid rgba(255, 107, 107, 0.2);
    background: rgba(255, 255, 255, 0.6);
    font-family: 'HSR', sans-serif;
    transition: all 0.3s ease;
    box-sizing: border-box;
    resize: none;
}

.admin-form input:focus,
.admin-form textarea:focus {
    outline: none;
    border-color: #ff6b6b;
    background: rgba(255, 255, 255, 0.8);
    transform: scale(1.01);
    box-shadow: 0 0 10px rgba(255, 107, 107, 0.3);
}

.btn {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 1rem 1.8rem;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ff8c42);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(255, 107, 107, 0.3);
  transition: all 0.35s ease;
  overflow: hidden;
  text-align: center;
  margin-top: 20px;
  position: relative;
}

.btn::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn:hover::after { left: 0; }
.btn:hover { 
  transform: translateY(-4px) scale(1.03); 
  box-shadow: 0 12px 25px rgba(255, 107, 107, 0.4); 
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.95rem;
    table-layout: fixed;
}

th, td {
    padding: 1rem;
    border-bottom: 2px solid rgba(255, 107, 107, 0.2);
    text-align: left;
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

th {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.3), rgba(255, 140, 66, 0.3));
    font-weight: 700;
    color: #333;
}

/* Largeurs spécifiques des colonnes */
th:nth-child(1), td:nth-child(1) { width: 15%; } /* Titre */
th:nth-child(2), td:nth-child(2) { width: 35%; } /* Message */
th:nth-child(3), td:nth-child(3) { width: 12%; } /* Début */
th:nth-child(4), td:nth-child(4) { width: 12%; } /* Fin */
th:nth-child(5), td:nth-child(5) { width: 13%; } /* Statut */
th:nth-child(6), td:nth-child(6) { width: 13%; } /* Action */

/* Colonne Message avec formatage */
td:nth-child(2) {
    word-break: break-word;
    line-height: 1.6;
    max-height: 150px;
    overflow-y: auto;
    white-space: normal;
}

tr.inactive-annonce {
    opacity: 0.5;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.status-badge.active {
    background: rgba(76, 175, 80, 0.2);
    color: #2e7d32;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
}

.status-badge.inactive {
    background: rgba(158, 158, 158, 0.2);
    color: #616161;
}

.status-badge.expired {
    background: rgba(244, 67, 54, 0.2);
    color: #c62828;
}

.status-badge.pending {
    background: rgba(33, 150, 243, 0.2);
    color: #1565c0;
}

a.delete {
    color: #ff3b3b;
    font-weight: bold;
    text-decoration: none;
    transition: all 0.3s ease;
}

a.delete:hover {
    color: #cc0000;
    text-decoration: underline;
}

/* Style pour les cellules éditables */
.editable {
    cursor: pointer;
    position: relative;
    border-radius: 0.4rem;
    transition: all 0.2s ease;
    resize: none;
}

.editable:hover {
    background: rgba(255, 107, 107, 0.15);
}

.editable.editing {
    padding: 0.5rem !important;
}

.editable-input,
.editable-textarea {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #ff6b6b;
    border-radius: 0.4rem;
    font-family: inherit;
    font-size: inherit;
    background: rgba(255, 255, 255, 0.9);
    box-sizing: border-box;
    margin-bottom: -8px;
    resize: none;
}

.editable-textarea {
    resize: none;
    min-height: 80px;
}

.editable-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.editable-save,
.editable-cancel {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.6rem;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Boutons plus petits pour les champs titre */
.editable[data-field="titre"] .editable-save,
.editable[data-field="titre"] .editable-cancel {
    padding: 0.3rem 0.6rem;
    font-size: 0.8rem;
}

.editable-save {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
}

.editable-save:hover {
    background: linear-gradient(135deg, #45a049, #388e3c);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
}

.editable-cancel {
    background: linear-gradient(135deg, #ff6b6b, #ff5252);
    color: white;
}

.editable-cancel:hover {
    background: linear-gradient(135deg, #ff5252, #ff3b3b);
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
}

.back-link {
    display: inline-block;
    margin-top: -10px;
    margin-bottom: -20px;
    color: #ff6b6b;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
    border-radius: 0.8rem;
    background: rgba(255, 107, 107, 0.1);
}

.back-link:hover {
    background: rgba(255, 107, 107, 0.2);
    transform: translateX(-5px);
}

/* Responsive */
@media (max-width: 1200px) {
    .admin-table {
        overflow-x: auto;
    }
}
</style>

<script>
(function(){
  const adjustHeight = el => {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  };

  const attachAutoResize = el => {
    if (!el) return;
    if (el.__autoResizeAttached) return;
    el.__autoResizeAttached = true;
    adjustHeight(el);
    el.addEventListener('input', () => adjustHeight(el));
  };

  document.querySelectorAll('textarea').forEach(attachAutoResize);

  const addPlatBtn = document.getElementById('add-plat');
  if (addPlatBtn) {
    addPlatBtn.addEventListener('click', () => {
      setTimeout(() => {
        document.querySelectorAll('textarea').forEach(attachAutoResize);
      }, 0);
    });
  }

  /* Gestion de l'édition des cellules */
  document.querySelectorAll('.editable').forEach(cell => {
    cell.addEventListener('click', function(e) {
      if (this.classList.contains('editing')) return;
      e.stopPropagation();

      const id = this.dataset.id;
      const field = this.dataset.field;
      let currentValue = this.dataset.original || this.innerText;
      const originalHtml = this.innerHTML;
      const isTextarea = field === 'message';

      this.classList.add('editing');
      this.innerHTML = '';

      const input = document.createElement(isTextarea ? 'textarea' : 'input');
      input.className = isTextarea ? 'editable-textarea' : 'editable-input';
      input.value = currentValue;
      if (!isTextarea) input.type = 'text';

      const actionsDiv = document.createElement('div');
      actionsDiv.className = 'editable-actions';

      const saveBtn = document.createElement('button');
      saveBtn.className = 'editable-save';
      saveBtn.textContent = 'Enregistrer';
      saveBtn.type = 'button';

      const cancelBtn = document.createElement('button');
      cancelBtn.className = 'editable-cancel';
      cancelBtn.textContent = 'Annuler';
      cancelBtn.type = 'button';

      actionsDiv.appendChild(saveBtn);
      actionsDiv.appendChild(cancelBtn);

      this.appendChild(input);
      this.appendChild(actionsDiv);

      if (isTextarea) {
        attachAutoResize(input);
        input.focus();
      } else {
        input.focus();
        input.select();
      }

      const cancelEdit = () => {
        cell.classList.remove('editing');
        cell.innerHTML = originalHtml;
      };

      const saveEdit = () => {
        const newValue = input.value.trim();
        if (!newValue) {
          alert('Le champ ne peut pas être vide');
          input.focus();
          return;
        }

        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('annonce_id', id);
        formData.append('field', field);
        formData.append('value', newValue);

        fetch('admin_annonces.php', {
          method: 'POST',
          body: formData
        })
        .then(resp => {
          if (!resp.ok) {
            throw new Error('Erreur HTTP: ' + resp.status);
          }
          return resp.json();
        })
        .then(data => {
          if (data.success) {
            cell.classList.remove('editing');
            const displayText = newValue.replace(/\n/g, '<br>');
            cell.innerHTML = displayText;
            cell.dataset.original = newValue;
          } else {
            alert('Erreur : ' + (data.error || 'Impossible de mettre à jour'));
            input.focus();
          }
        })
        .catch(err => {
          console.error(err);
          alert('Erreur réseau');
          input.focus();
        });
      };

      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        saveEdit();
      });
      cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        cancelEdit();
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.ctrlKey) saveEdit();
        if (e.key === 'Escape') cancelEdit();
      });
    });
  });

  function htmlEscape(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }
})();
</script>
</body>
</html>