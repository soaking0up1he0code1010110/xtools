<?php
/**
 * This file contains the code that powers the RfX Vote Calculator page of XTools.
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\CssSelector\Exception\InternalErrorException;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\PageRepository;
use Xtools\RfXVoteCalculator;
use Xtools\RfXVoteCalculatorRepository;
use Xtools\User;

/**
 * Controller for the RfX Vote Calculator.
 */
class RfXVoteCalculatorController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'rfxvote';
    }

    /**
     * Renders the index page for RfXVoteCalculator
     *
     * @Route("/rfxvote", name="rfxvote")
     * @Route("/rfxvote/", name="rfxvoteSlash")
     * @Route("/rfxvote/index.php", name="rfxvoteIndexPhp")
     * @Route("/rfxvote", name="RfXVoteCalculator")
     *
     * @return Response
     */
    public function indexAction()
    {
        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username');

        if ($projectQuery != '' && $username != '') {
            $routeParams = [ 'project' => $projectQuery, 'username' => $username ];
            return $this->redirectToRoute(
                'rfxvoteResult',
                $routeParams
            );
        } elseif ($projectQuery != '') {
            return $this->redirectToRoute(
                'rfxvoteResult',
                [
                    'project' => $projectQuery
                ]
            );
        }

        // Instantiate the project if we can, or use the default.
        $project = (!empty($projectQuery))
            ? ProjectRepository::getProject($projectQuery, $this->container)
            : ProjectRepository::getDefaultProject($this->container);

        return $this->render(
            'rfxVoteCalculator/index.html.twig',
            [
                'xtPageTitle' => 'tool-rfxvote',
                'xtSubtitle' => 'tool-rfxvote-desc',
                'xtPage' => 'rfxvote',
                'project' => $project,
            ]
        );
    }

    /**
     * Result View of RfXVoteCalculator
     * @Route("/rfxvote/{project}/{username}", name="rfxvoteResult")
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        $vc = new RfXVoteCalculator($this->container, $project, $user);
        $vcRepo = new RfXVoteCalculatorRepository();
        $vcRepo->setContainer($this->container);
        $vc->setRepository($vcRepo);

        if (!$vc->isConfigured()) {
            $this->addFlash('notice', ['invalid-project-cant-use', $project->getDomain()]);
            return $this->redirectToRoute('rfxvote');
        }

        $vc->prepareData();

        var_dump($vc->getTotals());

        // $finalData = [];

        // /**
        //  * Contains the total number of !votes the user made, keyed by the RfX
        //  * type and then the vote type.
        //  * @var array
        //  */
        // $totals = [];

        // foreach ($pageTypes as $type) {
        //     $type = explode(':', $type, 2)[1];

        //     $type = str_replace(' ', '_', $type);

        //     $pageTable = $project->getTableName('page');
        //     $revisionTable
        //         = $project->getTableName('revision');

        //     $sql = "SELECT DISTINCT p.page_namespace, p.page_title
        //             FROM $pageTable p
        //             RIGHT JOIN $revisionTable r on p.page_id=r.rev_page
        //             WHERE p.page_namespace = :namespace
        //             AND r.rev_user_text = :username
        //             And p.page_title LIKE \"$type/%\"
        //             AND p.page_title NOT LIKE \"%$type/$username%\"
        //             $ignoredPages";

        //     $sth = $conn->prepare($sql);
        //     $sth->bindParam('namespace', $namespace);
        //     $sth->bindParam('username', $username);

        //     $sth->execute();

        //     $titles = [];

        //     while ($row = $sth->fetch()) {
        //         $titles[] = $namespaces[$row['page_namespace']] .
        //             ':' .$row['page_title'];
        //     }

        //     // Chunking... it's possible to make a URI too long
        //     $titleArray = array_chunk($titles, 20);

        //     foreach ($titleArray as $titlesWorked) {
        //         $pageData = $pageRepo->getPagesWikitext($project, $titlesWorked);

        //         foreach ($pageData as $title => $text) {
        //             $type = str_replace('_', ' ', $type);
        //             $rfx = new RfX(
        //                 $this->container,
        //                 $text,
        //                 $rfxConfig[$project->getDomain()]['sections'],
        //                 $namespaces[2],
        //                 $rfxConfig[$project->getDomain()]['date_regexp'],
        //                 $username
        //             );
        //             $section = $rfx->getUserSectionFound();

        //             if ($section == '') {
        //                 // Skip over ones where the user didn't !vote.
        //                 continue;
        //             }

        //             if (!isset($totals[$type])) {
        //                 $totals[$type] = [];
        //             }
        //             if (!isset($totals[$type][$section])) {
        //                 $totals[$type][$section] = 0;
        //             }
        //             if (!isset($totals[$type]['total'])) {
        //                 $totals[$type]['total'] = 0;
        //             }
        //             $totals[$type][$section] += 1;
        //             $totals[$type]['total'] += 1;

        //             // Todo: i18n-ize this
        //             $finalData[$type][$section][$title]['Support']
        //                 = sizeof($rfx->getSection('support'));
        //             $finalData[$type][$section][$title]['Oppose']
        //                 = sizeof($rfx->getSection('oppose'));
        //             $finalData[$type][$section][$title]['Neutral']
        //                 = sizeof($rfx->getSection('neutral'));
        //             $finalData[$type][$section][$title]['Date']
        //                 = $rfx->getEndDate();
        //             $finalData[$type][$section][$title]['name']
        //                 = explode('/', $title)[1];

        //             unset($rfx);
        //         }
        //     }
        // }

        return $this->render(
            'rfxVoteCalculator/result.html.twig',
            [
                'xtPage' => 'rfxvote',
                'xtTitle' => $user->getUsername(),
                'user' => $user,
                'project' => $project,
                'vc' => $vc,
            ]
        );
    }
}
