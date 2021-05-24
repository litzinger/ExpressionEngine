<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2021, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Controller\Logs;

/**
 * Logs\CP Controller
 */
class Consent extends Logs
{
    /**
     * View Consent Audit Log Files
     *
     * @access	public
     * @return	mixed
     */
    public function index()
    {
        if (! ee('Permission')->can('manage_consents')) {
            show_error(lang('unauthorized_access'), 403);
        }

        ee('CP/Alert')->makeDeprecationNotice()->now();

        $this->base_url->path = 'logs/consent';
        ee()->view->cp_page_title = lang('view_consent_log');

        $logs = ee('Model')->get('ConsentAuditLog')->with('Member', 'ConsentRequest');

        if ($search = ee()->input->get_post('filter_by_keyword')) {
            $logs->search(['action', 'ip_address', 'user_agent', 'Member.username', 'ConsentRequest.title'], $search);
        }

        $filters = ee('CP/Filter')
            ->add('Username')
            ->add('Date')
            ->add('Keyword')
            ->add('Perpage', $logs->count(), 'all_consent_logs');
        ee()->view->filters = $filters->render($this->base_url);
        $this->params = $filters->values();
        $this->base_url->addQueryStringVariables($this->params);

        $page = ((int) ee()->input->get('page')) ?: 1;
        $offset = ($page - 1) * $this->params['perpage']; // Offset is 0 indexed

        if (! empty($this->params['filter_by_username'])) {
            $logs = $logs->filter('member_id', 'IN', $this->params['filter_by_username']);
        }

        if (! empty($this->params['filter_by_date'])) {
            if (is_array($this->params['filter_by_date'])) {
                $logs = $logs->filter('log_date', '>=', $this->params['filter_by_date'][0]);
                $logs = $logs->filter('log_date', '<', $this->params['filter_by_date'][1]);
            } else {
                $logs = $logs->filter('log_date', '>=', ee()->localize->now - $this->params['filter_by_date']);
            }
        }

        $count = $logs->count();

        // Set the page heading
        if (! empty($search)) {
            ee()->view->cp_heading = sprintf(
                lang('search_results_heading'),
                $count,
                ee('Format')->make('Text', $search)->convertToEntities()
            );
        }

        ee()->view->header = array(
            'title' => lang('system_logs'),
            'form_url' => $this->base_url->compile(),
        );

        $logs = $logs->order('log_date', 'desc')
            ->limit($this->params['perpage'])
            ->offset($offset)
            ->all();

        $pagination = ee('CP/Pagination', $count)
            ->perPage($this->params['perpage'])
            ->currentPage($page)
            ->render($this->base_url);

        $vars = array(
            'logs' => $logs,
            'pagination' => $pagination,
            'form_url' => $this->base_url->compile(),
        );

        ee()->view->cp_breadcrumbs = array(
            '' => lang('view_consent_log')
        );

        ee()->cp->render('logs/consent', $vars);
    }
}
// END CLASS

// EOF
