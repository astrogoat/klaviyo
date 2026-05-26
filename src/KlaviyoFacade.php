<?php

namespace Astrogoat\Klaviyo;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Astrogoat\Klaviyo\Klaviyo
 */
class KlaviyoFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'klaviyo';
    }
}
