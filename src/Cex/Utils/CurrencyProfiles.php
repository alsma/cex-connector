<?php

namespace Cex\Utils;

class CurrencyProfiles
{
    /** @var array */
    private static $config = [
        'BTC'  => ['scale' => 0, 'precision' => 8],
        'LTC'  => ['scale' => 0, 'precision' => 8],
        'USD'  => ['scale' => 0, 'precision' => 2],
        'EUR'  => ['scale' => 0, 'precision' => 2],
        'RUB'  => ['scale' => 0, 'precision' => 2],
        'DOGE' => ['scale' => 2, 'precision' => 2]
    ];

    /**
     * @param string               $currency
     * @param string|float|integer $amount
     *
     * @return float
     */
    public static function formatAmount($currency, $amount)
    {
        $currency = strtoupper($currency);

        if (isset(self::$config[$currency])) {
            $precision = self::$config[$currency]['precision'];
            $scale = self::$config[$currency]['scale'];

            if ($scale > $precision) {
                $constTail = str_repeat('0', $scale - $precision + 1);

                return (float)sprintf('%d%s', $amount, $constTail);
            }

            $scaleMultiplier = pow(10, $scale);
            $precisionDivider = ($precision === 0) ? 1 : pow(10, -$precision);

            return floatval($amount * $scaleMultiplier * $precisionDivider);
        } else {
            return floatval($amount) / 1e8;
        }
    }
}
