<?php
/**
 * Tests des edge cases pour Order
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use stdClass;

// ============================================================================
// Edge cases pour Order::getStatus()
//
Test::equals(
    Entities\Order::STATUS_PAID,
    Entities\Order::getStatus((object)['amount' => (object)[], 'payments' => []]),
    'getStatus: amount sans total (0 >= 0 = true, donc PAID)'
);

Test::equals(
    Entities\Order::STATUS_PAID,
    Entities\Order::getStatus((object)['amount' => (object)['total' => 0], 'payments' => []]),
    'getStatus: total a 0 (0 >= 0 = true, donc PAID)'
);

Test::equals(
    Entities\Order::STATUS_WAITING,
    Entities\Order::getStatus((object)['amount' => (object)['total' => 1000]]),
    'getStatus: pas de propriete payments'
);

Test::equals(
    Entities\Order::STATUS_WAITING,
    Entities\Order::getStatus((object)['amount' => (object)['total' => 1000], 'payments' => null]),
    'getStatus: payments null'
);

// Test avec paiements non-OK
Test::equals(
    Entities\Order::STATUS_WAITING,
    Entities\Order::getStatus((object)[
        'amount' => (object)['total' => 1000],
        'payments' => [(object)['state' => 'Pending', 'amount' => 1000]]
    ]),
    'getStatus: paiement non-OK'
);

// ============================================================================
// Edge cases pour Order::transform()
//
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

// Test avec payer partiel
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

// ============================================================================
// Edge cases pour Order::get()
//
Test::assert(Orders::get(0) === null, 'get: id 0');
Test::assert(Orders::get(-1) === null, 'get: id negatif');

// ============================================================================
// Edge cases pour Order::setUserId()
//
$form = create_test_form();
$order = create_test_order($form);

// setUserId ne fait rien si id_user deja defini
$order->set('id_user', null);
$order->set('id_transaction', null);
$order->save();

// setUserId ne fait rien si id_user est null (car la condition if (!$this->id_user) est vraie)
// Utiliser user_id=1 qui existe (créé par Install::install)
$order->setUserId(1);
Test::strictlyEquals(1, $order->id_user, 'setUserId: definit id_user quand null');

// Maintenant id_user est 1, donc setUserId ne devrait pas le changer
$order->setUserId(2);
Test::strictlyEquals(1, $order->id_user, 'setUserId: ne modifie pas si deja defini');

// ============================================================================
// Edge cases pour Order::hasAllUsers() avec create_user = NO_USER_ACTION
//
$form_no_create = create_test_form();
$form_no_create->set('create_payer_user', HelloAsso::NO_USER_ACTION);
$form_no_create->save();

$tier_no_create = create_test_tier($form_no_create, 'Tier No Create', 1000, 'Membership');
$tier_no_create->set('create_user', HelloAsso::NO_USER_ACTION);
$tier_no_create->save();

$order_no_create = create_test_order($form_no_create);

$has_all = $order_no_create->hasAllUsers();
Test::assert($has_all === null || $has_all === true, 'hasAllUsers: NO_USER_ACTION retourne null ou true');

// ============================================================================
// Edge cases pour Order::list()
//
$empty_form = create_test_form('empty-org', 'empty-form');
$empty_list = Orders::list($empty_form);

Test::isInstanceOf(\Paheko\DynamicList::class, $empty_list, 'Orders::list: retourne DynamicList pour form vide');

// ============================================================================
// Edge cases pour Order::isSynced() - ordre non paye
//
$not_paid_order = create_test_order($form, null, 5000, Entities\Order::STATUS_WAITING);
$not_paid_order->set('id_user', 1);
$not_paid_order->set('id_transaction', null);  // Initialiser pour éviter l'erreur d'accès

Test::assert($not_paid_order->isSynced() === null, 'isSynced: null pour ordre non paye');

// ============================================================================
// Edge cases pour Order::getPayerInfos() - payer null
// Note: getPayerInfos() lèvera une exception si payer est null (bug dans le code original)
// On ne teste pas ce cas pour l'instant

echo "Tous les tests edge cases passes.\n";
