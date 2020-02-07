<?php
namespace Custom\DeliveryDate\Observer;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class UpdateProductQtyObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectmanager
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectmanager
    ) {
        $this->_objectManager = $objectmanager;
    }

    public function execute(EventObserver $observer)
    {
        $proxy = new \SoapClient('http://magento1937.suketuparikh.webfactional.com/api/v2_soap/?wsdl'); // TODO : change url
        $sessionId = $proxy->login('admin', 'test@123'); // TODO : change login and pwd if necessary
        $quote = $observer->getEvent()->getQuote();   
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/templog.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        // retrieve quote items array
        $items = $quote->getAllVisibleItems();
        $magesku = '';
        foreach($items as $item) {
            $productRepository = $this->_objectManager->create('\Magento\Catalog\Model\ProductRepository');
            $productObj = $productRepository->get($item->getSku());
            $magesku = $productObj->getData('sku_mag1');
            $logger->info("Mage 2 product id". $productObj->getId() . "----- Mage 1 Sku  ". $magesku );
            if ($magesku) {
                $logger->info("Mage 2 product id data ". $productObj->getId() . "----- Mage 1 Sku  ". $magesku );
                $productStockdataGet = $proxy->catalogInventoryStockItemList($sessionId, array($magesku));
                if (!empty($productStockdataGet)) {
                    $oldproductQty = (int)$productStockdataGet[0]->qty;
                    if ((int)$oldproductQty > 0) {
                        $updateQty = (int)$oldproductQty - (int)$item->getQty();
                        if ($updateQty <= 0) {
                            $updateQty = 0;
                            $productStockdataUpdate = $proxy->catalogInventoryStockItemUpdate($sessionId, $magesku, array(
                                'qty' => $updateQty, 
                                'is_in_stock' => 0
                            ));
                            $logger->info("Mage 2 Sku = ". $item->getSku() . ",Mage 1 Sku = ". $magesku . ",M2 Order Qty = ". $item->getQty() . ",Mage 1 Update Qty = ". $updateQty .",Stock = ". 'Out Of Stock');
                        } else {
                            $productStockdataUpdate = $proxy->catalogInventoryStockItemUpdate($sessionId, $magesku, array(
                                'qty' => $updateQty, 
                                'is_in_stock' => 1
                            ));
                             $logger->info("Mage 2 Sku = ". $item->getSku() . ",Mage 1 Sku = ". $magesku . ",M2 Order Qty = ". $item->getQty() . ",Mage 1 Update Qty = ". $updateQty .",Stock = ". 'In Stock');
                        }
                    }
                    
                }
            } else {
                continue;
            }
                      
       }
    }
}
