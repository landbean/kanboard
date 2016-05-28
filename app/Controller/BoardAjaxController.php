<?php

namespace Kanboard\Controller;

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Formatter\BoardFormatter;

/**
 * Class BoardAjaxController
 *
 * @package Kanboard\Controller
 * @author  Fredric Guillot
 */
class BoardAjaxController extends BaseController
{
    /**
     * Save new task positions (Ajax request made by the drag and drop)
     *
     * @access public
     */
    public function save()
    {
        $project_id = $this->request->getIntegerParam('project_id');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getJson();

        $result =$this->taskPosition->movePosition(
            $project_id,
            $values['task_id'],
            $values['column_id'],
            $values['position'],
            $values['swimlane_id']
        );

        if (! $result) {
            $this->response->status(400);
        } else {
            $this->response->html($this->renderBoard($project_id), 201);
        }
    }

    /**
     * Check if the board have been changed
     *
     * @access public
     */
    public function check()
    {
        $project_id = $this->request->getIntegerParam('project_id');
        $timestamp = $this->request->getIntegerParam('timestamp');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        } elseif (! $this->project->isModifiedSince($project_id, $timestamp)) {
            $this->response->status(304);
        } else {
            $this->response->html($this->renderBoard($project_id));
        }
    }

    /**
     * Reload the board with new filters
     *
     * @access public
     */
    public function reload()
    {
        $project_id = $this->request->getIntegerParam('project_id');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getJson();
        $this->userSession->setFilters($project_id, empty($values['search']) ? '' : $values['search']);

        $this->response->html($this->renderBoard($project_id));
    }

    /**
     * Enable collapsed mode
     *
     * @access public
     */
    public function collapse()
    {
        $this->changeDisplayMode(true);
    }

    /**
     * Enable expanded mode
     *
     * @access public
     */
    public function expand()
    {
        $this->changeDisplayMode(false);
    }

    /**
     * Change display mode
     *
     * @access private
     * @param  boolean $mode
     */
    private function changeDisplayMode($mode)
    {
        $project_id = $this->request->getIntegerParam('project_id');
        $this->userSession->setBoardDisplayMode($project_id, $mode);

        if ($this->request->isAjax()) {
            $this->response->html($this->renderBoard($project_id));
        } else {
            $this->response->redirect($this->helper->url->to('BoardViewController', 'show', array('project_id' => $project_id)));
        }
    }

    /**
     * Render board
     *
     * @access protected
     * @param  integer $project_id
     * @return string
     */
    protected function renderBoard($project_id)
    {
        return $this->template->render('board/table_container', array(
            'project' => $this->project->getById($project_id),
            'board_private_refresh_interval' => $this->config->get('board_private_refresh_interval'),
            'board_highlight_period' => $this->config->get('board_highlight_period'),
            'swimlanes' => $this->taskLexer
                ->build($this->userSession->getFilters($project_id))
                ->format(BoardFormatter::getInstance($this->container)->setProjectId($project_id))
        ));
    }
}