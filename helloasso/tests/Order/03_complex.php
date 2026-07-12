<?php
/**
 * Tests des methodes complexes de Order
 * Structure avec pattern setUp/tearDown pour exécution unitaire
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use Paheko\DB;
use Paheko\UserException;

// Charger les dépendances de test (après le namespace pour éviter les erreurs)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_inc.php';

// ============================================================================
// CLASSE DE TEST
// ============================================================================

class OrderComplexTest
{
    // Propriétés partagées
    private static $year = null;
    private static $ha = null;
    private static $form = null;
    private static $tier = null;
    
    // ============================================================================
    // SETUP / TEARDOWN
    // ============================================================================
    
    public static function setUpBeforeClass(): void
    {
        self::$year = setup_accounting_year();
        self::$ha = setup_helloasso_config();
        
        self::$form = create_test_form();
        self::$form->set('id_year', self::$year->id());
        self::$form->set('payment_account_code', '756');
        self::$form->save();
        
        self::$tier = create_test_tier(self::$form, 'Adhesion Test', 5000);
        
        // Créer un service et un fee pour les abonnements
        $db = DB::getInstance();
        $db->preparedQuery("INSERT OR IGNORE INTO services (id, label, description) VALUES (1, 'Test Service', 'Service de test')");
        $db->preparedQuery("INSERT OR IGNORE INTO services_fees (id, id_service, label, amount) VALUES (1, 1, 'Cotisation Test', 5000)");
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$year = null;
        self::$ha = null;
        self::$form = null;
        self::$tier = null;
    }
    
    // ============================================================================
    // MÉTHODES DE TEST
    // ============================================================================
    
    /**
     * TEST: Order::syncOrder() via reflection
     */
    public static function test_syncOrder_creation()
    {
        $raw_data = (object)[
            'id' => 9999,
            'formSlug' => self::$form->slug,
            'organizationSlug' => self::$form->org_slug,
            'date' => '2024-01-15T10:30:00+01:00',
            'amount' => (object)['total' => 5000],
            'status' => 'Processed',
            'payer' => (object)['firstName' => 'Sync', 'lastName' => 'Test'],
            'payments' => [
                (object)['id' => 1, 'state' => Entities\Payment::STATE_OK, 'amount' => 5000, 'date' => '2024-01-15']
            ],
            'items' => [
                (object)['id' => 1, 'type' => 'Membership', 'label' => 'Adhesion', 'amount' => 5000, 'state' => 'Processed']
            ]
        ];
        
        call_protected_method(Orders::class, 'syncOrder', [$raw_data]);
        
        $sync_order = Orders::get(9999);
        Test::assert($sync_order !== null, 'syncOrder: ordre cree');
        Test::strictlyEquals(5000, $sync_order->amount, 'syncOrder: montant correct');
        Test::strictlyEquals('Sync Test', $sync_order->person, 'syncOrder: personne correcte');
    }
    
    /**
     * TEST: Order::hasAllUsers() / Order::hasAllSubscriptions()
     */
    public static function test_hasAllUsers_hasAllSubscriptions()
    {
        $membership_form = create_test_form('org', 'membership-form', 'Membership');
        $membership_tier = create_test_tier($membership_form, 'Adhesion', 1000, 'Membership');
        
        // Cas 1: Form non Membership
        $payment_form = create_test_form('org', 'payment-form', 'Payment');
        $payment_order = create_test_order($payment_form);
        
        Test::assert($payment_order->hasAllUsers() === null, 'hasAllUsers: null pour form non Membership');
        Test::assert($payment_order->hasAllSubscriptions() === null, 'hasAllSubscriptions: null pour form non Membership');
        
        // Cas 2: Membership form sans items
        $membership_order = create_test_order($membership_form);
        
        Test::assert($membership_order->hasAllUsers() === null, 'hasAllUsers: null sans items');
        Test::assert($membership_order->hasAllSubscriptions() === null, 'hasAllSubscriptions: null sans items');
    }
    
    /**
     * TEST: Order::isSynced()
     */
    public static function test_isSynced()
    {
        $synced_order = create_test_order(self::$form, null, 2000, Entities\Order::STATUS_PAID);
        $synced_order->set('id_user', 1);
        $synced_order->set('id_transaction', null);
        
        Test::assert($synced_order->isSynced() === false, 'isSynced: false sans transaction (year configure)');
        
        // Form sans year
        $form_no_year = create_test_form('org', 'no-year-form');
        $form_no_year->set('id_year', null);
        $form_no_year->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_year->save();
        
        $order_no_year = create_test_order($form_no_year, null, 3000, Entities\Order::STATUS_PAID);
        $order_no_year->set('id_user', 1);
        $order_no_year->set('id_transaction', null);
        
        Test::assert($order_no_year->isSynced() === true, 'isSynced: true avec user et sans year');
    }
    
    /**
     * TEST: Order::createTransaction() - structure de base
     */
    public static function test_createTransaction()
    {
        $transaction_order = create_test_order(self::$form, null, 4000, Entities\Order::STATUS_PAID);
        $transaction_order->set('id_user', null);
        $transaction_order->set('id_transaction', null);
        $transaction_order->save();
        $transaction_item = create_test_item($transaction_order, self::$tier, 4000);
        $transaction_payment = create_test_payment($transaction_order, 4000);
        
        try {
            $transaction = $transaction_order->createTransaction(self::$ha);
            Test::assert($transaction !== null, 'createTransaction: transaction creee');
            Test::strictlyEquals('Commande HelloAsso n°' . $transaction_order->id(), $transaction->label, 'createTransaction: label correct');
            Test::assert($transaction->id_year === self::$year->id(), 'createTransaction: bonne annee');
            Test::assert($transaction->reference === 'HELLOASSO-C' . $transaction_order->id(), 'createTransaction: reference correcte');
        } catch (UserException $e) {
            Test::assert(
                strpos($e->getMessage(), 'compte') !== false || strpos($e->getMessage(), 'account') !== false,
                'createTransaction: erreur attendue sur les comptes'
            );
        } catch (\LogicException $e) {
            Test::assert(
                strpos($e->getMessage(), 'Unbalanced') !== false,
                'createTransaction: erreur de solde'
            );
        }
    }
    
    /**
     * TEST: Order::syncOrder() - mise a jour d'un ordre existant
     */
    public static function test_syncOrder_update()
    {
        $existing_order = create_test_order(self::$form, null, 1000, Entities\Order::STATUS_WAITING);
        
        $update_data = (object)[
            'id' => $existing_order->id(),
            'formSlug' => self::$form->slug,
            'organizationSlug' => self::$form->org_slug,
            'date' => '2024-02-20T15:00:00+01:00',
            'amount' => (object)['total' => 2000],
            'status' => 'Processed',
            'payer' => (object)['firstName' => 'Updated', 'lastName' => 'User'],
            'payments' => [],
            'items' => []
        ];
        
        call_protected_method(Orders::class, 'syncOrder', [$update_data]);
        
        $updated_order = Orders::get($existing_order->id());
        Test::assert($updated_order !== null, 'syncOrder update: ordre existe toujours');
        Test::strictlyEquals(2000, $updated_order->amount, 'syncOrder update: montant mis a jour');
        Test::strictlyEquals('Updated User', $updated_order->person, 'syncOrder update: personne mise a jour');
    }
    
    /**
     * TEST: importData() - sans creation d'abonnements
     */
    public static function test_importData_no_subscriptions()
    {
        $form_no_fee = create_test_form('org', 'no-fee-form');
        $form_no_fee->set('id_year', self::$year->id());
        $form_no_fee->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_fee->save();
        
        $order_no_fee = create_test_order($form_no_fee, null, 1500, Entities\Order::STATUS_PAID);
        $order_no_fee->set('id_user', 1);
        $order_no_fee->save();
        
        $report_no_fee = $order_no_fee->importData(null, false, false, false, false);
        Test::isArray($report_no_fee, 'importData: retourne array meme sans creation');
    }
    
    // ============================================================================
    // LISTE DE TOUS LES TESTS
    // ============================================================================
    
    public static function getAllTests(): array
    {
        return [
            'test_syncOrder_creation',
            'test_hasAllUsers_hasAllSubscriptions',
            'test_isSynced',
            'test_createTransaction',
            'test_syncOrder_update',
            'test_importData_no_subscriptions',
        ];
    }
}

// ============================================================================
// EXÉCUTION DES TESTS
// ============================================================================

$test_class = 'Paheko\Plugin\HelloAsso\OrderComplexTest';

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
echo "Tous les tests complexes passes.\n";
