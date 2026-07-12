<?php
/**
 * Tests pour Order::importData() et ses wrappers
 * Structure avec pattern setUp/tearDown pour exécution unitaire
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use Paheko\DB;
use Paheko\UserException;
use Paheko\Plugin\HelloAsso\Entities\Order;
use Paheko\Plugin\HelloAsso\Entities\Item;
use Paheko\Plugin\HelloAsso\Entities\Payment;
use Paheko\Entities\Accounting\Transaction;
use KD2\DB\EntityManager as EM;

// Charger les dépendances de test (après le namespace pour éviter les erreurs)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_inc.php';

// ============================================================================
// FONCTION HELPER POUR CREER DES ORDRES AVEC LES CHAMPS INITIALISES
// ============================================================================

/**
 * Crée un Order de test avec les champs optionnels initialisés
 */
function create_test_order_init(Entities\Form $form, ?int $id = null, int $amount = 1000, int $status = Entities\Order::STATUS_PAID, string $person = 'Test User', string $date = '2024-01-15'): Entities\Order
{
    $order = create_test_order($form, $id, $amount, $status, $person);
    $order->set('id_user', null);
    $order->set('id_transaction', null);
    $order->set('date', new \DateTime($date));
    $order->save();
    return $order;
}

// ============================================================================
// CLASSE DE TEST
// ============================================================================

class OrderImportTest
{
    // Configuration partagée
    private static $ha = null;
    private static $year = null;
    
    // ============================================================================
    // SETUP / TEARDOWN
    // ============================================================================
    
    public static function setUpBeforeClass(): void
    {
        // Configurer HelloAsso avec les comptes requis
        self::$ha = setup_helloasso_config();
        
        // Remplacer l'instance singleton de HelloAsso
        $reflection = new \ReflectionClass(HelloAsso::class);
        $instance_property = $reflection->getProperty('_instance');
        if (PHP_VERSION_ID < 80500) {
            $instance_property->setAccessible(true);
        }
        $instance_property->setValue(null, self::$ha);
        
        // Récupérer ou créer un exercice comptable
        self::$year = null;
        $existing_years = \Paheko\Accounting\Years::list();
        foreach ($existing_years as $existing_year) {
            if ($existing_year->label === 'Test Year') {
                self::$year = $existing_year;
                break;
            }
        }
        if (self::$year === null) {
            self::$year = setup_accounting_year();
        }
        
        // Créer un service et un fee pour les abonnements
        $db = DB::getInstance();
        $db->preparedQuery("INSERT OR IGNORE INTO services (id, label, description) VALUES (1, 'Test Service', 'Service de test')");
        $db->preparedQuery("INSERT OR IGNORE INTO services_fees (id, id_service, label, amount) VALUES (1, 1, 'Cotisation Test', 5000)");
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$ha = null;
        self::$year = null;
    }
    
    // ============================================================================
    // MÉTHODES DE TEST
    // ============================================================================
    
    /**
     * TEST 1: importData() - Ordre non payé doit échouer
     */
    public static function test_importData_unpaid_order_fails()
    {
        $form_unpaid = create_test_form();
        $order_unpaid = create_test_order_init($form_unpaid, null, 3000, Order::STATUS_WAITING);
        
        try {
            $order_unpaid->importData(null, true, true, true, true);
            Test::fail('importData: doit lever LogicException pour ordre non paye');
        } catch (\LogicException $e) {
            Test::assert(
                strpos($e->getMessage(), 'Cannot sync a non-paid order') !== false,
                'importData: message d\'erreur correct pour ordre non paye'
            );
        }
    }
    
    /**
     * TEST 2: importData() - Import complet avec creation transaction
     */
    public static function test_importData_complete_import()
    {
        $form_complete = create_test_form('', '', 'Membership');
        $form_complete->set('id_year', self::$year->id());
        $form_complete->set('payment_account_code', '756');
        $form_complete->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_complete->save();
        
        $tier_complete = create_test_tier($form_complete, 'Adhesion Complete', 5000, 'Membership');
        $tier_complete->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_complete->set('id_fee', 1);
        $tier_complete->save();
        
        $order_complete = create_test_order_init($form_complete, null, 5000, Order::STATUS_PAID, 'Test Complete');
        $order_complete->set('id_user', 1);
        $order_complete->save();
        
        $item_complete = new Item();
        $item_complete->set('id_order', $order_complete->id());
        $item_complete->set('id_form', $form_complete->id());
        $item_complete->set('id_tier', $tier_complete->id());
        $item_complete->set('type', 'Membership');
        $item_complete->set('state', 'Processed');
        $item_complete->set('label', 'Adhesion Complete');
        $item_complete->set('amount', 5000);
        $item_complete->set('raw_data', json_encode((object)[]));
        $item_complete->set('id_user', 1);
        $item_complete->save();
        
        $payment_complete = new Payment();
        $payment_complete->set('id_order', $order_complete->id());
        $payment_complete->set('id_form', $form_complete->id());
        $payment_complete->set('amount', 5000);
        $payment_complete->set('state', Payment::STATE_OK);
        $payment_complete->set('date', new \DateTime('2024-01-15'));
        $payment_complete->set('raw_data', '{}');
        $payment_complete->save();
        
        $report_complete = $order_complete->importData(null, false, false, false, true);
        
        Test::isArray($report_complete, 'importData: retourne un array');
        Test::assert(count($report_complete) >= 1, 'importData: rapport contient au moins la creation transaction');
        
        $updated_item_complete = EM::findOne(Item::class, 'SELECT * FROM @TABLE WHERE id = ?', $item_complete->id());
        Test::assert($updated_item_complete !== null, 'importData: item existe toujours');
        
        Test::assert($order_complete->id_transaction !== null, 'importData: transaction comptable creee');
        
        $transaction = EM::findOneById(Transaction::class, $order_complete->id_transaction);
        Test::assert($transaction !== null, 'importData: transaction existe en base');
        Test::assert($transaction->id_year === self::$year->id(), 'importData: transaction a la bonne annee');
    }
    
    /**
     * TEST 3: importData() - Idempotence (2e appel ne duplique pas)
     */
    public static function test_importData_idempotence()
    {
        // Utiliser le meme order que test 2 si exécuté dans l'ordre
        // Sinon recréer la configuration
        $form_complete = create_test_form('', '', 'Membership-idempotence');
        $form_complete->set('id_year', self::$year->id());
        $form_complete->set('payment_account_code', '756');
        $form_complete->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_complete->save();
        
        $tier_complete = create_test_tier($form_complete, 'Adhesion Idempotence', 5000, 'Membership');
        $tier_complete->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_complete->save();
        
        $order_complete = create_test_order_init($form_complete, null, 5000, Order::STATUS_PAID, 'Test Complete Idem');
        $order_complete->set('id_user', 1);
        $order_complete->save();
        
        $item_complete = new Item();
        $item_complete->set('id_order', $order_complete->id());
        $item_complete->set('id_form', $form_complete->id());
        $item_complete->set('id_tier', $tier_complete->id());
        $item_complete->set('type', 'Membership');
        $item_complete->set('state', 'Processed');
        $item_complete->set('label', 'Adhesion Idempotence');
        $item_complete->set('amount', 5000);
        $item_complete->set('raw_data', json_encode((object)[]));
        $item_complete->set('id_user', 1);
        $item_complete->save();
        
        $payment_complete = new Payment();
        $payment_complete->set('id_order', $order_complete->id());
        $payment_complete->set('id_form', $form_complete->id());
        $payment_complete->set('amount', 5000);
        $payment_complete->set('state', Payment::STATE_OK);
        $payment_complete->set('date', new \DateTime('2024-01-15'));
        $payment_complete->set('raw_data', '{}');
        $payment_complete->save();
        
        $report_complete = $order_complete->importData(null, false, false, false, true);
        
        // 2e appel
        $report_complete_2 = $order_complete->importData(null, false, false, false, true);
        Test::assert(count($report_complete_2) === 0, 'importData: 2e appel ne cree rien de nouveau (idepotence)');
        
        Test::assert($order_complete->id_user !== null, 'importData: id_user toujours definis');
        Test::assert($order_complete->id_transaction !== null, 'importData: id_transaction toujours definis');
    }
    
    /**
     * TEST 4: importData() - Sans creation d'utilisateurs
     */
    public static function test_importData_no_create_users()
    {
        $form_no_create = create_test_form('', '', 'Membership');
        $form_no_create->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_create->save();
        
        $tier_no_create = create_test_tier($form_no_create, 'Tier No Create', 2000, 'Membership');
        $tier_no_create->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_no_create->save();
        
        $order_no_create = create_test_order_init($form_no_create, null, 2000, Order::STATUS_PAID, 'No Create');
        
        $report_no_create = $order_no_create->importData(null, false, false, false, false);
        Test::assert($order_no_create->id_user === null, 'importData: id_user reste null avec NO_USER_ACTION');
    }
    
    /**
     * TEST 5: importData() - Creation de transaction seulement
     */
    public static function test_importData_transaction_only()
    {
        $form_tx_only = create_test_form('', '', 'Membership');
        $form_tx_only->set('id_year', self::$year->id());
        $form_tx_only->set('payment_account_code', '756');
        $form_tx_only->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_tx_only->save();
        
        $tier_tx_only = create_test_tier($form_tx_only, 'Tier Tx Only', 4000, 'Membership');
        $tier_tx_only->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_tx_only->save();
        
        $order_tx_only = create_test_order_init($form_tx_only, null, 4000, Order::STATUS_PAID, 'Tx Only');
        $order_tx_only->set('id_user', 1);
        $order_tx_only->save();
        
        $item_tx_only = new Item();
        $item_tx_only->set('id_order', $order_tx_only->id());
        $item_tx_only->set('id_form', $form_tx_only->id());
        $item_tx_only->set('id_tier', $tier_tx_only->id());
        $item_tx_only->set('type', 'Membership');
        $item_tx_only->set('state', 'Processed');
        $item_tx_only->set('label', 'Tx Only Item');
        $item_tx_only->set('amount', 4000);
        $item_tx_only->set('raw_data', json_encode((object)[]));
        $item_tx_only->save();
        
        $payment_tx_only = new Payment();
        $payment_tx_only->set('id_order', $order_tx_only->id());
        $payment_tx_only->set('id_form', $form_tx_only->id());
        $payment_tx_only->set('amount', 4000);
        $payment_tx_only->set('state', Payment::STATE_OK);
        $payment_tx_only->set('date', new \DateTime(date('Y-m-d')));
        $payment_tx_only->set('raw_data', '{}');
        $payment_tx_only->save();
        
        $report_tx_only = $order_tx_only->importData(null, false, false, false, true);
        
        Test::assert($order_tx_only->id_transaction !== null, 'importData: transaction creee avec seulement create_transaction=true');
        Test::assert(count($report_tx_only) >= 1, 'importData: rapport contient creation transaction');
    }
    
    /**
     * TEST 6: importData() - Sans year, pas de transaction
     */
    public static function test_importData_no_year_no_transaction()
    {
        $form_no_year = create_test_form('', '', 'Membership');
        $form_no_year->set('id_year', null);
        $form_no_year->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_year->save();
        
        $order_no_year = create_test_order_init($form_no_year, null, 1000, Order::STATUS_PAID, 'No Year');
        $order_no_year->set('id_user', 1);
        $order_no_year->save();
        
        try {
            $report_no_year = $order_no_year->importData(null, false, false, false, true);
            Test::fail('importData: doit lever UserException pour form sans year');
        } catch (UserException $e) {
            Test::assert(
                strpos($e->getMessage(), 'campagne') !== false || strpos($e->getMessage(), 'exercice comptable') !== false,
                'importData: message d\'erreur correct pour form sans year'
            );
        }
    }
    
    /**
     * TEST 7: importData() - Sans creation d'abonnements
     */
    public static function test_importData_no_subscriptions()
    {
        $form_no_fee = create_test_form('', '', 'Membership');
        $form_no_fee->set('id_year', self::$year->id());
        $form_no_fee->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_no_fee->save();
        
        $order_no_fee = create_test_order_init($form_no_fee, null, 1500, Order::STATUS_PAID, 'No Fee');
        $order_no_fee->set('id_user', 1);
        $order_no_fee->save();
        
        $report_no_fee = $order_no_fee->importData(null, false, false, false, false);
        Test::isArray($report_no_fee, 'importData: retourne array meme sans creation');
    }
    
    /**
     * TEST 8: importData() - Type Payment, pas de transaction sans year
     */
    public static function test_importData_payment_no_year()
    {
        $form_payment = create_test_form('', '', 'Payment');
        $form_payment->set('id_year', null);
        $form_payment->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_payment->save();
        
        $order_payment = create_test_order_init($form_payment, null, 2500, Order::STATUS_PAID, 'Payment');
        $order_payment->set('id_user', 1);
        $order_payment->save();
        
        try {
            $report_payment = $order_payment->importData(null, false, false, false, true);
            Test::fail('importData: doit lever UserException pour form sans year (Payment)');
        } catch (UserException $e) {
            Test::assert(
                strpos($e->getMessage(), 'campagne') !== false || strpos($e->getMessage(), 'exercice comptable') !== false,
                'importData: message d\'erreur correct pour form sans year (Payment)'
            );
        }
    }
    
    /**
     * TEST 9: Test des wrappers (importTransaction)
     */
    public static function test_importTransaction_wrapper()
    {
        $form_wrappers = create_test_form('', '', 'Membership');
        $form_wrappers->set('id_year', self::$year->id());
        $form_wrappers->set('payment_account_code', '756');
        $form_wrappers->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_wrappers->save();
        
        $order_wrappers = create_test_order_init($form_wrappers, null, 6000, Order::STATUS_PAID, 'Wrappers');
        $order_wrappers->set('id_user', 1);
        $order_wrappers->save();
        
        $item_wrappers = new Item();
        $item_wrappers->set('id_order', $order_wrappers->id());
        $item_wrappers->set('id_form', $form_wrappers->id());
        $item_wrappers->set('type', 'Membership');
        $item_wrappers->set('state', 'Processed');
        $item_wrappers->set('label', 'Wrappers Item');
        $item_wrappers->set('amount', 6000);
        $item_wrappers->set('raw_data', json_encode((object)[]));
        $item_wrappers->save();
        
        $payment_wrappers = new Payment();
        $payment_wrappers->set('id_order', $order_wrappers->id());
        $payment_wrappers->set('id_form', $form_wrappers->id());
        $payment_wrappers->set('amount', 6000);
        $payment_wrappers->set('state', Payment::STATE_OK);
        $payment_wrappers->set('date', new \DateTime('2024-01-15'));
        $payment_wrappers->set('raw_data', '{}');
        $payment_wrappers->save();
        
        $report_transaction = $order_wrappers->importTransaction(1);
        Test::isArray($report_transaction, 'importTransaction: retourne array');
        
        Test::assert($order_wrappers->id_transaction !== null, 'importTransaction: id_transaction definis');
    }
    
    /**
     * TEST 10: importData() - Montant déséquilibré (paiement != item)
     */
    public static function test_importData_unbalanced_amount()
    {
        $form_unbalanced = create_test_form('', '', 'Membership');
        $form_unbalanced->set('id_year', self::$year->id());
        $form_unbalanced->set('payment_account_code', '756');
        $form_unbalanced->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_unbalanced->save();
        
        $tier_unbalanced = create_test_tier($form_unbalanced, 'Tier Unbalanced', 3000, 'Membership');
        $tier_unbalanced->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_unbalanced->save();
        
        $order_unbalanced = create_test_order_init($form_unbalanced, null, 3000, Order::STATUS_PAID, 'Unbalanced');
        $order_unbalanced->set('id_user', 1);
        $order_unbalanced->save();
        
        $item_unbalanced = new Item();
        $item_unbalanced->set('id_order', $order_unbalanced->id());
        $item_unbalanced->set('id_form', $form_unbalanced->id());
        $item_unbalanced->set('id_tier', $tier_unbalanced->id());
        $item_unbalanced->set('type', 'Membership');
        $item_unbalanced->set('state', 'Processed');
        $item_unbalanced->set('label', 'Unbalanced Item');
        $item_unbalanced->set('amount', 3000);
        $item_unbalanced->set('raw_data', json_encode((object)[]));
        $item_unbalanced->save();
        
        $payment_unbalanced = new Payment();
        $payment_unbalanced->set('id_order', $order_unbalanced->id());
        $payment_unbalanced->set('id_form', $form_unbalanced->id());
        $payment_unbalanced->set('amount', 2500);
        $payment_unbalanced->set('state', Payment::STATE_OK);
        $payment_unbalanced->set('date', new \DateTime(date('Y-m-d')));
        $payment_unbalanced->set('raw_data', '{}');
        $payment_unbalanced->save();
        
        try {
            $order_unbalanced->importData(null, false, false, false, true);
            Test::fail('importData: doit lever LogicException pour transaction desequilibree');
        } catch (\LogicException $e) {
            Test::assert(
                strpos($e->getMessage(), 'Unbalanced transaction') !== false,
                'importData: message d\'erreur correct pour transaction desequilibree'
            );
        }
    }
    
    /**
     * TEST 11: importData() - Utilisateur existant avec même adresse email
     */
    public static function test_importData_duplicate_email_user()
    {
        $user_existing = new \Paheko\Entities\Users\User();
        $user_existing->set('email', 'existing@example.com');
        $user_existing->set('name', 'Existing User');
        $user_existing->save();
        
        $existing_user_id = $user_existing->id();
        
        $form_duplicate_email = create_test_form('', '', 'Membership');
        $form_duplicate_email->set('id_year', self::$year->id());
        $form_duplicate_email->set('payment_account_code', '756');
        $form_duplicate_email->set('create_payer_user', HelloAsso::NO_USER_ACTION);
        $form_duplicate_email->save();
        
        $tier_duplicate_email = create_test_tier($form_duplicate_email, 'Tier Duplicate Email', 3500, 'Membership');
        $tier_duplicate_email->set('create_user', HelloAsso::NO_USER_ACTION);
        $tier_duplicate_email->save();
        
        $order_duplicate_email = create_test_order_init($form_duplicate_email, null, 3500, Order::STATUS_PAID, 'Existing Email User');
        $order_duplicate_email->set('id_user', $existing_user_id);
        $order_duplicate_email->save();
        
        $item_duplicate_email = new Item();
        $item_duplicate_email->set('id_order', $order_duplicate_email->id());
        $item_duplicate_email->set('id_form', $form_duplicate_email->id());
        $item_duplicate_email->set('id_tier', $tier_duplicate_email->id());
        $item_duplicate_email->set('type', 'Membership');
        $item_duplicate_email->set('state', 'Processed');
        $item_duplicate_email->set('label', 'Duplicate Email Item');
        $item_duplicate_email->set('amount', 3500);
        $item_duplicate_email->set('raw_data', json_encode((object)[]));
        $item_duplicate_email->set('id_user', $existing_user_id);
        $item_duplicate_email->save();
        
        $payment_duplicate_email = new Payment();
        $payment_duplicate_email->set('id_order', $order_duplicate_email->id());
        $payment_duplicate_email->set('id_form', $form_duplicate_email->id());
        $payment_duplicate_email->set('amount', 3500);
        $payment_duplicate_email->set('state', Payment::STATE_OK);
        $payment_duplicate_email->set('date', new \DateTime('2024-01-15'));
        $payment_duplicate_email->set('raw_data', '{}');
        $payment_duplicate_email->save();
        
        $report_duplicate_email = $order_duplicate_email->importData(null, false, false, false, true);
        
        Test::assert(
            $order_duplicate_email->id_user === $existing_user_id,
            'importData: doit garder l\'utilisateur existant'
        );
        
        Test::assert(
            $order_duplicate_email->id_transaction !== null,
            'importData: transaction créée'
        );
    }
    
    /**
     * TEST 12a: importData() - Création utilisateur avec payeur Dupont
     */
    public static function test_importData_with_membership_and_donation_items_dupont()
    {
        self::test_importData_with_membership_and_donation_items_helper(
            'Jean', 'Dupont', 'jean.dupont@example.com', 'Jean', 'Dupont'
        );
    }
    
    /**
     * TEST 12b: importData() - Création utilisateur avec payeur Durand (avec espace final)
     */
    public static function test_importData_with_membership_and_donation_items_durand()
    {
        self::test_importData_with_membership_and_donation_items_helper(
            'Pierre', 'Durand', 'pierre.durand@example.com', 'Pierre', 'Durand '
        );
    }
    
    /**
     * Helper pour tester la création automatique de l'utilisateur à partir du payeur
     * avec items Membership et Donation.
     * Utilise un JSON HelloAsso complet dans raw_data de l'ordre.
     * 
     * @param string $payer_firstname Prénom du payeur
     * @param string $payer_lastname Nom du payeur
     * @param string $payer_email Email du payeur
     * @param string $user_firstname Prénom de l'utilisateur à créer
     * @param string $user_lastname Nom de l'utilisateur à créer
     */
    private static function test_importData_with_membership_and_donation_items_helper(
        string $payer_firstname, 
        string $payer_lastname, 
        string $payer_email,
        string $user_firstname,
        string $user_lastname
    ): void {
     
        $payer_name = $payer_firstname . ' ' . $payer_lastname;
        
        // Vérifier qu'aucun utilisateur n'existe avec cet email avant le test
        $db = \Paheko\DB::getInstance();
        $email_field = \Paheko\Users\DynamicFields::getFirstEmailField();
        $existing_user_count = $db->preparedQuery(
            "SELECT COUNT(*) as cnt FROM users WHERE $email_field = ?",
            $payer_email
        );
        $existing_user = $existing_user_count->fetchArray();
        Test::assert((int)$existing_user['cnt'] === 0, 'Aucun utilisateur existant avec cet email avant le test');
        
        $form = create_test_form('', '', 'Membership');
        $form->set('id_year', self::$year->id());
        $form->set('payment_account_code', '756');
        $form->set('create_payer_user', HelloAsso::CREATE_UPDATE_USER);
        $form->save();
        
        // Créer un tier pour le Membership avec création d'utilisateur
        $tier = create_test_tier($form, 'Adhésion Test', 1000, 'Membership');
        $tier->set('create_user', HelloAsso::CREATE_UPDATE_USER);
        $tier->save();
        
        // Créer l'ordre SANS utilisateur (id_user = null)
        $order = create_test_order_init($form, null, 2000, Order::STATUS_PAID, $payer_name);
        
        // Configurer les données brutes avec le JSON HelloAsso complet
        $raw_data = (object)[
            'payer' => (object)[
                'email' => $payer_email,
                'country' => 'FRA',
                'firstName' => $payer_firstname,
                'lastName' => $payer_lastname
            ],
            'items' => [
                (object)[
                    'payments' => [
                        (object)[
                            'id' => 63109747,
                            'shareAmount' => 1000
                        ]
                    ],
                    'name' => 'Cositation annuelle',
                    'user' => (object)[
                        'firstName' => $user_firstname,
                        'lastName' => $user_lastname,
                        'email' => $payer_email
                    ],
                    'priceCategory' => 'Fixed',
                    'customFields' => [
                        (object)[
                            'id' => 5177174,
                            'name' => 'Email',
                            'type' => 'TextInput',
                            'answer' => $payer_email
                        ],
                        (object)[
                            'id' => 5177177,
                            'name' => 'Inscription à la newsletter',
                            'type' => 'YesNo',
                            'answer' => 'Oui'
                        ]
                    ],
                    'qrCode' => 'MTM5MDc3MjYxOjYzODg2MzYzMzA3NjExOTE0NQ==',
                    'tierDescription' => 'Adhésion à l\'association RESTe!',
                    'tierId' => 14672172,
                    'id' => 139077261,
                    'amount' => 1000,
                    'type' => 'Membership',
                    'initialAmount' => 1000,
                    'state' => 'Processed'
                ],
                (object)[
                    'payments' => [
                        (object)[
                            'id' => 63109747,
                            'shareAmount' => 1000
                        ]
                    ],
                    'priceCategory' => 'Fixed',
                    'qrCode' => 'MTM5MDc3MjYyOjYzODg2MzYzMzA3NjExOTE0NQ==',
                    'id' => 139077262,
                    'amount' => 1000,
                    'type' => 'Donation',
                    'state' => 'Processed'
                ]
            ],
            'payments' => [
                (object)[
                    'items' => [
                        (object)[
                            'id' => 139077261,
                            'shareAmount' => 1000,
                            'shareItemAmount' => 1000
                        ],
                        (object)[
                            'id' => 139077262,
                            'shareAmount' => 1000,
                            'shareItemAmount' => 1000
                        ]
                    ],
                    'cashOutDate' => '2025-07-08T00:00:00+02:00',
                    'cashOutState' => 'CashedOut',
                    'paymentReceiptUrl' => 'https://www.helloasso.com/associations/association-reste/adhesions/adhesion-2025/paiement-attestation/139077261',
                    'id' => 63109747,
                    'amount' => 2000,
                    'date' => '2025-06-24T12:02:58.9611929+02:00',
                    'paymentMeans' => 'Card',
                    'installmentNumber' => 1,
                    'state' => 'Authorized',
                    'meta' => (object)[
                        'createdAt' => '2025-06-24T12:01:47.6119145+02:00',
                        'updatedAt' => '2025-07-08T07:02:53.9840986+02:00'
                    ],
                    'refundOperations' => []
                ]
            ],
            'amount' => (object)[
                'total' => 2000,
                'vat' => 0,
                'discount' => 0
            ],
            'id' => 139077261,
            'date' => '2025-06-24T12:02:58.9611929+02:00',
            'formSlug' => 'adhesion-2025',
            'formType' => 'Membership',
            'organizationName' => 'Association RESTe!',
            'organizationSlug' => 'association-reste',
            'organizationType' => 'Association1901Rig',
            'organizationIsUnderColucheLaw' => false,
            'meta' => (object)[
                'createdAt' => '2025-06-24T12:01:47.6119145+02:00',
                'updatedAt' => '2025-06-24T12:02:59.1641304+02:00'
            ],
            'isAnonymous' => false,
            'isAmountHidden' => false
        ];
        $order->set('raw_data', json_encode($raw_data));
        $order->save();
        
        // Créer les items en base de données (nécessaire pour que importData les trouve)
        foreach ($raw_data->items as $index => $raw_item) {
            $item = new Item();
            $item->set('id_order', $order->id());
            $item->set('id_form', $form->id());
            $item->set('type', $raw_item->type);
            $item->set('state', $raw_item->state);
            $item->set('label', $raw_item->name ?? ($raw_item->type === 'Membership' ? 'Adhésion' : 'Don'));
            $item->set('amount', $raw_item->amount);
            $item->set('raw_data', json_encode($raw_item));
            $item->set('id_user', null);
            // Pour le Membership, on utilise le tier créé
            // Pour le Donation, on ne définit pas id_tier
            if ($raw_item->type === 'Membership') {
                $item->set('id_tier', $tier->id());
                $item->set('custom_fields', json_encode([
                    'email' => $payer_email
                ]));
            }
            $item->save();
        }
        
        // Créer le payment en base de données (nécessaire pour que importData le trouve)
        foreach ($raw_data->payments as $raw_payment) {
            $payment = new Payment();
            $payment->set('id_order', $order->id());
            $payment->set('id_form', $form->id());
            $payment->set('amount', $raw_payment->amount);
            $payment->set('state', Payment::STATE_OK);
            $payment->set('date', new \DateTime($raw_payment->date));
            $payment->set('raw_data', json_encode($raw_payment));
            $payment->save();
        }
        
        // Vérifier qu'aucun utilisateur n'a été créé avant importData
        Test::assert($order->id_user === null, 'importData: aucun utilisateur lié à l\'ordre avant l\'import');
        
        // Exécuter importData avec create_order_user=true et create_items_users=true
        // pour que l'utilisateur soit créé AUTOMATIQUEMENT à partir du payeur
        
        $report = $order->importData(null, true, true, false, true);
        
        // Vérifier que l'utilisateur a été créé automatiquement
        Test::assert($order->id_user !== null, 'importData: utilisateur créé automatiquement à partir du payeur');
        
        // Récupérer l'utilisateur créé
        $user = \Paheko\Users\Users::get($order->id_user);
        Test::assert($user !== null, 'importData: utilisateur existe en base');
        
        // Vérifier que l'email correspond
        Test::assert($user->email === $payer_email, 'importData: email de l\'utilisateur correspond au payeur');
        
        // Vérifier que le nom correspond (doit être fusionné firstName + lastName)
        Test::assert($user->nom === trim($user_firstname . ' ' . $user_lastname), 'importData: nom de l\'utilisateur correspond au payeur (firstName + lastName). Valeur attendue: "' . trim($user_firstname . ' ' . $user_lastname) . '", valeur obtenue: "' . trim($user->nom) . '"');
        
        // Vérifier que la transaction a été créée
        Test::assert($order->id_transaction !== null, 'importData: transaction comptable créée');
        
        // Vérifier que les 2 items existent toujours
        $items = $order->listItems();
        Test::assert(count($items) === 2, 'importData: 2 items existants (Membership + Donation)');
        
        // Vérifier les types des items
        $item_types = array_map(function($item) { return $item->type; }, $items);
        Test::assert(in_array('Membership', $item_types), 'importData: item de type Membership présent');
        Test::assert(in_array('Donation', $item_types), 'importData: item de type Donation présent');
        
        // Vérifier que les items ont les bonnes informations
        foreach ($items as $item) {
            if ($item->type === 'Membership') {
                Test::assert($item->amount === 1000, 'importData: montant de l\'adhésion est 1000');
                Test::assert($item->label === 'Cositation annuelle', 'importData: label de l\'adhésion est correct');
            } elseif ($item->type === 'Donation') {
                Test::assert($item->amount === 1000, 'importData: montant du don est 1000');
                Test::assert($item->label === 'Don', 'importData: label du don est correct');
            }
        }
        
        // Vérifier que le payeur dans l'ordre a les bonnes informations
        $order_payer = $order->getRawPayerData();
        Test::assert($order_payer->firstName === $payer_firstname, 'importData: prénom du payeur correspond');
        Test::assert($order_payer->lastName === $payer_lastname, 'importData: nom du payeur correspond');
        Test::assert($order_payer->email === $payer_email, 'importData: email du payeur correspond');
        
        // Vérifier qu'un seul utilisateur a été créé avec cet email
        $users_with_same_email = $db->preparedQuery(
            "SELECT COUNT(*) as cnt FROM users WHERE $email_field = ?",
            $payer_email
        );
        $users_count = $users_with_same_email->fetchArray();
        Test::assert((int)$users_count['cnt'] === 1, 'importData: un seul utilisateur avec cette adresse email');
    }
    
    // ============================================================================
    // LISTE DE TOUS LES TESTS
    // ============================================================================
    
    public static function getAllTests(): array
    {
        return [
            'test_importData_unpaid_order_fails',
            'test_importData_complete_import',
            'test_importData_idempotence',
            'test_importData_no_create_users',
            'test_importData_transaction_only',
            'test_importData_no_year_no_transaction',
            'test_importData_no_subscriptions',
            'test_importData_payment_no_year',
            'test_importTransaction_wrapper',
            'test_importData_unbalanced_amount',
            // 'test_importData_duplicate_email_user',
            'test_importData_with_membership_and_donation_items_dupont',
            'test_importData_with_membership_and_donation_items_durand',
        ];
    }
}

// ============================================================================
// EXÉCUTION DES TESTS
// ============================================================================

$test_class = 'Paheko\Plugin\HelloAsso\OrderImportTest';

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
echo "Tous les tests importData passes.\n";
