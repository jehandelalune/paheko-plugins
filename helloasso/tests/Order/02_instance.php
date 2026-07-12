<?php
/**
 * Tests des methodes d'instance de Order
 * Structure avec pattern setUp/tearDown pour exécution unitaire
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use Paheko\DB;

// Charger les dépendances de test (après le namespace pour éviter les erreurs)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_inc.php';

// ============================================================================
// CLASSE DE TEST
// ============================================================================

class OrderInstanceTest
{
    // Propriétés partagées entre les tests
    private static $form = null;
    private static $tier = null;
    
    // ============================================================================
    // SETUP / TEARDOWN
    // ============================================================================
    
    /**
     * Exécuté AVANT tous les tests (une seule fois)
     */
    public static function setUpBeforeClass(): void
    {
        // Configuration partagée pour tous les tests
        self::$form = create_test_form();
        self::$tier = create_test_tier(self::$form, 'Adhesion Test', 1000);
    }
    
    /**
     * Exécuté APRÈS tous les tests (une seule fois)
     */
    public static function tearDownAfterClass(): void
    {
        self::$form = null;
        self::$tier = null;
    }
    
    // ============================================================================
    // MÉTHODES DE TEST
    // ============================================================================
    
    /**
     * TEST: Order::form() - cache
     */
    public static function test_form_cache()
    {
        $order = create_test_order(self::$form, null);
        
        $form1 = $order->form();
        $form2 = $order->form();
        
        Test::assert($form1 === $form2, 'form(): meme instance (cache)');
        Test::strictlyEquals(self::$form->id(), $form1->id(), 'form(): bon ID');
    }
    
    /**
     * TEST: Order::tier() - cache
     */
    public static function test_tier_cache()
    {
        $order = create_test_order(self::$form, null);
        
        $tier1 = $order->tier(self::$tier->id());
        $tier2 = $order->tier(self::$tier->id());
        
        Test::assert($tier1 === $tier2, 'tier(): meme instance (cache)');
        Test::assert($tier1 !== null, 'tier(): tier existe');
        Test::strictlyEquals(self::$tier->id(), $tier1->id(), 'tier(): bon ID');
    }
    
    /**
     * TEST: Order::getLinkedUserName()
     */
    public static function test_getLinkedUserName()
    {
        $order_with_user = create_test_order(self::$form, null);
        $order_with_user->set('id_user', 1);
        Test::assert($order_with_user->getLinkedUserName() !== null, 'getLinkedUserName(): pas null quand id_user defini');
    }
    
    /**
     * TEST: Order::getRawPayerData()
     */
    public static function test_getRawPayerData()
    {
        $order_with_payer = create_test_order(self::$form, null);
        $raw_data = json_decode($order_with_payer->raw_data);
        $raw_data->payer = (object)['firstName' => 'Alice', 'lastName' => 'Smith', 'email' => 'alice@example.com'];
        $order_with_payer->set('raw_data', json_encode($raw_data));
        $order_with_payer->save();
        
        $payer_data = $order_with_payer->getRawPayerData();
        Test::assert($payer_data !== null, 'getRawPayerData(): pas null');
        Test::strictlyEquals('Alice', $payer_data->firstName, 'getRawPayerData(): firstName');
        Test::strictlyEquals('Smith', $payer_data->lastName, 'getRawPayerData(): lastName');
    }
    
    /**
     * TEST: Order::getPayerInfos()
     */
    public static function test_getPayerInfos()
    {
        $order_with_payer = create_test_order(self::$form, null);
        $raw_data = json_decode($order_with_payer->raw_data);
        $raw_data->payer = (object)['firstName' => 'Alice', 'lastName' => 'Smith', 'email' => 'alice@example.com'];
        $order_with_payer->set('raw_data', json_encode($raw_data));
        $order_with_payer->save();
        
        $infos = $order_with_payer->getPayerInfos();
        Test::isArray($infos, 'getPayerInfos(): retourne array');
        Test::hasKey('Prénom', $infos, 'getPayerInfos(): a Prénom');
        Test::strictlyEquals('Alice', $infos['Prénom'], 'getPayerInfos(): valeur Prénom');
    }
    
    /**
     * TEST: Order::listItems() / Order::getItem()
     */
    public static function test_listItems_getItem()
    {
        $order = create_test_order(self::$form, null);
        
        $items = $order->listItems();
        Test::isArray($items, 'listItems(): retourne array');
        Test::assert(count($items) === 0, 'listItems(): vide initialement');
        
        // Ajouter un item
        $item = create_test_item($order, self::$tier, 1000);
        
        $items = $order->listItems();
        Test::assert(count($items) === 1, 'listItems(): 1 item apres ajout');
        
        $found_item = $order->getItem($item->id());
        Test::assert($found_item !== null, 'getItem(): trouve item existant');
        Test::strictlyEquals($item->id(), $found_item->id(), 'getItem(): bon ID');
    }
    
    /**
     * TEST: Order::listPayments()
     */
    public static function test_listPayments()
    {
        $order = create_test_order(self::$form, null);
        
        $payments = $order->listPayments();
        Test::isArray($payments, 'listPayments(): retourne array');
        Test::assert(count($payments) === 0, 'listPayments(): vide initialement');
        
        // Ajouter un paiement
        $payment = create_test_payment($order, 1000);
        
        $payments = $order->listPayments();
        Test::assert(count($payments) === 1, 'listPayments(): 1 paiement apres ajout');
    }
    
    /**
     * TEST: Order::setUserId() - idempotence
     */
    public static function test_setUserId_idempotence()
    {
        $order = create_test_order(self::$form, null);
        $order->set('id_user', null);
        $order->set('id_transaction', null);
        $order->save();
        
        // Maintenant on peut utiliser setUserId
        $order->setUserId(1);
        Test::strictlyEquals(1, $order->id_user, 'setUserId: premier appel');
        
        $order->setUserId(2);
        Test::strictlyEquals(1, $order->id_user, 'setUserId: idempotent (toujours 1)');
    }
    
    /**
     * TEST: Order::tier() avec tier inexistant
     */
    public static function test_tier_nonexistent()
    {
        $order = create_test_order(self::$form, null);
        
        $non_existent_tier = $order->tier(999999);
        Test::assert($non_existent_tier === null, 'tier(): retourne null pour tier inexistant');
    }
    
    // ============================================================================
    // LISTE DE TOUS LES TESTS
    // ============================================================================
    
    public static function getAllTests(): array
    {
        return [
            'test_form_cache',
            'test_tier_cache',
            'test_getLinkedUserName',
            'test_getRawPayerData',
            'test_getPayerInfos',
            'test_listItems_getItem',
            'test_listPayments',
            'test_setUserId_idempotence',
            'test_tier_nonexistent',
        ];
    }
}

// ============================================================================
// EXÉCUTION DES TESTS
// ============================================================================

$test_class = 'Paheko\Plugin\HelloAsso\OrderInstanceTest';

// Setup avant tous les tests
$test_class::setUpBeforeClass();

// Si un test spécifique est demandé en ligne de commande
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $test_method = $argv[1];
    
    if (method_exists($test_class, $test_method)) {
        try {
            $test_class::$test_method();
            echo "✓ Test $test_method passé\n";
            exit(0);
        } catch (\Throwable $e) {
            echo "✗ Test $test_method échoué: " . $e->getMessage() . "\n";
            echo "  Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
            exit(1);
        }
    } else {
        echo "Erreur: Test '$test_method' introuvable\n";
        echo "Tests disponibles: " . implode(', ', $test_class::getAllTests()) . "\n";
        exit(1);
    }
}

// Exécution de tous les tests
foreach ($test_class::getAllTests() as $test_method) {
    try {
        $test_class::$test_method();
    } catch (\Throwable $e) {
        echo "✗ Test $test_method échoué: " . $e->getMessage() . "\n";
        echo "  Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

$test_class::tearDownAfterClass();
echo "Tous les tests d'instance passes.\n";
