<?php

namespace  Custom\DeliveryDate\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	
	/**
	 * @var Magento\Eav\Model\Entity\Attribute
	 */
	protected $attribute;
	/**
	 * @var Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection
	 */
	protected $optionCollection;
    /**
     * @var Magento\CatalogInventory\Model\Stock\Item
     */
    protected $product;
    /**
     * @var Magento\CatalogInventory\Model\Stock\Item
     */
    protected $productStockItem;

    /**
     * @var Magento\Catalog\Helper\Output
     */
    protected $output;

    /*protected $productDataChild = [];*/

	const ATTRIBUTE_CODE = 'type_images';

	const ENTITY_TYPE = 'catalog_product';
	
	protected $_productCollectionFactory;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $_dirReader;

    protected $_cart;

	/**
     * Description
     * @param \Magento\Eav\Model\Entity\Attribute $attribute 
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $optionCollection 
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory 
     * @param \Magento\CatalogInventory\Model\Stock\Item $productStockItem 
     * @param \Magento\Catalog\Model\Product $product 
     * @param \Magento\Catalog\Helper\Output $output 
     * 
     */
	public function __construct(
        \Magento\Eav\Model\Entity\Attribute $attribute,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $optionCollection,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\CatalogInventory\Model\Stock\Item $productStockItem,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Helper\Output $output,
        \Magento\Framework\Module\Dir\Reader $dirReader,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->attribute = $attribute;
        $this->optionCollection = $optionCollection;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->product = $product;
        $this->productStockItem = $productStockItem;
        $this->output = $output;
        $this->_dirReader = $dirReader;
        $this->_cart = $cart;
    }
    /**
     * get the product attributes option of type_images
     * @return []
     */
    public function getProductAttributesOption()
    {
        $attributeInfo =  $this->attribute->loadByCode(self::ENTITY_TYPE, self::ATTRIBUTE_CODE);
		/**
		 * Get all options name and value of the attribute
		 */ 
		$attributeId = $attributeInfo->getAttributeId();
		$attributeOptionAll = $this->optionCollection
		                           ->setPositionOrder('asc')
		                           ->setAttributeFilter($attributeId)                                               
		                           ->setStoreFilter()
		                           ->load();
		return $attributeOptionAll->getData();
    }
    /**
     * get current product ref_groupe product collection
     * @param type $sku 
     * @param type $ref_groupe 
     * @return Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    public function getCurrentRefGroupProductCollection($sku,$ref_groupe)
    {
    	$collection = $this->_productCollectionFactory->create();
    	$collection->addAttributeToSelect('*');
    	$collection->addAttributeToFilter('type_id', array('eq' => 'configurable'));
    	$collection->addAttributeToFilter('ref_groupe', array('eq' => $ref_groupe));
    	$collection->addAttributeToFilter('sku', array('neq' => $sku));
        $collection->AddAttributeToFilter('visibility',['in' => [2,3,4]]);
        $collection->AddAttributeToFilter('status',1);
    	$collection->setFlag('has_stock_status_filter', true);
        return $collection;
    }

    public function getProductStockItemData($productId)
    {
        $stockItemRepository = $this->productStockItem->load($productId, 'product_id');
        return $stockItemRepository;
    }
    /**
     * get All product attributes list
     * @param type $productParent 
     * @param type $_additional 
     * @return []
     */
    public function getChildProductAdditionalAttributes($productParent,$_additional) {
        $productChildAttributes = [];
        $productAttributeParent = [];
        foreach ($_additional as $_data) :
            $productAttributeParent[]= [
                'label' => $_data['label'],
                $_data['code'] => $this->output->productAttribute($productParent, $_data['value'], $_data['code'])
            ];
        endforeach;
        if ($productParent->getTypeId() == 'configurable') {
            # code...
            $_children = $productParent->getTypeInstance(true)->getUsedProducts($productParent);
            foreach ($_children as $key => $child) :
                $currentLoadedProduct = $child->load($child->getId());
                if ($child->isAvailable() == false) : continue; endif; 
                foreach ($_additional as $_data) :
                    $productChildAttributes[]= [
                        'label' => $_data['label'],
                        $_data['code'] => $this->output->productAttribute($child, $currentLoadedProduct->getResource()->getAttribute($_data['code'])->getFrontend()->getValue($child), $_data['code'])
                    ];

                endforeach;
            endforeach;
        }
        $AllProductAttributes = array_merge($productAttributeParent, $productChildAttributes);
        return $AllProductAttributes;
    }
    /**
     * Description
     * @param type &$array 
     * @param type $subfield 
     * @return type
     */
    public function sort_array_of_array(&$array, $subfield) {
        $sortarray = array();
        foreach ($array as $key => $row) {
            $sortarray[$key] = $row[$subfield];
        }
        array_multisort($sortarray, SORT_ASC, $array);
    }
    /**
     * read the csv and   redirect magento 1 product to magento 2 url
     * @return []
     */
    public function readProdutUrlcsv() {
            $UrlCsv="PRODUCT_SKU_URL.csv";
            $viewDir = $this->_dirReader->getModuleDir(
                            \Magento\Framework\Module\Dir::MODULE_VIEW_DIR,
                            'Custom_Custom'
                        );  
            $path_url_csv=$viewDir."/../Helper/$UrlCsv";
            $filedata = $this->read_csv_array($path_url_csv);
            return $filedata;
    }
    /**
     * csv first header title as a key assign
     * @param type $file_name 
     * @return []
     */
    public function read_csv_array($file_name) {
        $data =  $header = array();
        $i = 0;
        $file = fopen($file_name, 'r');
        while (($line = fgetcsv($file,',')) !== FALSE) {
            if( $i==0 ) {
                $header = $line;
            } else {
                $data[] = $line;        
            }
            $i++;
        }
        fclose($file);
        foreach ($data as $key => $_value) {
            $new_item = array();
            foreach ($_value as $key => $value) {
                $new_item[ $header[$key] ] =$value;
            }
            $_data[] = $new_item;
        }
        return $_data;
    }
    /**
     * get Quote prouct data
     * @return Magento\Checkout\Model\Cart
     */
    public function getQuoteItems()
    {
        return $this->_cart->getQuote()->getAllVisibleItems();
        # code...
    }

}