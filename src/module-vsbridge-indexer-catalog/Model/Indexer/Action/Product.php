<?php
/**
 * @package   magento-2-1.dev
 * @author    Agata Firlejczyk <afirlejczyk@divante.pl>
 * @copyright 2019 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\VsbridgeIndexerCatalog\Model\Indexer\Action;

use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product as ResourceModel;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class Product
 */
class Product
{
    /**
     * @var ResourceModel
     */
    private $resourceModel;
    /**
     * @var EventManager $eventManager
     */
    private $eventManager;

    /**
     * Product constructor.
     * @param ResourceModel $resourceModel
     * @param EventManager  $eventManager
     */
    public function __construct(
        ResourceModel $resourceModel,
        EventManager $eventManager
    ) {
        $this->resourceModel = $resourceModel;
        $this->eventManager = $eventManager;
    }

    /**
     * @param int $storeId
     * @param array $productIds
     *
     * @return \Generator
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function rebuild($storeId = 1, array $productIds = [])
    {
        $lastProductId = 0;

        // Ensure to reindex also the parents product ids
        if (!empty($productIds)) {
            $productIds = $this->getProductIds($productIds);
        }

        do {
            $products = $this->resourceModel->getProducts($storeId, $productIds, $lastProductId);

            /** @var array $product */
            foreach ($products as $product) {
                $lastProductId = $product['entity_id'];
                $product['id'] = (int)$product['entity_id'];

                unset($product['required_options']);
                unset($product['has_options']);

                $productObject = new \Magento\Framework\DataObject();
                $productObject->setData($product);

                $this->eventManager->dispatch(
                    'elasticsearch_product_build_entity_data_after',
                    ['data_object' => $productObject]
                );

                $product = $productObject->getData();

                yield $lastProductId => $product;
            }
        } while (!empty($products));
    }

    /**
     * @param array $childrenIds
     *
     * @return array
     */
    private function getProductIds(array $childrenIds)
    {
        $parentIds = $this->resourceModel->getRelationsByChild($childrenIds);

        if (!empty($parentIds)) {
            $parentIds = array_map('intval', $parentIds);
        }

        return array_unique(array_merge($childrenIds, $parentIds));
    }
}
