<?php

// File generated from our OpenAPI spec

namespace StoreEngine\Stripe\Service\Reporting;

/**
 * Service factory class for API resources in the Reporting namespace.
 *
 * @property ReportRunService $reportRuns
 * @property ReportTypeService $reportTypes
 *
 * @license MIT
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */
class ReportingServiceFactory extends \StoreEngine\Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'reportRuns' => ReportRunService::class,
        'reportTypes' => ReportTypeService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
