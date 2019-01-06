<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Attachment editing interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_object_attachments extends midcom_baseclasses_components_handler
{
    /**
     * Current loaded object
     *
     * @var midcom_core_dbaobject
     */
    private $_object;

    /**
     * Files in the current object
     *
     * @var midcom_db_attachment[]
     */
    private $_files = [];

    /**
     * Current file being edited
     *
     * @var midcom_db_attachment
     */
    private $_file;

    public function _on_initialize()
    {
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.datamanager/default.css');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midgard.admin.asgard/attachments/layout.css');
    }

    private function _process_file_upload(UploadedFile $file)
    {
        if (is_null($this->_file)) {
            $local_filename = midcom_db_attachment::safe_filename($file->getClientOriginalName());
            $local_file = $this->_get_file($local_filename, true);
        } else {
            $local_file = $this->_file;
        }

        if ($local_file->mimetype != $file->getMimeType()) {
            $local_file->mimetype = $file->getMimeType();
            $local_file->update();
        }

        if (!$local_file->copy_from_file($file->getPathname())) {
            return false;
        }
        return $local_file->name;
    }

    private function _process_form(Request $request)
    {
        if (!$request->request->has('midgard_admin_asgard_save')) {
            return false;
        }

        // Check if we have an uploaded file
        $file = $request->files->get('midgard_admin_asgard_file');
        if ($file && $file instanceof UploadedFile) {
            return $this->_process_file_upload($file);
        }

        if (is_null($this->_file)) {
            if (!$request->request->has('midgard_admin_asgard_filename')) {
                return false;
            }

            // We're creating a new file
            $local_filename = midcom_db_attachment::safe_filename($request->request->get('midgard_admin_asgard_filename'));
            $local_file = $this->_get_file($local_filename, true);
        } else {
            $local_file = $this->_file;
        }

        $needs_update = false;

        $filename = $request->request->get('midgard_admin_asgard_filename');
        if (!empty($filename) && $local_file->name != $filename) {
            $local_file->name = $filename;
            $needs_update = true;
        }

        $mimetype = $request->request->get('midgard_admin_asgard_mimetype');
        if (   !empty($mimetype)
            && $local_file->mimetype != $mimetype) {
            $local_file->mimetype = $mimetype;
            $needs_update = true;
        }

        if (   $needs_update
            && !$local_file->update()) {
            return false;
        }

        // We should always store at least an empty string so it can be edited later
        $contents = $request->request->get('midgard_admin_asgard_contents', '');

        if (!$local_file->copy_from_memory($contents)) {
            return false;
        }
        return $local_file->name;
    }

    /**
     *
     * @param string $filename
     * @param boolean $autocreate
     * @return midcom_db_attachment
     */
    private function _get_file($filename, $autocreate = false)
    {
        $qb = midcom_db_attachment::new_query_builder();
        $qb->add_constraint('parentguid', '=', $this->_object->guid);
        $qb->add_constraint('name', '=', $filename);

        $files = $qb->execute();
        if (empty($files)) {
            if (!$autocreate) {
                throw new midcom_error_notfound("Attachment '{$filename}' of object {$this->_object->guid} was not found.");
            }
            $file = new midcom_db_attachment();
            $file->name = $filename;
            $file->parentguid = $this->_object->guid;

            if (!$file->create()) {
                throw new midcom_error('Failed to create attachment, reason: ' . midcom_connection::get_error_string());
            }
            return $file;
        }
        return $files[0];
    }

    private function _list_files()
    {
        $qb = midcom_db_attachment::new_query_builder();
        $qb->add_constraint('parentguid', '=', $this->_object->guid);
        $qb->add_order('mimetype');
        $qb->add_order('metadata.score', 'DESC');
        $qb->add_order('name');
        $this->_files = $qb->execute();
    }

    /**
     * Add the necessary files for attachment operations, if attachments exist
     */
    private function _add_jscripts()
    {
        if (!empty($this->_files)) {
            // Add Colorbox
            midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/colorbox/jquery.colorbox-min.js');
            $this->add_stylesheet(MIDCOM_STATIC_URL . '/jQuery/colorbox/colorbox.css', 'screen');
            midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midgard.admin.asgard/object_browser.js');

            //add table widget
            midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.tablesorter.pack.js');
            $this->add_stylesheet(MIDCOM_STATIC_URL . '/midgard.admin.asgard/tablewidget.css');
            midcom\workflow\delete::add_head_elements();
        }
    }

    private function prepare_object($guid)
    {
        midcom::get()->auth->require_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin');
        $this->_object = midcom::get()->dbfactory->get_object_by_guid($guid);
        $this->_object->require_do('midgard:update');
        $this->_object->require_do('midgard:attachments');
    }

    /**
     * Handler for creating new attachments
     *
     * @param string $handler_id Name of the used handler
     * @param string $guid The object's GUID
     * @param array &$data Data passed to the show method
     */
    public function _handler_create(Request $request, $handler_id, $guid, array &$data)
    {
        $this->prepare_object($guid);

        if ($filename = $this->_process_form($request)) {
            return $this->relocate_to_file($filename);
        }

        $this->_list_files();
        $this->_add_jscripts();

        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        return new midgard_admin_asgard_response($this, '_show_create');
    }

    private function relocate_to_file($filename)
    {
        $url = $this->router->generate('object_attachments_edit', [
            'guid' => $this->_object->guid,
            'filename' => $filename
        ]);
        return new midcom_response_relocate($url);
    }

    /**
     * @param string $handler_id Name of the used handler
     * @param array &$data Data passed to the show method
     */
    public function _show_create($handler_id, array &$data)
    {
        $data['files'] = $this->_files;
        $data['object'] = $this->_object;
        midcom_show_style('midgard_admin_asgard_object_attachments_header');

        $data['attachment_text_types'] = $this->_config->get('attachment_text_types');
        midcom_show_style('midgard_admin_asgard_object_attachments_new');
        midcom_show_style('midgard_admin_asgard_object_attachments_footer');
    }

    /**
     * @param string $handler_id Name of the used handler
     * @param string $guid The object's GUID
     * @param string $filename The filename
     * @param array &$data Data passed to the show method
     */
    public function _handler_edit(Request $request, $handler_id, $guid, $filename, array &$data)
    {
        $this->prepare_object($guid);

        $data['filename'] = $filename;
        $this->_file = $this->_get_file($data['filename']);
        $this->_file->require_do('midgard:update');
        $this->bind_view_to_object($this->_file);

        $filename = $this->_process_form($request);
        if (   $filename
            && $filename != $data['filename']) {
            return $this->relocate_to_file($filename);
        }

        $this->_list_files();
        $this->_add_jscripts();

        $data['attachment_text_types'] = $this->_config->get('attachment_text_types');
        if (array_key_exists($this->_file->mimetype, $data['attachment_text_types'])) {
            // Figure out correct syntax from MIME type
            switch (preg_replace('/.+?\//', '', $this->_file->mimetype)) {
                case 'css':
                    $data['file_syntax'] = 'css';
                    break;

                case 'html':
                    $data['file_syntax'] = 'html';
                    break;

                case 'x-javascript':
                case 'javascript':
                    $data['file_syntax'] = 'javascript';
                    break;

                default:
                    $data['file_syntax'] = 'text';
            }
        }

        midgard_admin_asgard_plugin::bind_to_object($this->_object, $handler_id, $data);
        return new midgard_admin_asgard_response($this, '_show_edit');
    }

    /**
     * Show the editing view for the requested style
     *
     * @param string $handler_id Name of the used handler
     * @param array &$data Data passed to the show method
     */
    public function _show_edit($handler_id, array &$data)
    {
        $data['files'] = $this->_files;
        $data['file'] = $this->_file;
        $data['object'] = $this->_object;
        midcom_show_style('midgard_admin_asgard_object_attachments_header');
        midcom_show_style('midgard_admin_asgard_object_attachments_file');
        midcom_show_style('midgard_admin_asgard_object_attachments_footer');
    }

    /**
     * Handler for confirming file deleting for the requested file
     *
     * @param string $guid The object's GUID
     * @param string $filename The filename
     */
    public function _handler_delete($guid, $filename)
    {
        $this->prepare_object($guid);
        $file = $this->_get_file($filename);

        $workflow = $this->get_workflow('delete', [
            'object' => $file,
            'label' => $filename,
            'success_url' => $this->router->generate('object_attachments', ['guid' => $this->_object->guid])
        ]);
        return $workflow->run();
    }
}
