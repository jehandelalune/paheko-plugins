<?php
/**
 * Tests des methodes d'instance de Order
 */

namespace Paheko\Plugin\HelloAsso;

use KD2\Test;
use Paheko\DB;

// ============================================================================
// Configuration
$form = create_test_form();
$tier = create_test_tier($form, 'Adhesion Test', 1000);

// ============================================================================
// TEST: Order::form() - cache
//
$order = create_test_order($form, 100);

$form1 = $order->form();
$form2 = $order->form();

Test::assert($form1 === $form2, 'form(): meme instance (cache)');
Test::strictlyEquals($form->id(), $form1->id(), 'form(): bon ID');

// ============================================================================
// TEST: Order::tier() - cache
//
$tier1 = $order->tier($tier->id());
$tier2 = $order->tier($tier->id());

Test::assert($tier1 === $tier2, 'tier(): meme instance (cache)');
Test::assert($tier1 !== null, 'tier(): tier existe');
Test::strictlyEquals($tier->id(), $tier1->id(), 'tier(): bon ID');

// ============================================================================
// TEST: Order::getLinkedUserName()
// Note: on ne peut pas tester directement car id_user n'est pas initialise
// On teste avec un user defini
$order_with_user = create_test_order($form, 102);
$order_with_user->set('id_user', 1);
Test::assert($order_with_user->getLinkedUserName() !== null, 'getLinkedUserName(): pas null quand id_user defini');

// ============================================================================
// TEST: Order::getRawPayerData()
//
$order_with_payer = create_test_order($form, 101);
$raw_data = json_decode($order_with_payer->raw_data);
$raw_data->payer = (object)['firstName' => 'Alice', 'lastName' => 'Smith', 'email' => 'alice@example.com'];
$order_with_payer->set('raw_data', json_encode($raw_data));
$order_with_payer->save();

$payer_data = $order_with_payer->getRawPayerData();
Test::assert($payer_data !== null, 'getRawPayerData(): pas null');
Test::strictlyEquals('Alice', $payer_data->firstName, 'getRawPayerData(): firstName');
Test::strictlyEquals('Smith', $payer_data->lastName, 'getRawPayerData(): lastName');

// ============================================================================
// TEST: Order::getPayerInfos()
//
$infos = $order_with_payer->getPayerInfos();
Test::isArray($infos, 'getPayerInfos(): retourne array');
// Les clés sont en français car PAYER_FIELDS mappe firstName -> Prénom
Test::hasKey('Prénom', $infos, 'getPayerInfos(): a Prénom');
Test::strictlyEquals('Alice', $infos['Prénom'], 'getPayerInfos(): valeur Prénom');

// ============================================================================
// TEST: Order::listItems() / Order::getItem()
//
$items = $order->listItems();
Test::isArray($items, 'listItems(): retourne array');
Test::assert(count($items) === 0, 'listItems(): vide initialement');

// Ajouter un item
$item = create_test_item($order, $tier, 1000);

$items = $order->listItems();
Test::assert(count($items) === 1, 'listItems(): 1 item apres ajout');

$found_item = $order->getItem($item->id());
Test::assert($found_item !== null, 'getItem(): trouve item existant');
Test::strictlyEquals($item->id(), $found_item->id(), 'getItem(): bon ID');

// ============================================================================
// TEST: Order::listPayments()
//
$payments = $order->listPayments();
Test::isArray($payments, 'listPayments(): retourne array');
Test::assert(count($payments) === 0, 'listPayments(): vide initialement');

// Ajouter un paiement
$payment = create_test_payment($order, 1000);

$payments = $order->listPayments();
Test::assert(count($payments) === 1, 'listPayments(): 1 paiement apres ajout');

// ============================================================================
// TEST: Order::setUserId() - idempotence
// Note: on doit initialiser id_user et id_transaction pour éviter les erreurs d'accès
$order->set('id_user', null);
$order->set('id_transaction', null);
$order->save();

// Maintenant on peut utiliser setUserId
$order->setUserId(1);
Test::strictlyEquals(1, $order->id_user, 'setUserId: premier appel');

$order->setUserId(2);
Test::strictlyEquals(1, $order->id_user, 'setUserId: idempotent (toujours 1)');

// ============================================================================
// TEST: Order::tier() avec tier inexistant
//
$non_existent_tier = $order->tier(999999);
Test::assert($non_existent_tier === null, 'tier(): retourne null pour tier inexistant');

echo "Tous les tests d'instance passes.\n";
