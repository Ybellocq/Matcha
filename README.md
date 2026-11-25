# 🔥 Matcha — Site de rencontre

Application de rencontre type Tinder, construite avec **Vue 3 + PHP/Slim 4 + PostgreSQL**.

## Prérequis
- PHP 8.1+
- Composer
- Node.js 18+
- PostgreSQL 14+

## Démarrage rapide

### Tout lancer d'un coup
```bash
./start.sh    # Api :8000 + frontend :5173, watchdog auto-restart, logs dans backend.log / frontend.log
```

> Stabilité : l'API tourne avec `PHP_CLI_SERVER_WORKERS=8` (requêtes concurrentes OK pour le
> polling temps réel) et les endpoints en lecture ferment la session PHP tôt pour éviter les
> verrous entre requêtes d'un même utilisateur.

### Backend
```bash
cd backend
cp .env.example .env         # Édite les identifiants DB si besoin
composer install
psql -U postgres -c "CREATE DATABASE matcha"
psql -U postgres -d matcha -f db/schema.sql
php db/seed.php               # Crée 500 profils factices
php db/seed_matches.php       # Faux matchs + conversations pour le 1er compte enregistré (ou : `php db/seed_matches.php <email|username> [n]`, défaut 8)
php db/humanize_seed.php      # Rajeunit les profils factices existants : prénoms mixtes réalistes, 18 villes géolocalisées, bios FR (préserve matchs et messages)
php db/coherence.php          # Répare les incohérences like/match en base (match sans like mutuel, like mutuel sans match) + `composer coherence`
composer checkup              # Suite de tests end-to-end sur l'API (46 vérifications : comptes, deck, likes, matchs, chat, blocage, sécurité)
composer start                # Tourne sur localhost:8000
```

### Frontend
```bash
cd frontend
cp .env.example .env          # Édite VITE_API_URL si le backend n'est pas sur :8000
npm install
npm run dev                   # Tourne sur localhost:5173
```

## E-mail
- `MAIL_MODE=log` (défaut, dev) → les e-mails ne sont pas réellement envoyés : leurs **liens cliquables** sont visibles dans la boîte mail de dev **http://localhost:8000/dev/mailbox** (ou dans `backend/storage/logs/mail.log`, commande rapide : `composer mails` dans `backend/`). Après inscription, clique le lien de vérification dedans pour activer ton compte.
- `MAIL_MODE=smtp` → renseigne `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` dans `backend/.env` (ex. Gmail : host `smtp.gmail.com`, port 587, un mot de passe d'application). L'envoi passe par PHPMailer (STARTTLS). Disponible uniquement en mode smtp, l'endpoint `/dev/mailbox` renvoie 404 en production.

## Direction artistique
- 🔥 Style Tinder : swipe gauche/droite avec drag, badges LIKE/NOPE, boutons ronds (passer / profil / liker)
- 💥 Dégradé vibrant rose→orange (#fd267a → #ff6036), ombres colorées, interface 100 % française
- 🌗 Switch thème clair / sombre dans la navbar (préférence persistée)
- 📱 Responsive mobile-first, layout en-tête + section principale + pied de page

## Fonctionnalités
- 💘 Swipe avec drag, "C'est un Match !" en overlay animé — les profils **likés ou passés ne sont plus jamais représentés** dans le deck (persistés en base) ; unprofil déliké revient dans la sélection
- 👤 Profil détaillé consultable depuis le deck avec bouton **Retour** vers la sélection
- 💬 Chat temps réel entre matchs (polling ≤ 5 s)
- 🔔 Notifications temps réel (like, visite, message, match, unlike)
- 📍 Géolocalisation GPS avec consentement + saisie manuelle de ville **géocodée automatiquement** (Nominatim, serveur et client)
- 🧰 Maintenance : `php db/geocode.php` — géocode en masse les profils qui ont une ville sans coordonnées
- 📸 Upload de photos (5 max, photo principale sélectionnable)
- 🏷️ Tags d'intérêts réutilisables
- 🔍 Recherche avancée : âge, popularité, distance, ville, tags — triable et filtrable
- 👁️ Suivi des visites de profil + score de popularité
- 🚫 Blocage et signalement de faux comptes

## Suggestions & priorité géographique
Score = seau de distance (0–10 km : +300, ≤50 : +200, ≤200 : +100, ≤500 : +40) + tags communs × 10 + popularité (plafonnée à 100).
Les profils de la même zone géographique passent donc toujours avant les lointains.

## Formule de popularité
`vues + (likes × 2) + (matchs × 3) + activité` où `activité = 5` si actif dans les dernières 24 h, sinon `1`.

## Sécurité
- Mots de passe hachés en bcrypt (+ liste de mots de passe courants rejetée)
- Requêtes 100 % préparées (aucune injection SQL)
- Assainissement des entrées (strip_tags)
- Protection CSRF sur tous les endpoints mutatifs
- CORS avec origines explicitement whitelistées
- Vérification e-mail + reset de mot de passe par tokens hachés et limités dans le temps
- Uploads validés par MIME réel (finfo), max 5 Mo, JPG/PNG uniquement
- `.env` exclu de Git

## Conformité sujet (Web Matcha v6.0)
Audit complet effectué :
- [x] Inscription / vérification e-mail / connexion / reset / déconnexion 1 clic
- [x] Profil complet éditable (genre, orientation, bio, tags, 5 photos, nom, prénom, e-mail)
- [x] Vues + likes reçus + score de popularité public
- [x] GPS optionnel avec saisie manuelle obligatoire sinon
- [x] Suggestions selon orientation (bi par défaut), triables + filtrables
- [x] Recherche avancée triable + filtrable
- [x] Like requiert une photo de profil · unlik · self
- [x] Bloqué ⇒ invisible en recherche, zéro notification, chat coupé
- [x] Chat et notifications < 10 s
- [x] Layout header/main/footer, responsive, zéro warning PHP
