/* ============================================================
   ScholarIS — js/admin.js
   Logique du dashboard administrateur
   Responsable : Baba Sarr

   Ce script communique avec database/api.php (MySQL)
   pour afficher et gérer les candidatures.
============================================================ */

// ── URL de l'API PHP ──────────────────────────────────────
var API = '../database/api.php';

// ── État global ───────────────────────────────────────────
var filtreActuel           = 'tous';
var idCandidatureActive    = null;
var toutesLesCandidatures  = [];


/* ============================================================
   DÉMARRAGE — Au chargement de la page
============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  chargerDonnees();

  // Clics sur les liens de la sidebar
  document.querySelectorAll('.sidebar-link').forEach(function (lien) {
    lien.addEventListener('click', function (e) {
      e.preventDefault();

      // Lien actif
      document.querySelectorAll('.sidebar-link').forEach(function (l) {
        l.classList.remove('actif');
      });
      this.classList.add('actif');

      // Appliquer le filtre
      filtreActuel = this.getAttribute('data-filtre');
      mettreAJourEnTete(filtreActuel);
      afficherTableau(filtreActuel);
    });
  });
});


/* ============================================================
   CHARGER TOUTES LES DONNÉES (stats + candidatures)
============================================================ */
function chargerDonnees() {
  chargerStats();
  chargerCandidatures();

  // Heure d'actualisation
  var d = new Date();
  document.getElementById('heure-maj').textContent =
    pad(d.getHours()) + ':' + pad(d.getMinutes());
}

function pad(n) { return n < 10 ? '0' + n : n; }


/* ============================================================
   TESTER LA CONNEXION MYSQL
============================================================ */
function testerConnexion() {
  var div = document.getElementById('statut-connexion');
  div.textContent = '⏳ Test en cours...';
  div.className   = 'statut-connexion';

  fetch(API + '?action=tester_connexion')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.succes) {
        div.textContent = '✅ MySQL connecté !';
        div.className   = 'statut-connexion ok';
      } else {
        div.textContent = '❌ ' + data.message;
        div.className   = 'statut-connexion erreur';
      }
    })
    .catch(function () {
      div.textContent = '❌ Serveur inaccessible. XAMPP lancé ?';
      div.className   = 'statut-connexion erreur';
    });
}


/* ============================================================
   CHARGER LES STATISTIQUES
============================================================ */
function chargerStats() {
  fetch(API + '?action=obtenir_stats')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.succes) return;
      var s = data.stats;

      // Cartes
      setText('stat-total',      s.total           || 0);
      setText('stat-attente',    s.en_attente       || 0);
      setText('stat-traitement', s.en_traitement    || 0);
      setText('stat-validees',   s.validees         || 0);
      setText('stat-refusees',   s.refusees         || 0);
      setText('stat-taux',       (s.taux_validation || 0) + '%');

      // Badges sidebar
      setText('badge-en_attente',    s.en_attente    || 0);
      setText('badge-en_traitement', s.en_traitement || 0);
      setText('badge-validee',       s.validees      || 0);
      setText('badge-refusee',       s.refusees      || 0);
    })
    .catch(function () {
      console.warn('Impossible de charger les stats.');
    });
}

function setText(id, valeur) {
  var el = document.getElementById(id);
  if (el) el.textContent = valeur;
}


/* ============================================================
   CHARGER LES CANDIDATURES
============================================================ */
function chargerCandidatures() {
  var corps = document.getElementById('corps-tableau');
  corps.innerHTML = '<tr><td colspan="8" class="chargement">Chargement...</td></tr>';

  fetch(API + '?action=lister_candidatures&statut=tous')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.succes) {
        toutesLesCandidatures = data.candidatures;
        afficherTableau(filtreActuel);
      } else {
        corps.innerHTML = '<tr><td colspan="8" class="chargement erreur-txt">❌ ' + data.message + '</td></tr>';
      }
    })
    .catch(function () {
      corps.innerHTML = '<tr><td colspan="8" class="chargement erreur-txt">' +
        '❌ Impossible de joindre le serveur.<br/>' +
        'Vérifiez que XAMPP (Apache + MySQL) est lancé.' +
        '</td></tr>';
    });
}


/* ============================================================
   AFFICHER LE TABLEAU selon le filtre actuel
============================================================ */
function afficherTableau(filtre) {
  var corps = document.getElementById('corps-tableau');

  var liste = toutesLesCandidatures.filter(function (c) {
    return filtre === 'tous' || c.statut === filtre;
  });

  if (liste.length === 0) {
    corps.innerHTML = '<tr><td colspan="8" class="chargement">Aucune candidature pour ce filtre.</td></tr>';
    return;
  }

  var html = '';
  liste.forEach(function (c) {

    var classeScore = c.score >= 75 ? 'score-eleve' : (c.score >= 50 ? 'score-moyen' : 'score-faible');
    var date = c.date_soumission ? c.date_soumission.substring(0, 10) : '—';

    html += '<tr data-id="' + c.id + '">';
    html += '<td>' + c.id + '</td>';
    html += '<td><span class="candidat-nom">' + esc(c.nom) + '</span>'
          + '<span class="candidat-email">' + esc(c.email) + '</span></td>';
    html += '<td>' + esc(c.filiere) + '<br/><small>' + esc(c.annee) + '</small></td>';
    html += '<td>' + c.moyenne + '/20</td>';
    html += '<td><span class="' + classeScore + '">' + c.score + '/100</span></td>';
    html += '<td>' + badgeStatut(c.statut) + '</td>';
    html += '<td>' + date + '</td>';
    html += '<td><button class="btn-voir" onclick="ouvrirModal(' + c.id + ')">Voir</button></td>';
    html += '</tr>';
  });

  corps.innerHTML = html;
}


/* ============================================================
   RECHERCHE LOCALE dans le tableau
============================================================ */
function filtrerTableau() {
  var terme = document.getElementById('champ-recherche').value.toLowerCase();
  document.querySelectorAll('#corps-tableau tr').forEach(function (ligne) {
    ligne.style.display = ligne.textContent.toLowerCase().includes(terme) ? '' : 'none';
  });
}


/* ============================================================
   OUVRIR LE MODAL de détail
============================================================ */
function ouvrirModal(id) {
  var c = toutesLesCandidatures.find(function (x) { return parseInt(x.id) === id; });
  if (!c) return;

  idCandidatureActive = id;

  document.getElementById('modal-titre').textContent = 'Candidature de ' + c.nom;

  var classeScore = c.score >= 75 ? 'score-eleve' : (c.score >= 50 ? 'score-moyen' : 'score-faible');

  var html = '<div class="detail-grille">';
  html += detail('Nom complet',  c.nom);
  html += detail('Email',        c.email);
  html += detail('Filière',      c.filiere);
  html += detail('Année',        c.annee);
  html += detail('Moyenne',      c.moyenne + ' / 20');
  html += '<div class="detail-item"><label>Score IA</label>'
        + '<span class="' + classeScore + '">' + c.score + ' / 100</span></div>';
  html += detail('Situation',    c.situation);
  html += '<div class="detail-item"><label>Statut</label><span>' + badgeStatut(c.statut) + '</span></div>';
  html += '</div>';

  if (c.message) {
    html += '<p class="detail-label-message">Message du candidat :</p>';
    html += '<div class="detail-message">' + esc(c.message) + '</div>';
  }
  if (c.note_admin) {
    html += '<p class="detail-label-message" style="margin-top:10px">Note admin :</p>';
    html += '<div class="detail-message detail-message--admin">' + esc(c.note_admin) + '</div>';
  }

  document.getElementById('modal-corps').innerHTML = html;
  document.getElementById('note-admin').value = '';
  document.getElementById('modal-overlay').classList.add('ouvert');
}

function detail(label, valeur) {
  return '<div class="detail-item"><label>' + label + '</label><span>' + esc(valeur || '—') + '</span></div>';
}


/* ============================================================
   FERMER LE MODAL
============================================================ */
function fermerModal() {
  document.getElementById('modal-overlay').classList.remove('ouvert');
  idCandidatureActive = null;
}


/* ============================================================
   CHANGER LE STATUT d'une candidature
============================================================ */
function changerStatut(nouveauStatut) {
  if (!idCandidatureActive) return;

  var note = document.getElementById('note-admin').value.trim();

  var form = new FormData();
  form.append('action',     'changer_statut');
  form.append('id',         idCandidatureActive);
  form.append('statut',     nouveauStatut);
  form.append('note_admin', note);

  fetch(API, { method: 'POST', body: form })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.succes) {
        // Mise à jour locale sans rechargement complet
        var idx = toutesLesCandidatures.findIndex(function (x) {
          return parseInt(x.id) === idCandidatureActive;
        });
        if (idx !== -1) {
          toutesLesCandidatures[idx].statut     = nouveauStatut;
          toutesLesCandidatures[idx].note_admin = note;
        }
        fermerModal();
        chargerStats();
        afficherTableau(filtreActuel);
        afficherToast('Statut mis à jour : ' + libelleStatut(nouveauStatut), 'succes');
      } else {
        afficherToast('Erreur : ' + data.message, 'erreur');
      }
    })
    .catch(function () {
      afficherToast('Impossible de contacter le serveur.', 'erreur');
    });
}


/* ============================================================
   EN-TÊTE selon le filtre
============================================================ */
function mettreAJourEnTete(filtre) {
  var titres = {
    'tous'          : ['Toutes les candidatures',   'Vue d\'ensemble complète'],
    'en_attente'    : ['En attente',                'Candidatures non encore traitées'],
    'en_traitement' : ['En traitement',             'Candidatures en cours d\'examen'],
    'validee'       : ['Candidatures validées',     'Bourses accordées à ces étudiants'],
    'refusee'       : ['Candidatures refusées',     'Non retenues pour cette période']
  };
  var t = titres[filtre] || titres['tous'];
  document.getElementById('titre-section').textContent      = t[0];
  document.getElementById('sous-titre-section').textContent = t[1];
}


/* ============================================================
   NOTIFICATION TOAST
============================================================ */
function afficherToast(texte, type) {
  var toast = document.createElement('div');
  toast.className = 'toast toast--' + type;
  toast.textContent = texte;
  document.body.appendChild(toast);
  setTimeout(function () { toast.classList.add('toast--visible'); }, 10);
  setTimeout(function () {
    toast.classList.remove('toast--visible');
    setTimeout(function () { document.body.removeChild(toast); }, 300);
  }, 3000);
}


/* ============================================================
   UTILITAIRES
============================================================ */
function badgeStatut(statut) {
  var map = {
    'en_attente'    : ['⏳ En attente',    'statut-en_attente'],
    'en_traitement' : ['🔄 En traitement', 'statut-en_traitement'],
    'validee'       : ['✅ Validée',       'statut-validee'],
    'refusee'       : ['❌ Refusée',       'statut-refusee'],
  };
  var v = map[statut] || [statut, ''];
  return '<span class="badge-statut ' + v[1] + '">' + v[0] + '</span>';
}

function libelleStatut(statut) {
  var map = { 'en_attente':'En attente','en_traitement':'En traitement','validee':'Validée','refusee':'Refusée' };
  return map[statut] || statut;
}

function esc(t) {
  return String(t || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
