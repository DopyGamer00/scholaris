/* ============================================================
   ScholarIS — js/contact.js
   Formulaire de candidature → API MySQL
   Responsable : Richi
============================================================ */

var API = '../database/api.php';

function envoyerCandidature() {
  var nom       = document.getElementById('champ-nom').value.trim();
  var email     = document.getElementById('champ-email').value.trim();
  var filiere   = document.getElementById('champ-filiere').value.trim();
  var annee     = document.getElementById('champ-annee').value;
  var moyenne   = document.getElementById('champ-moyenne').value;
  var situation = document.getElementById('champ-situation').value;
  var message   = document.getElementById('champ-message').value.trim();

  if (!nom || !email || !filiere || !annee || !moyenne || !situation || !message) {
    afficherMessage('⚠️ Veuillez remplir tous les champs obligatoires.', 'erreur');
    return;
  }

  var btn = document.getElementById('btn-envoyer');
  btn.disabled = true;
  btn.textContent = 'Envoi en cours...';

  var form = new FormData();
  form.append('action',    'soumettre_candidature');
  form.append('nom',       nom);
  form.append('email',     email);
  form.append('filiere',   filiere);
  form.append('annee',     annee);
  form.append('moyenne',   moyenne);
  form.append('situation', situation);
  form.append('message',   message);

  fetch(API, { method: 'POST', body: form })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.succes) {
        afficherMessage(
          '✅ Candidature soumise ! Score préliminaire : ' + data.score + '/100. Nous vous recontacterons.',
          'succes'
        );
        viderFormulaire();
      } else {
        afficherMessage('❌ ' + data.message, 'erreur');
      }
    })
    .catch(function () {
      afficherMessage('⚠️ Serveur indisponible. Vérifiez que XAMPP (Apache + MySQL port 3307) est lancé.', 'erreur');
    })
    .finally(function () {
      btn.disabled = false;
      btn.textContent = 'Soumettre ma candidature →';
    });
}

function afficherMessage(texte, type) {
  var div = document.getElementById('message-resultat');
  div.style.display = 'block';
  div.className = 'confirmation confirmation-' + type;
  div.textContent = texte;
  div.scrollIntoView({ behavior: 'smooth' });
}

function viderFormulaire() {
  ['champ-nom','champ-email','champ-filiere','champ-annee',
   'champ-moyenne','champ-situation','champ-message'].forEach(function (id) {
    document.getElementById(id).value = '';
  });
}
