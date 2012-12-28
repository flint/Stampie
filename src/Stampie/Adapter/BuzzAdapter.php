<?php

/*
 * (c) Henrik Bjornskov <henrik@bjrnskov.dk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stampie\Adapter;

use Buzz\Browser;

/**
 * @package Stampie
 */
class BuzzAdapter implements AdapterInterface
{
    protected $browser;

    /**
     * @param Browser $browser
     */
    public function __construct(Browser $browser)
    {
        $this->browser = $browser;
    }

    /**
     * {@inheritDoc}
     */
    public function call(Request $request)
    {
        $response = $this->browser->post($request->getBody(), $request->getHeaders(), $request->getBody());

        var_dump($response);
    }
}
