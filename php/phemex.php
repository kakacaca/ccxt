<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\NotSupported;
use \ccxt\Precise;

class phemex extends \ccxt\async\phemex {

    use ClientTrait;

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'has' => array(
                'ws' => true,
                'watchTicker' => true,
                'watchTickers' => false, // for now
                'watchTrades' => true,
                'watchOrderBook' => true,
                'watchOHLCV' => true,
            ),
            'urls' => array(
                'test' => array(
                    'ws' => 'wss://testnet.phemex.com/ws',
                ),
                'api' => array(
                    'ws' => 'wss://phemex.com/ws',
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'OHLCVLimit' => 1000,
            ),
            'streaming' => array(
                'keepAlive' => 20000,
            ),
        ));
    }

    public function from_en($en, $scale) {
        if ($en === null) {
            return null;
        }
        $precise = new Precise ($en);
        $precise->decimals = $this->sum($precise->decimals, $scale);
        $precise->reduce ();
        return (string) $precise;
    }

    public function from_ep($ep, $market = null) {
        if (($ep === null) || ($market === null)) {
            return $ep;
        }
        return $this->from_en($ep, $this->safe_integer($market, 'priceScale'));
    }

    public function from_ev($ev, $market = null) {
        if (($ev === null) || ($market === null)) {
            return $ev;
        }
        return $this->from_en($ev, $this->safe_integer($market, 'valueScale'));
    }

    public function from_er($er, $market = null) {
        if (($er === null) || ($market === null)) {
            return $er;
        }
        return $this->from_en($er, $this->safe_integer($market, 'ratioScale'));
    }

    public function request_id() {
        $requestId = $this->sum($this->safe_integer($this->options, 'requestId', 0), 1);
        $this->options['requestId'] = $requestId;
        return $requestId;
    }

    public function parse_swap_ticker($ticker, $market = null) {
        //
        //     {
        //         close => 442800,
        //         fundingRate => 10000,
        //         high => 445400,
        //         indexPrice => 442621,
        //         low => 428400,
        //         markPrice => 442659,
        //         $open => 432200,
        //         openInterest => 744183,
        //         predFundingRate => 10000,
        //         $symbol => 'LTCUSD',
        //         turnover => 8133238294,
        //         volume => 934292
        //     }
        //
        $marketId = $this->safe_string($ticker, 'symbol');
        $market = $this->safe_market($marketId, $market);
        $symbol = $market['symbol'];
        $timestamp = $this->safe_integer_product($ticker, 'timestamp', 0.000001);
        $lastString = $this->from_ep($this->safe_string($ticker, 'close'), $market);
        $last = $this->parse_number($lastString);
        $quoteVolume = $this->parse_number($this->from_ev($this->safe_string($ticker, 'turnover'), $market));
        $baseVolume = $this->parse_number($this->from_ev($this->safe_string($ticker, 'volume'), $market));
        $change = null;
        $percentage = null;
        $average = null;
        $openString = $this->omit_zero($this->from_ep($this->safe_string($ticker, 'open'), $market));
        $open = $this->parse_number($openString);
        if (($openString !== null) && ($lastString !== null)) {
            $change = $this->parse_number(Precise::string_sub($lastString, $openString));
            $average = $this->parse_number(Precise::string_div(Precise::string_add($lastString, $openString), '2'));
            $percentage = $this->parse_number(Precise::string_mul(Precise::string_sub(Precise::string_div($lastString, $openString), '1'), '100'));
        }
        $result = array(
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'high' => $this->parse_number($this->from_ep($this->safe_string($ticker, 'high'), $market)),
            'low' => $this->parse_number($this->from_ep($this->safe_string($ticker, 'low'), $market)),
            'bid' => null,
            'bidVolume' => null,
            'ask' => null,
            'askVolume' => null,
            'vwap' => null,
            'open' => $open,
            'close' => $last,
            'last' => $last,
            'previousClose' => null, // previous day close
            'change' => $change,
            'percentage' => $percentage,
            'average' => $average,
            'baseVolume' => $baseVolume,
            'quoteVolume' => $quoteVolume,
            'info' => $ticker,
        );
        return $result;
    }

    public function handle_ticker($client, $message) {
        //
        //     {
        //         spot_market24h => array(
        //             askEp => 958148000000,
        //             bidEp => 957884000000,
        //             highEp => 962000000000,
        //             lastEp => 958220000000,
        //             lowEp => 928049000000,
        //             openEp => 935597000000,
        //             $symbol => 'sBTCUSDT',
        //             turnoverEv => 146074214388978,
        //             volumeEv => 15492228900
        //         ),
        //         $timestamp => 1592847265888272100
        //     }
        //
        // swap
        //
        //     {
        //         market24h => array(
        //             close => 442800,
        //             fundingRate => 10000,
        //             high => 445400,
        //             indexPrice => 442621,
        //             low => 428400,
        //             markPrice => 442659,
        //             open => 432200,
        //             openInterest => 744183,
        //             predFundingRate => 10000,
        //             $symbol => 'LTCUSD',
        //             turnover => 8133238294,
        //             volume => 934292
        //         ),
        //         $timestamp => 1592845585373374500
        //     }
        //
        $name = 'market24h';
        $ticker = $this->safe_value($message, $name);
        $result = null;
        if ($ticker === null) {
            $name = 'spot_market24h';
            $ticker = $this->safe_value($message, $name);
            $result = $this->parse_ticker($ticker);
        } else {
            $result = $this->parse_swap_ticker($ticker);
        }
        $symbol = $result['symbol'];
        $messageHash = $name . ':' . $symbol;
        $timestamp = $this->safe_integer_product($message, 'timestamp', 0.000001);
        $result['timestamp'] = $timestamp;
        $result['datetime'] = $this->iso8601($timestamp);
        $this->tickers[$symbol] = $result;
        $client->resolve ($result, $messageHash);
    }

    public function watch_balance($params = array ()) {
        yield $this->load_markets();
        throw new NotSupported($this->id . ' watchBalance() not implemented yet');
    }

    public function handle_trades($client, $message) {
        //
        //     {
        //         sequence => 1795484727,
        //         $symbol => 'sBTCUSDT',
        //         $trades => array(
        //             array( 1592891002064516600, 'Buy', 964020000000, 1431000 ),
        //             array( 1592890978987934500, 'Sell', 963704000000, 1401800 ),
        //             array( 1592890972918701800, 'Buy', 963938000000, 2018600 ),
        //         ),
        //         type => 'snapshot'
        //     }
        //
        $name = 'trade';
        $marketId = $this->safe_string($message, 'symbol');
        $market = $this->safe_market($marketId);
        $symbol = $market['symbol'];
        $messageHash = $name . ':' . $symbol;
        $stored = $this->safe_value($this->trades, $symbol);
        if ($stored === null) {
            $limit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $stored = new ArrayCache ($limit);
            $this->trades[$symbol] = $stored;
        }
        $trades = $this->safe_value($message, 'trades', array());
        $parsed = $this->parse_trades($trades, $market);
        for ($i = 0; $i < count($parsed); $i++) {
            $stored->append ($parsed[$i]);
        }
        $client->resolve ($stored, $messageHash);
    }

    public function handle_ohlcv($client, $message) {
        //
        //     {
        //         kline => array(
        //             array( 1592905200, 60, 960688000000, 960709000000, 960709000000, 960400000000, 960400000000, 848100, 8146756046 ),
        //             array( 1592905140, 60, 960718000000, 960716000000, 960717000000, 960560000000, 960688000000, 4284900, 41163743512 ),
        //             array( 1592905080, 60, 960513000000, 960684000000, 960718000000, 960684000000, 960718000000, 4880500, 46887494349 ),
        //         ),
        //         sequence => 1804401474,
        //         $symbol => 'sBTCUSDT',
        //         type => 'snapshot'
        //     }
        //
        $name = 'kline';
        $marketId = $this->safe_string($message, 'symbol');
        $market = $this->safe_market($marketId);
        $symbol = $market['symbol'];
        $candles = $this->safe_value($message, $name, array());
        $first = $this->safe_value($candles, 0, array());
        $interval = $this->safe_string($first, 1);
        $timeframe = $this->find_timeframe($interval);
        if ($timeframe !== null) {
            $messageHash = $name . ':' . $timeframe . ':' . $symbol;
            $ohlcvs = $this->parse_ohlcvs($candles, $market);
            $this->ohlcvs[$symbol] = $this->safe_value($this->ohlcvs, $symbol, array());
            $stored = $this->safe_value($this->ohlcvs[$symbol], $timeframe);
            if ($stored === null) {
                $limit = $this->safe_integer($this->options, 'OHLCVLimit', 1000);
                $stored = new ArrayCacheByTimestamp ($limit);
                $this->ohlcvs[$symbol][$timeframe] = $stored;
            }
            for ($i = 0; $i < count($ohlcvs); $i++) {
                $candle = $ohlcvs[$i];
                $stored->append ($candle);
            }
            $client->resolve ($stored, $messageHash);
        }
    }

    public function watch_ticker($symbol, $params = array ()) {
        yield $this->load_markets();
        $market = $this->market($symbol);
        $name = $market['spot'] ? 'spot_market24h' : 'market24h';
        $url = $this->urls['api']['ws'];
        $requestId = $this->request_id();
        $subscriptionHash = $name . '.subscribe';
        $messageHash = $name . ':' . $symbol;
        $subscribe = array(
            'method' => $subscriptionHash,
            'id' => $requestId,
            'params' => array(),
        );
        $request = $this->deep_extend($subscribe, $params);
        return yield $this->watch($url, $messageHash, $request, $subscriptionHash);
    }

    public function watch_trades($symbol, $since = null, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $market = $this->market($symbol);
        $url = $this->urls['api']['ws'];
        $requestId = $this->request_id();
        $name = 'trade';
        $messageHash = $name . ':' . $symbol;
        $method = $name . '.subscribe';
        $subscribe = array(
            'method' => $method,
            'id' => $requestId,
            'params' => [
                $market['id'],
            ],
        );
        $request = $this->deep_extend($subscribe, $params);
        $trades = yield $this->watch($url, $messageHash, $request, $messageHash);
        if ($this->newUpdates) {
            $limit = $trades->getLimit ($symbol, $limit);
        }
        return $this->filter_by_since_limit($trades, $since, $limit, 'timestamp', true);
    }

    public function watch_order_book($symbol, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $market = $this->market($symbol);
        $url = $this->urls['api']['ws'];
        $requestId = $this->request_id();
        $name = 'orderbook';
        $messageHash = $name . ':' . $symbol;
        $method = $name . '.subscribe';
        $subscribe = array(
            'method' => $method,
            'id' => $requestId,
            'params' => [
                $market['id'],
            ],
        );
        $request = $this->deep_extend($subscribe, $params);
        $orderbook = yield $this->watch($url, $messageHash, $request, $messageHash);
        return $orderbook->limit ($limit);
    }

    public function watch_ohlcv($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        yield $this->load_markets();
        $market = $this->market($symbol);
        $url = $this->urls['api']['ws'];
        $requestId = $this->request_id();
        $name = 'kline';
        $messageHash = $name . ':' . $timeframe . ':' . $symbol;
        $method = $name . '.subscribe';
        $subscribe = array(
            'method' => $method,
            'id' => $requestId,
            'params' => [
                $market['id'],
                $this->safe_integer($this->timeframes, $timeframe),
            ],
        );
        $request = $this->deep_extend($subscribe, $params);
        $ohlcv = yield $this->watch($url, $messageHash, $request, $messageHash);
        if ($this->newUpdates) {
            $limit = $ohlcv->getLimit ($symbol, $limit);
        }
        return $this->filter_by_since_limit($ohlcv, $since, $limit, 0, true);
    }

    public function handle_delta($bookside, $delta, $market = null) {
        $bidAsk = $this->parse_bid_ask($delta, 0, 1, $market);
        $bookside->storeArray ($bidAsk);
    }

    public function handle_deltas($bookside, $deltas, $market = null) {
        for ($i = 0; $i < count($deltas); $i++) {
            $this->handle_delta($bookside, $deltas[$i], $market);
        }
    }

    public function handle_order_book($client, $message) {
        //
        //     {
        //         $book => array(
        //             $asks => array(
        //                 array( 960316000000, 6993800 ),
        //                 array( 960318000000, 13183000 ),
        //                 array( 960319000000, 9170200 ),
        //             ),
        //             $bids => array(
        //                 array( 959941000000, 8385300 ),
        //                 array( 959939000000, 10296600 ),
        //                 array( 959930000000, 3672400 ),
        //             )
        //         ),
        //         $depth => 30,
        //         sequence => 1805784701,
        //         $symbol => 'sBTCUSDT',
        //         $timestamp => 1592908460404461600,
        //         $type => 'snapshot'
        //     }
        //
        $marketId = $this->safe_string($message, 'symbol');
        $market = $this->safe_market($marketId);
        $symbol = $market['symbol'];
        $type = $this->safe_string($message, 'type');
        $depth = $this->safe_integer($message, 'depth');
        $name = 'orderbook';
        $messageHash = $name . ':' . $symbol;
        $nonce = $this->safe_integer($message, 'sequence');
        $timestamp = $this->safe_integer_product($message, 'timestamp', 0.000001);
        if ($type === 'snapshot') {
            $book = $this->safe_value($message, 'book', array());
            $snapshot = $this->parse_order_book($book, $symbol, $timestamp, 'bids', 'asks', 0, 1, $market);
            $snapshot['nonce'] = $nonce;
            $orderbook = $this->order_book($snapshot, $depth);
            $this->orderbooks[$symbol] = $orderbook;
            $client->resolve ($orderbook, $messageHash);
        } else {
            $orderbook = $this->safe_value($this->orderbooks, $symbol);
            if ($orderbook !== null) {
                $changes = $this->safe_value($message, 'book', array());
                $asks = $this->safe_value($changes, 'asks', array());
                $bids = $this->safe_value($changes, 'bids', array());
                $this->handle_deltas($orderbook['asks'], $asks, $market);
                $this->handle_deltas($orderbook['bids'], $bids, $market);
                $orderbook['nonce'] = $nonce;
                $orderbook['timestamp'] = $timestamp;
                $orderbook['datetime'] = $this->iso8601($timestamp);
                $this->orderbooks[$symbol] = $orderbook;
                $client->resolve ($orderbook, $messageHash);
            }
        }
    }

    public function handle_message($client, $message) {
        if ((is_array($message) && array_key_exists('market24h', $message)) || (is_array($message) && array_key_exists('spot_market24h', $message))) {
            return $this->handle_ticker($client, $message);
        } else if (is_array($message) && array_key_exists('trades', $message)) {
            return $this->handle_trades($client, $message);
        } else if (is_array($message) && array_key_exists('kline', $message)) {
            return $this->handle_ohlcv($client, $message);
        } else if (is_array($message) && array_key_exists('book', $message)) {
            return $this->handle_order_book($client, $message);
        } else {
            //
            //     array( error => null, id => 1, result => array( status => 'success' ) )
            //
            return $message;
        }
    }
}
