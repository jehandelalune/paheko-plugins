<?php
/**
 * Point d'entrée pour exécuter les tests du plugin helloasso
 * Usage: php tests/run.php [fichier.php]
 */

// Charger le bootstrap
require __DIR__ . '/bootstrap.php';

// Charger les helpers
require __DIR__ . '/_inc.php';

// KD2\Test est déjà chargé via l'include_path dans bootstrap.php
if (!class_exists('KD2\\Test')) {
    die("KD2\\Test non trouve. Verifiez l'include_path dans bootstrap.php\n");
}

use KD2\Test;

// Si un fichier est spécifié, l'exécuter directement
if (!empty($_SERVER['argv'][1])) {
    $file = __DIR__ . '/Order/' . $_SERVER['argv'][1];
    if (file_exists($file)) {
        require $file;
        exit(0);
    }
    echo "Fichier non trouvé: " . $file . "\n";
    exit(1);
}

// Sinon, exécuter tous les tests dans Order/
$test_files = glob(__DIR__ . '/Order/0*.php');

if (empty($test_files)) {
    echo "Aucun fichier de test trouvé dans " . __DIR__ . "/Order/\n";
    exit(1);
}

sort($test_files);

echo "Exécution des tests pour HelloAsso Order...\n\n";

$failed = false;
foreach ($test_files as $file) {
    echo "=== " . basename($file) . " ===\n";
    try {
        require $file;
    } catch (\Throwable $e) {
        echo "ERREUR: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        $failed = true;
    }
    echo "\n";
}

echo "Tous les tests terminés.\n";
exit($failed ? 1 : 0);
