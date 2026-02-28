# Quantyss Core

Plugin WordPress sur mesure pour [quantyss.com](https://quantyss.com).  
Développé et maintenu par Khalil Laajine(https://novasiteweb.fr).
76a018b (feat: add Elementor native widget for posts slider)

## Description

Quantyss Core regroupe les outils développés spécifiquement pour le site Quantyss :
shortcodes, extensions métier et intégrations sur mesure.

## Fonctionnalités

- **Slider d'articles** — affichage dynamique des posts avec Swiper.js, multilingue (Polylang)

## Installation

1. Cloner le repo dans `wp-content/plugins/`
2. Activer le plugin dans WordPress → Extensions
3. Utiliser le shortcode `[slider_quantyss]` dans une page ou un widget Elementor

## Shortcodes

### `[slider_quantyss]`

Affiche un slider des derniers articles.

| Attribut   | Défaut | Description                        |
|------------|--------|------------------------------------|
| `posts`    | `6`    | Nombre d'articles à afficher       |
| `category` | —      | Slug de catégorie à filtrer        |

**Exemples**
```
[slider_quantyss]
[slider_quantyss posts="3"]
[slider_quantyss posts="4" category="ia"]
```

## Roadmap

- [ ] Widget Elementor natif pour le slider
- [ ] Shortcode testimonials
- [ ] Dashboard KPIs CEO

## Versions

| Version | Description         |
|---------|---------------------|
| 1.0.0   | Slider Swiper initial |

## Licence

GPL-2.0+
