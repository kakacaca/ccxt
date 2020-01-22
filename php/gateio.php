<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use \ccxtpro\ClientTrait; // websocket functionality
use Exception; // a common import
use \ccxt\ExchangeError;
use \ccxt\AuthenticationError;

class gateio extends \ccxt\gateio {

    use ClientTrait;

    public function describe () {
        return array_replace_recursive(parent::describe (), array(
            'has' => array(
                'watchOrderBook' => true,
                'watchTicker' => true,
                'watchTrades' => true,
                'watchOHLCV' => true,
                'watchBalance' => true,
                'watchOrders' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://ws.gate.io/v3',
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'OHLCVLimit' => 1000,
            ),
        ));
    }

    public function watch_order_book ($symbol, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $marketId = $market['id'];
        $wsMarketId = strtoupper($marketId);
        $requestId = $this->nonce ();
        $url = $this->urls['api']['ws'];
        if (!$limit) {
            $limit = 30;
        } else if ($limit !== 1 && $limit !== 5 && $limit !== 10 && $limit !== 20 && $limit !== 30) {
            throw new ExchangeError($this->id . ' watchOrderBook $limit argument must be null, 1, 5, 10, 20, or 30');
        }
        $interval = $this->safe_string($params, 'interval', '0.00000001');
        $floatInterval = floatval ($interval);
        $precision = -1 * log10 ($floatInterval);
        if (($precision < 0) || ($precision > 8) || (fmod($precision, 1) !== 0.0)) {
            throw new ExchangeError($this->id . ' invalid interval');
        }
        $messageHash = 'depth.update' . ':' . $marketId;
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'depth.subscribe',
            'params' => [$wsMarketId, $limit, $interval],
        );
        $subscription = array(
            'id' => $requestId,
        );
        $future = $this->watch ($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
        return $this->after ($future, array($this, 'limit_order_book'), $symbol, $limit, $params);
    }

    public function sign_message ($client, $messageHash, $message, $params = array ()) {
        // todo => implement gateio signMessage
        return $message;
    }

    public function limit_order_book ($orderbook, $symbol, $limit = null, $params = array ()) {
        return $orderbook->limit ($limit);
    }

    public function handle_delta ($bookside, $delta) {
        $price = $this->safe_float($delta, 0);
        $amount = $this->safe_float($delta, 1);
        $bookside->store ($price, $amount);
    }

    public function handle_deltas ($bookside, $deltas) {
        for ($i = 0; $i < count($deltas); $i++) {
            $this->handle_delta ($bookside, $deltas[$i]);
        }
    }

    public function handle_order_book ($client, $message) {
        $params = $this->safe_value($message, 'params', array());
        $clean = $this->safe_value($params, 0);
        $book = $this->safe_value($params, 1);
        $marketId = $this->safe_string_lower($params, 2);
        $symbol = null;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        } else {
            $symbol = $marketId;
        }
        $method = $this->safe_string($message, 'method');
        $messageHash = $method . ':' . $marketId;
        $orderBook = null;
        if ($clean) {
            $orderBook = $this->order_book (array());
            $this->orderbooks[$symbol] = $orderBook;
        } else {
            $orderBook = $this->orderbooks[$symbol];
        }
        $this->handle_deltas ($orderBook['asks'], $this->safe_value($book, 'asks', array()));
        $this->handle_deltas ($orderBook['bids'], $this->safe_value($book, 'bids', array()));
        $client->resolve ($orderBook, $messageHash);
    }

    public function watch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $marketId = $market['id'];
        $wsMarketId = strtoupper($marketId);
        $requestId = $this->nonce ();
        $url = $this->urls['api']['ws'];
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'ticker.subscribe',
            'params' => [$wsMarketId],
        );
        $subscription = array(
            'id' => $requestId,
        );
        $messageHash = 'ticker.update' . ':' . $marketId;
        return $this->watch ($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
    }

    public function handle_ticker ($client, $message) {
        $result = $message['params'];
        $marketId = $this->safe_string_lower($result, 0);
        $market = null;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
        }
        $ticker = $result[1];
        $parsed = $this->parse_ticker($ticker, $market);
        $methodType = $message['method'];
        $messageHash = $methodType . ':' . $marketId;
        $client->resolve ($parsed, $messageHash);
    }

    public function watch_trades ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $marketId = strtoupper($market['id']);
        $requestId = $this->nonce ();
        $url = $this->urls['api']['ws'];
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'trades.subscribe',
            'params' => [$marketId],
        );
        $subscription = array(
            'id' => $requestId,
        );
        $messageHash = 'trades.update' . ':' . $marketId;
        return $this->watch ($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
    }

    public function handle_trades ($client, $messsage) {
        $result = $messsage['params'];
        $wsMarketId = $this->safe_string($result, 0);
        $marketId = $this->safe_string_lower($result, 0);
        $market = null;
        $symbol = $marketId;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        }
        if (!(is_array($this->trades) && array_key_exists($symbol, $this->trades))) {
            $this->trades[$symbol] = array();
        }
        $stored = $this->trades[$symbol];
        $trades = $result[1];
        for ($i = 0; $i < count($trades); $i++) {
            $trade = $trades[$i];
            $parsed = $this->parse_trade($trade, $market);
            $stored[] = $parsed;
            $length = is_array($stored) ? count($stored) : 0;
            if ($length > $this->options['tradesLimit']) {
                array_shift($stored);
            }
        }
        $this->trades[$symbol] = $stored;
        $methodType = $messsage['method'];
        $messageHash = $methodType . ':' . $wsMarketId;
        $client->resolve ($stored, $messageHash);
    }

    public function watch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $marketId = strtoupper($market['id']);
        $requestId = $this->nonce ();
        $url = $this->urls['api']['ws'];
        $interval = intval ($this->timeframes[$timeframe]);
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'kline.subscribe',
            'params' => [$marketId, $interval],
        );
        $subscription = array(
            'id' => $requestId,
        );
        $messageHash = 'kline.update' . ':' . $marketId;
        return $this->watch ($url, $messageHash, $subscribeMessage, $messageHash, $subscription);
    }

    public function authenticate () {
        $url = $this->urls['api']['ws'];
        $client = $this->client ($url);
        $future = $client->future ('authenticated');
        $method = 'server.sign';
        $authenticate = $this->safe_value($client->subscriptions, $method);
        if ($authenticate === null) {
            $requestId = $this->milliseconds ();
            $requestIdString = (string) $requestId;
            $signature = $this->hmac ($this->encode ($requestIdString), $this->encode ($this->secret), 'sha512', 'base64');
            $authenticateMessage = array(
                'id' => $requestId,
                'method' => $method,
                'params' => array( $this->apiKey, $this->decode ($signature), $requestId ),
            );
            $subscribe = array(
                'id' => $requestId,
                'method' => array($this, 'handle_authentication_message'),
            );
            $this->spawn (array($this, 'watch'), $url, $requestId, $authenticateMessage, $method, $subscribe);
        }
        return $future;
    }

    public function handle_ohlcv ($client, $message) {
        $ohlcv = $message['params'][0];
        $wsMarketId = $this->safe_string($ohlcv, 7);
        $marketId = $this->safe_string_lower($ohlcv, 7);
        $parsed = [
            intval ($ohlcv[0]),    // t
            floatval ($ohlcv[1]),  // o
            floatval ($ohlcv[3]),  // h
            floatval ($ohlcv[2]),  // c
            floatval ($ohlcv[5]),  // v
        ];
        $market = null;
        $symbol = $marketId;
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
        }
        if (!(is_array($this->ohlcvs) && array_key_exists($symbol, $this->ohlcvs))) {
            $this->ohlcvs[$symbol] = array();
        }
        $stored = $this->ohlcvs[$symbol];
        $length = is_array($stored) ? count($stored) : 0;
        if ($length && $parsed[0] === $stored[$length - 1][0]) {
            $stored[$length - 1] = $parsed;
        } else {
            $stored[] = $parsed;
            if ($length === $this->options['OHLCVLimit']) {
                array_shift($stored);
            }
        }
        $this->ohlcvs[$symbol] = $stored;
        $methodType = $message['method'];
        $messageHash = $methodType . ':' . $wsMarketId;
        $client->resolve ($stored, $messageHash);
    }

    public function watch_balance ($params = array ()) {
        $this->load_markets();
        $this->check_required_credentials();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate ();
        $requestId = $this->nonce ();
        $method = 'balance.update';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'balance.subscribe',
            'params' => array(),
        );
        $subscription = array(
            'id' => $requestId,
            'method' => array($this, 'handle_balance_subscription'),
        );
        return $this->after_dropped ($future, array($this, 'watch'), $url, $method, $subscribeMessage, $method, $subscription);
    }

    public function fetch_balance_snapshot () {
        $this->load_markets();
        $this->check_required_credentials();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate ();
        $requestId = $this->nonce ();
        $method = 'balance.query';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => $method,
            'params' => array(),
        );
        $subscription = array(
            'id' => $requestId,
            'method' => array($this, 'handle_balance_snapshot'),
        );
        return $this->after_dropped ($future, array($this, 'watch'), $url, $requestId, $subscribeMessage, $method, $subscription);
    }

    public function handle_balance_snapshot ($client, $message) {
        $messageHash = $message['id'];
        $result = $message['result'];
        $this->handle_balance_message ($client, $messageHash, $result);
        unset($client->subscriptions['balance.query']);
    }

    public function handle_balance ($client, $message) {
        $messageHash = $message['method'];
        $result = $message['params'][0];
        $this->handle_balance_message ($client, $messageHash, $result);
    }

    public function handle_balance_message ($client, $messageHash, $result) {
        $keys = is_array($result) ? array_keys($result) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $account = $this->account ();
            $key = $keys[$i];
            $code = $this->safe_currency_code($key);
            $balance = $result[$key];
            $account['free'] = $this->safe_float($balance, 'available');
            $account['used'] = $this->safe_float($balance, 'freeze');
            $this->balance[$code] = $account;
        }
        $client->resolve ($this->parse_balance($this->balance), $messageHash);
    }

    public function watch_orders ($params = array ()) {
        $this->check_required_credentials();
        $this->load_markets();
        $url = $this->urls['api']['ws'];
        $future = $this->authenticate ();
        $requestId = $this->nonce ();
        $method = 'order.update';
        $subscribeMessage = array(
            'id' => $requestId,
            'method' => 'order.subscribe',
            'params' => array(),
        );
        return $this->after_dropped ($future, array($this, 'watch'), $url, $method, $subscribeMessage, $method);
    }

    public function handle_order ($client, $message) {
        $messageHash = $message['method'];
        $order = $message['params'][1];
        $marketId = $order['market'];
        $normalMarketId = strtolower($marketId);
        $market = null;
        if (is_array($this->markets_by_id) && array_key_exists($normalMarketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$normalMarketId];
        }
        $parsed = $this->parse_order($order, $market);
        $client->resolve ($parsed, $messageHash);
    }

    public function handle_authentication_message ($client, $message, $subscription) {
        $result = $this->safe_value($message, 'result');
        $status = $this->safe_string($result, 'status');
        if ($status === 'success') {
            // $client->resolve (true, 'authenticated') will delete the $future
            // we want to remember that we are authenticated in subsequent call to private methods
            $future = $client->futures['authenticated'];
            $future->resolve (true);
        } else {
            // delete authenticate subscribeHash to release the "subscribe lock"
            // allows subsequent calls to subscribe to reauthenticate
            // avoids sending two authentication messages before receiving a reply
            $error = new AuthenticationError ('not success');
            $client->reject ($error, 'autheticated');
            if (is_array($client->subscriptions) && array_key_exists('server.sign', $client->subscriptions)) {
                unset($client->subscriptions['server.sign']);
            }
        }
    }

    public function handle_error_message ($client, $message) {
        // todo use $error map here
        $error = $this->safe_value($message, 'error', array());
        $code = $this->safe_integer($error, 'code');
        if ($code === 11 || $code === 6) {
            $error = new AuthenticationError ('invalid credentials');
            $client->reject ($error, $message['id']);
            $client->reject ($error, 'authenticated');
        }
    }

    public function handle_balance_subscription ($client, $message, $subscription) {
        $this->spawn (array($this, 'fetch_balance_snapshot'));
    }

    public function handle_subscription_status ($client, $message) {
        $messageId = $message['id'];
        $subscriptionsById = $this->index_by($client->subscriptions, 'id');
        $subscription = $this->safe_value($subscriptionsById, $messageId, array());
        if (is_array($subscription) && array_key_exists('method', $subscription)) {
            $method = $subscription['method'];
            $method($client, $message, $subscription);
        }
        $client->resolve ($message, $messageId);
    }

    public function handle_message ($client, $message) {
        $this->handle_error_message ($client, $message);
        $methods = array(
            'depth.update' => array($this, 'handle_order_book'),
            'ticker.update' => array($this, 'handle_ticker'),
            'trades.update' => array($this, 'handle_trades'),
            'kline.update' => array($this, 'handle_ohlcv'),
            'balance.update' => array($this, 'handle_balance'),
            'order.update' => array($this, 'handle_order'),
        );
        $methodType = $this->safe_string($message, 'method');
        $method = $this->safe_value($methods, $methodType);
        if ($method === null) {
            $messageId = $this->safe_integer($message, 'id');
            if ($messageId !== null) {
                $this->handle_subscription_status ($client, $message);
            }
        } else {
            $method($client, $message);
        }
    }
}