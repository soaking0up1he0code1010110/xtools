<?php
/**
 * An RFX object contains the parsed information for an RfX.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * This class contains information about a single RfX page.
 */
class RfX
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Page Page object for the RfX. */
    protected $page;

    /** @var string[] RfX configuration. */
    protected $config;

    /** @var array Data we parsed out of the page text. */
    private $data;

    /** @var array Duplicate voters. */
    private $duplicates;

    /** @var null|string Username of the user we're looking for. */
    private $userLookingFor;

    /** @var string Section we found the user we're looking for */
    private $userSectionFound;

    /** @var string Ending date of the RFX. */
    private $endDate;

    /**
     * RFX constructor.
     *
     * @param Container $container The DI container.
     * @param Page $page
     * @param string[] $config RfX configuration.
     */
    public function __construct(
        Container $container,
        Page $page,
        $config
    ) {
        $this->container = $container;
        $this->page = $page;
        $this->config = $config;

        $this->prepareData($page->getContent(), $config['date_regexp']);
    }

    /**
     * Attempts to find a signature in $input using the default regex.
     * Returns matches.
     *
     * @param string $input   The line we're looking for
     * @param array  $matches Pointer to an array where we stash results
     *
     * @TODO: Make this cleaner
     *
     * @return int
     */
    protected function findSig($input, &$matches)
    {
        //Supports User: and User talk: wikilinks, {{fullurl}},
        // unsubsted {{unsigned}}, unsubsted {{unsigned2}},
        // anything that looks like a custom sig template
        // TODO: Cross-wiki this sucker
        $regexp
            = //1: Normal [[User:XX]] and [[User talk:XX]]
            "/\[\[[Uu]ser(?:[\s_][Tt]alk)?\:([^\]\|\/]*)(?:\|[^\]]*)?\]\]"
            //2: {{fullurl}} and {{unsigned}} templates
            . "|\{\{(?:[Ff]ullurl\:[Uu]ser(?:[\s_][Tt]alk)?\:|"
            . "[Uu]nsigned\|)([^\}\|]*)(?:|[\|\}]*)?\}\}"
            //3: {{User:XX/sig}} templates
            . "|(?:\{\{)[Uu]ser(?:[\s_][Tt]alk)?\:([^\}\/\|]*)"
            //4: {{unsigned2|Date|XX}} templates
            . "|\{\{[Uu]nsigned2\|[^\|]*\|([^\}]*)\}\}"
            //5: [[User:XX/sig]] links (compromise measure)
            . "|(?:\[\[)[Uu]ser\:([^\]\/\|]*)\/[Ss]ig[\|\]]/";

        return preg_match_all(
            $regexp,
            $input,
            $matches,
            PREG_OFFSET_CAPTURE
        );
    }

    /**
     * This function parses the wikitext and stores it within this function.
     * It's been split out to make this class testable
     *
     * @param string $rawWikiText  The text of the page we're parsing
     * @param string $dateRegexp   Valid Regular Expression for the end date
     *
     * @return null
     */
    private function prepareData($rawWikiText, $dateRegexp)
    {
        $this->data = [];

        var_dump($rawWikiText);

        $lines = explode("\n", $rawWikiText);

        $keys = join('|', $this->getSectionNames());

        $lastSection = '';

        foreach ($lines as $line) {
            if (preg_match("/={1,6}\s?($keys)\s?={1,6}/i", $line, $matches)) {
                $lastSection = strtolower($matches[1]);
            } elseif ($lastSection == ""
                && preg_match(
                    "/$dateRegexp/i",
                    $line,
                    $matches
                )
            ) {
                $this->endDate = $matches[1];
            } elseif ($lastSection != ""
                && preg_match("/^\s*#?:.*/i", $line) === 0
            ) {
                $this->findSig($line, $matches);
                if (!isset($matches[1][0])) {
                    continue;
                }
                $foundUser = trim($matches[1][0][0]);
                $this->data[$lastSection][] = $foundUser;
                if (strtolower($foundUser) === strtolower($this->userLookingFor)) {
                    $this->userSectionFound = $lastSection;
                }
            }
        }

        $final = [];    // initialize the final array
        $finalRaw = []; // Initialize the raw data array

        foreach ($this->data as $key => $value) {
            $finalRaw = array_merge($finalRaw, $this->data[$key]);
        }

        foreach ($finalRaw as $foundUsername) {
            $final[] = $foundUsername; // group all array's elements
        }

        $final = array_count_values($final); // find repetition and its count

        $final = array_diff($final, [1]);    // remove single occurrences

        $this->duplicates = array_keys($final);
    }

    /**
     * Which section we found the user we're looking for.
     *
     * @return string
     */
    public function getUserSectionFound()
    {
        return $this->userSectionFound;
    }

    /**
     * Get the titles of the sections that make up an RfX.
     * @return string[]
     */
    private function getSectionNames()
    {
        return $this->config['sections'];
    }

    /**
     * Returns data on the given section name.
     *
     * @param string $sectionName The section we're looking at
     *
     * @return array
     */
    public function getSection($sectionName)
    {
        $sectionName = strtolower($sectionName);
        if (!isset($this->data[$sectionName])) {
            return [];
        } else {
            return $this->data[$sectionName];
        }
    }

    /**
     * Get an array of duplicate votes.
     *
     * @return array
     */
    public function getDuplicates()
    {
        return $this->duplicates;
    }

    /**
     * Get the End Date of the RFX
     *
     * @return string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }
}
