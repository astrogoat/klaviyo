<?php

namespace Astrogoat\Klaviyo;

class Klaviyo
{
    protected ?Events $events = null;

    public function events(): Events
    {
        if ($this->events === null) {
            $this->events = new Events();
        }

        return $this->events;
    }
}
