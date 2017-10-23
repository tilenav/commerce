<?php

namespace Drupal\commerce_payment\Entity;

use Drupal\commerce\CommerceSinglePluginCollection;
use Drupal\commerce\ConditionGroup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the payment gateway entity class.
 *
 * @ConfigEntityType(
 *   id = "commerce_payment_gateway",
 *   label = @Translation("Payment gateway"),
 *   label_collection = @Translation("Payment gateways"),
 *   label_singular = @Translation("payment gateway"),
 *   label_plural = @Translation("payment gateways"),
 *   label_count = @PluralTranslation(
 *     singular = "@count payment gateway",
 *     plural = "@count payment gateways",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_payment\PaymentGatewayListBuilder",
 *     "storage" = "Drupal\commerce_payment\PaymentGatewayStorage",
 *     "form" = {
 *       "add" = "Drupal\commerce_payment\Form\PaymentGatewayForm",
 *       "edit" = "Drupal\commerce_payment\Form\PaymentGatewayForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce_payment_gateway",
 *   config_prefix = "commerce_payment_gateway",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "status",
 *     "plugin",
 *     "configuration",
 *     "conditions",
 *     "conditionOperator",
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/payment-gateways/add",
 *     "edit-form" = "/admin/commerce/config/payment-gateways/manage/{commerce_payment_gateway}",
 *     "delete-form" = "/admin/commerce/config/payment-gateways/manage/{commerce_payment_gateway}/delete",
 *     "collection" =  "/admin/commerce/config/payment-gateways"
 *   }
 * )
 */
class PaymentGateway extends ConfigEntityBase implements PaymentGatewayInterface {

  /**
   * The payment gateway ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The payment gateway label.
   *
   * @var string
   */
  protected $label;

  /**
   * The payment gateway weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The conditions.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The condition operator.
   *
   * @var string
   */
  protected $conditionOperator = 'AND';

  /**
   * The plugin collection that holds the payment gateway plugin.
   *
   * @var \Drupal\commerce\CommerceSinglePluginCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->plugin = $plugin_id;
    $this->configuration = [];
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration) {
    $this->configuration = $configuration;
    $this->pluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions() {
    $plugin_manager = \Drupal::service('plugin.manager.commerce_condition');
    $conditions = [];
    foreach ($this->conditions as $condition) {
      $conditions[] = $plugin_manager->createInstance($condition['plugin'], $condition['configuration']);
    }
    return $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionOperator() {
    return $this->conditionOperator;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditionOperator($condition_operator) {
    $this->conditionOperator = $condition_operator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    $conditions = $this->getConditions();
    if (!$conditions) {
      // Payment gateways without conditions always apply.
      return TRUE;
    }
    $order_conditions = array_filter($conditions, function ($condition) {
      /** @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface $condition */
      return $condition->getEntityTypeId() == 'commerce_order';
    });
    $order_conditions = new ConditionGroup($order_conditions, $this->getConditionOperator());

    return $order_conditions->evaluate($order);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // Invoke the setters to clear related properties.
    if ($property_name == 'plugin') {
      $this->setPluginId($value);
    }
    elseif ($property_name == 'configuration') {
      $this->setPluginConfiguration($value);
    }
    else {
      return parent::set($property_name, $value);
    }
  }

  /**
   * Gets the plugin collection that holds the payment gateway plugin.
   *
   * Ensures the plugin collection is initialized before returning it.
   *
   * @return \Drupal\commerce\CommerceSinglePluginCollection
   *   The plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $plugin_manager = \Drupal::service('plugin.manager.commerce_payment_gateway');
      $this->pluginCollection = new CommerceSinglePluginCollection($plugin_manager, $this->plugin, $this->configuration, $this->id);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = \Drupal::service('entity_type.manager')
      ->getStorage('commerce_payment_method');
    
    foreach ($entities as $payment_gateway) {
      $payment_methods = $payment_method_storage->loadByPaymentGateway($payment_gateway);
      if (empty($payment_methods)) {
        continue;
      }
      if (count($payment_methods) < 50) {
        // If there is less than 50 payment methods, we delete them straight away.
        $payment_method_storage->delete($payment_methods);
      } else {
        // Else we queue them for deletion.
        $queue = \Drupal::queue('payment_methods_delete_queue');
        //$queue->createQueue();
        foreach (array_chunk($payment_methods, 50) as $payment_methods_chunk) {
          $queue->createItem($payment_methods_chunk);
        }
      }
    }
  }

}
