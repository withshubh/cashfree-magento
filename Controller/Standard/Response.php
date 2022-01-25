<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Cashfree\Cfcheckout\Model\Config;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;

class Response extends \Cashfree\Cfcheckout\Controller\CfAbstract
{
    /**
     * @var \Psr\Log\LoggerInterface 
     */
    protected $logger;

    /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Action\Context
     */

    protected $context;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagement;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Customer\Model\Session
    */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

     /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $customerSession,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $storeManagement,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );
    }

    /**
     * Get order response from cashfree to complete order
     * @return array
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        $code = 400;

        $request = $this->getRequest()->getParams();

        $responseContent = [
                'success'       => false,
                'redirect_url'  => 'checkout/#payment',
                'parameters'    => []
            ];
        
        $transactionId = $request['additional_data']['cf_transaction_id'];
        
        if(empty($transactionId) === false && $request['additional_data']['cf_order_status'] === 'PAID')
        {
            $orderAmount = round($order->getGrandTotal(), 2);
            
            $orderId = $order->getIncrementId();

            $validateOrder = $this->validateSignature($request, $order);

            if(!empty($validateOrder['status']) && $validateOrder['status'] === true) {
                $this->processPayment($request, $order);

                $responseContent = [
                    'success'       => true,
                    'redirect_url'  => 'checkout/onepage/success/',
                    'order_id'      => $orderId,
                ];

                $code = 200;
            } else {
                $responseContent['message'] = $validateOrder['errorMsg'];
            }

            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setData($responseContent);
            $response->setHttpResponseCode($code);
            return $response;
        } else {
            $responseContent['message'] = "Cashfree Payment details missing.";
        }

        //update/disable the quote
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($order->getQuoteId());
        $quote->setIsActive(true)->save();
        $this->checkoutSession->setFirstTimeChk('0');
        
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($code);
        return $response;
    }
}