<?php
/**
 * This file contains only the RfXTool class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * This class contains shared logic for the RfXVoteCalculator
 * and RfXAnalysis models.
 */
class RfXTool extends Model
{
    /** @var Container The DI container. */
    protected $container;

    /** @var array The configuration for RfX's on the given wiki. */
    protected $rfxConfig;

    /**
     * RFX constructor.
     *
     * @param Container $container The DI container.
     * @param Page $page
     * @param string|null $userLookingFor User we're trying to find.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Is the given wiki configured for the RfX tool?
     * @return bool
     */
    public function isConfigured()
    {
        return $this->getConfig() !==  null;
    }

    /**
     * Get the configuration for RfX's on the given wiki.
     * @return array|null
     */
    public function getConfig()
    {
        if ($this->rfxConfig) {
            return $this->rfxConfig;
        }
        $allRfxConfigs = $this->container->getParameter('rfx');

        if (isset($allRfxConfigs[$this->project->getDomain()])) {
            $this->rfxConfig = $allRfxConfigs[$this->project->getDomain()];
        }

        return $this->rfxConfig;
    }

    /**
     * Get the namespace the RfXs take place in.
     * @return int
     */
    public function getNamespace()
    {
        return $this->getConfig()['rfx_namespace'];
    }
}
