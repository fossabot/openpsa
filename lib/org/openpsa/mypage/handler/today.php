<?php
/**
 * @package org.openpsa.mypage
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * My page today handler
 *
 * @package org.openpsa.mypage
 */
class org_openpsa_mypage_handler_today extends midcom_baseclasses_components_handler
{
    var $user = null;

    public function _on_initialize()
    {
        $_MIDCOM->auth->require_valid_user();
    }

    private function _populate_toolbar()
    {
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'day/' . $this->_request_data['prev_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('previous'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/up.png',
            )
        );
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'day/' . $this->_request_data['next_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('next'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/down.png',
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'weekreview/' . $this->_request_data['this_day'] . '/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('week review'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
            )
        );
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_today($handler_id, array $args, array &$data)
    {
        $this->user = $_MIDCOM->auth->user->get_storage();

        if ($handler_id == 'today')
        {
            $data['requested_time'] = date('Y-m-d');
        }
        else
        {
            // TODO: Check format as YYYY-MM-DD via regexp
            $data['requested_time'] = $args[0];
        }

        $this->_master->calculate_day($data['requested_time']);

        $this->_populate_toolbar();

        $data['title'] = strftime($data['requested_time']);
        $_MIDCOM->set_pagetitle($data['title']);

        // Add the JS file for workingon widget
        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . "/jQuery/jquery.epiclock.min.js");
        midcom_helper_datamanager2_widget_autocomplete::add_head_elements();
        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.js");

        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.mypage/mypage.css");
        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/list.css");

        //needed js/css-files for jqgrid
        org_openpsa_core_ui_jqgrid::add_head_elements();

        //set the start-constraints for journal-entries
        $time_span = 7 * 24 * 60 *60 ; //7 days

        $this->_request_data['journal_constraints'] = array();
        //just show entries of current_user
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'metadata.creator',
                        'operator' => '=',
                        'value' => $_MIDCOM->auth->user->guid,
                        );
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'followUp',
                        'operator' => '<',
                        'value' => $this->_request_data['day_start'] + $time_span,
                        );
        $this->_request_data['journal_constraints'][] = array(
                        'property' => 'closed',
                        'operator' => '=',
                        'value' => false,
                        );
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_today($handler_id, array &$data)
    {
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $data['calendar_url'] = $siteconfig->get_node_relative_url('org.openpsa.calendar');
        $data['projects_url'] = $siteconfig->get_node_full_url('org.openpsa.projects');
        $data['projects_relative_url'] = $siteconfig->get_node_relative_url('org.openpsa.projects');
        $data['expenses_url'] = $siteconfig->get_node_full_url('org.openpsa.expenses');
        $data['wiki_url'] = $siteconfig->get_node_relative_url('net.nemein.wiki');

        $data_url = $_MIDCOM->get_host_name() . $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);
        $data['journal_url'] = $data_url . '/__mfa/org.openpsa.relatedto/journalentry/list/xml/';

        midcom_show_style('show-today');
    }
}
?>