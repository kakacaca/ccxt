'use strict';

//  ---------------------------------------------------------------------------

const ccxt = require ('ccxt');
const { ExchangeError } = require ('ccxt/js/base/errors');

//  ---------------------------------------------------------------------------

module.exports = class kucoin extends ccxt.kucoin {
    describe () {
        return this.deepExtend (super.describe (), {
            'has': {
                'watchOrderBook': true,
            },
            'options': {
                'watchOrderBookRate': 100, // get updates every 100ms or 1000ms
            },
            'streaming': {
                'keepAlive': false,
            },
        });
    }

    async watchOrderBook (symbol, limit = undefined, params = {}) {
        if (limit !== undefined) {
            if ((limit !== 20) && (limit !== 100)) {
                throw new ExchangeError (this.id + ' watchOrderBook limit argument must be undefined, 20 or 100');
            }
        }
        await this.loadMarkets ();
        const market = this.market (symbol);
        //
        // https://docs.kucoin.com/#level-2-market-data
        //
        // 1. After receiving the websocket Level 2 data flow, cache the data.
        // 2. Initiate a REST request to get the snapshot data of Level 2 order book.
        // 3. Playback the cached Level 2 data flow.
        // 4. Apply the new Level 2 data flow to the local snapshot to ensure that
        // the sequence of the new Level 2 update lines up with the sequence of
        // the previous Level 2 data. Discard all the message prior to that
        // sequence, and then playback the change to snapshot.
        // 5. Update the level2 full data based on sequence according to the
        // size. If the price is 0, ignore the messages and update the sequence.
        // If the size=0, update the sequence and remove the price of which the
        // size is 0 out of level 2. For other cases, please update the price.
        //
        let tokenResponse = this.safeValue (this.options, 'tokenResponse');
        if (tokenResponse === undefined) {
            const throwException = false;
            if (this.checkRequiredCredentials (throwException)) {
                tokenResponse = await this.privatePostBulletPrivate ();
                //
                //     {
                //         code: "200000",
                //         data: {
                //             instanceServers: [
                //                 {
                //                     pingInterval:  50000,
                //                     endpoint: "wss://push-private.kucoin.com/endpoint",
                //                     protocol: "websocket",
                //                     encrypt: true,
                //                     pingTimeout: 10000
                //                 }
                //             ],
                //             token: "2neAiuYvAU61ZDXANAGAsiL4-iAExhsBXZxftpOeh_55i3Ysy2q2LEsEWU64mdzUOPusi34M_wGoSf7iNyEWJ1UQy47YbpY4zVdzilNP-Bj3iXzrjjGlWtiYB9J6i9GjsxUuhPw3BlrzazF6ghq4Lzf7scStOz3KkxjwpsOBCH4=.WNQmhZQeUKIkh97KYgU0Lg=="
                //         }
                //     }
                //
            } else {
                tokenResponse = await this.publicPostBulletPublic ();
            }
            this.options['tokenResponse'] = tokenResponse;
        }
        const data = this.safeValue (tokenResponse, 'data', {});
        const instanceServers = this.safeValue (data, 'instanceServers', []);
        const firstServer = this.safeValue (instanceServers, 0, {});
        const endpoint = this.safeString (firstServer, 'endpoint');
        const token = this.safeString (data, 'token');
        const nonce = this.nonce ();
        const query = {
            'token': token,
            'acceptUserMessage': 'true',
            // 'connectId': nonce, // user-defined id is supported, received by handleSystemStatus
        };
        const url = endpoint + '?' + this.urlencode (query);
        const topic = '/market/level2';
        const messageHash = topic + ':' + market['id'];
        const subscribe = {
            'id': nonce,
            'type': 'subscribe',
            'topic': messageHash,
            'response': true,
        };
        const subscription = {
            'id': nonce.toString (),
            'symbol': symbol,
            'topic': topic,
            'messageHash': messageHash,
            'method': this.handleOrderBookSubscription,
            'limit': limit,
        };
        const request = this.extend (subscribe, params);
        const future = this.watch (url, messageHash, request, messageHash, subscription);
        return await this.after (future, this.limitOrderBook, symbol, limit, params);
    }

    limitOrderBook (orderbook, symbol, limit = undefined, params = {}) {
        return orderbook.limit (limit);
    }

    async fetchOrderBookSnapshot (client, message, subscription) {
        const symbol = this.safeString (subscription, 'symbol');
        const limit = this.safeInteger (subscription, 'limit');
        const messageHash = this.safeString (subscription, 'messageHash');
        // 2. Initiate a REST request to get the snapshot data of Level 2 order book.
        // todo: this is a synch blocking call in ccxt.php - make it async
        const snapshot = await this.fetchOrderBook (symbol, limit);
        const orderbook = this.orderbooks[symbol];
        orderbook.reset (snapshot);
        // unroll the accumulated deltas
        const messages = orderbook.cache;
        // 3. Playback the cached Level 2 data flow.
        for (let i = 0; i < messages.length; i++) {
            const message = messages[i];
            this.handleOrderBookMessage (client, message, orderbook);
        }
        this.orderbooks[symbol] = orderbook;
        client.resolve (orderbook, messageHash);
    }

    handleDelta (bookside, delta, nonce) {
        const price = this.safeFloat (delta, 0);
        if (price > 0) {
            const sequence = this.safeInteger (delta, 2);
            if (sequence > nonce) {
                const amount = this.safeFloat (delta, 1);
                bookside.store (price, amount);
            }
        }
    }

    handleDeltas (bookside, deltas, nonce) {
        for (let i = 0; i < deltas.length; i++) {
            this.handleDelta (bookside, deltas[i], nonce);
        }
    }

    handleOrderBookMessage (client, message, orderbook) {
        //
        //     {
        //         "type":"message",
        //         "topic":"/market/level2:BTC-USDT",
        //         "subject":"trade.l2update",
        //         "data":{
        //             "sequenceStart":1545896669105,
        //             "sequenceEnd":1545896669106,
        //             "symbol":"BTC-USDT",
        //             "changes": {
        //                 "asks": [["6","1","1545896669105"]], // price, size, sequence
        //                 "bids": [["4","1","1545896669106"]]
        //             }
        //         }
        //     }
        //
        const data = this.safeValue (message, 'data', {});
        const sequenceEnd = this.safeInteger (data, 'sequenceEnd');
        // 4. Apply the new Level 2 data flow to the local snapshot to ensure that
        // the sequence of the new Level 2 update lines up with the sequence of
        // the previous Level 2 data. Discard all the message prior to that
        // sequence, and then playback the change to snapshot.
        if (sequenceEnd > orderbook['nonce']) {
            const sequenceStart = this.safeInteger (message, 'sequenceStart');
            if ((sequenceStart !== undefined) && ((sequenceStart - 1) > orderbook['nonce'])) {
                // todo: client.reject from handleOrderBookMessage properly
                throw new ExchangeError (this.id + ' handleOrderBook received an out-of-order nonce');
            }
            const changes = this.safeValue (data, 'changes', {});
            let asks = this.safeValue (changes, 'asks', []);
            let bids = this.safeValue (changes, 'bids', []);
            asks = this.sortBy (asks, 2); // sort by sequence
            bids = this.sortBy (bids, 2);
            // 5. Update the level2 full data based on sequence according to the
            // size. If the price is 0, ignore the messages and update the sequence.
            // If the size=0, update the sequence and remove the price of which the
            // size is 0 out of level 2. For other cases, please update the price.
            this.handleDeltas (orderbook['asks'], asks, orderbook['nonce']);
            this.handleDeltas (orderbook['bids'], bids, orderbook['nonce']);
            orderbook['nonce'] = sequenceEnd;
            orderbook['timestamp'] = undefined;
            orderbook['datetime'] = undefined;
        }
        return orderbook;
    }

    handleOrderBook (client, message) {
        //
        // initial snapshot is fetched with ccxt's fetchOrderBook
        // the feed does not include a snapshot, just the deltas
        //
        //     {
        //         "type":"message",
        //         "topic":"/market/level2:BTC-USDT",
        //         "subject":"trade.l2update",
        //         "data":{
        //             "sequenceStart":1545896669105,
        //             "sequenceEnd":1545896669106,
        //             "symbol":"BTC-USDT",
        //             "changes": {
        //                 "asks": [["6","1","1545896669105"]], // price, size, sequence
        //                 "bids": [["4","1","1545896669106"]]
        //             }
        //         }
        //     }
        //
        const messageHash = this.safeString (message, 'topic');
        const data = this.safeValue (message, 'data');
        const marketId = this.safeString (data, 'symbol');
        let market = undefined;
        let symbol = undefined;
        if (marketId !== undefined) {
            if (marketId in this.markets_by_id) {
                market = this.markets_by_id[marketId];
                symbol = market['symbol'];
            }
        }
        const orderbook = this.orderbooks[symbol];
        if (orderbook['nonce'] !== undefined) {
            this.handleOrderBookMessage (client, message, orderbook);
            client.resolve (orderbook, messageHash);
        } else {
            // 1. After receiving the websocket Level 2 data flow, cache the data.
            orderbook.cache.push (message);
        }
    }

    signMessage (client, messageHash, message, params = {}) {
        // todo: implement kucoin signMessage
        return message;
    }

    handleOrderBookSubscription (client, message, subscription) {
        const symbol = this.safeString (subscription, 'symbol');
        const limit = this.safeString (subscription, 'limit');
        if (symbol in this.orderbooks) {
            delete this.orderbooks[symbol];
        }
        this.orderbooks[symbol] = this.limitedOrderBook ({}, limit);
        // fetch the snapshot in a separate async call
        this.spawn (this.fetchOrderBookSnapshot, client, message, subscription);
    }

    handleSubscriptionStatus (client, message) {
        //
        //     {
        //         id: '1578090438322',
        //         type: 'ack'
        //     }
        //
        const id = this.safeString (message, 'id');
        const subscriptionsById = this.indexBy (client.subscriptions, 'id');
        const subscription = this.safeValue (subscriptionsById, id, {});
        const method = this.safeValue (subscription, 'method');
        if (method !== undefined) {
            this.call (method, client, message, subscription);
        }
        return message;
    }

    handleSystemStatus (client, message) {
        //
        // todo: answer the question whether handleSystemStatus should be renamed
        // and unified as handleStatus for any usage pattern that
        // involves system status and maintenance updates
        //
        //     {
        //         id: '1578090234088', // connectId
        //         type: 'welcome',
        //     }
        //
        console.log (message);
        return message;
    }

    handleSubject (client, message) {
        //
        //     {
        //         "type":"message",
        //         "topic":"/market/level2:BTC-USDT",
        //         "subject":"trade.l2update",
        //         "data":{
        //             "sequenceStart":1545896669105,
        //             "sequenceEnd":1545896669106,
        //             "symbol":"BTC-USDT",
        //             "changes": {
        //                 "asks": [["6","1","1545896669105"]], // price, size, sequence
        //                 "bids": [["4","1","1545896669106"]]
        //             }
        //         }
        //     }
        //
        const subject = this.safeString (message, 'subject');
        const methods = {
            'trade.l2update': this.handleOrderBook,
        };
        const method = this.safeValue (methods, subject);
        if (method === undefined) {
            return message;
        } else {
            return this.call (method, client, message);
        }
    }

    handleErrorMessage (client, message) {
        return message;
    }

    handleMessage (client, message) {
        if (this.handleErrorMessage (client, message)) {
            const type = this.safeString (message, 'type');
            const methods = {
                // 'heartbeat': this.handleHeartbeat,
                'welcome': this.handleSystemStatus,
                'ack': this.handleSubscriptionStatus,
                'message': this.handleSubject,
            };
            const method = this.safeValue (methods, type);
            if (method === undefined) {
                return message;
            } else {
                return this.call (method, client, message);
            }
        }
    }
};