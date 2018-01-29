<?php
/**
 * This file contains only the RfXVoteCalculatorRepository class.
 */

namespace Xtools;

/**
 * RfXVoteCalculatorRepository is responsible for retrieving data
 * from the database for the RfX Vote Calculator tool.
 * @codeCoverageIgnore
 */
class RfXVoteCalculatorRepository extends Repository
{
    public function getRfXTitles(
        Project $project,
        User $user,
        $rfxNamespace = 4,
        $rfxPrefix = '',
        $ignoredPages = []
    ) {
        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $username = $user->getUsername();
        $rfxPrefix = str_replace(' ', '_', $rfxPrefix);
        $userpage = str_replace(' ', '_', $username);

        $sql = "SELECT DISTINCT(p.page_title)
                FROM $pageTable p
                RIGHT JOIN $revisionTable r ON p.page_id = r.rev_page
                WHERE p.page_namespace = :namespace
                AND r.rev_user_text = :username
                AND p.page_title LIKE \"$rfxPrefix/%\"
                AND p.page_title NOT LIKE \"%$rfxPrefix/$userpage%\"
                $ignoredPages";

        $sth = $this->getProjectsConnection()->prepare($sql);
        $sth->bindParam('namespace', $rfxNamespace);
        $sth->bindParam('username', $username);
        $sth->execute();

        return $sth->fetchAll();
    }
}
