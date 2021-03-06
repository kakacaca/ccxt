<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import

class gopax extends \ccxt\async\gopax {

    use ClientTrait;

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'has' => array(
                'ws' => true,
                'watchOrderBook' => true,
                'watchMyTrades' => true,
                'watchBalance' => true,
                'watchOrders' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://wsapi.gopax.co.kr',
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'ordersLimit' => 1000,
                'OHLCVLimit' => 1000,
            ),
        ));
    }

    public function get_signed_url() {
        $options = $this->safe_value($this->options, 'ws', array());
        if (is_array($options) && array_key_exists('url', $options)) {
            return $options['url'];
        }
        $this->check_required_credentials();
        $nonce = (string) $this->nonce();
        $auth = 't' . $nonce;
        $rawSecret = base64_decode($this->secret);
        $signature = $this->hmac($this->encode($auth), $rawSecret, 'sha512', 'base64');
        $query = array(
            'apiKey' => $this->apiKey,
            'timestamp' => $nonce,
            'signature' => $signature,
        );
        $url = $this->urls['api']['ws'] . '?' . $this->urlencode($query);
        $options['url'] = $url;
        $this->options['ws'] = $options;
        return $url;
    }

    public function watch_order_book($symbol, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $market = $this->market($symbol);
        $name = 'orderbook';
        $messageHash = $name . ':' . $market['id'];
        $url = $this->get_signed_url();
        $request = array(
            'n' => 'SubscribeToOrderBook',
            'o' => array(
                'tradingPairName' => $market['id'],
            ),
        );
        $subscription = array(
            'messageHash' => $messageHash,
            'name' => $name,
            'symbol' => $symbol,
            'marketId' => $market['id'],
            'method' => array($this, 'handle_order_book'),
            'limit' => $limit,
            'params' => $params,
        );
        $message = array_merge($request, $params);
        $orderbook = yield $this->watch($url, $messageHash, $message, $messageHash, $subscription);
        return $orderbook->limit ($limit);
    }

    public function handle_delta($orderbook, $bookside, $delta) {
        //
        //     {
        //         $entryId => 60949856,
        //         $price => 31575000,
        //         volume => 0.3163,
        //         updatedAt => 1609420344.174
        //     }
        //
        $entryId = $this->safe_integer($delta, 'entryId');
        if (($orderbook['nonce'] !== null) && ($entryId >= $orderbook['nonce'])) {
            $price = $this->safe_float($delta, 'price');
            $amount = $this->safe_float($delta, 'volume');
            $bookside->store ($price, $amount);
        }
        return $entryId;
    }

    public function handle_deltas($orderbook, $bookside, $deltas) {
        $nonce = 0;
        for ($i = 0; $i < count($deltas); $i++) {
            $n = $this->handle_delta($orderbook, $bookside, $deltas[$i]);
            $nonce = max ($nonce, $n);
        }
        return $nonce;
    }

    public function handle_order_book_message($client, $message, $orderbook) {
        //
        //     {
        //         i => -1,
        //         n => 'OrderBookEvent',
        //         $o => {
        //             ask => array(
        //                 array( entryId => 60949856, price => 31575000, volume => 0.3163, updatedAt => 1609420344.174 )
        //             ),
        //             bid => array(),
        //             tradingPairName => 'BTC-KRW'
        //         }
        //     }
        //
        $o = $this->safe_value($message, 'o', array());
        $askNonce = $this->handle_deltas($orderbook, $orderbook['asks'], $this->safe_value($o, 'ask', array()));
        $bidNonce = $this->handle_deltas($orderbook, $orderbook['bids'], $this->safe_value($o, 'bid', array()));
        $nonce = max ($askNonce, $bidNonce);
        $orderbook['nonce'] = $nonce;
        return $orderbook;
    }

    public function handle_order_book($client, $message) {
        //
        // initial snapshot
        //
        //     {
        //         $n => 'SubscribeToOrderBook',
        //         $o => {
        //             ask => array(
        //                 array( entryId => 60490601, price => 32061000, volume => 0.09996, updatedAt => 1609412729.325 ),
        //                 array( entryId => 60490959, price => 32078000, volume => 0.206, updatedAt => 1609412735.793 ),
        //                 array( entryId => 60490687, price => 32085000, volume => 0.192, updatedAt => 1609412730.373 ),
        //             ),
        //             bid => array(
        //                 array( entryId => 60491143, price => 32059000, volume => 0.3118, updatedAt => 1609412740.011 ),
        //                 array( entryId => 60490948, price => 32058000, volume => 0.00162449, updatedAt => 1609412735.555 ),
        //                 array( entryId => 60488158, price => 32053000, volume => 0.206, updatedAt => 1609412680.169 ),
        //             ),
        //             tradingPairName => 'BTC-KRW',
        //             maxEntryId => 60491355
        //         }
        //     }
        //
        // delta update
        //
        //     {
        //         $i => -1,
        //         $n => 'OrderBookEvent',
        //         $o => {
        //             ask => array(
        //                 array( entryId => 60949856, price => 31575000, volume => 0.3163, updatedAt => 1609420344.174 )
        //             ),
        //             bid => array(),
        //             tradingPairName => 'BTC-KRW'
        //         }
        //     }
        //
        $n = $this->safe_string($message, 'n');
        $o = $this->safe_value($message, 'o');
        $marketId = $this->safe_string($o, 'tradingPairName');
        $market = $this->safe_market($marketId, null, '-');
        $symbol = $market['symbol'];
        // $nonce = $this->safe_integer($o, 'maxEntryId');
        $name = 'orderbook';
        $messageHash = $name . ':' . $market['id'];
        $subscription = $this->safe_value($client->subscriptions, $messageHash, array());
        $limit = $this->safe_integer($subscription, 'limit');
        if (!(is_array($this->orderbooks) && array_key_exists($symbol, $this->orderbooks))) {
            $this->orderbooks[$symbol] = $this->order_book(array(), $limit);
        }
        $orderbook = $this->safe_value($this->orderbooks, $symbol);
        if ($n === 'SubscribeToOrderBook') {
            $orderbook['nonce'] = 0;
            $this->handle_order_book_message($client, $message, $orderbook);
            for ($i = 0; $i < count($orderbook->cache); $i++) {
                $message = $orderbook->cache[$i];
                $this->handle_order_book_message($client, $message, $orderbook);
            }
            $client->resolve ($orderbook, $messageHash);
        } else {
            if ($orderbook['nonce'] === null) {
                $orderbook->cache[] = $message;
            } else {
                $this->handle_order_book_message($client, $message, $orderbook);
                $client->resolve ($orderbook, $messageHash);
            }
        }
    }

    public function watch_orders($symbol = null, $since = null, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $name = 'orders';
        $subscriptionHash = $name;
        $messageHash = $name;
        if ($symbol !== null) {
            $messageHash .= ':' . $symbol;
        }
        $url = $this->get_signed_url();
        $request = array(
            'n' => 'SubscribeToOrders',
            'o' => array(),
        );
        $subscription = array(
            'messageHash' => $messageHash,
            'name' => $name,
            'symbol' => $symbol,
            'method' => array($this, 'handle_order_book'),
            'limit' => $limit,
            'params' => $params,
        );
        $message = array_merge($request, $params);
        $orders = yield $this->watch($url, $messageHash, $message, $subscriptionHash, $subscription);
        if ($this->newUpdates) {
            $limit = $orders->getLimit ($symbol, $limit);
        }
        return $this->filter_by_symbol_since_limit($orders, $symbol, $since, $limit, true);
    }

    public function parse_ws_order_status($status) {
        $statuses = array(
            '1' => 'open',
            '2' => 'canceled',
            '3' => 'closed',
            '4' => 'open',
            // '5' => 'reserved',
        );
        return $this->safe_string($statuses, $status, $status);
    }

    public function parse_ws_order_type($orderType) {
        $types = array(
            '1' => 'limit',
            '2' => 'market',
        );
        return $this->safe_string($types, $orderType, $orderType);
    }

    public function parse_ws_order_side($side) {
        $sides = array(
            '1' => 'buy',
            '2' => 'sell',
        );
        return $this->safe_string($sides, $side, $side);
    }

    public function parse_ws_time_in_force($timeInForce) {
        $timeInForces = array(
            '0' => 'GTC',
            '1' => 'PO',
            '2' => 'IOC',
            '3' => 'FOK',
        );
        return $this->safe_string($timeInForces, $timeInForce, $timeInForce);
    }

    public function parse_ws_order($order, $market = null) {
        //
        //     {
        //         "orderId" => 327347,           // $order ID
        //         "$status" => 1,                 // 1(not $filled), 2(canceled), 3(completely $filled), 4(partially $filled), 5(reserved)
        //         "$side" => 2,                   // 1(bid), 2(ask)
        //         "$type" => 1,                   // 1(limit), 2($market)
        //         "$price" => 5500000,            // $price
        //         "orgAmount" => 1,              // initially placed $amount
        //         "remainAmount" => 1,           // unfilled or $remaining $amount
        //         "createdAt" => 1597218137,     // placement time
        //         "updatedAt" => 1597218137,     // last update time
        //         "tradedBaseAmount" => 0,       // $filled base asset $amount (in ZEC for this case)
        //         "tradedQuoteAmount" => 0,      // $filled quote asset $amount (in KRW for this case)
        //         "feeAmount" => 0,              // $fee $amount
        //         "rewardAmount" => 0,           // reward $amount
        //         "$timeInForce" => 0,            // 0(gtc), 1(post only), 2(ioc), 3(fok)
        //         "protection" => 1,             // 1(not applied), 2(applied)
        //         "forcedCompletionReason" => 0, // 0(n/a), 1($timeInForce), 2(protection)
        //         "$stopPrice" => 0,              // stop $price (> 0 only for stop orders)
        //         "takerFeeAmount" => 0,         // $fee $amount paid as a taker position
        //         "tradingPairName" => "ZEC-KRW" // $order book
        //     }
        //
        $id = $this->safe_string($order, 'orderId');
        $clientOrderId = $this->safe_string($order, 'clientOrderId');
        $timestamp = $this->safe_timestamp($order, 'createdAt');
        $type = $this->parse_ws_order_type($this->safe_string($order, 'type'));
        $side = $this->parse_ws_order_side($this->safe_string($order, 'side'));
        $timeInForce = $this->parse_ws_time_in_force($this->safe_string($order, 'timeInForce'));
        $price = $this->safe_float($order, 'price');
        $amount = $this->safe_float($order, 'orgAmount');
        $stopPrice = $this->safe_float($order, 'stopPrice');
        $remaining = $this->safe_float($order, 'remainAmount');
        $marketId = $this->safe_string($order, 'tradingPairName');
        $market = $this->safe_market($marketId, $market, '-');
        $status = $this->parse_ws_order_status($this->safe_string($order, 'status'));
        $filled = $this->safe_float($order, 'tradedBaseAmount');
        $cost = $this->safe_float($order, 'tradedQuoteAmount');
        $updated = null;
        if (($amount !== null) && ($remaining !== null)) {
            $filled = max (0, $amount - $remaining);
            if ($filled > 0) {
                $updated = $this->safe_timestamp($order, 'updatedAt');
            }
            if ($price !== null) {
                $cost = $filled * $price;
            }
        }
        $postOnly = null;
        if ($timeInForce !== null) {
            $postOnly = ($timeInForce === 'PO');
        }
        $fee = null;
        return array(
            'id' => $id,
            'clientOrderId' => $clientOrderId,
            'datetime' => $this->iso8601($timestamp),
            'timestamp' => $timestamp,
            'lastTradeTimestamp' => $updated,
            'status' => $status,
            'symbol' => $market['symbol'],
            'type' => $type,
            'timeInForce' => $timeInForce,
            'postOnly' => $postOnly,
            'side' => $side,
            'price' => $price,
            'stopPrice' => $stopPrice,
            'average' => $price,
            'amount' => $amount,
            'filled' => $filled,
            'remaining' => $remaining,
            'cost' => $cost,
            'trades' => null,
            'fee' => $fee,
            'info' => $order,
        );
    }

    public function handle_order($client, $message, $order, $market = null) {
        $parsed = $this->parse_ws_order($order);
        if ($this->orders === null) {
            $limit = $this->safe_integer($this->options, 'ordersLimit', 1000);
            $this->orders = new ArrayCacheBySymbolById ($limit);
        }
        $orders = $this->orders;
        $orders->append ($parsed);
        return $parsed;
    }

    public function handle_orders($client, $message) {
        //
        // subscription response
        //
        //
        //     {
        //         "n" => "SubscribeToOrders",
        //         "$o" => {
        //             "$data" => array(
        //                 {
        //                     "orderId" => 327347,           // $order ID
        //                     "status" => 1,                 // 1(not filled), 2(canceled), 3(completely filled), 4(partially filled), 5(reserved)
        //                     "side" => 2,                   // 1(bid), 2(ask)
        //                     "type" => 1,                   // 1(limit), 2(market)
        //                     "price" => 5500000,            // price
        //                     "orgAmount" => 1,              // initially placed amount
        //                     "remainAmount" => 1,           // unfilled or remaining amount
        //                     "createdAt" => 1597218137,     // placement time
        //                     "updatedAt" => 1597218137,     // last update time
        //                     "tradedBaseAmount" => 0,       // filled base asset amount (in ZEC for this case)
        //                     "tradedQuoteAmount" => 0,      // filled quote asset amount (in KRW for this case)
        //                     "feeAmount" => 0,              // fee amount
        //                     "rewardAmount" => 0,           // reward amount
        //                     "timeInForce" => 0,            // 0(gtc), 1(post only), 2(ioc), 3(fok)
        //                     "protection" => 1,             // 1(not applied), 2(applied)
        //                     "forcedCompletionReason" => 0, // 0(n/a), 1(timeInForce), 2(protection)
        //                     "stopPrice" => 0,              // stop price (> 0 only for stop orders)
        //                     "takerFeeAmount" => 0,         // fee amount paid as a taker position
        //                     "tradingPairName" => "ZEC-KRW" // $order book
        //                 }
        //             )
        //         }
        //     }
        //
        // delta update
        //
        //     {
        //         "$i" => -1,                         // always -1 in case of delta push
        //         "n" => "OrderEvent",
        //         "$o" => {
        //             "orderId" => 327347,
        //             "status" => 4,                 // changed to 4(partially filled)
        //             "side" => 2,
        //             "type" => 1,
        //             "price" => 5500000,
        //             "orgAmount" => 1,
        //             "remainAmount" => 0.8,         // -0.2 as 0.2 ZEC is filled
        //             "createdAt" => 1597218137,
        //             "updatedAt" => 1599093631,     // updated
        //             "tradedBaseAmount" => 0.2,     // 0.2 ZEC goes out
        //             "tradedQuoteAmount" => 1100000,// 1,100,000 KRW comes in
        //             "feeAmount" => 440,            // fee amount (in KRW and 0.04% for this case)
        //             "rewardAmount" => 0,
        //             "timeInForce" => 0,
        //             "protection" => 1,
        //             "forcedCompletionReason" => 0,
        //             "stopPrice" => 0,
        //             "takerFeeAmount" => 0,
        //             "tradingPairName" => "ZEC-KRW"
        //         }
        //     }
        //
        $o = $this->safe_value($message, 'o', array());
        $data = $this->safe_value($o, 'data');
        $messageHash = 'orders';
        if ($data === null) {
            // single $order delta update
            $order = $this->handle_order($client, $message, $data);
            $symbol = $order['symbol'];
            $client->resolve ($this->orders, $messageHash);
            $client->resolve ($this->orders, $messageHash . ':' . $symbol);
        } else {
            // initial subscription response with multiple orders
            $dataLength = is_array($data) ? count($data) : 0;
            if ($dataLength > 0) {
                $symbols = array();
                for ($i = 0; $i < $dataLength; $i++) {
                    $order = $this->handle_order($client, $message, $data[$i]);
                    $symbol = $order['symbol'];
                    $symbols[$symbol] = true;
                }
                $client->resolve ($this->orders, $messageHash);
                $keys = is_array($symbols) ? array_keys($symbols) : array();
                for ($i = 0; $i < count($keys); $i++) {
                    $symbol = $keys[$i];
                    $client->resolve ($this->orders, $messageHash . ':' . $symbol);
                }
            }
        }
    }

    public function watch_my_trades($symbol = null, $since = null, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $name = 'myTrades';
        $subscriptionHash = $name;
        $messageHash = $name;
        if ($symbol !== null) {
            $messageHash .= ':' . $symbol;
        }
        $url = $this->get_signed_url();
        $request = array(
            'n' => 'SubscribeToTrades',
            'o' => array(),
        );
        $subscription = array(
            'messageHash' => $messageHash,
            'name' => $name,
            'symbol' => $symbol,
            'method' => array($this, 'handle_my_trades'),
            'limit' => $limit,
            'params' => $params,
        );
        $message = array_merge($request, $params);
        $trades = yield $this->watch($url, $messageHash, $message, $subscriptionHash, $subscription);
        if ($this->newUpdates) {
            $limit = $trades->getLimit ($symbol, $limit);
        }
        return $this->filter_by_since_limit($trades, $since, $limit, 'timestamp', true);
    }

    public function handle_my_trades($client, $message) {
        //
        // subscription response
        //
        //     $array( n => 'SubscribeToTrades', $o => $array() )
        //
        //  regular update
        //
        //     {
        //         "i" => -1,
        //         "n" => "TradeEvent",
        //         "$o" => {
        //             "tradeId" => 74072,            // $trade ID
        //             "orderId" => 453529,           // order ID
        //             "side" => 2,                   // 1(bid), 2(ask)
        //             "type" => 1,                   // 1($limit), 2(market)
        //             "baseAmount" => 0.01,          // filled base asset amount (in ZEC for this case)
        //             "quoteAmount" => 1,            // filled quote asset amount (in KRW for this case)
        //             "fee" => 0.0004,               // fee
        //             "price" => 100,                // price
        //             "isSelfTrade" => false,        // whether both of matching orders are yours
        //             "occurredAt" => 1603932107,    // $trade occurrence time
        //             "tradingPairName" => "ZEC-KRW" // order book
        //         }
        //     }
        //
        $o = $this->safe_value($message, 'o', $array());
        $name = 'myTrades';
        $messageHash = $name;
        $trade = $this->parse_trade($o);
        $symbol = $trade['symbol'];
        $array = $this->safe_value($this->myTrades, $symbol);
        if ($array === null) {
            $limit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $array = new ArrayCache ($limit);
        }
        $array->append ($trade);
        $this->myTrades[$symbol] = $array;
        $client->resolve ($array, $messageHash);
        $client->resolve ($array, $messageHash . ':' . $symbol);
    }

    public function watch_balance($params = array ()) {
        yield $this->load_markets();
        $name = 'balance';
        $messageHash = $name;
        $url = $this->get_signed_url();
        $request = array(
            'n' => 'SubscribeToBalances',
            'o' => array(),
        );
        $subscription = array(
            'messageHash' => $messageHash,
            'name' => $name,
            'params' => $params,
        );
        $message = array_merge($request, $params);
        return yield $this->watch($url, $messageHash, $message, $messageHash, $subscription);
    }

    public function handle_balance($client, $message) {
        //
        //     {
        //         n => 'SubscribeToBalances',
        //         $o => array(
        //             result => true,
        //             $data => array(
        //                 array( assetId => 1, avail => 30000.74103433, hold => 0, pendingWithdrawal => 0, blendedPrice => 1, lastUpdatedAt => 1609519939.412, isoAlpha3 => 'KRW' ),
        //                 array( assetId => 3, avail => 0, hold => 0, pendingWithdrawal => 0, blendedPrice => 0, lastUpdatedAt => 0, isoAlpha3 => 'ETH' ),
        //                 array( assetId => 4, avail => 0, hold => 0, pendingWithdrawal => 0, blendedPrice => 0, lastUpdatedAt => 0, isoAlpha3 => 'BTC' ),
        //             ),
        //         ),
        //     }
        //
        //     {
        //         "i" => -1,                             // always -1 in case of delta push
        //         "n" => "BalanceEvent",
        //         "$o" => {
        //             "assetId" => 7,
        //             "avail" => 990.4998,               // +1 as you can use 1 ZEC more to place a new order
        //             "hold" => 1,                       // -1 as you take it back from an order book
        //             "pendingWithdrawal" => 0,
        //             "blendedPrice" => 429413.08986192,
        //             "lastUpdatedAt" => 1599098077.27,
        //             "isoAlpha3" => "ZEC"
        //         }
        //     }
        //
        $o = $this->safe_value($message, 'o');
        $data = $this->safe_value($o, 'data');
        if ($data === null) {
            $balance = $this->parse_balance_response(array( $o ));
            $this->balance = $this->parse_balance(array_merge($this->balance, $balance));
        } else {
            $this->balance = $this->parse_balance_response($data);
        }
        $messageHash = 'balance';
        $client->resolve ($this->balance, $messageHash);
    }

    public function pong($client, $message) {
        //
        //     "primus::ping::1609504526621"
        //
        $messageString = json_decode($message, $as_associative_array = true);
        $parts = explode('::', $messageString);
        $requestId = $this->safe_string($parts, 2);
        $response = 'primus::pong::' . $requestId;
        yield $client->send ($response);
    }

    public function handle_ping($client, $message) {
        $this->spawn(array($this, 'pong'), $client, $message);
    }

    public function handle_message($client, $message) {
        //
        // ping string $message
        //
        //     "primus::ping::1609504526621"
        //
        // regular json $message
        //
        //     {
        //         $n => 'SubscribeToOrderBook',
        //         o => {
        //             ask => array(
        //                 array( entryId => 60490601, price => 32061000, volume => 0.09996, updatedAt => 1609412729.325 ),
        //                 array( entryId => 60490959, price => 32078000, volume => 0.206, updatedAt => 1609412735.793 ),
        //                 array( entryId => 60490687, price => 32085000, volume => 0.192, updatedAt => 1609412730.373 ),
        //             ),
        //             bid => array(
        //                 array( entryId => 60491143, price => 32059000, volume => 0.3118, updatedAt => 1609412740.011 ),
        //                 array( entryId => 60490948, price => 32058000, volume => 0.00162449, updatedAt => 1609412735.555 ),
        //                 array( entryId => 60488158, price => 32053000, volume => 0.206, updatedAt => 1609412680.169 ),
        //             ),
        //             tradingPairName => 'BTC-KRW',
        //             maxEntryId => 60491355
        //         }
        //     }
        //
        if (gettype($message) === 'string') {
            $this->handle_ping($client, $message);
        } else {
            $methods = array(
                'OrderBookEvent' => array($this, 'handle_order_book'),
                'SubscribeToOrderBook' => array($this, 'handle_order_book'),
                // 'SubscribeToTrades' => array($this, 'handle_my_trades'),
                'TradeEvent' => array($this, 'handle_my_trades'),
                'SubscribeToOrders' => array($this, 'handle_orders'),
                'OrderEvent' => array($this, 'handle_orders'),
                'SubscribeToBalances' => array($this, 'handle_balance'),
                'BalanceEvent' => array($this, 'handle_balance'),
            );
            $n = $this->safe_string($message, 'n');
            $method = $this->safe_value($methods, $n);
            if ($method !== null) {
                return $method($client, $message);
            }
        }
        return $message;
    }
}
