<?php

namespace ccxt;

include_once ('base/Exchange.php');

class btcx extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'btcx',
            'name' => 'BTCX',
            'countries' => array ( 'IS', 'US', 'EU' ),
            'rateLimit' => 1500, // support in english is very poor, unable to tell rate limits
            'version' => 'v1',
            'hasCORS' => false,
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/27766385-9fdcc98c-5ed6-11e7-8f14-66d5e5cd47e6.jpg',
                'api' => 'https://btc-x.is/api',
                'www' => 'https://btc-x.is',
                'doc' => 'https://btc-x.is/custom/api-document.html',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'depth/{id}/{limit}',
                        'ticker/{id}',
                        'trade/{id}/{limit}',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'balance',
                        'cancel',
                        'history',
                        'order',
                        'redeem',
                        'trade',
                        'withdraw',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/USD' => array ( 'id' => 'btc/usd', 'symbol' => 'BTC/USD', 'base' => 'BTC', 'quote' => 'USD' ),
                'BTC/EUR' => array ( 'id' => 'btc/eur', 'symbol' => 'BTC/EUR', 'base' => 'BTC', 'quote' => 'EUR' ),
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $balances = $this->privatePostBalance ();
        $result = array ( 'info' => $balances );
        $currencies = array_keys ($balances);
        for ($c = 0; $c < count ($currencies); $c++) {
            $currency = $currencies[$c];
            $uppercase = strtoupper ($currency);
            $account = array (
                'free' => $balances[$currency],
                'used' => 0.0,
                'total' => $balances[$currency],
            );
            $result[$uppercase] = $account;
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $orderbook = $this->publicGetDepthIdLimit (array_merge (array (
            'id' => $this->market_id($symbol),
            'limit' => 1000,
        ), $params));
        return $this->parse_order_book($orderbook, null, 'bids', 'asks', 'price', 'amount');
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $ticker = $this->publicGetTickerId (array_merge (array (
            'id' => $this->market_id($symbol),
        ), $params));
        $timestamp = $ticker['time'] * 1000;
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => floatval ($ticker['high']),
            'low' => floatval ($ticker['low']),
            'bid' => floatval ($ticker['sell']),
            'ask' => floatval ($ticker['buy']),
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => floatval ($ticker['last']),
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => null,
            'quoteVolume' => floatval ($ticker['volume']),
            'info' => $ticker,
        );
    }

    public function parse_trade ($trade, $market) {
        $timestamp = intval ($trade['date']) * 1000;
        $side = ($trade['type'] == 'ask') ? 'sell' : 'buy';
        return array (
            'id' => $trade['id'],
            'info' => $trade,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'symbol' => $market['symbol'],
            'type' => null,
            'side' => $side,
            'price' => $trade['price'],
            'amount' => $trade['amount'],
        );
    }

    public function fetch_trades ($symbol, $params = array ()) {
        $market = $this->market ($symbol);
        $response = $this->publicGetTradeIdLimit (array_merge (array (
            'id' => $market['id'],
            'limit' => 1000,
        ), $params));
        return $this->parse_trades($response, $market);
    }

    public function create_order ($symbol, $type, $side, $amount, $price = null, $params = array ()) {
        $response = $this->privatePostTrade (array_merge (array (
            'type' => strtoupper ($side),
            'market' => $this->market_id($symbol),
            'amount' => $amount,
            'price' => $price,
        ), $params));
        return array (
            'info' => $response,
            'id' => $response['order']['id'],
        );
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        return $this->privatePostCancel (array ( 'order' => $id ));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $url = $this->urls['api'] . '/' . $this->version . '/';
        if ($api == 'public') {
            $url .= $this->implode_params($path, $params);
        } else {
            $nonce = $this->nonce ();
            $url .= $api;
            $body = $this->urlencode (array_merge (array (
                'Method' => strtoupper ($path),
                'Nonce' => $nonce,
            ), $params));
            $headers = array (
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Key' => $this->apiKey,
                'Signature' => $this->hmac ($this->encode ($body), $this->encode ($this->secret), 'sha512'),
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }

    public function request ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        $response = $this->fetch2 ($path, $api, $method, $params, $headers, $body);
        if (array_key_exists ('error', $response))
            throw new ExchangeError ($this->id . ' ' . $this->json ($response));
        return $response;
    }
}

?>