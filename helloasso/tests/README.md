# Tests Unitaires pour le plugin HelloAsso

Ce répertoire contient les tests unitaires pour la classe `Order` du plugin HelloAsso.

## Structure

```
helloasso/tests/
├── bootstrap.php      # Initialisation de l'environnement Paheko
├── run.php            # Exécuteur de tests (point d'entrée)
├── _inc.php           # Helpers pour créer des objets de test
└── Order/
    ├── 01_static.php      # Tests des méthodes statiques
    ├── 02_instance.php    # Tests des méthodes d'instance
    ├── 03_complex.php     # Tests des méthodes complexes
    └── 04_edge_cases.php  # Tests des cas limites
```

## Prérequis

- PHP 8.5+
- Le projet Paheko doit être accessible (via le lien symbolique ou directement)
- Les dépendances KD2 doivent être chargées

## Exécution des tests

### Tous les tests

```bash
cd paheko/src/data/paheko-plugins/helloasso
php tests/run.php
```

### Un test spécifique

```bash
php tests/run.php 01_static.php
php tests/run.php 02_instance.php
php tests/run.php 03_complex.php
php tests/run.php 04_edge_cases.php
```

## Couverture de code (Code Coverage)

### Prérequis

- Extension PHP **PCOV** activée (pour la collecte des données de couverture)
- Dépendances installées via Composer : `phpunit/php-code-coverage` et `phpunit/phpcov`

### Génération des rapports de couverture

La couverture de code est générée en exécutant les tests avec l'outil `php-code-coverage`.
Plusieurs formats de rapport sont disponibles via le `Makefile` :

#### Avec Makefile (recommandé)

```bash
# Générer le rapport HTML (par défaut)
cd paheko/src/data/paheko-plugins/helloasso
git submodule update --init --recursive  # Si nécessaire pour les dépendances
make test-coverage

# Autres formats disponibles
make coverage          # Rapport HTML
make coverage-text    # Rapport texte (console)
make coverage-clover   # Rapport Clover XML
make coverage-cobertura # Rapport Cobertura XML
make coverage-junit    # Rapport PHPUnit XML (format proche de JUnit)
```

#### Exécution directe

Vous pouvez aussi exécuter directement le script de couverture :

```bash
cd paheko/src/data/paheko-plugins/helloasso/tests
php -d pcov.enabled=1 -d pcov.directory=../lib run_with_pcov.php [format]
```

Formats disponibles : `html`, `text`, `clover`, `cobertura`, `xml`

### Rapports générés

Tous les rapports sont créés dans le répertoire `tests/coverage/` :

| Format | Fichier | Description |
|--------|--------|-------------|
| HTML | `coverage/index.html` | Rapport interactif avec navigation |
| Texte | `coverage/coverage.txt` | Résumé en texte brut |
| Clover | `coverage/clover.xml` | Format XML pour Clover |
| Cobertura | `coverage/cobertura-coverage.xml` | Format XML pour Cobertura |
| PHPUnit XML | `coverage/*.xml` | Format XML compatible PHPUnit |

### Configuration

Le script `run_with_pcov.php` est configuré pour :
- Collecter la couverture uniquement sur le code dans `lib/`
- Exclure les fichiers de test, templates, admin, etc.
- Utiliser le driver PCOV pour la collecte
- Générer des rapports dans le format demandé

Le fichier `phpcov.xml` à la racine du plugin contient la configuration du filtre.

---

## Méthodes testées

### 01_static.php
- `Order::getStatus()` - statique, calcul du statut de paiement
- `Orders::get()` - récupération d'un ordre par ID
- `Orders::transform()` - transformation des données API HelloAsso
- `Orders::list()` - liste des ordres pour un formulaire

### 02_instance.php
- `Order::form()` - cache du formulaire associé
- `Order::tier()` - cache du tier associé
- `Order::getLinkedUserName()` - nom de l'utilisateur lié
- `Order::getRawPayerData()` - données brutes du payeur
- `Order::getPayerInfos()` - informations formatées du payeur
- `Order::listItems()` / `Order::getItem()` - liste et récupération des items
- `Order::listPayments()` - liste des paiements
- `Order::setUserId()` - définition de l'ID utilisateur (idempotent)

### 03_complex.php
- `Orders::syncOrder()` - synchronisation d'un ordre depuis l'API
- `Order::hasAllUsers()` / `Order::hasAllSubscriptions()` - vérification des utilisateurs
- `Order::isSynced()` - vérification de la synchronisation
- `Order::createTransaction()` - création d'une écriture comptable
- Mise à jour d'un ordre existant via `syncOrder()`

### 04_edge_cases.php
- `Order::getStatus()` avec données manquantes/invalides
- `Orders::transform()` avec données minimales
- `Orders::get()` avec IDs invalides
- `Order::hasAllUsers()` avec différentes configurations
- `Order::hasAllSubscriptions()` avec différentes configurations
- `Order::isSynced()` avec différents statuts

## Architecture des tests

### Initialisation

Le fichier `bootstrap.php` :
1. Trouve automatiquement le répertoire `src/` de Paheko
2. Configure les constantes nécessaires
3. Charge l'initialisation de Paheko
4. Charge les classes du plugin HelloAsso
5. Crée une base de données SQLite temporaire
6. Installe le schema Paheko et le schema du plugin

### Helpers

Le fichier `_inc.php` fournit des fonctions pour créer facilement des objets de test :
- `create_test_form()` - crée un formulaire HelloAsso
- `create_test_tier()` - crée un tier (niveau de cotisation)
- `create_test_order()` - crée une commande
- `create_test_item()` - crée un item dans une commande
- `create_test_payment()` - crée un paiement
- `setup_accounting_year()` - configure un exercice comptable
- `setup_helloasso_config()` - configure HelloAsso avec des comptes par défaut
- `call_protected_method()` - appelle une méthode protégée via reflection

### Framework de test

Utilise `KD2\Test` fourni par Paheko avec les assertions suivantes :
- `Test::assert($condition, $message)` - vérifie qu'une condition est vraie
- `Test::equals($expected, $actual, $message)` - vérifie l'égalité
- `Test::strictlyEquals($expected, $actual, $message)` - vérifie l'égalité stricte
- `Test::isInstanceOf($class, $object, $message)` - vérifie le type
- `Test::isArray($value, $message)` - vérifie que c'est un tableau
- `Test::hasKey($key, $array, $message)` - vérifie qu'une clé existe

## Ajouter de nouveaux tests

1. Créer un nouveau fichier dans `tests/Order/` (ex: `05_new_feature.php`)
2. Utiliser les helpers de `_inc.php` pour créer les objets nécessaires
3. Utiliser les assertions `KD2\Test` pour vérifier les résultats
4. Ne pas inclure `bootstrap.php` ou `_inc.php` - ils sont chargés par `run.php`

Exemple :
```php
<?php
use KD2\Test;

$form = create_test_form();
$order = create_test_order($form);

// Test
Test::assert($order->id() > 0, 'Nouvel ordre a un ID');

echo "Nouveaux tests passes.\n";
```

## Notes

- Les tests utilisent une base de données SQLite temporaire qui est créée à chaque exécution
- Chaque fichier de test est autonome mais partage la même base de données
- Les propriétés typées des entités doivent être initialisées avant d'être accédées
- Pour les méthodes protégées, utiliser `call_protected_method(Class::class, 'method', [$args])`
