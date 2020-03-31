<?php
/**
 * Factory for SolrDefault record drivers.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace TUBfind\RecordDriver;

use Interop\Container\ContainerInterface;

/**
 * Factory for SolrDefault record drivers.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SolrGBVFactory extends \VuFind\RecordDriver\AbstractBaseFactory
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $parts = explode('\\', $requestedName);
        $configName = array_pop($parts);
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get($configName);
        $finalOptions = [$config, $config];
        $driver = parent::__invoke($container, $requestedName, $finalOptions);
        $driver->attachSearchService($container->get(\VuFindSearch\Service::class));
        $driver->attachILS(
            $container->get(\TUBfind\ILS\Connection::class),
            $container->get(\TUBfind\ILSHoldLogic::class),
            $container->get(\VuFind\ILSTitleHoldLogic::class)
        );

        $ilsBackends = $this->getIlsBackends($container);
        $driver->setIlsBackends($ilsBackends);

        return $driver;
    }

    /**
     * Get the ILS backend configuration.
     *
     * @param ContainerInterface $container Service container
     *
     * @return string[]
     */
    protected function getIlsBackends(ContainerInterface $container)
    {
        // Get a list of ILS-compatible backends.
        static $ilsBackends = null;
        if (!is_array($ilsBackends)) {
            $config = $container->get(\VuFind\Config\PluginManager::class)
                ->get('config');
            $settings = isset($config->Catalog) ? $config->Catalog->toArray() : [];

            // If the setting is missing, default to the default backend; if it
            // is present but empty, don't put an empty string in the final array!
            $rawSetting = $settings['ilsBackends'] ?? [DEFAULT_SEARCH_BACKEND];
            $ilsBackends = empty($rawSetting) ? [] : (array)$rawSetting;
        }
        return $ilsBackends;
    }
}
