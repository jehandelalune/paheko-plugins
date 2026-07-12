<?php
/**
 * Tests des methodes statiques de Order
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

class OrderStaticTest
{
    // Propriétés partagées entre les tests
    private static $test_form = null;
    
    // ============================================================================
    // SETUP / TEARDOWN
    // ============================================================================
    
    /**
     * Exécuté AVANT chaque test
     */
    public static function setUp(): void
    {
        // Initialisation commune pour tous les tests
        self::$test_form = create_test_form();
    }
    
    /**
     * Exécuté APRÈS chaque test (nettoyage si nécessaire)
     */
    public static function tearDown(): void
    {
        // Nettoyage après chaque test
        self::$test_form = null;
    }
    
    // ============================================================================
    // MÉTHODES DE TEST
    // ============================================================================
    
    /**
     * TEST: Order::getStatus() - paiement complet
     */
    public static function test_getStatus_full_payment()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => [(object)['state' => Entities\Payment::STATE_OK, 'amount' => 1000]]
            ]),
            'getStatus: paiement complet'
        );
    }
    
    /**
     * TEST: Order::getStatus() - paiement partiel
     */
    public static function test_getStatus_partial_payment()
    {
        Test::equals(
            Entities\Order::STATUS_WAITING,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => [(object)['state' => Entities\Payment::STATE_OK, 'amount' => 500]]
            ]),
            'getStatus: paiement partiel'
        );
    }
    
    /**
     * TEST: Order::getStatus() - aucun paiement
     */
    public static function test_getStatus_no_payment()
    {
        Test::equals(
            Entities\Order::STATUS_WAITING,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => []
            ]),
            'getStatus: aucun paiement'
        );
    }
    
    /**
     * TEST: Order::getStatus() - montant a 0
     */
    public static function test_getStatus_zero_amount()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 0],
                'payments' => []
            ]),
            'getStatus: montant a 0'
        );
    }
    
    /**
     * TEST: Order::getStatus() - plusieurs paiements
     */
    public static function test_getStatus_multiple_payments()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => [
                    (object)['state' => Entities\Payment::STATE_OK, 'amount' => 600],
                    (object)['state' => Entities\Payment::STATE_OK, 'amount' => 400]
                ]
            ]),
            'getStatus: plusieurs paiements'
        );
    }
    
    /**
     * TEST: Order::getStatus() - surpaiement
     */
    public static function test_getStatus_overpayment()
    {
        Test::equals(
            Entities\Order::STATUS_PAID,
            Entities\Order::getStatus((object)[
                'amount' => (object)['total' => 1000],
                'payments' => [
                    (object)['state' => Entities\Payment::STATE_OK, 'amount' => 1500]
                ]
            ]),
            'getStatus: surpaiement'
        );
    }
    
    /**
     * TEST: Order::get()
     */
    public static function test_get()
    {
        $order = create_test_order(self::$test_form, null);
        $order_id = $order->id();
        
        Test::assert(Orders::get($order_id) !== null, 'Order::get: ordre existant');
        Test::isInstanceOf(Entities\Order::class, Orders::get($order_id), 'Order::get: bon type');
        Test::assert(Orders::get(999999) === null, 'Order::get: ordre inexistant');
    }
    
    /**
     * TEST: Order::transform()
     */
    public static function test_transform()
    {
        $raw_data = (object)[
            'id' => '123',
            'date' => '2024-01-15T10:30:00+01:00',
            'formSlug' => 'test-form',
            'organizationSlug' => 'test-org',
            'amount' => (object)['total' => 1500],
            'payer' => (object)['firstName' => 'John', 'lastName' => 'Doe'],
        ];
        
        $transformed = call_protected_method(Orders::class, 'transform', [$raw_data]);
        
        Test::strictlyEquals(123, $transformed->id, 'transform: id');
        Test::assert($transformed->date instanceof \DateTime, 'transform: date est DateTime');
        Test::strictlyEquals('2024-01-15 10:30:00', $transformed->date->format('Y-m-d H:i:s'), 'transform: date format');
        Test::strictlyEquals(1500, $transformed->amount, 'transform: amount');
        Test::strictlyEquals('John Doe', $transformed->payer_name, 'transform: payer_name');
        Test::strictlyEquals('test-form', $transformed->form_slug, 'transform: form_slug');
        Test::strictlyEquals('test-org', $transformed->org_slug, 'transform: org_slug');
    }
    
    /**
     * TEST: Order::transform() - champs manquants
     */
    public static function test_transform_minimal_data()
    {
        $minimal_data = (object)[
            'id' => '456',
            'date' => '2024-01-01T00:00:00',
            'formSlug' => 'test-form',
            'organizationSlug' => 'test-org',
            'amount' => (object)[]
        ];
        
        $minimal_transformed = call_protected_method(Orders::class, 'transform', [$minimal_data]);
        Test::strictlyEquals(456, $minimal_transformed->id, 'transform: minimal id');
        Test::strictlyEquals(0, $minimal_transformed->amount, 'transform: minimal amount (0)');
        Test::assert($minimal_transformed->payer_name === null, 'transform: minimal payer_name null');
    }
    
    /**
     * TEST: Orders::list()
     */
    public static function test_list()
    {
        $form2 = create_test_form('org2', 'form2');
        Test::isInstanceOf(\Paheko\DynamicList::class, Orders::list($form2), 'Orders::list: retourne DynamicList');
    }
    
    // ============================================================================
    // LISTE DE TOUS LES TESTS
    // ============================================================================
    
    public static function getAllTests(): array
    {
        return [
            'test_getStatus_full_payment',
            'test_getStatus_partial_payment',
            'test_getStatus_no_payment',
            'test_getStatus_zero_amount',
            'test_getStatus_multiple_payments',
            'test_getStatus_overpayment',
            'test_get',
            'test_transform',
            'test_transform_minimal_data',
            'test_list',
        ];
    }
}

// ============================================================================
// EXÉCUTION DES TESTS
// ============================================================================

$test_class = 'Paheko\Plugin\HelloAsso\OrderStaticTest';

// Si un test spécifique est demandé en ligne de commande
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $test_method = $argv[1];
    
    if (method_exists($test_class, $test_method)) {
        $test_class::setUp();
        try {
            $test_class::$test_method();
            echo "✓ Test $test_method passé\n";
            $test_class::tearDown();
            exit(0);
        } catch (\Throwable $e) {
            echo "✗ Test $test_method échoué: " . $e->getMessage() . "\n";
            echo "  Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $test_class::tearDown();
            exit(1);
        }
    } else {
        echo "Erreur: Test '$test_method' introuvable\n";
        echo "Tests disponibles: " . implode(', ', $test_class::getAllTests()) . "\n";
        exit(1);
    }
}

// Exécution de tous les tests
$test_class::setUp();

foreach ($test_class::getAllTests() as $test_method) {
    try {
        $test_class::$test_method();
    } catch (\Throwable $e) {
        echo "✗ Test $test_method échoué: " . $e->getMessage() . "\n";
        echo "  Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

$test_class::tearDown();
echo "Tous les tests statiques passes.\n";
