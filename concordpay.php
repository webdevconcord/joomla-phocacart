<?php
/**
 * PHP version 7.4.27
 *
 * @category   Class
 * @package    Joomla
 * @extension  Phoca Extension
 * @subpackage ConcordPay
 * @author     MustPay <info@mustpay.tech>
 * @copyright  2022 ConcordPay
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://concordpay.concord.ua
 * @since      3.8.0
 */

use Joomla\Registry\Registry;

defined('_JEXEC') or die;
require_once 'ConcordPayApi.php';
jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.file');
jimport('joomla.html.parameter');

// Jimport('joomla.log.log');
// JLog::addLogger( array('text_file' => 'com_phocacart_error_log.php'), JLog::ALL, array('com_phocacart'));
// phocacartimport('phocacart.utils.log');

JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');

class plgPCPConcordPay extends JPlugin
{
	/**
	 * @var string
	 *
	 * @since 3.8.0
	 */
	protected $name = 'concordpay';

	/**
	 * @var ConcordPayApi
	 * @since 3.8.0
	 */
	protected $concordpay;

	/**
	 * Constructor class.
	 *
	 * @param   Joomla\Event\Dispatcher $subject Dispatcher object
	 * @param   array                   $config  Plugin config
	 *
	 * @since 3.8.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * Proceed to payment - some method do not have proceed to payment gateway like e.g. cash on delivery.
	 *
	 * @param   integer $proceed   Proceed or not proceed to payment gateway
	 * @param   string  $message   Custom message array set by plugin to override standard messages made by component
	 * @param   array   $eventData Event data
	 * @return  boolean  True
	 *
	 * @since 3.8.0
	 */
	public function onPCPbeforeProceedToPayment(&$proceed, &$message, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] !== $this->name)
		{
			return false;
		}

		$proceed = 1;

		return true;
	}

	/**
	 * Payment Canceled.
	 *
	 * @param   integer $mid       ID of message - can be set in PCPbeforeSetPaymentForm
	 * @param   array   $message   Custom message array set by plugin to override standard messages made by component
	 * @param   array   $eventData Event data
	 *
	 * @return  boolean  True
	 *
	 * @since 3.8.0
	 */
	public function onPCPafterCancelPayment($mid, &$message, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] != $this->name)
		{
			return false;
		}

		if (!$message)
		{
			$message = array();
		}

		return true;
	}

	/**
	 * Generate payment form.
	 *
	 * @param   string   $form      Form
	 * @param   Registry $paramsC   Joomla config
	 * @param   Registry $params    Plugin config
	 * @param   array    $order     Order data
	 * @param   array    $eventData Event data
	 *
	 * @return boolean
	 *
	 * @since 3.8.0
	 */
	public function onPCPbeforeSetPaymentForm(&$form, $paramsC, $params, $order, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] !== $this->name)
		{
			return false;
		}

		$document = JFactory::getDocument();
		$this->concordpay = new ConcordPayApi($params->get('secret_key', ''));

		if (!$params)
		{
			$paramsC = PhocacartUtils::getComponentParameters();
		}

		$price = new PhocacartPrice;

		/*
		 !IMPORTANT =================================================================================
		 Items price * Quantities = Subtotal
		 -  Discounts (Product, Cart, Reward Points) and Coupons
		 +  Shipping Costs
		 +  Payment Costs
		 -+ Currency Rounding (in ConcordPay the plus is new item, the minus is discount)
		 -+ Total Amount Rounding (in ConcordPay the plus is new item, the minus is discount)
		 ============================================================================================
		*/

		$invoiceNr = PhocacartOrder::getInvoiceNumber($order['common']->id, $order['common']->date, $order['common']->invoice_number);
		$orderNr = PhocacartOrder::getOrderNumber($order['common']->id, $order['common']->date, $order['common']->order_number);
		$itemName = JText::_('COM_PHOCACART_ORDER') . ': ' . $orderNr;

		if (isset($order['common']->payment_id) && (int) $order['common']->payment_id > 0)
		{
			$paymentId = (int) $order['common']->payment_id;
		}
		else
		{
			$paymentId = 0;
		}

		// Payment data.
		$data = [];
		$data['merchant_id'] = $params->get('merchant_id', '');
		$amount = $this->getAmount($order, $price, $paramsC);

		$data['amount']       = $amount['cartBrutto'];
		$data['order_id']     = $order['common']->order_number_id;
		$data['currency_iso'] = $order['common']->currency_code;

		$data['client_first_name'] = $order['bas']['b']['name_first'] ?? '';
		$data['client_last_name'] = $order['bas']['b']['name_last'] ?? '';

		$data['email'] = $order['bas']['b']['email'] ?? '';
		$data['phone'] = $order['bas']['b']['phone'] ?? '';
		$data['description'] = JText::_('PLG_PCP_CONCORDPAY_ORDER_DESC') . ' ' . htmlspecialchars($_SERVER['HTTP_HOST']) . ', '
			. $data['client_first_name'] . ' ' . $data['client_last_name']
			. ($data['phone'] ? (', ' . $data['phone'] . '.') : '.');

		$baseUrl = JURI::root(false) . 'index.php?option=com_phocacart&view=response&task=response.paymentrecieve&type=concordpay&mid=1';

		$data['approve_url']  = $baseUrl . '&result=success';
		$data['decline_url']  = $baseUrl . '&result=fail';
		$data['cancel_url']   = $baseUrl . '&result=cancel';
		$data['callback_url'] = JURI::root(false) . 'index.php?option=com_phocacart&view=response&task=response.paymentnotify&type=concordpay'
			. '&pid=' . (int) $paymentId . '&tmpl=component';

		$data['signature'] = $this->concordpay->getRequestSignature($data);

		// Payment form.
		$f = array();
		$f[] = '<form name="phCartPayment" id="phCartPayment" method="POST" action="' . ConcordPayApi::getApiUrl() . '" >';
		$f[] = '<input type="hidden" name="operation"    value="Purchase" />';
		$f[] = '<input type="hidden" name="merchant_id"  value="' . $data['merchant_id'] . '" />';
		$f[] = '<input type="hidden" name="amount"       value="' . $data['amount'] . '" />';
		$f[] = '<input type="hidden" name="order_id"     value="' . $data['order_id'] . '" />';
		$f[] = '<input type="hidden" name="currency_iso" value="' . $data['currency_iso'] . '" />';
		$f[] = '<input type="hidden" name="description"  value="' . $data['description'] . '" />';
		$f[] = '<input type="hidden" name="approve_url"  value="' . $data['approve_url'] . '" />';
		$f[] = '<input type="hidden" name="decline_url"  value="' . $data['decline_url'] . '" />';
		$f[] = '<input type="hidden" name="cancel_url"   value="' . $data['cancel_url'] . '" />';
		$f[] = '<input type="hidden" name="callback_url" value="' . $data['callback_url'] . '" />';
		$f[] = '<input type="hidden" name="signature"    value="' . $data['signature'] . '" />';

		// Statistics.
		$f[] = '<input type="hidden" name="client_last_name"  value="' . $data['client_last_name'] . '" />';
		$f[] = '<input type="hidden" name="client_first_name" value="' . $data['client_first_name'] . '" />';
		$f[] = '<input type="hidden" name="email"             value="' . $data['email'] . '" />';
		$f[] = '<input type="hidden" name="phone"             value="' . $data['phone'] . '" />';

		$f[] = '<div class="ph-center">';
		$f[] = '<div>' . JText::_('COM_PHOCACART_ORDER_SUCCESSFULLY_PROCESSED') . '</div>';
		$f[] = '<div>' . JText::_('PLG_PCP_CONCORDPAY_YOU_ARE_NOW_BEING_REDIRECTED_TO_CONCORDPAY') . '</div>';

		$f[] = '<div class="ph-loader"></div>';

		$f[] = '<div>' . JText::_('PLG_PCP_CONCORDPAY_IF_YOU_ARE_NOT_REDIRECTED_WITHIN_A_FEW_SECONDS_PLEASE') . ' ';
		$f[] = '<input type="submit" class="btn btn-primary"
		 value="' . JText::_('PLG_PCP_CONCORDPAY_CLICK_HERE_TO_BE_REDIRECTED_TO_CONCORDPAY') . '" class="button" />';
		$f[] = '</div>';
		$f[] = '</div>';

		$f[] = '</form>';

		$form = implode("\n", $f);

		$js = 'window.onload = function(){window.setTimeout(document.phCartPayment.submit.bind(document.phCartPayment), 1100); };';

		$document->addScriptDeclaration($js);

		/*
		$form2 = str_replace('<', '&lt;', $form);
		$form2 = str_replace('>', '&gt;', $form2);
		$form2 = '<pre><code>'.$form2.'</code></pre>';
		echo $form2;
		*/
		PhocacartLog::add(1, 'Payment - ConcordPay - SENDING FORM TO CONCORDPAY', (int) $order['common']->id, $form);

		return true;
	}

	/**
	 * Get amount information.
	 *
	 * @param   array          $order   Order data
	 * @param   PhocacartPrice $price   Price config
	 * @param   Registry       $paramsC Joomla config
	 *
	 * @return array
	 *
	 * @since 3.8.0
	 */
	protected function getAmount($order, $price, $paramsC)
	{
		$rounding_calculation = $paramsC->get('rounding_calculation', 1);

		// Other currency in order - r = rate
		$r = 1;

		if (isset($order['common']->currency_exchange_rate))
		{
			$r = $order['common']->currency_exchange_rate;
		}

		if (isset($order['common']->payment_id) && (int) $order['common']->payment_id > 0)
		{
			$paymentId = (int) $order['common']->payment_id;
		}
		else
		{
			$paymentId = 0;
		}

		/*
		 There can be difference between cart total amount and payment total amount (because of currency and its rounding)
		 cart total amount (brutto) = (item * quantity) * currency rate
		 payment total amount		= (item * currency rate) * quantity
		*/
		$cartBrutto     = 0; // Total amount (brutto) calculated by cart
		$paymentBrutto  = 0; // Total amount (brutto) calculated by payment method
		$discountAmount = 0; // Sum of all discount values - all MINUS values
		$currencyAmount = 0; // Sum of all currency rounding amounts - all PLUS values

		foreach ($order['total'] as $k => $v)
		{
			if ($v->amount != 0 || $v->amount_currency != 0)
			{
				switch ($v->type)
				{
					// All discounts (MINUS)
					case 'dnetto':
						$paymentBrutto += $price->roundPrice($v->amount * $r);
						$discountAmount += $price->roundPrice(abs($v->amount * $r));
						break;

					// Tax (PLUS)
					case 'tax':
						$paymentBrutto += $price->roundPrice($v->amount * $r);
						$f[] = '';
						break;

					// Payment Method, Shipping Method (PLUS)
					case 'sbrutto':
					case 'pbrutto':
						$paymentBrutto += $price->roundPrice($v->amount * $r);
						$f[] = '';

						break;

					// Rounding (PLUS/MINUS)
					case 'rounding':
						if ($v->amount_currency != 0)
						{
							// Rounding is set in order currency
							if ($v->amount_currency > 0)
							{
								$currencyAmount += round($v->amount_currency, 2, $rounding_calculation);
								$paymentBrutto += round($v->amount_currency, 2, $rounding_calculation);
							}
							elseif ($v->amount_currency < 0)
							{
								$discountAmount += round(abs($v->amount_currency), 2, $rounding_calculation);
								$paymentBrutto += round($v->amount_currency, 2, $rounding_calculation);
							}
						}
						else
						{
							// Rounding is set in default currency
							if ($v->amount > 0 && round(($v->amount * $r), 2, $rounding_calculation) > 0)
							{
								$f[] = '';
								$paymentBrutto += round(($v->amount * $r), 2, $rounding_calculation);
							}
							elseif ($v->amount < 0)
							{
								$discountAmount += round(abs($v->amount * $r), 2, $rounding_calculation);
								$paymentBrutto += round(($v->amount * $r), 2, $rounding_calculation);
							}
						}
						break;

					// Brutto (total amount)
					case 'brutto':
						if ($v->amount_currency != 0)
						{
							// Brutto is set in order currency
							$cartBrutto = $price->roundPrice($v->amount_currency);
						}
						else
						{
							// Brutto is set in default currency
							$cartBrutto = $price->roundPrice($v->amount * $r);
						}
						break;
				}
			}
		}

		return [
			'cartBrutto'     => $cartBrutto,
			'paymentBrutto'  => $paymentBrutto,
			'discountAmount' => $discountAmount,
			'currencyAmount' => $currencyAmount,
		];
	}

	/**
	 * Callback handler.
	 *
	 * @param   int   $pid       Payment plugin ID
	 * @param   array $eventData Event data.
	 *
	 * @return false|void
	 * @throws Exception
	 *
	 * @since 3.8.0
	 */
	public function onPCPbeforeCheckPayment($pid, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] != $this->name)
		{
			return false;
		}

		$this->callbackHandler($pid, $eventData);
	}

	/**
	 * The payment method plugin can decide whether or not to empty the cart when an order is placed.
	 * For example, if the payment gateway returns information about a failed payment,
	 * the cart can remain filled and the customer can try to make the payment again.
	 * However, if the payment method plugin decides not to delete the items in the cart,
	 * then it must use other events to ensure that the cart is deleted. For example, on a successful payment.
	 *
	 * To empty cart:
	 *
	 *  $cart = new PhocacartCart();
	 *    $cart->emptyCart();
	 *  PhocacartUserGuestuser::cancelGuestUser();
	 *
	 * For example in following events:
	 * - PCPafterRecievePayment
	 * - PCPafterCancelPayment
	 * - PCPbeforeCheckPayment
	 * - PCPonPaymentWebhook
	 *
	 * If the cart is not emptied and the user re-orders,
	 * then a new order ID is created - which is generally standard procedure
	 *
	 * @param   string         $form       Form
	 * @param   array          $pluginData Plugin cart config
	 * @param   Registry       $paramsC    Joomla registry
	 * @param   Registry       $params     Plugin config
	 * @param   PhocacartOrder $order      Order object
	 * @param   array          $eventData  Event data
	 *
	 * @return boolean
	 *
	 * @since 3.8.0
	 */
	public function onPCPbeforeEmptyCartAfterOrder(&$form, &$pluginData, $paramsC, $params, $order, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] !== $this->name)
		{
			return false;
		}

		// Uncomment to not empty cart when order is placed
		// $pluginData['emptycart'] = false;

		return true;
	}

	/**
	 * Payment Receive.
	 *
	 * @param   integer $mid       ID of message - can be set in PCPbeforeSetPaymentForm
	 * @param   array   $message   Custom message array set by plugin to override standard messages made by component
	 * @param   array   $eventData Event data.
	 *
	 * @return  boolean  True
	 *
	 * @throws Exception
	 * @since 3.8.0
	 */
	public function onPCPafterRecievePayment($mid, &$message, $eventData)
	{
		if (!isset($eventData['pluginname']) || $eventData['pluginname'] !== $this->name)
		{
			return false;
		}

		// Uncomment to empty cart when PCPafterRecievePayment is reached
		// $cart = new PhocacartCart();
		// $cart->emptyCart();
		// PhocacartUserGuestuser::cancelGuestUser();

		$app = JFactory::getApplication() or die();
		$response = $app->input->get->getArray();

		if (isset($response['result']))
		{
			$result = $response['result'] ?? 'fail';
			$message = $this->processReturn($result);
		}

		if (!$message)
		{
			$message = array();
		}

		return true;
	}

	/**
	 * Callback handler. Update order status.
	 *
	 * @param   string $pid       Payment method ID
	 * @param   array  $eventData Event data
	 *
	 * @return void
	 * @throws Exception
	 *
	 * @since 3.8.0
	 */
	protected function callbackHandler($pid, $eventData)
	{
		$app = JFactory::getApplication() or die();
		$response = $app->input->json->getArray();

		$paymentTemp = new PhocacartPayment;
		$paymentOTemp = $paymentTemp->getPaymentMethod((int) $pid);
		$paramsPaymentTemp = $paymentOTemp->params;

		// Checking for Required Query Parameters.
		if (ConcordPayApi::checkResponseParamsExisting($response))
		{
			throw new \RuntimeException("Error: wrong response data.");
		}

		// Check merchant.
		if ($response['merchantAccount'] !== $paramsPaymentTemp->get('merchant_id'))
		{
			throw new \RuntimeException("Error: wrong merchant.");
		}

		// Check amount.
		$orderObject   = new PhocacartOrderView;
		$orderId       = $response['orderReference'] ? (int) $response['orderReference'] : '';
		$transactionId = $response['transactionId'] ?? '';

		$order = array();

		$order['total']  = $orderObject->getItemTotal($orderId);
		$order['common'] = $orderObject->getItemCommon($orderId);

		$paramsC = PhocacartUtils::getComponentParameters();

		$price   = new PhocacartPrice;
		$amount  = $this->getAmount($order, $price, $paramsC);
		$mcGross = (float) $amount['cartBrutto'];

		if ((float) $response['amount'] !== (float) $amount['cartBrutto'])
		{
			throw new \RuntimeException("Error: wrong amount.");
		}

		// Check currency.
		$currency = $orderObject->getItemCommon($orderId)->currency_code;

		if ($response['currency'] !== $currency)
		{
			throw new \RuntimeException("Error: wrong currency.");
		}

		// Check operation type.
		if (!in_array($response['type'], ConcordPayApi::getAllowedOperationTypes(), true))
		{
			throw new \RuntimeException("Error: wrong operation type.");
		}

		// Check signature.
		$this->concordpay = new ConcordPayApi($paramsPaymentTemp->get('secret_key', ''));
		$signature = $this->concordpay->getResponseSignature($response);

		if ($response['merchantSignature'] !== $signature)
		{
			throw new \RuntimeException("Error: wrong signature.");
		}

		if ($response['transactionStatus'] === ConcordPayApi::TRANSACTION_STATUS_APPROVED)
		{
			// Ordinary payment.
			if ($response['type'] === ConcordPayApi::RESPONSE_TYPE_PAYMENT)
			{
				$newStatus = (int) $paramsPaymentTemp->get('status_approved', 1);

				if (PhocacartOrderStatus::changeStatusInOrderTable($orderId, $newStatus))
				{
					$comment = JText::_('COM_PHOCACART_ORDER_STATUS_CHANGED_BY_PAYMENT_SERVICE_PROVIDER') . '(ConcordPay)';

					$comment .= "\n" . JText::_('COM_PHOCACART_INFORMATION');
					$comment .= "\n" . JText::_('COM_PHOCACART_PAYMENT_ID') . ': ' . $transactionId;
					$comment .= "\n" . JText::_('COM_PHOCACART_PAYMENT_AMOUNT') . ': ' . $mcGross;
					$comment .= "\n" . JText::_('COM_PHOCACART_PAYMENT_STATUS') . ': ' . $newStatus;

					// Add status history
					$notify = false;

					try
					{
						$notify = PhocacartOrderStatus::changeStatus($orderId, $newStatus, $order['common']->order_token);
					}
					catch (RuntimeException $e)
					{
						PhocacartLog::add(1, "Payment - ConcordPay - ERROR", $orderId, $e->getMessage());
					}

					PhocacartOrderStatus::setHistory($orderId, $newStatus, (int) $notify, $comment);

					// Add log
					$msg = 'Order Id: ' . $orderId . " \n"
						. 'Trx Id: ' . $transactionId . " \n"
						. 'Message: Payment successfully made' . " \n"
						. 'Response: ' . json_encode($response);
					PhocacartLog::add(1, "Payment - {$this->name} - SUCCESS", $orderId, $msg);
				}
			}
			elseif ($response['type'] === ConcordPayApi::RESPONSE_TYPE_REVERSE)
			{
				$newStatus = (int) $paramsPaymentTemp->get('status_refunded', 1);

				if (PhocacartOrderStatus::changeStatusInOrderTable($orderId, $newStatus))
				{
					// Add log
					$msg = 'Order Id: ' . $orderId . " \n"
						. 'Trx Id: ' . $transactionId . " \n"
						. 'Message: Refund successfully made' . " \n"
						. 'Response: ' . json_encode($response);
					PhocacartLog::add(1, "Refund - {$this->name} - SUCCESS", $orderId, $msg);
				}
			}
		}
	}

	/**
	 * Shows message on return page.
	 *
	 * @param   string $result Payment result key.
	 *
	 * @return array
	 *
	 * @since 3.8.0
	 */
	protected function processReturn($result)
	{
		$session = JFactory::getSession();

		switch ($result)
		{
			case 'success':
				$message = ['order_nodownload' => JText::_('PLG_PCP_CONCORDPAY_MESSAGE_APPROVED')];

				// ORDER PROCESSED - STANDARD PRODUCTS
				$session->set('infoaction', 1, 'phocaCart');
				break;
			case 'cancel':
				$message = ['payment_canceled' => JText::_('PLG_PCP_CONCORDPAY_MESSAGE_CANCELED')];

				// PAYMENT CANCELED
				$session->set('infoaction', 5, 'phocaCart');
				break;
			default:
				$message = ['payment_canceled' => JText::_('PLG_PCP_CONCORDPAY_MESSAGE_DECLINED')];
				$session->set('infoaction', 5, 'phocaCart');
		}

		$session->set('infomessage', $message, 'phocaCart');

		return $message;
	}
}
