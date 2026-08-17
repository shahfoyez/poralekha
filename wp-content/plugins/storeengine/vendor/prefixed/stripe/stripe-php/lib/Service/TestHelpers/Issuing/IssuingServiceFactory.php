<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\TestHelpers\Issuing;

/**
 * Service factory class for API resources in the Issuing namespace.
 *
 * @property AuthorizationService $authorizations
 * @property CardService $cards
 * @property PersonalizationDesignService $personalizationDesigns
 * @property TransactionService $transactions
 *
 * @license MIT
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */
class IssuingServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'authorizations' => AuthorizationService::class,
        'cards' => CardService::class,
        'personalizationDesigns' => PersonalizationDesignService::class,
        'transactions' => TransactionService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
