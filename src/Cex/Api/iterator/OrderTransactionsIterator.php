<?php

namespace Cex\Api\Iterator;

use Cex\Api\HttpClient;
use Cex\Utils\TransactionUtils;

class OrderTransactionsIterator extends \FilterIterator
{
    /** @var string */
    protected $orderOriginId;

    /**
     * @param HttpClient $client
     * @param array      $orderData
     */
    public function __construct(HttpClient $client, array $orderData)
    {
        $this->orderOriginId = $orderData['id'];

        $filters = ['start' => $orderData['createdAt'], 'type' => HttpClient::TRANSACTION_TYPE_TRADE];
        parent::__construct(new TransactionsIterator($client, $filters));
    }

    /**
     * {@inheritdoc}
     */
    public function accept()
    {
        $tx = $this->current();
        $originOrderId = TransactionUtils::getOrderId($tx);

        return $originOrderId === $this->orderOriginId;
    }
}
