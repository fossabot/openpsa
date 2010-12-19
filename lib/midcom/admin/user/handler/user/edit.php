<?php
/**
 * @package midcom.admin.user
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Style editor class for listing style elements
 *
 * @package midcom.admin.user
 */
class midcom_admin_user_handler_user_edit extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_edit
{
    private $_person = null;

    private $_schemadb_name = 'schemadb_person';

    public function _on_initialize()
    {
        $_MIDCOM->load_library('midcom.helper.datamanager2');

        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.admin.user/usermgmt.css');

        midgard_admin_asgard_plugin::prepare_plugin($this->_l10n->get('midcom.admin.user'),$this->_request_data);
    }

    private function _prepare_toolbar(&$data, $handler_id)
    {
        if (   $handler_id !== '____mfa-asgard_midcom.admin.user-user_edit_password'
            && $this->_config->get('allow_manage_accounts')
            && $this->_person)
        {
            $data['asgard_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard/preferences/{$this->_person->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('user preferences', 'midgard.admin.asgard'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/configuration.png',
                )
            );
            $data['asgard_toolbar']->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "__mfa/asgard_midcom.admin.user/password/{$this->_person->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $_MIDCOM->i18n->get_string('edit account', 'midcom.admin.user'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/repair.png',
                )
            );

        }
        $data['asgard_toolbar']->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "__mfa/asgard_midcom.admin.user/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('midcom.admin.user'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_person-new.png',
            )
        );

        midgard_admin_asgard_plugin::bind_to_object($this->_person, $handler_id, $data);
    }

    /**
     * Loads and prepares the schema database.
     */
    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get($this->_schemadb_name));
    }

    /**
     * Handler method for listing style elements for the currently used component topic
     *
     * @param string $handler_id Name of the used handler
     * @param mixed $args Array containing the variable arguments passed to the handler
     * @param mixed &$data Data passed to the show method
     * @return boolean Indicating successful request
     */
    public function _handler_edit($handler_id, $args, &$data)
    {
        $this->_person = new midcom_db_person($args[0]);

        if (!$this->_person->guid)
        {
            return false;
        }

        $this->_person->require_do('midgard:update');

        if ($handler_id == '____mfa-asgard_midcom.admin.user-user_edit_password')
        {
            if (!$this->_config->get('allow_manage_accounts'))
            {
                return false;
            }
            $this->_schemadb_name = 'schemadb_account';
        }

        $data['view_title'] = sprintf($_MIDCOM->i18n->get_string('edit %s', 'midcom.admin.user'), $this->_person->name);
        $_MIDCOM->set_pagetitle($data['view_title']);
        $this->_prepare_toolbar($data, $handler_id);
        $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/", $data['view_title']);

        // Add jQuery Form handling for generating passwords with AJAX
        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.form.js');

        // Manually check the username to prevent duplicates
        if (   isset($_REQUEST['midcom_helper_datamanager2_save'])
            && isset($_POST['username']))
        {
            // If there was a username, check against duplicates
            if ($_POST['username'])
            {
                $qb = midcom_db_person::new_query_builder();
                $qb->add_constraint('username', '=', $_POST['username']);
                $qb->add_constraint('guid', '<>', $this->_person->guid);

                // If matches were found, add an error message
                if ($qb->count() > 0)
                {
                    $_MIDCOM->uimessages->add($_MIDCOM->i18n->get_string('midcom.admin.user', 'midcom.admin.user'), sprintf($_MIDCOM->i18n->get_string('username %s is already in use', 'midcom.admin.user'), $_REQUEST['username']));
                    unset($_POST['midcom_helper_datamanager2_save']);
                    unset($_REQUEST['midcom_helper_datamanager2_save']);
                }
            }
            else
            {
                // Remove the password requirement if there is no username present
                foreach ($this->_schemadb as $key => $schema)
                {
                    if (isset($schema->fields['password']))
                    {
                        $this->_schemadb[$key]->fields['password']['widget_config']['require_password'] = false;
                    }
                }
            }
        }

        $data['controller'] = $this->get_controller('simple', $this->_person);

        switch ($data['controller']->process_form())
        {
            case 'save':
                // Show confirmation for the user
                $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), sprintf($this->_l10n->get('person %s saved'), $this->_person->name));
                $_MIDCOM->relocate("__mfa/asgard_midcom.admin.user/edit/{$this->_person->guid}/");
                // This will exit.

            case 'cancel':
                $_MIDCOM->relocate('__mfa/asgard_midcom.admin.user/');
                // This will exit.
        }

        return true;
    }

    /**
     * Show list of the style elements for the currently edited topic component
     *
     * @param string $handler_id Name of the used handler
     * @param mixed &$data Data passed to the show method
     */
    public function _show_edit($handler_id, &$data)
    {
        midgard_admin_asgard_plugin::asgard_header();

        $data['handler_id'] = $handler_id;
        $data['l10n'] =& $this->_l10n;
        $data['person'] =& $this->_person;
        midcom_show_style('midcom-admin-user-person-edit');

        if (isset($_GET['f_submit']))
        {
            midcom_show_style('midcom-admin-user-generate-passwords');
        }

        midgard_admin_asgard_plugin::asgard_footer();
    }

    /**
     * Auto-generate passwords on the fly
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_passwords($handler_id, $args, &$data)
    {
        $_MIDCOM->skip_page_style = true;
        return true;
    }

    /**
     * Auto-generate passwords on the fly
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    public function _show_passwords($handler_id, &$data)
    {
        // Show passwords
        $data['l10n'] =& $this->_l10n;
        midcom_show_style('midcom-admin-user-generate-passwords');
    }

    /**
     * Internal helper for processing the batch change of passwords
     */
    private function _process_batch_change()
    {
        // Load the org.openpsa.mail class
        $_MIDCOM->componentloader->load('org.openpsa.mail');

        // Set the mail commo parts
        $mail = new org_openpsa_mail();
        $mail->from = $this->_config->get('message_sender');
        $mail->encoding = 'UTF-8';

        // Success switch
        $success = true;

        // Get the context prefix
        $prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

        // Change every user or continue to next on failure - failures will show UI messages
        foreach ($_POST['midcom_admin_user'] as $id)
        {
            $person = new midcom_db_person($id);

            // Check integrity
            if (!$person->guid)
            {
                $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), sprintf($this->_l10n->get('failed to get the user with id %s'), $id), 'error');
                $success = false;
                continue;
            }

            // This shortcut is used in case of errors
            $person_edit_url = "<a href=\"{$prefix}__mfa/asgard_midcom.admin.user/edit/{$person->guid}\">{$person->name}</a>";

            // Cannot send the email if address is not specified
            if (!$person->email)
            {
                $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), sprintf($this->_l10n->get('no email address defined for %s'), $person_edit_url), 'error');
                continue;
            }

            // Recipient
            $mail->to = $person->email;

            // Store the old password
            $person->set_parameter('midcom.admin.user', 'old_password', $person->password);

            // Get a new password
            $password = midcom_admin_user_plugin::generate_password(8);

            $mail->body = $this->_personalize_mail_string($_POST['body'], $person, $password);
            $mail->subject = $this->_personalize_mail_string($_POST['subject'], $person, $password);;

            // Send the message
            if ($mail->send())
            {
                // Set the password
                $person->password = "**{$password}";

                if (!$person->update())
                {
                    $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), sprintf($this->_l10n->get('failed to update the password for %s'), $person_edit_url));
                    $success = false;
                }
            }
            else
            {
                throw new midcom_error("Failed to send the mail, SMTP returned error " . $mail->get_error_message());
            }
        }

        // Show UI message on success
        if ($success)
        {
            $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), $this->_l10n->get('passwords updated and mail sent'));
        }
    }

    private function _personalize_mail_string($string, &$person, $password)
    {
        foreach ($this->_request_data['variables'] as $key => $value)
        {
            // Replace the variables with personalized values
            switch ($key)
            {
                case '__PASSWORD__':
                    $string = str_replace($key, $password, $string);
                    break;

                case '__FROM__':
                    $string = str_replace($key, $this->_config->get('message_sender'), $string);
                    break;

                case '__LONGDATE__':
                    $string = str_replace($key, strftime('%c'), $string);
                    break;

                case '__SHORTDATE__':
                    $string = str_replace($key, strftime('%x'), $string);
                    break;

                case '__TIME__':
                    $string = str_replace($key, strftime('%X'), $string);
                    break;

                default:
                    if (!isset($person->$key))
                    {
                        continue;
                    }
                    $string = str_replace($key, $person->$key, $string);
            }
        }
        return $string;
    }

    /**
     * Batch process password change
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_batch($handler_id, $args, &$data)
    {
        $_MIDCOM->auth->require_admin_user();

        // Set page title and default variables
        $data['view_title'] = $this->_l10n->get('batch generate passwords');
        $data['variables'] = array
        (
            '__FIRSTNAME__' => $this->_l10n->get('firstname'),
            '__LASTNAME__' => $this->_l10n->get('lastname'),
            '__USERNAME__' => $this->_l10n->get('username'),
            '__EMAIL__' => $this->_l10n->get('email'),
            '__PASSWORD__' => $this->_l10n->get('password'),
            '__FROM__' => $this->_l10n->get('sender'),
            '__LONGDATE__' => sprintf($this->_l10n->get('long dateformat (%s)'), strftime('%c')),
            '__SHORTDATE__' => sprintf($this->_l10n->get('short dateformat (%s)'), strftime('%x')),
            '__TIME__' => sprintf($this->_l10n->get('current time (%s)'), strftime('%X')),
        );

        if (   isset($_POST['midcom_admin_user'])
            && count($_POST['midcom_admin_user']) > 0)
        {
            if (isset($_POST['f_cancel']))
            {
                $_MIDCOM->uimessages->add($this->_l10n->get('midcom.admin.user'), $_MIDCOM->i18n->get_string('cancelled', 'midcom'));
                $_MIDCOM->relocate('__mfa/asgard_midcom.admin.user/');
                // This will exit
            }
            $this->_process_batch_change();
            // Relocate to the user administration front page
            $_MIDCOM->relocate('__mfa/asgard_midcom.admin.user/');
            // This will exit
        }

        if (isset($_GET['ajax']))
        {
            return true;
        }

        // Prepare the toolbar and breadcrumb
        $this->_prepare_toolbar($data, $handler_id);

        // Populate breadcrumb
        $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/", $this->_l10n->get('midcom.admin.user'));
        $this->add_breadcrumb('__mfa/asgard_midcom.admin.user/password/batch/', $data['view_title']);

        return true;
    }

    /**
     * Show the batch password change form
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    public function _show_batch($handler_id, &$data)
    {
        if (!isset($_GET['ajax']))
        {
            midgard_admin_asgard_plugin::asgard_header();
            midcom_show_style('midcom-admin-user-password-nonajax-header');
        }

        $data['message_subject'] = $this->_l10n->get($this->_config->get('message_subject'));
        $data['message_body'] = $this->_l10n->get($this->_config->get('message_body'));

        midcom_show_style('midcom-admin-user-password-email');

        if (!isset($_GET['ajax']))
        {
            midcom_show_style('midcom-admin-user-passwords-list');
            midgard_admin_asgard_plugin::asgard_footer();
        }
    }
}
?>