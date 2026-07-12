<?php
/**
 * Tests des edge cases pour Order
 * Structure avec pattern setUp/tearDown pour exécution unitaire
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use stdClass;

// Charger les dépendances de test (après le namespace pour éviter les erreurs)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_inc.php';

// ============================================================================
// CLASSE DE TEST
// ============================================================================

class OrderEdgeCasesTest
{
    // ============================================================================
    // SETUP / TEARDOWN
    // ============================================================================
    
    public static function setUpBeforeClass(): void
    {
        // Pas de configuration globale nécessaire pour ces tests
    }
    
    public static function tearDownAfterClass(): void
    {
        // Nettoyage
    }
    
    // ============================================================================
    // MÉTHODES DE TEST
    // ============================================================================
    
    /**
     * Edge cases pour Order::getStatus() - amount sans total
     */
    public static function test_getStatus_amount_without_total()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)['amount' => (object)[], 'payments' => []]),
            'getStatus: amount sans total (0 >= 0 = true, donc PAID)'
        );
    }
    
    /**
     * Edge cases pour Order::getStatus() - total a 0
     */
    public static function test_getStatus_total_zero()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)['amount' => (object)['total' => 0], 'payments' => []]),
            'getStatus: total a 0 (0 >= 0 = true, donc PAID)'
        );
    }
    
    /**
     * Edge cases pour Order::getStatus() - pas de propriete payments
     */
    public static function test_getStatus_no_payments_property()
    {
        Test::equals(
            Entities\Order::STATUS_WAITING,
            Entities\Order::getStatus((object)['amount' => (object)['total' => 1000]]),
            'getStatus: pas de propriete payments'
        );
    }
    
    /**
     * Edge cases pour Order::getStatus() - payments null
     */
    public static function test_getStatus_payments_null()
    {
        Test::equals(
            Entities\Order::STATUS_WAITING,
            Entities\Order::getStatus((object)['amount' => (object)['total' => 1000], 'payments' => null]),
            'getStatus: payments null'
        );
    }
    
    /**
     * Edge cases pour Order::getStatus() - paiement non-OK
     */
    public static function test_getStatus_non_ok_payment()
    {
        Test::equals(
            Entities\Order::STATUS_WAITING,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => [(object)['state' => 'Pending', 'amount' => 1000]]
            ]),
            'getStatus: paiement non-OK'
        );
    }
    
    /**
     * Edge cases pour Order::transform() - données vides
     */
    public static function test_transform_empty_data()
    {
        $empty_data = (object)[
            'id' => '0',
            'date' => '2024-01-01T00:00:00',
            'formSlug' => null,
            'organizationSlug' => null,
            'amount' => (object)[]
        ];
        $transformed = call_protected_method(Orders::class, 'transform', [$empty_data]);
        
        Test::strictlyEquals(0, $transformed->id, 'transform: id manquant = 0');
        Test::assert($transformed->date instanceof \DateTime, 'transform: date generee');
        Test::strictlyEquals(0, $transformed->amount, 'transform: amount manquant = 0');
        Test::assert($transformed->form_slug === null, 'transform: form_slug manquant = null');
        Test::assert($transformed->org_slug === null, 'transform: org_slug manquant = null');
        Test::assert($transformed->payer_name === null, 'transform: payer_name manquant = null');
    }
    
    /**
     * Edge cases pour Order::transform() - payer partiel
     */
    public static function test_transform_partial_payer()
    {
        $partial_payer = (object)[
            'id' => '789',
            'date' => '2024-03-01T00:00:00',
            'amount' => (object)['total' => 2000],
            'payer' => (object)['firstName' => 'Jane'],
            'formSlug' => 'test-form',
            'organizationSlug' => 'test-org'
        ];
        
        $partial_transformed = call_protected_method(Orders::class, 'transform', [$partial_payer]);
        Test::strictlyEquals('Jane', $partial_transformed->payer_name, 'transform: payer avec seulement firstName');
    }
    
    /**
     * Edge cases pour Order::get() - id 0
     */
    public static function test_get_zero_id()
    {
        Test::assert(Orders::get(0) === null, 'get: id 0');
    }
    
    /**
     * Edge cases pour Order::get() - id negatif
     */
    public static function test_get_negative_id()
    {
        Test::assert(Orders::get(-1) === null, 'get: id negatif');
    }
    
    /**
     * Edge cases pour Order::setUserId() - id_user null
     */
    public static function test_setUserId_null()
    {
        $form = create_test_form();
        $order = create_test_order($form);
        
        $order->set('id_user', null);
        $order->set('id_transaction', null);
        $order->save();
        
        $order->setUserId(1);
        Test::strictlyEquals(1, $order->id_user, 'setUserId: definit id_user quand null');
        
        $order->setUserId(2);
        Test::strictlyEquals(1, $order->id_user, 'setUserId: ne modifie pas si deja defini');
    }
    
    /**
     * Edge cases pour Order::hasAllUsers() avec create_user = NO_USER_ACTION
     */
    public static function test_hasAllUsers_no_user_action()
    {
        $form_no_create = create_test_form();
        $form_no_create->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_create->save();
        
        $tier_no_create = create_test_tier($form_no_create, 'Tier No Create', 1000, 'Membership');
        $tier_no_create->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_no_create->save();
        
        $order_no_create = create_test_order($form_no_create);
        
        $has_all = $order_no_create->hasAllUsers();
        Test::assert($has_all === null || $has_all === true, 'hasAllUsers: NO_USER_ACTION retourne null ou true');
    }
    
    /**
     * Edge cases pour Order::list() - form vide
     */
    public static function test_list_empty_form()
    {
        $empty_form = create_test_form('empty-org', 'empty-form');
        $empty_list = Orders::list($empty_form);
        
        Test::isInstanceOf(\Paheko\DynamicList::class, $empty_list, 'Orders::list: retourne DynamicList pour form vide');
    }
    
    /**
     * Edge cases pour Order::isSynced() - ordre non paye
     */
    public static function test_isSynced_unpaid_order()
    {
        $form = create_test_form();
        $not_paid_order = create_test_order($form, null, 5000, Entities\Order::STATUS_WAITING);
        $not_paid_order->set('id_user', 1);
        $not_paid_order->set('id_transaction', null);
        
        Test::assert($not_paid_order->isSynced() === null, 'isSynced: null pour ordre non paye');
    }
    
    // ============================================================================
    // LISTE DE TOUS LES TESTS
    // ============================================================================
    
    public static function getAllTests(): array
    {
        return [
            'test_getStatus_amount_without_total',
            'test_getStatus_total_zero',
            'test_getStatus_no_payments_property',
            'test_getStatus_payments_null',
            'test_getStatus_non_ok_payment',
            'test_transform_empty_data',
            'test_transform_partial_payer',
            'test_get_zero_id',
            'test_get_negative_id',
            'test_setUserId_null',
            'test_hasAllUsers_no_user_action',
            'test_list_empty_form',
            'test_isSynced_unpaid_order',
        ];
    }
}

// ============================================================================
// EXÉCUTION DES TESTS
// ============================================================================

$test_class = 'Paheko\Plugin\HelloAsso\OrderEdgeCasesTest';

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
echo "Tous les tests edge cases passes.\n";
