<?php
namespace Custom\DeliveryDate\Plugin\Model\Checkout;


class ShippingInformationManagement
{
    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;
    /**
     * @var Custom\DeliveryDate\Helper\Data
     */
    protected $customHelper;

    /**
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     */
    public function __construct(
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Custom\DeliveryDate\Helper\Data $customHelper
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->customHelper = $customHelper;
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $cartId
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    ) {
        $startDate = date('Y-m-d', strtotime("+3 days"));
        $endDate = date('Y-m-d', strtotime("+7 days"));
        $cartData = $this->customHelper->getQuoteItems();
        $cartDataCount = count($cartData);
        if ($cartDataCount > 0) {
            foreach ($cartData as $item) {
                $product = $item->getProduct();
                $currentLoadedProduct = $product->load($product->getId()); 
                $stockItemRepository = $this->customHelper->getProductStockItemData($product->getId());
                if ($stockItemRepository->getIsInStock() == '1' && $stockItemRepository->getBackorders() == '1') {
                    $delivery_day =  (int)$currentLoadedProduct->getResource()->getAttribute('delai_livraison')->getFrontend()->getValue($currentLoadedProduct);
                    $productDeliverDate = date('Y-m-d', strtotime("+".$delivery_day."days"));
                    if ($productDeliverDate > $endDate) {
                        $endDate = $productDeliverDate;
                    }
                }
               
            }
        }
        $extAttributes = $addressInformation->getExtensionAttributes();
        $deliveryDate = date("Y-m-d H:i:s",strtotime($endDate));
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->setDeliveryDate($deliveryDate);
        $quote->save();
    }
}