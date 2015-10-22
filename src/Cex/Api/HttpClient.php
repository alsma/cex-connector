<?php

namespace Cex\Api;

use Cex\Utils\CurrencyProfiles;

class HttpClient
{
    const TRANSACTION_TYPE_TRADE = 'trade';       // buy, sell, cancel
    const TRANSACTION_TYPE_MINING = 'mining';     // mining, maintenance
    const TRANSACTION_TYPE_DEPOSIT = 'deposit';   // deposit
    const TRANSACTION_TYPE_WITHDRAW = 'withdraw'; // withdraw

    /** @var string */
    private $baseURL;

    /** @var string */
    private $username;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var string */
    private $nonceV;

    /** @var bool */
    private $verifySSL;

    /**
     * Create cexapi object
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->baseURL = $config['baseURL'];
        $this->username = $config['username'];
        $this->apiKey = $config['apiKey'];
        $this->apiSecret = $config['apiSecret'];
        $this->verifySSL = isset($config['verifySSL']) ? $config['verifySSL'] : true;

        $this->nonce();
    }

    /**
     * Send API call (over post request), to Cex.io server.
     *
     * @param string      $method
     * @param array       $param
     * @param bool|string $private
     * @param string      $pair
     *
     * @return array JSON results
     */
    public function apiCall($method, $param = array(), $private = false, $pair = '')
    {
        $url = sprintf('%s/api/%s', $this->baseURL, $method);

        if ($pair !== '') {
            $url .= "$pair/"; //set PAIR if needed
        }

        if ($private === true) { //Create param
            $param = array_merge([
                'key'       => $this->apiKey,
                'signature' => $this->signature(),
                'nonce'     => $this->nonceV++
            ], $param);
        }

        $answer = $this->post($url, $param);
        $answer = json_decode($answer, true);

        return $answer;
    }

    /**
     * Get the current ticker results for the given pair, or 'GHS/BTC' by default.
     *
     * @param string $pair
     *
     * @return array JSON results
     */
    public function ticker($pair = 'GHS/BTC')
    {
        return $this->apiCall('ticker/', [], false, $pair);
    }

    /**
     * Get the current bids and asks for the given pair, or 'GHS/BTC' by default.
     *
     * @param string $pair
     *
     * @return array JSON results
     */
    public function orderBook($pair = 'GHS/BTC')
    {
        return $this->apiCall('order_book/', [], false, $pair);
    }

    /**
     * Get the current trade history for the given pair, or 'GHS/BTC' by default.
     *
     * @param string   $pair
     * @param int|null $since
     *
     * @return array JSON results
     */
    public function tradeHistory($pair = 'GHS/BTC', $since = null)
    {
        $params = [];
        if (null !== $since) {
            $params['since'] = $since;
        }

        return $this->apiCall('trade_history/', $params, false, $pair);
    }

    /**
     * Get the current account balance.
     *
     * @return array JSON results
     */
    public function balance()
    {
        return $this->apiCall('balance/', [], true);
    }

    /**
     * Get the current account open orders for the given pair, or 'GHS/BTC' by default.
     *
     * @param string $pair
     *
     * @return array JSON results
     */
    public function openOrders($pair = 'GHS/BTC')
    {
        return $this->apiCall('open_orders/', [], true, $pair);
    }


    /**
     * @param string $pair
     *
     * @return array JSON results
     */
    public function archivedOrders($pair = 'GHS/BTC')
    {
        return $this->apiCall('archived_orders/', [], true, $pair);
    }

    /**
     * Cancel the given order for the account.
     *
     * @param int $orderId
     *
     * @return boolean success
     */
    public function cancelOrder($orderId)
    {
        return $this->apiCall('cancel_order/', ['id' => $orderId], true);
    }

    /**
     * Get order information
     *
     * @param int $orderId
     *
     * @return boolean success
     */
    public function getOrder($orderId)
    {
        return $this->apiCall('get_order/', ['id' => $orderId], true);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function getRawTransactions(array $filters)
    {
        return $this->apiCall('raw_tx_history/', $filters, true);
    }

    /**
     * @param string    $type    'buy'|'sell'
     * @param float|int $amount
     * @param float|int $price
     * @param string    $pair
     *
     * @return array JSON order data
     */
    public function placeOrder($type = 'buy', $amount = 1, $price = 1, $pair = 'GHS/BTC')
    {
        return $this->apiCall('place_order/', [
            'type'   => $type,
            'amount' => $amount,
            'price'  => $price
        ], true, $pair);
    }

    /**
     * @param string    $type   'buy'|'sell'
     * @param float|int $amount
     * @param string    $pair
     *
     * @return array
     */
    public function placeMarketOrder($type, $amount, $pair)
    {
        $result = $this->apiCall('place_order/', [
            'type'       => $type,
            'amount'     => $amount,
            'order_type' => 'market'
        ], true, $pair);

        if (empty($result['error'])) {
            $pairParts = explode('/', $pair);
            $result['symbol1Amount'] = CurrencyProfiles::formatAmount(current($pairParts), $result['symbol1Amount']);
            $result['symbol2Amount'] = CurrencyProfiles::formatAmount(end($pairParts), $result['symbol2Amount']);
        }

        return $result;
    }

    /**
     * Create signature for API call validation
     *
     * @return string hash
     */
    private function signature()
    {
        $string = $this->nonceV . $this->username . $this->apiKey; //Create string
        $hash = hash_hmac('sha256', $string, $this->apiSecret); //Create hash
        $hash = strtoupper($hash);

        return $hash;
    }

    /**
     * Set nonce as timestamp
     */
    private function nonce()
    {
        $this->nonceV = round(microtime(true) * 100);
    }

    /**
     * Send post request to Cex.io API.
     *
     * @param string $url
     * @param array  $param
     *
     * @return array JSON results
     */
    private function post($url, $param = array())
    {
        $post = '';
        if (!empty($param)) {
            $post = http_build_query($param);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'phpAPI');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        if (!$this->verifySSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $out = curl_exec($ch);

        if (curl_errno($ch)) {
            trigger_error("cURL failed. Error #" . curl_errno($ch) . ": " . curl_error($ch), E_USER_ERROR);
        }

        curl_close($ch);

        return $out;
    }
}
