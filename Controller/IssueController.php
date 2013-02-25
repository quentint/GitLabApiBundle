<?php
namespace Zeichen32\GitLabApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Zeichen32\GitLabApiBundle\Event\IssueEvent;
use Zeichen32\GitLabApiBundle\Events;
use Zeichen32\GitLabApiBundle\Form\IssueType;

class IssueController extends Controller
{
    protected function getProjectId()
    {
        return (int) $this->container->getParameter('zeichen32_gitlabapi.config.project');
    }


    /**
     * @param $page
     * @param $limit
     * @return array
     *
     * @Template()
     */
    public function listAction($page, $limit)
    {
        /**
         * @var $api \GitLab\Client
         */
        $api = $this->get('gitlab_api');

        try
        {
            $issues = $api->api('issue')->getProjectIssues($this->getProjectId(), (int) $page, (int) $limit);
        } catch(\Exception $e) {
            $issues = array();
        }

        return array('issues' => $issues);
    }

    /**
     * @Template()
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createAction()
    {
        $event = new IssueEvent(array(
            "description" => "Your Issue...\n\n-------------------------\n",
            'labels' => 'Bug'
        ));

        $this->get('event_dispatcher')->dispatch(Events::ISSUE_CREATE_INIT, $event);

        $form = $this->createForm(new IssueType(), $event->getData());

        if($this->getRequest()->isMethod('POST'))
        {
            $form->bind($this->getRequest());

            if($form->isValid()){

                $event->setData($form->getData());
                $this->get('event_dispatcher')->dispatch(Events::ISSUE_CREATE_PRESAVE, $event);

                /**
                 * @var $api \GitLab\Client
                 */
                $api = $this->get('gitlab_api');

                try
                {
                    $data = $event->getData();
                    $api->api('issue')->createIssue($this->getProjectId(), $data['title'], $data['description'], null, null, explode(',', $data['labels']));
                    $this->get('session')->getFlashBag()->add('success', 'Issue erstellt!');

                    $this->get('event_dispatcher')->dispatch(Events::ISSUE_CREATE_POSTSAVE, $event);

                    return $this->redirect($this->generateUrl('gitlabapi_issue_list'));
                }catch(\Exception $e)
                {
                    $this->get('session')->getFlashBag()->add('error', 'Es trat ein unbekannter Fehler auf!');
                    return $this->redirect($this->generateUrl('gitlabapi_issue_new'));
                }
            }
        }

        return array('form' => $form->createView());
    }
}