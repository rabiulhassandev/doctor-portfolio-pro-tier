<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Resolves payment gateways from configuration.
 *
 * Deliberately NOT extending Laravel's Illuminate\Support\Manager. That base
 * class resolves drivers by convention — a key of `foo` calls a method named
 * `createFooDriver` — and a buyer's developer adding a gateway has no way to
 * discover that rule except by reading framework internals. Three explicit
 * lines that read `config(...)['driver']` and `make()` it are worth more here
 * than the inheritance.
 */
final class PaymentManager
{
    /** The one gateway with no provider behind it. */
    public const PAY_AT_CLINIC = 'cash';

    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Get a gateway by its config key.
     *
     * @throws InvalidArgumentException when the key is not configured.
     */
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('booking.payment.default');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $config = config("booking.payment.gateways.{$name}");

        if (! is_array($config) || blank($config['driver'] ?? null)) {
            throw new InvalidArgumentException(
                "No payment gateway is configured under [{$name}]. Check config/booking.php.",
            );
        }

        // The whole config array goes to the constructor, so a new gateway can
        // carry whatever credentials it needs without changing this class.
        return $this->resolved[$name] = $this->container->make($config['driver'], ['config' => $config]);
    }

    public function default(): PaymentGateway
    {
        return $this->driver();
    }

    /**
     * Every gateway that is switched on and has its credentials.
     *
     * This is what the checkout radio is built from, which means a
     * half-configured install quietly offers fewer options rather than showing
     * a button that fails when pressed.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function available(): Collection
    {
        if (! config('booking.payment.enabled')) {
            return collect();
        }

        return collect(config('booking.payment.gateways', []))
            ->keys()
            ->map(function (string $name): ?PaymentGateway {
                try {
                    return $this->driver($name);
                } catch (InvalidArgumentException) {
                    // A malformed entry should not take the checkout page down.
                    return null;
                }
            })
            ->filter(fn (?PaymentGateway $gateway): bool => $gateway?->isConfigured() === true)
            ->values();
    }

    public function has(string $name): bool
    {
        return $this->available()->contains(fn (PaymentGateway $g): bool => $g->name() === $name);
    }
}
