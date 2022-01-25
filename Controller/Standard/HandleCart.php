<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

class HandleCart extends \Cashfree\Cfcheckout\Controller\CfAbstract
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

        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function execute()
    {
        $lastQuoteId = $this->checkoutSession->getLastQuoteId();
        $lastOrderId = $this->checkoutSession->getLastRealOrder();

        if ($lastQuoteId && $lastOrderId) {
            $orderModel = $this->objectManagement->get('Magento\Sales\Model\Order')->load($lastOrderId->getEntityId());

            if($orderModel->canCancel()) {
               
                $quote = $this->objectManagement->get('Magento\Quote\Model\Quote')->load($lastQuoteId);
                $quote->setIsActive(true)->save();
                 
                //not canceling order as cancled order can't be used again for order processing.
                //$orderModel->cancel(); 
                $orderModel->setStatus('canceled');
                $orderModel->save();
                $this->checkoutSession->setFirstTimeChk('0');                
                
                $responseContent = [
                    'success'       => true,
                    'redirect_url'  => 'checkout/#payment'
                ];
            }
        }
       
        if (!$lastQuoteId || !$lastOrderId) {
            $responseContent = [
                'success'       => true,
                'redirect_url'  => 'checkout/cart'
            ];
        }

        $this->messageManager->addError(__('Payment Failed or Payment closed'));
        
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode(200);

        return $response;

    }

}
