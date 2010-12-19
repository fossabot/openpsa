<?php
/**
 * @package org.openpsa.directmarketing
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.directmarketing campaign handler and viewer class.
 * @package org.openpsa.directmarketing
 */
class org_openpsa_directmarketing_handler_subscriber extends midcom_baseclasses_components_handler
{
    /**
     * Phase for showing the list of campaigns
     *
     * @param String $handler_id    Name of the request handler
     * @param array $args           Variable arguments
     * @param array &$data          Public request data, passed by reference
     * @return boolean              Indicating success
     */
    public function _handler_list($handler_id, $args, &$data)
    {
        $_MIDCOM->auth->require_valid_user();
        if (count($args) == 1)
        {
            $this->_request_data['person'] = new midcom_db_person($args[0]);
            if (!$this->_request_data['person'])
            {
                debug_add("Person record '{$args[0]}' not found");
                return false;
                // This will exit
            }

            if (array_key_exists('add_to_campaign', $_POST))
            {
                // Add person to campaign
                $campaign = new org_openpsa_directmarketing_campaign_dba($_POST['add_to_campaign']);
                if (   $campaign
                    /**
                     * no reason to limit this here
                    && $campaign->node == $this->_topic->id
                    */)
                {
                    // FIXME: use can_do check to be graceful
                    $_MIDCOM->auth->require_do('midgard:create', $campaign);

                    $member = new org_openpsa_directmarketing_campaign_member_dba();
                    $member->orgOpenpsaObType = ORG_OPENPSA_OBTYPE_CAMPAIGN_MEMBER;
                    $member->person = $this->_request_data['person']->id;
                    $member->campaign = $campaign->id;
                    $member->create();
                    if ($member->id)
                    {
                        $_MIDCOM->uimessages->add($this->_l10n->get('org.openpsa.directmarketing'),
                            sprintf(
                                $this->_l10n->get('added person %s to campaign %s'),
                                "{$this->_request_data['person']->firstname} {$this->_request_data['person']->lastname}",
                                $campaign->title
                            ),
                            'ok'
                        );
                    }
                    else
                    {
                        $_MIDCOM->uimessages->add($this->_l10n->get('org.openpsa.directmarketing'),
                            sprintf(
                                $this->_l10n->get('Failed adding person %s to campaign %s'),
                                "{$this->_request_data['person']->firstname} {$this->_request_data['person']->lastname}",
                                $campaign->title
                            ),
                            'error'
                        );
                    }
                }
                else
                {
                    // FIXME: More informative error message
                    $_MIDCOM->uimessages->add($this->_l10n->get('org.openpsa.directmarketing'),
                        sprintf(
                            $this->_l10n->get('Failed adding person %s to campaign %s'),
                            "{$this->_request_data['person']->firstname} {$this->_request_data['person']->lastname}",
                            $_POST['add_to_campaign']
                        ),
                        'error'
                    );
                }
            }
        }

        return true;
    }

    /**
     * Show the list of existing campaigns
     *
     * @param String $handler_id    Name of the request handler
     * @param array &$data          Public request data, passed by reference
     */
    public function _show_list($handler_id, &$data)
    {
        $qb_all = org_openpsa_directmarketing_campaign_dba::new_query_builder();
        $campaigns = array();

        midcom_show_style("show-campaign-list-header");
        $this->_request_data['campaigns_all'] = array();
        if (   array_key_exists('person', $this->_request_data)
            && $this->_request_data['person'])
        {
            debug_add("Listing campaigns person '{$this->_request_data['person']->guid}' is member of");

            $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
            $qb->add_constraint('person', '=', $this->_request_data['person']->id);
            $qb->add_constraint('orgOpenpsaObtype', '<>', ORG_OPENPSA_OBTYPE_CAMPAIGN_TESTER);
            $memberships = $qb->execute();

            $campaign_membership_map = array();
            if ($memberships)
            {
                foreach ($memberships as $membership)
                {
                    $campaign_membership_map[$membership->campaign] = $membership;
                    $campaigns[$membership->campaign] = new org_openpsa_directmarketing_campaign_dba($membership->campaign);
                }
            }

            // List active campaigns for the "add to campaign" selector
            $qb_all->add_constraint('archived', '=', 0);
            $qb_all->add_order('metadata.created', $this->_config->get('campaign_list_order'));
            $campaigns_all = $qb_all->execute();

            if ($campaigns_all)
            {
                foreach ($campaigns_all as $campaign)
                {
                    if (   !array_key_exists($campaign->id, $campaigns)
                        && $_MIDCOM->auth->can_do('midgard:create', $campaign))
                    {
                        $this->_request_data['campaigns_all'][] = $campaign;
                    }
                }
            }
        }
        else
        {
            debug_add("Listing campaigns visible to current user");

            $qb = org_openpsa_directmarketing_campaign_dba::new_query_builder();
            if ($this->_topic->component = 'org.openpsa.directmarketing')
            {
                $qb->add_constraint('node', '=', $this->_topic->id);
            }
            $qb->add_constraint('archived', '=', 0);
            $qb->add_order('metadata.created', $this->_config->get('campaign_list_order'));

            // Workgroup filtering
            if ($GLOBALS['org_openpsa_core_workgroup_filter'] != 'all')
            {
                debug_add("Filtering documents by workgroup {$GLOBALS['org_openpsa_core_workgroup_filter']}");
                $qb->add_constraint('orgOpenpsaOwnerWg', '=', $GLOBALS['org_openpsa_core_workgroup_filter']);
            }

            $campaigns = $qb->execute();
        }

        if (   is_array($campaigns)
            && count($campaigns) > 0)
        {
            foreach ($campaigns as $campaign)
            {
                $this->_request_data['campaign'] = new org_openpsa_directmarketing_campaign_dba($campaign->guid);
                if (   isset($campaign_membership_map)
                    && array_key_exists($campaign->id, $campaign_membership_map))
                {
                    $this->_request_data['membership'] = $campaign_membership_map[$campaign->id];
                }

                // TODO: Get count of members and messages here

                midcom_show_style('show-campaign-list-item');
            }
        }
        midcom_show_style("show-campaign-list-footer");
    }

    /**
     * Handle the unsubscribe phase
     *
     * @param String $handler_id    Name of the request handler
     * @param array $args           Variable arguments
     * @param array &$data          Public request data, passed by reference
     * @return boolean              Indicating success
     */
    public function _handler_unsubscribe($handler_id, $args, &$data)
    {
        if (count($args) != 1)
        {
            throw new midcom_error_notfound("Missing member ID.");
        }

        $_MIDCOM->auth->request_sudo();

        $this->_request_data['membership'] = new org_openpsa_directmarketing_campaign_member_dba($args[0]);
        if (!$this->_request_data['membership']->guid)
        {
            throw new midcom_error_notfound("Membership record '{$args[0]}' not found");
        }

        $this->_request_data['campaign'] = new org_openpsa_directmarketing_campaign_dba($this->_request_data['membership']->campaign);
        if ($this->_request_data['campaign']->node != $this->_topic->id)
        {
            throw new midcom_error_notfound("Campaign for member '{$args[0]}' not found");
        }

        $this->_request_data['membership']->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_CAMPAIGN_MEMBER_UNSUBSCRIBED;
        $this->_request_data['unsubscribe_status'] = $this->_request_data['membership']->update();
        debug_add("Unsubscribe status: {$this->_request_data['unsubscribe_status']}");
        $_MIDCOM->auth->drop_sudo();
        //This is often called by people who should not see anything pointing to OpenPSA, also allows full styling of the unsubscribe page
        $_MIDCOM->skip_page_style = true;

        return true;
    }

    /**
     * Show the unsubscribe action
     *
     * @param String $handler_id    Name of the request handler
     * @param array &$data          Public request data, passed by reference
     */
    public function _show_unsubscribe($handler_id, &$data)
    {
        if ($this->_request_data['unsubscribe_status'] == false)
        {
            midcom_show_style('show-unsubscribe-failed');
        }
        else
        {
            midcom_show_style('show-unsubscribe-ok');
        }
    }

    /**
     * Support the AJAX request for unsubscribing from a campaign
     *
     * @param String $handler_id    Name of the request handler
     * @param array $args           Variable arguments
     * @param array &$data          Public request data, passed by reference
     * @return boolean              Indicating success
     */
    public function _handler_unsubscribe_ajax($handler_id, $args, &$data)
    {
        if (count($args) != 1)
        {
            throw new midcom_error_notfound("Missing member ID.");
            // This will exit.
        }
        $_MIDCOM->auth->request_sudo();
        $this->_request_data['membership'] = new org_openpsa_directmarketing_campaign_member_dba($args[0]);
        if (!$this->_request_data['membership']->guid)
        {
            throw new midcom_error_notfound("Membership record '{$args[0]}' not found");
        }
        $this->_request_data['campaign'] = new org_openpsa_directmarketing_campaign_dba($this->_request_data['membership']->campaign);
        if ($this->_request_data['campaign']->node != $this->_topic->id)
        {
            throw new midcom_error_notfound("Campaign for member '{$args[0]}' not found");
        }

        $this->_request_data['membership']->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_CAMPAIGN_MEMBER_UNSUBSCRIBED;
        $this->_request_data['unsubscribe_status'] = $this->_request_data['membership']->update();

        debug_add("Unsubscribe status: {$this->_request_data['unsubscribe_status']}");

        $_MIDCOM->auth->drop_sudo();
        //This is often called by people who should not see anything pointing to OpenPSA, also allows full styling of the unsubscribe page
        $_MIDCOM->skip_page_style = true;

        $message = new org_openpsa_helpers_ajax();
        $message->simpleReply($this->_request_data['unsubscribe_status'], "Unsubscribe failed");
        // This will exit

        return true;
    }

    /**
     * Show the empty style of AJAX unsubscribing
     *
     * @param String $handler_id    Name of the request handler
     * @param array &$data          Public request data, passed by reference
     */
    public function _show_unsubscribe_ajax($handler_id, &$data)  { }

    /**
     * Handle the request for unsubscribing all subscribers from a campaign
     *
     * @param String $handler_id    Name of the request handler
     * @param array $args           Variable arguments
     * @param array &$data          Public request data, passed by reference
     * @return boolean              Indicating success
     */
    public function _handler_unsubscribe_all($handler_id, $args, &$data)
    {
        if (count($args) < 1)
        {
            throw new midcom_error_notfound("Missing member ID.");
        }
        $_MIDCOM->auth->request_sudo();
        $this->_request_data['person'] = new org_openpsa_contacts_person_dba($args[0]);

        if (!$this->_request_data['person']->id)
        {
            throw new midcom_error_notfound("Membership record '{$args[0]}' not found");
        }
        if ($handler_id === 'subscriber_unsubscribe_all_future')
        {
            $deny_type = strtolower($args[1]);
            $this->_request_data['person']->set_parameter('org.openpsa.directmarketing', "send_{$deny_type}_denied", '1');
        }
        $this->_request_data['unsubscribe_status'] = true;

        $qb = org_openpsa_directmarketing_campaign_member_dba::new_query_builder();
        $qb->add_constraint('campaign.node', '=', $this->_topic->id);
        $qb->add_constraint('person', '=', $this->_request_data['person']->id);
        // FIXME: Use NOT IN
        $qb->add_constraint('orgOpenpsaObtype', '<>', ORG_OPENPSA_OBTYPE_CAMPAIGN_MEMBER_UNSUBSCRIBED);
        $qb->add_constraint('orgOpenpsaObtype', '<>', ORG_OPENPSA_OBTYPE_CAMPAIGN_TESTER);
        $memberships = $qb->execute();

        if ($memberships === false)
        {
            //Some error occurred with QB
            $_MIDCOM->auth->drop_sudo();
            return false;
        }
        foreach ($memberships as $member)
        {
            $member->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_CAMPAIGN_MEMBER_UNSUBSCRIBED;
            $mret = $member->update();
            if (!$mret)
            {
                //TODO: How to report failures of single rows when other succeed sensibly ??
                $this->_request_data['unsubscribe_status'] = false;
            }
        }

        $_MIDCOM->auth->drop_sudo();
        //This is often called by people who should not see anything pointing to OpenPSA, also allows full styling of the unsubscribe page
        $_MIDCOM->skip_page_style = true;

        return true;
    }

    /**
     * Show the unsubscribe status for unsubscribe all
     *
     * @param String $handler_id    Name of the request handler
     * @param array &$data          Public request data, passed by reference
     */
    public function _show_unsubscribe_all($handler_id, &$data)
    {
        if ($data['unsubscribe_status'] == false)
        {
            midcom_show_style('show-unsubscribe-failed');
        }
        else
        {
            midcom_show_style('show-unsubscribe-ok');
        }
    }
}
?>