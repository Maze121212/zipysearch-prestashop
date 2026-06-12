# ZipySearch pour PrestaShop - Documentation

## Introduction

ZipySearch est un moteur de recherche intelligent qui remplace la recherche native de PrestaShop par une expérience de recherche ultra-rapide avec autocomplétion, filtres à facettes et analytics.

### Fonctionnalités principales

- **Recherche instantanée** - Temps de réponse inférieur à 50ms
- **Autocomplétion intelligente** - Suggestions en temps réel pendant la frappe
- **Tolérance aux fautes** - Trouve les produits même avec des erreurs de frappe
- **Filtres à facettes** - Filtrage par marque, catégorie, prix, couleur
- **Déclinaisons** - Chaque déclinaison est indexée séparément
- **Analytics** - Tableau de bord pour analyser les recherches
- **Suivi des conversions** - Mesurez l'impact de la recherche sur vos ventes

---

## Prérequis

- PrestaShop 1.7.6.0 ou supérieur
- PHP 7.2 ou supérieur
- Un compte ZipySearch (inscription sur https://search.zipybot.com)

---

## Installation

### Méthode 1 : Depuis le back-office PrestaShop

1. Téléchargez le fichier `zipysearch.zip`
2. Dans PrestaShop, allez dans **Modules > Gestionnaire de modules**
3. Cliquez sur **Installer un module** (bouton en haut à droite)
4. Sélectionnez le fichier ZIP
5. Cliquez sur **Configurer** après l'installation

### Méthode 2 : Via FTP

1. Décompressez le fichier `zipysearch.zip`
2. Uploadez le dossier `zipysearch` dans `/modules/`
3. Dans PrestaShop, allez dans **Modules > Gestionnaire de modules**
4. Recherchez "ZipySearch" et cliquez sur **Installer**

---

## Configuration

### Étape 1 : Créer un compte ZipySearch

1. Rendez-vous sur https://search.zipybot.com
2. Créez un compte gratuit
3. Notez votre **Account ID** et votre **API Key** depuis le tableau de bord

### Étape 2 : Configurer le module

1. Dans PrestaShop, allez dans **Modules > Gestionnaire de modules**
2. Recherchez "ZipySearch" et cliquez sur **Configurer**
3. Entrez votre **ZipySearch Account ID**
4. Entrez votre **API Key**
5. Cliquez sur **Enregistrer**

Vos produits seront automatiquement exportés et indexés.

### Étape 3 : Vérifier l'intégration

1. Visitez votre boutique
2. Cliquez sur le champ de recherche
3. Tapez quelques lettres - l'autocomplétion ZipySearch devrait apparaître

---

## Paramètres du module

### Configuration ZipySearch

| Paramètre | Description |
|-----------|-------------|
| **ZipySearch Account ID** | Votre identifiant unique ZipySearch (ex: ma-boutique) |
| **API Key** | Clé API pour l'authentification |
| **Products Export URL** | URL sécurisée pour l'export de vos produits (générée automatiquement) |

### Paramètres du widget

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| **Enable widget** | Active/désactive le widget de recherche | Activé |
| **Search input CSS selector** | Sélecteur CSS du champ de recherche de votre thème | `input[name="s"]` |

### Paramètres de tracking

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| **Track conversions** | Active le suivi des conversions sur la page de confirmation | Activé |

### Paramètres avancés

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| **Debug mode** | Affiche les logs dans la console du navigateur | Désactivé |

---

## Personnalisation du sélecteur CSS

Le module détecte automatiquement le champ de recherche via le sélecteur CSS configuré. Si votre thème utilise un sélecteur différent :

1. Inspectez le champ de recherche de votre thème (clic droit > Inspecter)
2. Identifiez la classe CSS ou l'ID du champ `<input>`
3. Mettez à jour le sélecteur dans la configuration du module

### Exemples de sélecteurs courants

| Thème | Sélecteur |
|-------|-----------|
| Classic (défaut) | `input[name="s"]` |
| Flavshop | `#search_widget input` |
| Flavor | `.search-widget input` |
| Warehouse | `input.search-input` |
| Flavor Theme | `input[name="s"]` |

---

## Export des produits

### Fonctionnement

Le module crée un endpoint sécurisé qui exporte vos produits au format CSV :

```
https://votre-site.com/module/zipysearch/export?token=VOTRE_TOKEN
```

### Données exportées

| Champ | Description |
|-------|-------------|
| `title` | Nom du produit |
| `link` | URL du produit |
| `description` | Description courte |
| `id` | ID du produit (format: id ou id-id_attribute pour les déclinaisons) |
| `price` | Prix régulier TTC |
| `sale_price` | Prix soldé TTC (si applicable) |
| `image link` | URL de l'image principale |
| `product type` | Catégories (format: Parent > Enfant) |
| `brand` | Fabricant |
| `color` | Couleur (si disponible) |
| `qt_vendu` | Nombre de ventes |
| `stocks` | Quantité en stock |

### Déclinaisons (Combinaisons)

Chaque déclinaison est exportée comme un produit séparé avec :
- Son propre titre (Produit - Taille / Couleur)
- Son propre prix (incluant l'impact de prix)
- Son propre stock
- Son image spécifique (ou image du parent)

### Régénérer le token d'export

Si vous devez invalider l'URL d'export actuelle :

1. Allez dans la configuration du module
2. Cliquez sur **Regenerate token**
3. L'ancienne URL ne fonctionnera plus
4. La nouvelle URL sera automatiquement synchronisée

---

## Suivi des conversions

Le module suit automatiquement les conversions lorsqu'un client :
1. Effectue une recherche via ZipySearch
2. Clique sur un produit
3. Finalise son achat

Les données apparaissent dans votre tableau de bord ZipySearch sous **Analytics > Conversions**.

### Hook utilisé

Le suivi s'effectue via le hook `displayOrderConfirmation` qui est appelé sur la page de confirmation de commande.

---

## Hooks PrestaShop utilisés

| Hook | Fonction |
|------|----------|
| `displayHeader` | Injection du widget de recherche dans le `<head>` |
| `displayOrderConfirmation` | Tracking des conversions sur la page de confirmation |

---

## Compatibilité

### Versions PrestaShop

- PrestaShop 1.7.6.0 à 1.7.8.x ✓
- PrestaShop 8.x ✓

### Multiboutique

Le module ne supporte pas le mode multiboutique. Si vous avez plusieurs boutiques PrestaShop, vous devez créer un compte ZipySearch séparé pour chacune d'entre elles.

### Multilingue

Le module exporte les produits dans la langue par défaut de la boutique. Pour les boutiques multilingues, configurez un tenant ZipySearch par langue.

---

## Dépannage

### Le widget ne s'affiche pas

1. Vérifiez que le widget est activé dans les paramètres
2. Vérifiez que l'Account ID est renseigné
3. Vérifiez le sélecteur CSS (inspectez votre champ de recherche)
4. Activez le mode Debug et consultez la console du navigateur
5. Videz le cache PrestaShop

### Les produits ne sont pas indexés

1. Vérifiez que l'API Key est correcte
2. Testez l'URL d'export dans votre navigateur
3. Vérifiez que vos produits sont actifs
4. Lancez un import manuel depuis le dashboard ZipySearch

### Erreur lors de la synchronisation

Si le message "Synchronization with ZipySearch failed" apparaît :
1. Vérifiez votre connexion internet
2. Vérifiez que l'Account ID et l'API Key sont corrects
3. Vérifiez que votre serveur peut faire des requêtes HTTPS sortantes

### Les déclinaisons n'apparaissent pas

Vérifiez que :
- Les déclinaisons sont actives
- Les déclinaisons ont un stock (ou le stock n'est pas géré)
- Le produit parent est actif

### Cache PrestaShop

Après toute modification, pensez à vider le cache :
1. Allez dans **Paramètres avancés > Performances**
2. Cliquez sur **Vider le cache**

---

## Désinstallation

1. Allez dans **Modules > Gestionnaire de modules**
2. Recherchez "ZipySearch"
3. Cliquez sur le menu déroulant et sélectionnez **Désinstaller**

La désinstallation supprime automatiquement toutes les configurations du module.

---

## Support

- **Email** : support@zipybot.com
- **Dashboard** : https://search.zipybot.com
- **Documentation** : https://zipybot.com/docs/prestashop

---

## Licence

Ce module est distribué sous licence **Academic Free License 3.0 (AFL-3.0)**.

Vous êtes libre de :
- Utiliser le module à des fins commerciales
- Modifier le code source
- Distribuer le module

Voir le texte complet de la licence : https://opensource.org/licenses/AFL-3.0

---

## Changelog

### Version 1.0.6 (2026-04-15)

- Essai Pro automatique (1 mois) pour les acheteurs PrestaShop Addons via activation sécurisée (phone-home)

### Version 1.0.5 (2026-03-25)

- Ajout des champs `reference` et `barcode` (ean13/upc) dans l'export CSV

### Version 1.0.4 (2026-03-22)

- Injection du widget sur les thèmes mobiles (hooks `displayBeforeBodyClosingTag` + `actionFrontControllerSetMedia`)

### Version 1.0.3 (2025-01-27)

- Changement de licence vers AFL-3.0

### Version 1.0.2

- Amélioration de la gestion des sélecteurs CSS
- Correction de l'encodage des caractères spéciaux

### Version 1.0.1

- Amélioration de la synchronisation avec ZipySearch
- Correction de bugs mineurs

### Version 1.0.0

- Version initiale
- Export des produits via endpoint sécurisé
- Widget de recherche avec autocomplétion
- Suivi des conversions
- Support des déclinaisons
- Configuration via back-office
