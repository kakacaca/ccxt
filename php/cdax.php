<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import

class cdax extends huobipro {

    public function describe() {
        return $this->deep_extend(parent::describe (), array(
            'id' => 'cdax',
            'name' => 'CDAX',
            'countries' => array( 'RU' ),
            'hostname' => 'cdax.io',
            'urls' => array(
                'logo' => 'https://user-images.githubusercontent.com/1294454/102157692-fd406280-3e90-11eb-8d46-4511b617cd17.jpg',
                'api' => array(
                    'ws' => array(
                        'api' => array(
                            'public' => 'wss://{hostname}/api/ws',
                            'private' => 'wss://{hostname}/api/ws/v2',
                        ),
                    ),
                    'market' => 'https://{hostname}/api',
                    'public' => 'https://{hostname}/api',
                    'private' => 'https://{hostname}/api',
                    'v2Public' => 'https://{hostname}/api',
                    'v2Private' => 'https://{hostname}/api',
                ),
                'www' => 'https://cdax.io',
                'referral' => 'https://cdax.io/invite?invite_code=esc74',
                'doc' => 'https://github.com/cloudapidoc/API_Docs',
                'fees' => 'https://cdax.io/about/fee',
            ),
        ));
    }
}
