<?php

declare(strict_types=1);

/**
 * Derafu: HTTP - Standard-Compliant HTTP Library with Extended Features.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsHttp\Fixture;

use Composer\InstalledVersions;
use Derafu\Http\Kernel;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Run the real HTTP kernel from a package checkout rather than vendor/derafu/http.
 *
 * Additional locator directories resolve the production YAML's sibling imports
 * against Composer's installed packages. Application services remain in YAML.
 */
final class ApplicationKernel extends Kernel
{
    /**
     * @param ContainerBuilder $container Container receiving the real application configuration.
     * @return DelegatingLoader Kernel loaders with Composer-aware lookup paths.
     */
    protected function getDelegatingLoader(ContainerBuilder $container): DelegatingLoader
    {
        $directories = [$this->environment->getConfigDir()];
        foreach (['derafu/routing', 'derafu/renderer'] as $package) {
            $directories[] = (InstalledVersions::getInstallPath($package)
                ?? throw new RuntimeException($package . ' must be installed for these tests.')) . '/resources/config';
        }
        $locator = new FileLocator($directories);
        $loaders = [];
        foreach (static::CONFIG_LOADERS as $loader) {
            $loaders[] = new $loader($container, $locator);
        }

        return new DelegatingLoader(new LoaderResolver($loaders));
    }
}
