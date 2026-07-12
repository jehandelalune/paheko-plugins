<?php
/**
 * Helpers pour les tests du plugin helloasso
 */

namespace Paheko\Plugin\HelloAsso;

use Paheko\DB;
use Paheko\Accounting\Charts;
use Paheko\Entities\Accounting\Year;
use DateTime;

/**
 * Appelle une méthode protégée ou privée de manière statique
 */
if (!function_exists('Paheko\Plugin\HelloAsso\call_protected_method')) {
    function call_protected_method(string $class, string $method, array $args = []) {
        $reflection = new \ReflectionMethod($class, $method);
        // setAccessible() est déprécié en PHP 8.5+ mais nécessaire pour les versions antérieures
        if (PHP_VERSION_ID < 80500) {
            $reflection->setAccessible(true);
        }
        return $reflection->invoke(null, ...$args);
    }
}

/**
 * Crée un Form de test avec des noms uniques
 */
function create_test_form(string $org_slug = '', string $form_slug = '', string $type = 'Membership'): Entities\Form
{
    static $counter = 0;
    $counter++;
    
    $org_slug = $org_slug ?: 'test-org-' . $counter;
    $form_slug = $form_slug ?: 'test-form-' . $counter;
    
    $form = new Entities\Form;
    $form->set('org_name', 'Test Organization ' . $counter);
    $form->set('org_slug', $org_slug);
    $form->set('name', 'Test Form ' . $counter);
    $form->set('slug', $form_slug);
    $form->set('type', $type);
    $form->set('state', 'Active');
    $form->set('raw_data', json_encode((object)[]));
    $form->set('create_payer_user', HelloAsso::NO_USER_ACTION);
    $form->save();
    return $form;
}

/**
 * Crée un Tier de test
 */
function create_test_tier(Entities\Form $form, string $label = 'Test Tier', int $amount = 1000, string $type = 'Membership'): Entities\Tier
{
    $tier = new Entities\Tier;
    $tier->set('id_form', $form->id());
    $tier->set('label', $label);
    $tier->set('amount', $amount);
    $tier->set('type', $type);
    $tier->set('create_user', HelloAsso::NO_USER_ACTION);
    $tier->save();
    return $tier;
}

/**
 * Crée un Order de test
 */
function create_test_order(
    Entities\Form $form,
    ?int $id = null,
    int $amount = 1000,
    int $status = Entities\Order::STATUS_PAID,
    string $person = 'Test User'
): Entities\Order {
    $order = new Entities\Order;
    if ($id !== null) {
        $order->set('id', $id);
    }
    $order->set('id_form', $form->id());
    $order->set('date', new \DateTime(date('Y-m-d')));
    $order->set('person', $person);
    $order->set('amount', $amount);
    $order->set('status', $status);
    $order->set('raw_data', json_encode((object)[
        'id' => $id ?? 123,
        'amount' => (object)['total' => $amount],
        'payer' => (object)['firstName' => 'Test', 'lastName' => 'User', 'email' => 'test@example.com'],
        'payments' => [],
        'items' => []
    ]));
    
    $order->save();
    return $order;
}

/**
 * Crée un Item de test
 */
function create_test_item(Entities\Order $order, ?Entities\Tier $tier = null, int $amount = 1000): Entities\Item
{
    $item = new Entities\Item;
    $item->set('id_order', $order->id());
    $item->set('id_form', $order->form()->id());
    if ($tier) {
        $item->set('id_tier', $tier->id());
    }
    $item->set('type', 'Membership');
    $item->set('state', 'Processed');
    $item->set('label', 'Test Item');
    $item->set('amount', $amount);
    $item->set('raw_data', '{}');
    $item->set('custom_fields', null);
    $item->save();
    return $item;
}

/**
 * Crée un Payment de test
 */
function create_test_payment(Entities\Order $order, int $amount = 1000, string $state = Entities\Payment::STATE_OK): Entities\Payment
{
    $payment = new Entities\Payment;
    $payment->set('id_order', $order->id());
    $payment->set('id_form', $order->form()->id());
    $payment->set('amount', $amount);
    $payment->set('state', $state);
    $payment->set('date', new \DateTime(date('Y-m-d')));
    $payment->set('raw_data', '{}');
    $payment->save();
    return $payment;
}

/**
 * Initialise un exercice comptable pour les tests
 */
function setup_accounting_year(): Year
{
    Charts::installCountryDefault('FR');
    $chart_id = Charts::getOrInstall('fr_pca_2018');

    $year = new Year;
    $year->import(['id_chart' => $chart_id, 'label' => 'Test Year', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
    $year->save();
    return $year;
}

/**
 * Configure HelloAsso avec des comptes par défaut
 * Crée une instance mock car le plugin n'est pas installé dans la base de test
 */
function setup_helloasso_config(): HelloAsso
{
    // Créer une instance de HelloAsso sans passer par getInstance()
    // car le plugin n'est pas installé dans la base de test
    $ha = new \ReflectionClass(HelloAsso::class);
    $instance = $ha->newInstanceWithoutConstructor();
    
    $config = new \stdClass();
    $config->payment_account_code = '756';
    $config->donation_account_code = '860';
    $config->provider_account_code = '530';
    
    // Configuration du mapping des champs pour createUser
    $config->fields_map = (object)[
        'firstName' => 'nom',
        'lastName' => 'nom',
        'email' => 'email'
    ];
    $config->merge_names_order = HelloAsso::MERGE_NAMES_FIRST_LAST;
    $config->match_email_field = false;
    
    // Utiliser reflection pour accéder à la propriété protégée
    $config_property = $ha->getProperty('config');
    $config_property->setValue($instance, $config);
    
    // Configurer aussi le singleton pour que HelloAsso::getInstance() retourne notre instance mock
    $instance_property = $ha->getProperty('_instance');
    $instance_property->setValue(null, $instance);
    
    return $instance;
}
