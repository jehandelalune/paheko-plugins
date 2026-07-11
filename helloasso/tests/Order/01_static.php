<?php
/**
 * Tests des methodes statiques de Order
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use stdClass;

// ============================================================================
// TEST: Order::getStatus()
//
Test::equals(
    Entities\Order::STATUS_PAID,
    Entities\Order::getStatus((object)[
        'amount' => (object)['total' => 1000],
        'payments' => [(object)['state' => Entities\Payment::STATE_OK, 'amount' => 1000]]
    ]),
    'getStatus: paiement complet'
);

Test::equals(
    Entities\Order::STATUS_WAITING,
    Entities\Order::getStatus((object)[
        'amount' => (object)['total' => 1000],
        'payments' => [(object)['state' => Entities\Payment::STATE_OK, 'amount' => 500]]
    ]),
    'getStatus: paiement partiel'
);

Test::equals(
    Entities\Order::STATUS_WAITING,
    Entities\Order::getStatus((object)[
        'amount' => (object)['total' => 1000],
        'payments' => []
    ]),
    'getStatus: aucun paiement'
);

Test::equals(
    Entities\Order::STATUS_PAID,
    Entities\Order::getStatus((object)[
        'amount' => (object)['total' => 0],
        'payments' => []
    ]),
    'getStatus: montant a 0'
);

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

// ============================================================================
// TEST: Order::get()
//
$form = create_test_form();
$order = create_test_order($form, 999);

Test::assert(Orders::get(999) !== null, 'Order::get: ordre existant');
Test::isInstanceOf(Entities\Order::class, Orders::get(999), 'Order::get: bon type');
Test::assert(Orders::get(999999) === null, 'Order::get: ordre inexistant');

// ============================================================================
// TEST: Order::transform()
//
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

// Cas avec champs manquants
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

// ============================================================================
// TEST: Orders::list()
//
$form2 = create_test_form('org2', 'form2');
Test::isInstanceOf(\Paheko\DynamicList::class, Orders::list($form2), 'Orders::list: retourne DynamicList');

echo "Tous les tests statiques passes.\n";
