<?php

namespace Cex\Utils;

class TransactionUtils
{
    const TX_TYPE_HOLD = 'hold';
    const TX_TYPE_RETURN = 'return';
    const TX_TYPE_BUY = 'buy';
    const TX_TYPE_SELL = 'sell';

    /**
     * Fetches main order ID from transaction info
     *
     * @param array $tx
     *
     * @return string
     */
    public static function getOrderId(array $tx)
    {
        return !empty($tx[$tx['type']]) ? $tx[$tx['type']] : $tx['order'];
    }

    /**
     * Returns whether given transaction is transaction which holds funds on balance, or returns funds back
     *
     * @param array $tx
     *
     * @return bool
     */
    public static function isNonDealTransaction(array $tx)
    {
        return in_array(self::getTransactionType($tx), [self::TX_TYPE_HOLD, self::TX_TYPE_RETURN], true);
    }

    /**
     * @param array $tx
     *
     * @return string
     * @throws \Exception
     */
    public static function getTransactionType(array $tx)
    {
        switch (true) {
            case isset($tx['order']) && \Bc::comp($tx['amount'], 0) === -1:
                return self::TX_TYPE_HOLD;
                break;
            case $tx['type'] === 'cancel':
                return self::TX_TYPE_RETURN;
                break;
            case $tx['type'] === 'buy';
                return self::TX_TYPE_BUY;
                break;
            case $tx['type'] === 'sell';
                return self::TX_TYPE_SELL;
                break;

            default:
                throw new \Exception('Unknown transaction type');
        }
    }
}
