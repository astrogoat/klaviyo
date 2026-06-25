<?php

namespace Astrogoat\Klaviyo;

use Astrogoat\Cart\CartItem;
use Astrogoat\Klaviyo\Settings\KlaviyoSettings;
use Astrogoat\Shopify\Models\Product;
use Astrogoat\Shopify\Models\ProductVariant;
use Astrogoat\Cart\Events\ClearingCart;
use Astrogoat\Cart\Events\ItemAddedToCart;
use Astrogoat\Cart\Events\ItemRemovedFromCart;
use Helix\Lego\Events\PageViewed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use KlaviyoAPI\ApiException;
use KlaviyoAPI\KlaviyoAPI;

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

    public function track(string $metricName, array $properties, ?string $uniqueId = null): mixed
    {
        $client = $this->getClient();

        if (!$client) {
            Log::warning('Klaviyo event not sent: Klaviyo is not enabled or Private API Key is not set.');
            return null;
        }

        $profile = $this->getProfile();

        $eventBody = [
            'data' => [
                'type' => 'event',
                'attributes' => [
                    'profile' => $profile,
                    'metric' => [
                        'name' => $metricName,
                    ],
                    'properties' => $properties,
                ],
            ],
        ];

        if ($uniqueId) {
            $eventBody['data']['attributes']['unique_id'] = $uniqueId;
        }

        try {
            return $client->Events->createEvent($eventBody);
        } catch (ApiException $e) {
            Log::error('Exception when calling Klaviyo API Events->createEvent: ' . $e->getMessage(), [
                'response' => $e->getResponseBody(),
            ]);
            return null;
        }
    }

    protected function getProfile(): array
    {
        $profile = [];

        $klaId = request()->cookie('__kla_id') ?? $_COOKIE['__kla_id'] ?? null;

        if ($klaId) {
            $decoded = json_decode(base64_decode($klaId), true);
            $profile = $decoded;
        }

        return $profile;
    }

    public function pageViewed(PageViewed $event): void
    {
        $settings = app(KlaviyoSettings::class);
        if (!$settings->enabled) {
            return;
        }

        match ($event->type) {
            'product' => $this->productViewed($event),
            'cart' => $this->cartPageViewed($event),
            'collection' => $this->collectionViewed($event),
            default => null,
        };
    }

    private function cartPageViewed(PageViewed $event): void
    {
        $properties = array_merge(
            $this->getKlaviyoBaseProperties(),
            $this->getKlaviyoEcommerceProperties(
                eventType: 'impressions',
                actionField: ['list' => 'Shopping Cart'],
            )
        );

        $this->track('dl_view_cart', $properties);
    }

    private function productViewed(PageViewed $event): void
    {
        $modelClass = $event->attributes['class'] ?? null;
        $modelId = $event->attributes['id'] ?? null;
        $model = null;

        if ($modelClass && $modelId) {
            $model = $modelClass::find($modelId);
        }

        $productVariant = match (true) {
            $model instanceof Product => $model->variants->first(),
            $model instanceof ProductVariant => $model,
            default => null,
        };

        if ($productVariant) {
            $properties = $this->getKlaviyoProductViewProperties(
                productVariants: collect([$productVariant]),
                actionField: ['list' => request()->fullUrl(), 'action' => 'detail']
            );

            $this->track('dl_view_item', $properties);
        }
    }

    private function collectionViewed(PageViewed $event): void
    {
        $products = [];
        $modelClass = $event->attributes['class'] ?? null;
        $modelId = $event->attributes['id'] ?? null;

        if ($modelClass && $modelId && $collection = $modelClass::find($modelId)) {
            $products = $collection->load('products.variants')->products->map(function (Product $product) {
                return $product->variants->first();
            });
        }

        $properties = array_merge(
            $this->getKlaviyoBaseProperties(),
            $this->getKlaviyoEcommerceProperties(
                eventType: 'impressions',
                productVariants: $products,
            )
        );

        $this->track('dl_view_item_list', $properties);
    }

    public function addToCart(ItemAddedToCart $event, ?ProductVariant $productVariant): void
    {
        $settings = app(KlaviyoSettings::class);
        if (!$settings->enabled) {
            return;
        }

        if ($productVariant) {
            $productVariant->quantity = $event->cartItem->getQuantity();
        }

        $properties = array_merge(
            $this->getKlaviyoBaseProperties(),
            $this->getKlaviyoEcommerceProperties(
                eventType: 'add',
                productVariants: collect([$productVariant]),
            )
        );

        $this->track('dl_add_to_cart', $properties);
    }

    public function removeFromCart(ItemRemovedFromCart $event, ?ProductVariant $productVariant): void
    {
        $settings = app(KlaviyoSettings::class);
        if (!$settings->enabled) {
            return;
        }

        if ($productVariant) {
            $productVariant->quantity = $event->cartItem->getQuantity();
        }

        $properties = array_merge(
            $this->getKlaviyoBaseProperties(),
            $this->getKlaviyoEcommerceProperties(
                eventType: 'remove',
                productVariants: collect([$productVariant]),
            )
        );

        $this->track('dl_remove_from_cart', $properties);
    }

    public function clearingCart(ClearingCart $event): void
    {
        $settings = app(KlaviyoSettings::class);
        if (!$settings->enabled) {
            return;
        }

        foreach ($event->cartItems as $cartItem) {
            $productVariant = $cartItem->findModel();
            if ($productVariant) {
                $productVariant->quantity = $cartItem->getQuantity();

                $properties = array_merge(
                    $this->getKlaviyoBaseProperties(),
                    $this->getKlaviyoEcommerceProperties(
                        eventType: 'remove',
                        productVariants: collect([$productVariant]),
                    )
                );

                $this->track('dl_remove_from_cart', $properties);
            }
        }
    }

    private function getKlaviyoBaseProperties(): array
    {
        return [
            'user_properties' => $this->getKlaviyoUserProperties(),
        ];
    }

    protected function getKlaviyoUserProperties($type = 'guest'): array
    {
        return [
            'visitor_type' => 'guest',
        ];
    }

    private function getKlaviyoEcommerceProperties(string $eventType, Collection $productVariants = null, mixed $actionField = null): array
    {
        if (is_null($productVariants)) {
            $productVariants = cart()->getItems()->map(function (CartItem $cartItem) {
                $productVariant = $cartItem->findModel();

                if (!$productVariant) {
                    return null;
                }

                $productVariant->quantity = $cartItem->getQuantity();

                return $productVariant;
            });
        }

        $products = $productVariants
            ->reject(fn($variant) => is_null($variant))
            ->map(function (ProductVariant $productVariant) {
                $productVariant->load('product');

                return [
                    'id' => $productVariant?->sku,
                    'name' => $productVariant?->product?->title,
                    'brand' => $productVariant?->product?->vendor,
                    'category' => $productVariant?->product?->type,
                    'variant' => $productVariant?->title,
                    'price' => $productVariant?->getBuyablePrice()->divide(100)->getAmount(),
                    'quantity' => (string) (!blank($productVariant->quantity) ? $productVariant->quantity : 1),
                    'product_id' => (string) $productVariant?->product?->shopify_id,
                    'variant_id' => (string) $productVariant?->shopify_id,
                    'list' => '',
                    'compare_at_price' => (string) 0.0,
                    'url' => route('products.variants.show', [$productVariant->product, $productVariant]),
                ];
            })->values();

        $actionField = blank($actionField)
            ? []
            : ['actionField' => $actionField];

        $collection = $eventType === 'cart_contents' || $eventType === 'detail' || $eventType === 'add'
            ? [$eventType => ['products' => $products, ...$actionField]]
            : [$eventType => $products];

        return [
            'ecommerce' => array_merge([
                'currencyCode' => cart()->getCurrency(),
            ], $collection, $actionField),
        ];
    }

    public function getKlaviyoProductViewProperties(Collection $productVariants, mixed $actionField): array
    {
        return array_merge(
            ['event' => 'dl_view_item'],
            $this->getKlaviyoBaseProperties(),
            $this->getKlaviyoEcommerceProperties(
                eventType: 'detail',
                productVariants: $productVariants,
                actionField: $actionField,
            )
        );
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
