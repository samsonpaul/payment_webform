<?php
namespace Drupal\payment_webform;

class PaymentWebformTestQueueWebTestCase extends PaymentWebTestCase {

  static function getInfo() {
    return array(
      'description' => '',
      'name' => 'Queue CRUD and Webform integration',
      'group' => 'Payment for Webform',
    );
  }

  function setUp(array $modules = array()) {
    parent::setUp($modules + array('payment_webform'));
  }

  function testQueueCRUD() {
    $payment_method = $this->paymentMethodCreate(1, payment_method_controller_load('PaymentMethodControllerUnavailable'));
    $payment = $this->paymentCreate(2, $payment_method);
    $payment->setStatus(new PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
    entity_save('payment', $payment);
    $cid = 1;

    // Test queueing a payment.
    payment_webform_insert($cid, $payment->pid);
    $count = db_query("SELECT COUNT(1) FROM {payment_webform} WHERE cid = :cid AND pid = :pid", array(
      ':cid' => $cid,
      ':pid' => $payment->pid,
    ))->fetchField();
    $this->assertTrue($count, 'A payment is saved to the queue correctly.');

    // Test loading a queued payment.
    $pid = payment_webform_load($cid, $payment->uid);
    $this->assertTrue((bool) $pid, 'A queued payment is loaded correctly.');
    $pid = payment_webform_load(2, $payment->uid);
    $this->assertFalse($pid, 'Loading a queued payment using the wrong CID fails.');
    $pid = payment_webform_load($cid, $payment->uid + 1);
    $this->assertFalse($pid, 'Loading a queued payment using the wrong UID fails.');

    // Test deleting a payment from the queue.
    payment_webform_delete_by_pid($payment->pid);
    $count = db_query("SELECT COUNT(1) FROM {payment_webform} WHERE pid = :pid", array(
      ':pid' => $payment->pid,
    ))->fetchField();
    $this->assertFalse($count, 'A payment can be deleted from the queue by PID.');
    payment_webform_insert($cid, $payment->pid);
    payment_webform_delete_by_cid($cid);
    $count = db_query("SELECT COUNT(1) FROM {payment_webform} WHERE cid = :cid", array(
      ':cid' => $cid,
    ))->fetchField();
    $this->assertFalse($count, 'A payment can be deleted from the queue by CID.');
  }

  function testQueueWebformImplementation() {
    // Create a webform node.
    $node = $this->drupalCreateNode(array(
      'type' => 'webform',
    ));

    // Create a component.
    $component = array(
      'nid' => $node->nid,
      'pid' => 0,
      'form_key' => 'foo',
      'name' => 'foo',
      'type' => 'payment_webform',
      'extra' => array(),
      'mandatory' => TRUE,
      'weight' => 0,
    );
    $cid = webform_component_insert($component);

    // Create two payments
    $payment_method = $this->paymentMethodCreate(1, payment_method_controller_load('PaymentMethodControllerUnavailable'));
    foreach (range(1, 2) as $pid) {
      $payment = $this->paymentCreate(2, $payment_method);
      $payment->setStatus(new PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
      entity_save('payment', $payment);
      payment_webform_insert($cid, $pid);
    }

    // Test response to payment deletion.
    entity_delete('payment', 1);
    $pid = payment_webform_load($cid, 2);
    $this->assertNotEqual($pid, 1, 'When deleting a payment, it is removed from the queue as well.');

    // Test response to component deletion.
    webform_component_delete($node, $component);
    $pid = payment_webform_load($cid, 2);
    $this->assertNotEqual($pid, 2, 'When deleting a component, all payments associated with it are removed from the queue.');
  }
}
