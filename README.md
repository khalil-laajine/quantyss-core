# Quantyss Core

> Plugin WordPress sur mesure pour [quantyss.com](https://quantyss.com)  
> Développé et maintenu par Khalil LAAJINE (https://novasiteweb.fr)

[![Version](https://img.shields.io/badge/version-1.0.0-6366f1)](#)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4)](#)
[![License](https://img.shields.io/badge/license-GPL--2.0-green)](#)

---

## Sommaire

- [Description](#description)
- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Installation](#installation)
- [Dépendances](#dépendances)
- [Modules](#modules)
- [Shortcodes](#shortcodes)
- [Hooks & Filtres](#hooks--filtres)
- [Base de données](#base-de-données)
- [Crons planifiés](#crons-planifiés)
- [Roadmap](#roadmap)
- [Conventions de code](#conventions-de-code)
- [Changelog](#changelog)

---

## Description

**Quantyss Core** est un plugin WordPress sur mesure qui regroupe l'ensemble des outils
développés spécifiquement pour le site Quantyss. Il remplace une dizaine de plugins tiers
par des briques métier cohérentes, performantes et entièrement contrôlées.

---

## Fonctionnalités

| Module | Description | Statut |
|--------|-------------|--------|
| **Slider Articles** | Shortcode + Widget Elementor, multilingue Polylang | ✅ v1.0.0 |
| **Dashboard CEO** | KPIs, graphiques, derniers articles | ✅ v1.0.0 |
| **Stats GA4** | Sessions, utilisateurs, taux de rebond, sources | ✅ v1.0.0 |
| **Gestion des Leads** | Capture CF7, BDD, pipeline, export CSV | ✅ v1.0.0 |
| **Lead Magnet** | Formulaire, token sécurisé, email, téléchargement | ✅ v1.0.0 |
| **Logs de sécurité** | Activité site, anti brute-force | ✅ v1.0.0 |
| **Performance** | Désactivation emoji, lazy load, nettoyage BDD | ✅ v1.0.0 |
| **SEO Technique** | Meta tags, JSON-LD, canonical, sitemap | ✅ v1.0.0 |
| **Monitoring Uptime** | Check toutes les 5 min, alertes email | ✅ v1.0.0 |
| **Rapport mensuel** | PDF auto généré et envoyé le 1er du mois | ✅ v1.0.0 |

---

## Architecture

```
quantyss-core/
├── quantyss-core.php              # Fichier principal — déclaration plugin
├── composer.json                  # Dépendances PHP (Google Analytics)
├── vendor/                        # Autoload Composer (gitignored)
├── scripts/
│   └── generate-report.py         # Générateur PDF rapport mensuel (Python)
├── assets/
│   ├── css/
│   │   ├── slider.css             # Styles slider front-end
│   │   └── lead-magnet.css        # Styles formulaire lead magnet
│   └── pdfs/
│       └── .htaccess              # Bloc accès direct aux PDFs
└── includes/
    ├── enqueue.php                # Enregistrement assets Swiper
    ├── slider.php                 # Shortcode [slider_quantyss]
    ├── leads-handler.php          # Interception CF7 + BDD + email
    ├── elementor/
    │   ├── elementor-init.php     # Init widgets Elementor
    │   └── widgets/
    │       └── slider-widget.php  # Widget Elementor slider
    ├── admin/
    │   ├── dashboard.php          # Page admin principale — KPIs
    │   ├── dashboard-style.css
    │   ├── stats.php              # Page stats GA4
    │   ├── stats-style.css
    │   ├── leads.php              # Page gestion leads
    │   ├── leads-style.css
    │   ├── lead-magnet-admin.php  # Page gestion lead magnets
    │   └── monthly-report.php    # Cron + génération rapport PDF
    └── tools/
        ├── security-logs.php      # Logs activité + brute force
        ├── performance.php        # Optimisations silencieuses
        ├── seo.php                # SEO technique automatique
        ├── uptime.php             # Monitoring disponibilité
        ├── lead-magnet.php        # Shortcodes + téléchargement
        └── monthly-report.php    # Rapport PDF mensuel
```

---

## Installation

### Prérequis

- WordPress 6.0+
- PHP 8.0+
- Composer (pour l'intégration GA4)
- Python 3.8+ avec `reportlab` et `pypdf` (pour les rapports PDF)
- Plugins requis : Elementor, Contact Form 7, Polylang

### Étapes

```bash
# 1. Cloner dans le dossier plugins
cd wp-content/plugins/
git clone https://github.com/tonpseudo/quantyss-core.git

# 2. Installer les dépendances PHP (GA4)
cd quantyss-core
composer install

# 3. Installer les dépendances Python (rapport PDF)
pip install reportlab pypdf --break-system-packages

# 4. Activer dans WordPress → Extensions
```

### Configuration post-activation

1. **GA4** — Aller dans *Quantyss → Statistiques* et configurer le Property ID + credentials JSON
2. **Lead Magnet** — Aller dans *Quantyss → Lead Magnets* et créer le premier guide
3. **Rapport mensuel** — Le premier rapport sera envoyé automatiquement le 1er du mois suivant. Utiliser le bouton "Générer maintenant" dans le dashboard pour un test immédiat.

---

## Dépendances

### PHP / Composer
| Package | Version | Usage |
|---------|---------|-------|
| `google/analytics-data` | ^0.9 | API Google Analytics 4 |

### JavaScript (CDN)
| Librairie | Version | Usage |
|-----------|---------|-------|
| Swiper.js | 11 | Slider articles |
| Chart.js | 4 | Graphiques dashboard |

### Python
| Package | Usage |
|---------|-------|
| `reportlab` | Génération PDF rapport mensuel |
| `pypdf` | Manipulation PDF |

---

## Modules

### Slider Articles

Affiche les derniers articles de blog dans un carousel Swiper.js responsive.
Gère automatiquement la langue courante via Polylang.

```php
// Shortcode
[slider_quantyss posts="6" category="ia"]

// Via Widget Elementor : glisser-déposer depuis la catégorie "Quantyss"
```

**Options shortcode :**
| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| `posts` | `6` | Nombre d'articles |
| `category` | — | Slug de catégorie |

---

### Dashboard CEO

Page admin accessible via *Quantyss → Dashboard*.

**KPIs affichés :**
- Articles publiés / en brouillon / ce mois
- Pages publiées
- Commentaires totaux / en attente
- Utilisateurs inscrits
- Graphique publications sur 6 mois
- Tableau des 8 derniers articles

---

### Stats GA4

Page admin accessible via *Quantyss → Statistiques*.

**Métriques disponibles :**
- Sessions, utilisateurs, taux de rebond, durée moyenne session
- Graphique sessions sur 30 jours
- Sources de trafic (doughnut chart)
- Sélecteur de période : 7j / 30j / 90j

**Configuration requise :**
- Compte de service Google Cloud avec accès en lecture à la propriété GA4
- Fichier JSON credentials uploadé dans l'interface

---

### Gestion des Leads

Capture automatique des soumissions Contact Form 7.

**Champs collectés :** prénom, nom, email, téléphone, entreprise, message

**Mapping CF7 (à adapter selon les noms de champs du formulaire) :**
```php
// Dans includes/leads-handler.php — fonction quantyss_catch_cf7_submission()
$first_name = $data['first-name'] ?? $data['your-name'] ?? '';
$email      = $data['your-email'] ?? $data['email'] ?? '';
// etc.
```

**Statuts disponibles :** Nouveau → En cours → Qualifié → Archivé

**Export CSV :** Bouton dans *Quantyss → Leads*

---

### Lead Magnet

Système de distribution de guides PDF contre email.

**Flow :**
```
Visiteur → Formulaire email → Token (24h) → Email → Téléchargement sécurisé → Lead en BDD
```

**Shortcodes :**
```php
// Bouton CTA dans un article
[lead_magnet id="1" label="Télécharger le guide"]

// Formulaire complet (page dédiée)
[lead_magnet_form id="1"]
```

**Sécurité :** Les PDFs sont stockés hors du webroot public (`assets/pdfs/` + `.htaccess`).
L'accès se fait uniquement via token temporaire unique.

---

### SEO Technique

S'active automatiquement **uniquement si Yoast SEO et Rank Math sont absents**.

**Fonctionnalités :**
- Balises `<meta name="description">` générées depuis le contenu
- Open Graph (og:title, og:description, og:image, og:url)
- Twitter Cards
- JSON-LD Article (posts) et Organization (homepage)
- Canonical URL
- Sitemap XML à `yoursite.com/quantyss-sitemap.xml`

---

### Monitoring Uptime

Vérifie la disponibilité du site toutes les 5 minutes via WordPress Cron.

**Alertes :** Email envoyé si le site est hors ligne — 1 alerte max toutes les 30 minutes.

**Historique :** 288 points conservés = 24h de données.

---

### Rapport Mensuel PDF

Généré et envoyé automatiquement le 1er de chaque mois à 8h.

**Contenu du rapport :**
- Page de couverture branded
- KPIs : articles, leads, téléchargements, uptime
- Tableau leads par source et par statut
- Tableau sécurité
- Liste des articles publiés dans le mois

**Génération manuelle :** Bouton "Générer maintenant" dans le dashboard.

**Stockage :** `wp-content/uploads/quantyss-reports/rapport-YYYY-MM.pdf`

---

## Shortcodes

| Shortcode | Description | Paramètres |
|-----------|-------------|------------|
| `[slider_quantyss]` | Slider articles | `posts`, `category` |
| `[lead_magnet]` | Bouton CTA lead magnet | `id`, `label` |
| `[lead_magnet_form]` | Formulaire téléchargement | `id` |

---

## Hooks & Filtres

### Actions utilisées
| Hook WordPress | Fonction Quantyss | Description |
|----------------|-------------------|-------------|
| `wp_enqueue_scripts` | `quantyss_enqueue_assets` | Enregistrement Swiper |
| `wpcf7_mail_sent` | `quantyss_catch_cf7_submission` | Capture leads CF7 |
| `wp_login` | `quantyss_log_login` | Log connexions |
| `wp_login_failed` | `quantyss_log_failed_login` | Log tentatives échouées |
| `save_post` | `quantyss_log_post_save` | Log modifications articles |
| `activated_plugin` | `quantyss_log_plugin_activated` | Log plugins activés |
| `quantyss_uptime_check` | `quantyss_run_uptime_check` | Check uptime (cron) |
| `quantyss_db_cleanup` | `quantyss_run_db_cleanup` | Nettoyage BDD (cron) |
| `quantyss_monthly_report` | `quantyss_generate_monthly_report` | Rapport PDF (cron) |

### Filtres utilisés
| Filtre WordPress | Fonction Quantyss | Description |
|-----------------|-------------------|-------------|
| `cron_schedules` | `quantyss_add_cron_interval` | Ajoute interval 5min |
| `cron_schedules` | `quantyss_add_monthly_interval` | Ajoute interval mensuel |
| `wp_get_attachment_image_attributes` | `quantyss_add_lazy_load` | Lazy load images |
| `xmlrpc_enabled` | `__return_false` | Désactive XML-RPC |
| `query_vars` | `quantyss_sitemap_query_var` | Var sitemap |

---

## Base de données

### `{prefix}_quantyss_leads`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | BIGINT PK | Identifiant |
| `first_name` | VARCHAR(100) | Prénom |
| `last_name` | VARCHAR(100) | Nom |
| `email` | VARCHAR(150) | Email |
| `phone` | VARCHAR(30) | Téléphone |
| `company` | VARCHAR(150) | Entreprise |
| `message` | TEXT | Message |
| `status` | VARCHAR(30) | new / in_progress / qualified / archived |
| `source` | VARCHAR(100) | cf7 / lead_magnet / manual |
| `created_at` | DATETIME | Date de création |

### `{prefix}_quantyss_logs`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | BIGINT PK | Identifiant |
| `user_id` | BIGINT | ID utilisateur WordPress |
| `username` | VARCHAR(100) | Login utilisateur |
| `action` | VARCHAR(100) | Type d'action |
| `object` | VARCHAR(200) | Objet concerné |
| `ip` | VARCHAR(45) | Adresse IP |
| `user_agent` | VARCHAR(255) | User agent |
| `created_at` | DATETIME | Date |

### `{prefix}_quantyss_magnet_tokens`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | BIGINT PK | Identifiant |
| `email` | VARCHAR(150) | Email du téléchargeur |
| `token` | VARCHAR(64) UNIQUE | Token sécurisé (bin2hex 32 bytes) |
| `magnet_id` | BIGINT | ID du lead magnet |
| `downloaded` | TINYINT(1) | 0 = pas encore, 1 = téléchargé |
| `expires_at` | DATETIME | Expiration (24h) |
| `created_at` | DATETIME | Date de création |

### Options WordPress utilisées
| Option | Description |
|--------|-------------|
| `quantyss_ga4_property_id` | Property ID GA4 |
| `quantyss_ga4_credentials_path` | Chemin vers le fichier JSON Google |
| `quantyss_magnets` | Array JSON des lead magnets |
| `quantyss_magnet_page_id` | ID de la page de téléchargement |
| `quantyss_uptime_history` | Historique uptime (288 entrées) |
| `quantyss_perf_history` | Historique temps de chargement (200 entrées) |
| `quantyss_last_report` | Métadonnées du dernier rapport PDF |
| `quantyss_db_version` | Version du schéma BDD |

---

## Crons planifiés

| Cron | Fréquence | Action |
|------|-----------|--------|
| `quantyss_uptime_check` | Toutes les 5 min | Vérification disponibilité |
| `quantyss_db_cleanup` | Hebdomadaire | Nettoyage BDD (révisions, transients, spam) |
| `quantyss_monthly_report` | Mensuel (1er à 8h) | Génération et envoi rapport PDF |

> **Note :** Les crons WordPress ne s'exécutent qu'en présence de trafic.
> Sur un site à faible trafic, configurer un vrai cron serveur :
> ```bash
> # crontab -e
> */5 * * * * curl -s https://quantyss.com/wp-cron.php?doing_wp_cron > /dev/null
> ```

---

## Roadmap

- [ ] Système de testimonials (Custom Post Type + shortcode + widget Elementor)
- [ ] Notifications Slack nouveaux leads
- [ ] Widget Elementor pour le formulaire de contact
- [ ] Export leads vers CRM externe (HubSpot, Pipedrive)
- [ ] Tableau de bord mobile-first (PWA)
- [ ] Tests unitaires (PHPUnit)

---

## Conventions de code

### Commits
```
feat:     nouvelle fonctionnalité
fix:      correction de bug
style:    CSS, mise en forme
refactor: restructuration sans changement de comportement
docs:     documentation
chore:    maintenance, dépendances, gitignore
perf:     optimisation performance
security: correctif sécurité
```

### PHP
- Nommage fonctions : `quantyss_nom_de_la_fonction()`
- Nommage classes : `Quantyss\Widgets\NomDeLaClasse`
- Toujours `defined('ABSPATH') || exit;` en début de fichier
- Toujours `sanitize_*` et `esc_*` sur les données entrantes/sortantes
- Nonces sur tous les formulaires admin
- `wp_verify_nonce()` avant tout traitement POST

### JavaScript
- Vanilla JS ou Swiper uniquement côté front
- Pas de jQuery sauf si WordPress l'impose

---

## Changelog

### v1.0.0 — 2025
- `feat` : init plugin Quantyss Core
- `feat` : slider shortcode multilingue Polylang + Swiper
- `feat` : widget Elementor natif slider articles
- `feat` : dashboard admin KPIs + graphique Chart.js
- `feat` : intégration stats GA4 (sessions, sources, bounce rate)
- `feat` : système gestion leads — capture CF7, BDD, pipeline, CSV
- `feat` : système lead magnet — token sécurisé, email, téléchargement
- `feat` : logs sécurité + protection brute force
- `feat` : optimisations performance silencieuses
- `feat` : SEO technique automatique (meta, JSON-LD, sitemap)
- `feat` : monitoring uptime toutes les 5 minutes
- `feat` : rapport PDF mensuel automatique

---

## Licence

GPL-2.0+ — Voir [LICENSE.txt](LICENSE.txt)

---

*Plugin développé sur mesure pour Quantyss. Non distribué publiquement.*