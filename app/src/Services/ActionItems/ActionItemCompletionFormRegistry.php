<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;

/**
 * Static registry for plugin-provided ActionItem completion forms.
 */
class ActionItemCompletionFormRegistry
{
    /**
     * @var array<string, \App\Services\ActionItems\ActionItemCompletionFormProviderInterface>
     */
    private static array $providers = [];

    /**
     * Register a completion form provider.
     *
     * @param string $source Source key.
     * @param \App\Services\ActionItems\ActionItemCompletionFormProviderInterface $provider Provider.
     * @return void
     */
    public static function register(string $source, ActionItemCompletionFormProviderInterface $provider): void
    {
        self::$providers[$source] = $provider;
    }

    /**
     * Resolve the first provider that can handle the item.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return \App\Services\ActionItems\ActionItemCompletionFormProviderInterface|null
     */
    public static function providerFor(ActionItem $item): ?ActionItemCompletionFormProviderInterface
    {
        foreach (self::$providers as $provider) {
            if ($provider->canHandle($item)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Build a provider-backed form when available.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param \App\KMP\KmpIdentityInterface $user Current user.
     * @return \App\Services\ActionItems\ActionItemCompletionForm|null
     */
    public static function formFor(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
    {
        return self::providerFor($item)?->buildForm($item, $user);
    }

    /**
     * Clear registered providers; primarily for tests.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$providers = [];
    }

    /**
     * @return array<string, \App\Services\ActionItems\ActionItemCompletionFormProviderInterface>
     */
    public static function providers(): array
    {
        return self::$providers;
    }
}
