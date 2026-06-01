/* ============================================================
   ScholarIS — main.js
   Script principal du site
   Responsable : Richi

   TABLE DES MATIÈRES :
   1. Navbar — ombre au scroll
   2. Menu mobile — bouton burger
   3. Animations au scroll — révélation des éléments
============================================================ */


/* ============================================================
   1. NAVBAR — Ombre au scroll
   On ajoute une classe CSS quand l'utilisateur défile
   pour montrer une ombre sous la barre de navigation.
============================================================ */
const navbar = document.getElementById('navbar');

window.addEventListener('scroll', function () {
  if (window.scrollY > 20) {
    navbar.classList.add('avec-ombre');
  } else {
    navbar.classList.remove('avec-ombre');
  }
});


/* ============================================================
   2. MENU MOBILE — Bouton Burger
   On affiche/masque les liens de navigation sur mobile.
============================================================ */
const burger   = document.getElementById('burger');
const navLinks = document.getElementById('navLinks');

// Ouvrir/fermer le menu au clic sur le burger
burger.addEventListener('click', function () {
  navLinks.classList.toggle('ouvert');
});

// Fermer le menu quand on clique sur un lien
navLinks.querySelectorAll('a').forEach(function (lien) {
  lien.addEventListener('click', function () {
    navLinks.classList.remove('ouvert');
  });
});


/* ============================================================
   3. ANIMATIONS AU SCROLL — Révélation des éléments
   On utilise l'IntersectionObserver pour détecter quand
   un élément entre dans le champ de vision et lui ajouter
   la classe "visible" pour déclencher son animation CSS.
============================================================ */

// On sélectionne tous les éléments à animer
const elementsAReveler = document.querySelectorAll(
  '.carte-apropos, .etape, .critere, .entete-section'
);

// On ajoute la classe de départ à chaque élément
elementsAReveler.forEach(function (el) {
  el.classList.add('a-reveler');
});

// On crée l'observateur
const observateur = new IntersectionObserver(
  function (entrees) {
    entrees.forEach(function (entree) {
      if (entree.isIntersecting) {
        // L'élément est visible → on déclenche l'animation
        entree.target.classList.add('visible');
      }
    });
  },
  { threshold: 0.12 } // L'animation se déclenche quand 12% de l'élément est visible
);

// On observe chaque élément
elementsAReveler.forEach(function (el) {
  observateur.observe(el);
});
