# eLabFTW Smart Picker (`elabftw_picker`)

Application Nextcloud qui :

1. ajoute **eLabFTW au Smart Picker** (tapez `/` dans Text, Talk, Notes, Collectives, Mail…) : recherche dans les **expériences** et les **ressources** (« items »), triées par dernière modification, archives comprises ;
2. affiche les liens eLabFTW insérés sous forme d'**aperçu riche** : titre, extrait du contenu, catégorie (avec sa couleur), auteur, dernière modification, statut (avec sa couleur), tags, et des icônes d'état (signé, verrouillé, horodaté, archivé).

> Cette application n'est pas affiliée au projet eLabFTW.

## Fonctionnement

| Élément | Implémentation |
|---|---|
| Recherche dans le Smart Picker | un *search provider* unique (`elabftw`) qui renvoie expériences et ressources dans la même liste, déclaré par un *reference provider* découvrable (`ISearchableReferenceProvider`) |
| Entrées archivées | cherchées après les entrées normales (voir [Recherche dans les archives](#recherche-dans-les-archives)) |
| Aperçu du lien | `ElabFtwReferenceProvider::resolveReference()` appelle `GET /api/v2/{experiments\|items}/{id}` (avec `state=2` pour une entrée archivée) et renvoie un *rich object* `elabftw_entity` |
| Rendu | widget Vue (`src/components/ElabFtwReferenceWidget.vue`) enregistré avec `registerWidget` |
| Liens reconnus | `<URL serveur>/experiments.php?mode=view&id=N` et `<URL serveur>/database.php?mode=view&id=N` |
| Authentification | clé API **propre à chaque utilisateur** (stockée chiffrée) : recherche et aperçus respectent les droits eLabFTW de l'utilisateur |
| Cache des aperçus | par lien **et par utilisateur** (les droits diffèrent d'un utilisateur à l'autre) |

L'administrateur ne configure que l'URL du serveur ; l'utilisateur ne peut pas la modifier, ce qui évite les risques de SSRF via la clé ou le lien.

> Le dépôt `nextcloud/picker` n'est pas le Smart Picker (c'est un sélecteur de fichiers pour Webex). Le Smart Picker repose sur les *reference providers* de Nextcloud : <https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/reference.html>

## Recherche dans les archives

L'API d'eLabFTW ne renvoie les entrées archivées que si on les demande explicitement (`state=2`). Le Smart Picker n'offrant pas de case à cocher, la recherche procède ainsi :

- les entrées **normales** s'affichent d'abord ;
- quand il n'y en a plus, le bouton « Charger d'avantage » du Smart Picker continue dans les **archives** ;
- si une recherche ne trouve **aucune** entrée normale, les archives sont cherchées tout de suite ;
- les entrées archivées portent la mention « Archivé » dans leur ligne de détail.

## Installation

### Depuis l'App Store de Nextcloud

*Applications → Intégration → eLabFTW Smart Picker* (une fois l'application publiée).

### Depuis les sources

```bash
npm ci
npm run build        # génère js/
# copier le dossier dans nextcloud/apps/elabftw_picker (sans node_modules)
occ app:enable elabftw_picker
```

## Configuration

1. **Administration → Connected accounts** : saisir l'URL du serveur eLabFTW (ex. `https://elab.example.org`).
2. **Paramètres personnels → Connected accounts** : saisir sa clé API eLabFTW (eLabFTW → Paramètres → Clés API, lecture seule suffit) ; un test de connexion est fait à l'enregistrement.

Si eLabFTW est sur le même réseau local que Nextcloud, il faut peut-être autoriser les serveurs distants locaux :

```bash
occ config:system:set allow_local_remote_servers --value true --type bool
```

Réglez aussi le **fuseau horaire** de votre profil Nextcloud (ou `default_timezone` dans `config.php`) : les dates d'eLabFTW n'ont pas de fuseau et sont lues dans celui de l'utilisateur.

## Développement

```bash
npm ci
npm run watch        # reconstruit js/ à chaque modification
```

Les icônes sont celles de Font Awesome (copiées dans `img/`). Les vignettes des résultats de recherche ne peuvent pas suivre le thème : `img/experiment-search.svg` et `img/resource-search.svg` sont des variantes grises et avec marge, générées à partir de `img/experiment.svg` et `img/resource.svg` :

```bash
python3 tools/make_search_icons.py img
```

### Publier une version

1. Mettre à jour la version dans `appinfo/info.xml` **et** `package.json`, et ajouter une entrée dans `CHANGELOG.md`.
2. Créer un tag `vX.Y.Z` et le pousser : le workflow GitHub (`.github/workflows/release.yml`) construit l'archive `elabftw_picker.tar.gz` et l'attache à la release.
3. Signer l'archive et la déposer sur <https://apps.nextcloud.com> en suivant la documentation des développeurs Nextcloud.

## Limites connues / à vérifier

- Compatibilité déclarée dans `appinfo/info.xml` : Nextcloud 30 à 35. Testé sur : *à compléter*.
- Les champs lus dans l'API v2 sont `title`, `fullname`, `modified_at` (ou `lastchange`), `status_title`, `status_color`, `category_title`, `category_color`, `tags`, `locked`, `timestamped`, `signature_count` / `last_signed_at`, `state` et `body_html` (ou `body`). Si la liste de recherche d'eLabFTW n'en renvoie pas certains, la ligne de résultat les omet ; l'aperçu, lui, utilise la lecture complète d'une entrée.
- Les aperçus de liens sont mis en cache par Nextcloud : une modification dans eLabFTW peut n'apparaître qu'après expiration du cache.
- Le périmètre de recherche (`scope`) suit la préférence de l'utilisateur dans eLabFTW.
- Frontend en Vue 3 / `@nextcloud/vue` 9 ; le widget d'aperçu passe par le registre partagé de `NcRichText` : à confirmer sur votre version.

## Crédits et licence

- Licence : **AGPL-3.0-or-later** (voir `LICENSE`).
- Icônes : [Font Awesome Free](https://fontawesome.com/license/free) 6, sous licence CC BY 4.0.
