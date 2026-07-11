<?php
/**
 * Bootstrap pour les tests du plugin helloasso
 * Trouve et initialise l'environnement Paheko depuis le plugin
 */

// Trouver le répertoire src de Paheko en cherchant include/init.php
$current_dir = __DIR__;
$paheko_src = null;

// Monter dans l'arborescence jusqu'à trouver include/init.php
for ($i = 0; $i < 10; $i++) {
    $test_path = realpath($current_dir . '/../../include/init.php');
    if ($test_path && file_exists($test_path)) {
        $paheko_src = dirname(dirname($test_path));
        break;
    }
    $current_dir = dirname($current_dir);
}

if ($paheko_src === null) {
    die("Impossible de trouver le répertoire src de Paheko.\n");
}

// Définir les chemins nécessaires
define('Paheko\\WWW_URI', '/');
define('Paheko\\WWW_URL', 'http://localhost/');
define('Paheko\\CONFIG_FILE', $paheko_src . '/config.local.php');

// Utiliser un fichier temporaire SQLite pour éviter les problèmes avec :memory: et le logging
define('Paheko\\DB_FILE', tempnam(sys_get_temp_dir(), 'paheko_test_') . '.sqlite');
define('Paheko\\SKIP_STARTUP_CHECK', true);

// Ajouter le chemin des libs Paheko et KD2 à l'autoload
set_include_path(get_include_path() . PATH_SEPARATOR . $paheko_src . '/include/lib');

// Charger l'initialisation de Paheko
require $paheko_src . '/include/init.php';

// Charger les classes du plugin helloasso
$plugin_dir = dirname(__DIR__); // helloasso
require $plugin_dir . '/lib/HelloAsso.php';
require $plugin_dir . '/lib/Orders.php';
require $plugin_dir . '/lib/Payments.php';
require $plugin_dir . '/lib/Items.php';
require $plugin_dir . '/lib/Forms.php';
require $plugin_dir . '/lib/API.php';
require $plugin_dir . '/lib/Entities/Order.php';
require $plugin_dir . '/lib/Entities/Item.php';
require $plugin_dir . '/lib/Entities/Payment.php';
require $plugin_dir . '/lib/Entities/Form.php';
require $plugin_dir . '/lib/Entities/Tier.php';
require $plugin_dir . '/lib/Entities/Option.php';

// Initialiser la base de données de test avec un mot de passe sécurisé
// Utiliser un mot de passe aléatoire et un email aléatoire pour éviter les listes de mots de passe compromis
$secure_password = bin2hex(random_bytes(16));
$random_email = bin2hex(random_bytes(8)) . '@example.com';
\Paheko\Install::install('FR', 'Test', $secure_password, $random_email, $random_email);

// Charger le schema du plugin helloasso
$db = \Paheko\DB::getInstance();
$db->connect();
$schema_sql = file_get_contents($plugin_dir . '/schema.sql');
if ($schema_sql) {
    $db->exec($schema_sql);
}
