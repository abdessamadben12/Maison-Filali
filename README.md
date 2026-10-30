# Maison Filali — site e-commerce (Laravel 12 + Filament 5)

Site de la boutique **Maison Filali** — https://maisonfilali.ma (vêtements traditionnels : caftans, takchitas, djellabas, babouches) réalisé d'après la maquette Figma
« Maison Filali — Maquette E-commerce ».

- **Site client** (Blade + CSS maison, sans build) : accueil, catalogue avec filtres, page produit, commande
  (paiement à la livraison, sans compte obligatoire), confirmation, suivi de commande, connexion / inscription,
  espace client « Mes commandes », page Contact (formulaire + WhatsApp + plan).
- **Administration** (`/admin`, Filament) : accueil (tâches du jour), commandes (confirmer / expédier / livrer /
  annuler, note interne), produits (photos, couleurs × tailles × stock), catégories, tailles, couleurs,
  stocks (ajout de stock avec historique), clients, messages du formulaire de contact.
- Pas de paiement en ligne, pas de statistiques de vente.

---

## 1. Lancer le projet sur Windows (5 minutes)

**Prérequis : PHP 8.2 ou plus récent** avec les extensions `intl`, `pdo_sqlite`, `fileinfo`, `mbstring`, `zip`
(XAMPP 8.2 ou Laragon conviennent).

> Avec XAMPP : ouvrez `C:\xampp\php\php.ini` et retirez le `;` devant `extension=intl`, `extension=zip`
> et `extension=pdo_sqlite`, puis enregistrez.

1. Décompressez `maison-filali.zip` (clic droit → *Extraire tout*).
2. Ouvrez un terminal dans le dossier `maison-filali` (dans l'Explorateur : clic droit → *Ouvrir dans le terminal*).
3. Lancez :
   ```
   php artisan serve
   ```
4. Ouvrez **http://127.0.0.1:8000** (site) et **http://127.0.0.1:8000/admin** (administration).

Le dossier `vendor` et la base de données SQLite (`database/database.sqlite`) sont déjà inclus :
pas besoin de `composer install` ni de MySQL pour tester.

### Accès administrateur

| E-mail | Mot de passe |
|---|---|
| `admin@maisonfilali.ma` | `MaisonFilali2026!` |

⚠️ Changez ce mot de passe avant la mise en ligne :
```
php artisan tinker --execute="App\Models\User::where('email','admin@maisonfilali.ma')->update(['password'=>bcrypt('NOUVEAU_MOT_DE_PASSE')]);"
```

### Données de démonstration

La base livrée contient les 7 produits de la maquette et 6 commandes de démonstration (Salma Benali, Imane Tazi…)
pour présenter l'admin au client.

- Repartir d'un catalogue propre **sans** commandes de démo : `php artisan migrate:fresh --seed`
- Remettre les commandes de démo : `php artisan db:seed --class=DemoOrdersSeeder`

---

## 2. Fonctionnement des commandes et du stock

1. La cliente choisit couleur + taille (les tailles épuisées sont barrées) puis remplit nom, téléphone, ville.
   Elle peut cocher « Créer un compte » pour suivre sa commande.
2. La commande arrive **En attente** dans l'admin (badge sur « Commandes »).
3. **Confirmer** la commande → le stock est déduit (refusé si le stock ne suffit plus).
4. **Expédiée** puis **Livrée**. Le statut se met à jour côté client (espace client ou « Suivre ma commande »
   avec n° + téléphone).
5. **Annuler** une commande confirmée remet automatiquement le stock.

Tous les mouvements de stock (réception atelier, retour client, correction, commande, annulation) sont enregistrés :
bouton « Historique » sur la page Stocks. Le seuil « stock faible » se règle dans `config/shop.php` (`low_stock`).

---

## 3. Où modifier quoi

| Quoi | Fichier |
|---|---|
| Téléphone, WhatsApp, Instagram, Facebook, TikTok, adresse, horaires, carte Google Maps | **Admin → Paramètres** (valeurs par défaut : `config/shop.php`) |
| Liste des villes, seuil de stock faible | `config/shop.php` |
| Textes des pages (Notre maison, CGV, mentions légales…) | `app/Support/Pages.php` (les mentions `[à compléter]` sont à remplir) |
| Styles du site | `public/css/site.css` (animations : `public/css/anim.css`) |
| Interactions (choix taille, fenêtre de commande) | `public/js/site.js` |
| Gabarits des pages | `resources/views/site/…` et `resources/views/layouts/site.blade.php` |
| Administration | `app/Filament/…` |
| Photos envoyées depuis l'admin | `public/uploads/` |

---

## 4. Passer sur MySQL (optionnel)

Dans `.env` :
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maison_filali
DB_USERNAME=root
DB_PASSWORD=
```
Créez la base `maison_filali` dans phpMyAdmin, puis : `php artisan migrate --seed`.

## 5. Mise en ligne (hébergement mutualisé ou VPS)

- Le dossier public du domaine doit pointer sur `maison-filali/public`.
- Dans `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://maisonfilali.ma`.
- Puis : `php artisan migrate --force` et `php artisan optimize`.
- Le dossier `storage/` et `public/uploads/` doivent être accessibles en écriture.

### Avant la mise en ligne (performance)
```
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan images:optimize
php artisan optimize
```
- `images:optimize` crée les versions WebP légères des photos existantes (les nouvelles photos envoyées depuis l'admin sont optimisées automatiquement). Nécessite l'extension PHP `gd`.
- `php artisan optimize` met en cache la configuration, les routes et les vues. **À relancer après chaque modification de fichier** (ou `php artisan optimize:clear` pour annuler).
- Dans `.env` : `CACHE_STORE=file` (plus rapide que `database` sur un seul serveur).
- `public/.htaccess` active la compression, le cache navigateur et la redirection vers `https://maisonfilali.ma` (sans www).

## 6. SEO technique (déjà en place)

- **Plan du site** : `/sitemap.xml` (produits avec photos, catégories, pages), mis à jour automatiquement. À déclarer dans Google Search Console.
- **robots.txt** : `/robots.txt` généré par Laravel. Il bloque l'admin, l'espace client, la commande, et **tout le site tant que `APP_ENV` n'est pas `production`** (évite d'indexer une version de test).
- Chaque page a son titre, sa description, une URL canonique et l'aperçu de partage (Open Graph) ; les pages filtrées, de recherche, de compte et de commande sont en `noindex`.
- Données structurées Google (JSON-LD) : boutique (ClothingStore), site + recherche, produits (prix MAD, stock, retours 7 jours), fil d'Ariane.
- Page 404 personnalisée.

## 7. Mettre à jour les dépendances (développeur)

```
composer install
php artisan filament:assets
```
