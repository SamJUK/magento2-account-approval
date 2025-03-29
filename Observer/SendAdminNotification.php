<?php

declare(strict_types=1);

namespace Amitshree\Customer\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class SendAdminNotification implements ObserverInterface
{
    const TEMPLATE_IDENTIFIER = 'amitshree_customer_new_registration';

    /** @var TransportBuilder */
    private $transportBuilder;
    
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(EventObserver $observer)
    {
        if (!$this->isEnabled()) {
            $this->logger->debug('[Amitshree_Customer] New customer registration emails are disabled');
            return;
        }

        try {
            $customer = $observer->getCustomer();
            $templateVariables = new \Magento\Framework\DataObject();
            $templateVariables->setData([
                'customer_name' => "{$customer->getFirstName()} {$customer->getLastName()}",
                'customer_email' => $customer->getEmail()
            ]);

            $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_IDENTIFIER)
                ->setTemplateOptions($this->getTemplateOptions())
                ->setFrom($this->getSenderDetails())
                ->setTemplateVars(['data' => $templateVariables])
                ->addTo($this->getRecipientAddresses())
                ->getTransport()
                ->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error("Error while sending new registration email {$e->getMessage()}");
        }
    }

    private function isEnabled()
    {
        return
            $this->scopeConfig->isSetFlag('customerlogin/general/enable', ScopeInterface::SCOPE_STORE)
            && $this->scopeConfig->isSetFlag('customerlogin/general/registration_notifications_enabled', ScopeInterface::SCOPE_STORE)
            && count($this->getRecipientAddresses()) > 0;
    }

    private function getRecipientAddresses()
    {
        $rawRecipients = $this->scopeConfig->getValue('customerlogin/general/registration_notifications', ScopeInterface::SCOPE_STORE);
        return explode(',', $rawRecipients);
    }

    private function getSenderDetails()
    {
        return [
            'name' => $this->scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE),
            'email' => $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE)
        ];
    }

    private function getTemplateOptions()
    {
        return [
            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID
        ];
    }
}
