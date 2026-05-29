<?php

namespace Astrogoat\Klaviyo;

use Astrogoat\Klaviyo\Settings\KlaviyoSettings;
use KlaviyoAPI\KlaviyoAPI;
use KlaviyoAPI\ApiException;
use Illuminate\Support\Facades\Log;

class Events
{
    protected ?KlaviyoAPI $client = null;

    public function setClient(KlaviyoAPI $client): void
    {
        $this->client = $client;
    }

    public function getClient(): ?KlaviyoAPI
    {
        if ($this->client === null) {
            $settings = app(KlaviyoSettings::class);
            if (!$settings->enabled || empty($settings->private_api_key)) {
                return null;
            }

            $this->client = new KlaviyoAPI(
                $settings->private_api_key,
                num_retries: 3
            );
        }

        return $this->client;
    }

    public function saveForLater(string $shopifyId, array $eventData): mixed
    {
        $client = $this->getClient();

        if (!$client) {
            Log::warning('Klaviyo event not sent: Klaviyo is not enabled or Private API Key is not set.');
            return null;
        }

        $email = $eventData['email'] ?? null;
        if (!$email) {
            Log::warning('Klaviyo event not sent: Email is required.');
            return null;
        }

        $eventBody = [
            'data' => [
                'type' => 'event',
                'attributes' => [
                    'profile' => [
                        'email' => $email,
                    ],
                    'metric' => [
                        'name' => 'Saved For Later',
                    ],
                    'properties' => $eventData,
                ],
            ],
        ];

        // Unique ID ensures that duplicate event records are prevented for the same user and product.
        $eventBody['data']['attributes']['unique_id'] = $email . '_' . $shopifyId;

        try {
            return $client->Events->createEvent($eventBody);
        } catch (ApiException $e) {
            Log::error('Exception when calling Klaviyo API Events->createEvent: ' . $e->getMessage(), [
                'response' => $e->getResponseBody(),
            ]);
            return null;
        }
    }
}
