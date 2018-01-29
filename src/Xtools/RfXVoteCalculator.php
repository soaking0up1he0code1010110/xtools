<?php
/**
 * This file contains only the RfXVoteCalculator class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * A RfXVoteCalculator provides the business logic for the
 * RfXVoteCalculator tool.
 */
class RfXVoteCalculator extends RfXTool
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var RfX[] The list of RfXs, keyed by type. */
    protected $rfxs;

    /** @var array Various totals for the RfXs. */
    protected $totals;

    /**
     * Constructor for the RfXVoteCalculator class.
     * @param Container $container The DI container.
     * @param Project   $project
     * @param User      $user
     */
    public function __construct(Container $container, Project $project, User $user)
    {
        $this->container = $container;
        $this->project = $project;
        $this->user = $user;
    }

    public function getTotals()
    {
        return $this->totals;
    }

    public function prepareData()
    {
        $this->totals = [];

        foreach ($this->getRfXs() as $prefix => $rfxsByPrefix) {
            if (!isset($this->totals[$prefix])) {
                $this->totals[$prefix] = [];
            }

            foreach ($rfxsByPrefix as $rfx) {
                $section = $rfx->getUserSectionFound();

                var_dump($section);

                if ($section == '') {
                    // Skip over ones where the user didn't !vote.
                    continue;
                }

                if (!isset($this->totals[$prefix][$section])) {
                    $this->totals[$prefix][$section] = 0;
                }

                if (!isset($this->totals[$prefix]['total'])) {
                    $this->totals[$prefix]['total'] = 0;
                }

                $this->totals[$prefix][$section] += 1;
                $this->totals[$prefix]['total'] += 1;
            }
        }
    }

    public function getRfXs()
    {
        if (isset($this->rfxs)) {
            return $this->rfxs;
        }
        $this->rfxs = [];

        foreach ($this->getConfig()['pages'] as $prefix) {
            if (!isset($rfxs[$prefix])) {
                $this->rfxs[$prefix] = [];
            }

            $rfxs = $this->getRfXsByPrefix($prefix);
            $this->rfxs[$prefix] = array_merge($this->rfxs[$prefix], $rfxs);
        }

        return $this->rfxs;
    }

    private function getRfXsByPrefix($prefix)
    {
        $rows = $this->getRepository()->getRfXTitles(
            $this->project,
            $this->user,
            $this->getNamespace(),
            $prefix,
            $this->getIgnoredSql()
        );
        $rfxs = [];
        $nsName = $this->project->getNamespaces()[$this->getNamespace()];
        $nsName = isset($nsName) ? $nsName.':' : '';

        foreach ($rows as $row) {
            $page = new Page($this->project, $nsName.$row['page_title']);
            $pageRepo = new PageRepository();
            $pageRepo->setContainer($this->container);
            $page->setRepository($pageRepo);
            $rfxs[] = new RfX($this->container, $page, $this->getConfig());
        }

        return $rfxs;
    }

    private function getIgnoredSql()
    {
        $ignoredSql = '';

        if (isset($this->getConfig()['excluded_title'])) {
            $titlesExcluded = $this->getConfig()['excluded_title'];

            foreach ($titlesExcluded as $ignoredPage) {
                $ignoredSql .= "AND p.page_title != \"$ignoredPage\"\r\n";
            }
        }

        if (isset($this->getConfig()['excluded_regex'])) {
            $titlesExcluded = $this->getConfig()['excluded_regex'];

            foreach ($titlesExcluded as $ignoredPage) {
                $ignoredSql .= "AND p.page_title NOT LIKE \"%$ignoredPage%\"\r\n";
            }
        }

        return $ignoredSql;
    }
}
