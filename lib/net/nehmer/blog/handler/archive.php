<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/** @ignore */
require_once('Date.php');

/**
 * Blog Archive pages handler
 *
 * Shows the various archive views.
 *
 * @package net.nehmer.blog
 */
class net_nehmer_blog_handler_archive extends midcom_baseclasses_components_handler
{
    /**
     * The content topic to use
     *
     * @var midcom_db_topic
     * @access private
     */
    private $_content_topic = null;

    /**
     * The articles to display
     *
     * @var Array
     * @access private
     */
    private $_articles = null;

    /**
     * The datamanager for the currently displayed article.
     *
     * @var midcom_helper_datamanager2_datamanager
     */
    private $_datamanager = null;

    /**
     * The start date of the Archive listing.
     *
     * @var Date
     * @access private
     */
    private $_start = null;

    /**
     * The end date of the Archive listing.
     *
     * @var Date
     * @access private
     */
    private $_end = null;

    /**
     * Maps the content topic from the request data to local member variables.
     */
    public function _on_initialize()
    {
        $this->_content_topic =& $this->_request_data['content_topic'];
        $this->_request_data['config'] =& $this->_config;
    }

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['datamanager'] =& $this->_datamanager;
        $this->_request_data['start'] =& $this->_start;
        $this->_request_data['end'] =& $this->_end;
    }

    /**
     * Shows the archive welcome page: A listing of years/months along with total post counts
     * and similar stuff.
     *
     * The handler computes all necessary data and populates the request array accordingly.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_welcome ($handler_id, $args, &$data)
    {
        $this->_compute_welcome_data();
        $this->_prepare_request_data();

        if ($this->_config->get('archive_in_navigation'))
        {
            $this->set_active_leaf($this->_topic->id . '_ARCHIVE');
        }

        $_MIDCOM->set_pagetitle("{$this->_topic->extra}: " . $this->_l10n->get('archive'));

        $_MIDCOM->set_26_request_metadata(net_nehmer_blog_viewer::get_last_modified($this->_topic, $this->_content_topic), $this->_topic->guid);
        return true;
    }

    /**
     * Loads the first posting time from the DB. This is the base for all operations on the
     * resultset.
     *
     * This is done under sudo if possible, to avoid problems arising if the first posting
     * is hidden. This keeps up performance, as an execute_unchecked() can be made in this case.
     * If sudo cannot be acquired, the system falls back to excute().
     *
     * @return Date The time of the first posting or null on failure.
     */
    private function _compute_welcome_first_post()
    {
        $qb = midcom_db_article::new_query_builder();
        $data =& $this->_request_data;
        net_nehmer_blog_viewer::article_qb_constraints($qb, $data, 'arhive_welcome');
        $qb->add_constraint('metadata.published', '>', '1970-01-02 23:59:59');

        $qb->add_order('metadata.published');
        $qb->set_limit(1);

        if ($_MIDCOM->auth->request_sudo())
        {
            $result = $qb->execute_unchecked();
            $_MIDCOM->auth->drop_sudo();
        }
        else
        {
            $result = $qb->execute();
        }

        if ($result)
        {
            return new Date($result[0]->metadata->published);
        }
        else
        {
            return null;
        }
    }

    /**
     * Computes the number of postings in a given timeframe.
     *
     * @param Date $start Start of the timeframe (inclusive)
     * @param Date $end End of the timeframe (exclusive)
     * @return int Posting count
     */
    private function _compute_welcome_posting_count($start, $end)
    {
        $data =& $this->_request_data;
        $qb = midcom_db_article::new_query_builder();

        $qb->add_constraint('metadata.published', '>=', $start->getDate());
        $qb->add_constraint('metadata.published', '<', $end->getDate());
        net_nehmer_blog_viewer::article_qb_constraints($qb, $data, 'archive_welcome');

        return $qb->count();
    }

    /**
     * Computes the data nececssary for the welcome screen. Automatically put into the request
     * data array.
     */
    private function _compute_welcome_data()
    {
        // Helpers
        $prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX) . 'archive/';

        // First step of request data: Overall info
        $total_count = 0;
        $year_data = Array();
        $first_post = $this->_compute_welcome_first_post();
        $this->_request_data['first_post'] =& $first_post;
        $this->_request_data['total_count'] =& $total_count;
        $this->_request_data['year_data'] =& $year_data;
        if (! $first_post)
        {
            return;
        }

        // Second step of request data: Years and months.
        $now = new Date();
        $first_year = $first_post->getYear();
        $last_year = $now->getYear();

        $month_names = Date_Calc::getMonthNames();

        //for ($year = $first_year; $year <= $last_year; $year++)
        for ($year = $last_year; $year >= $first_year; $year--)
        {
            $year_url = "{$prefix}year/{$year}/";
            $year_count = 0;
            $month_data = Array();

            // Loop over the months, start month is either first posting month
            // or January in all other cases. End months are treated similarly,
            // being december by default unless for the current year.
            if ($year == $first_year)
            {
                $first_month = $first_post->getMonth();
            }
            else
            {
                $first_month = 1;
            }

            if ($year == $last_year)
            {
                $last_month = $now->getMonth();
            }
            else
            {
                $last_month = 12;
            }

            for ($month = $first_month; $month <= $last_month; $month++)
            {
                $start_time = $now;
                $start_time->setYear($year);
                $start_time->setMonth($month);
                $start_time->setDay(1);
                $start_time->setHour(0);
                $start_time->setMinute(0);
                $start_time->setSecond(0);
                $end_time = clone($start_time);
                if ($month == 12)
                {
                    $end_time->setYear($year + 1);
                    $end_time->setMonth(1);
                }
                else
                {
                    $end_time->setMonth($month + 1);
                }

                $month_url = "{$prefix}month/{$year}/{$month}/";
                $month_count = $this->_compute_welcome_posting_count($start_time, $end_time);
                $year_count += $month_count;
                $total_count += $month_count;
                $month_data[$month] = Array
                (
                    'month' => $month,
                    'name' => $month_names[$month],
                    'url' => $month_url,
                    'count' => $month_count,
                );
            }

            $year_data[$year] = Array
            (
                'year' => $year,
                'url' => $year_url,
                'count' => $year_count,
                'month_data' => $month_data,
            );
        }
    }

    /**
     * Displays the welcome page.
     *
     * Element sequence:
     *
     * - archive-welcome-start (Start of the archive welcome page)
     * - archive-welcome-year (Display of a single year, may not be called when there are no postings)
     * - archive-welcome-end (End of the archive welcome page)
     *
     * Context data for all elements:
     *
     * - int total_count (total number of postings w/o ACL restrictions)
     * - Date first_post (the first posting date, may be null)
     * - Array year_data (the year data, contains the year context info as outlined below)
     *
     * Context data for year elements:
     *
     * - int year (the year displayed)
     * - string url (url to display the complete year)
     * - int count (Number of postings in that year)
     * - array month_data (the monthly data)
     *
     * month_data will contain an associative array containing the following array of data
     * indexed by month number (1-12):
     *
     * - string 'url' => The URL to the month.
     * - string 'name' => The localized name of the month.
     * - int 'count' => The number of postings in that month.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    public function _show_welcome($handler_id, &$data)
    {
        midcom_show_style('archive-welcome-start');

        foreach ($data['year_data'] as $year => $year_data)
        {
            $data['year'] = $year;
            $data['url'] = $year_data['url'];
            $data['count'] = $year_data['count'];
            $data['month_data'] = $year_data['month_data'];
            midcom_show_style('archive-welcome-year');
        }

        midcom_show_style('archive-welcome-end');
    }

    /**
     * Shows the archive. Depending on the selected handler various constraints are added to
     * the QB. See the add_*_constraint methods for details.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     * @return boolean Indicating success.
     */
    public function _handler_list ($handler_id, $args, &$data)
    {
        // Get Articles, distinguish by handler.
        $qb = midcom_db_article::new_query_builder();
        net_nehmer_blog_viewer::article_qb_constraints($qb, $data, $handler_id);

        // Use helper functions to determine start/end
        switch ($handler_id)
        {
            case 'archive-year-category':
                if (!$this->_config->get('archive_years_enable'))
                {
                    throw new midcom_error_notfound('Year archive not allowed');
                }

                $data['category'] = trim(strip_tags($args[1]));
                $multiple_categories = true;
                if (   isset($data['schemadb']['default']->fields['categories'])
                    && array_key_exists('allow_multiple', $data['schemadb']['default']->fields['categories']['type_config'])
                    && !$data['schemadb']['default']->fields['categories']['type_config']['allow_multiple'])
                {
                    $multiple_categories = false;
                }
                if ($multiple_categories)
                {
                    $qb->add_constraint('extra1', 'LIKE', "%|{$this->_request_data['category']}|%");
                }
                else
                {
                    $qb->add_constraint('extra1', '=', (string) $data['category']);
                }
            case 'archive-year':
                if (!$this->_config->get('archive_years_enable'))
                {
                    throw new midcom_error_notfound('Year archive not allowed');
                }

                $this->_set_startend_from_year($args[0]);
                break;

            case 'archive-month':
                $this->_set_startend_from_month($args[0], $args[1]);
                break;

            default:
                throw new midcom_error("The request handler {$handler_id} is not supported.");
        }

        $qb->add_constraint('metadata.published', '>=', $this->_start->getDate());
        $qb->add_constraint('metadata.published', '<', $this->_end->getDate());
        $qb->add_order('metadata.published', $this->_config->get('archive_item_order'));
        $this->_articles = $qb->execute();

        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_request_data['schemadb']);

        // Move end date one day backwards for display purposes.
        $now = new Date();
        if ($now->before($this->_end))
        {
            $this->_end = $now;
        }
        else
        {
            $this->_end->subtractSeconds(86400);
        }

        $start = $this->_start->format($this->_l10n_midcom->get('short date'));
        $end = $this->_end->format($this->_l10n_midcom->get('short date'));
        $this->add_breadcrumb("archive/year/{$args[0]}/", "{$start} - {$end}");

        $this->_prepare_request_data();

        if ($this->_config->get('archive_in_navigation'))
        {
            $this->set_active_leaf($this->_topic->id . '_ARCHIVE');
        }
        else
        {
            $this->set_active_leaf($this->_topic->id . '_ARCHIVE_' . $args[0]);
        }

        $_MIDCOM->set_26_request_metadata(net_nehmer_blog_viewer::get_last_modified($this->_topic, $this->_content_topic), $this->_topic->guid);
        $_MIDCOM->set_pagetitle("{$this->_topic->extra}: {$start} - {$end}");

        return true;
    }

    /**
     * Computes the start/end dates to only query a given year. It will do validation
     * before processing, throwing 404 in case of incorrectly formatted dates.
     *
     * This is used by the archive-year handler, which expects the year to be in $args[0].
     *
     * @param int $year The year to query.
     */
    private function _set_startend_from_year($year)
    {
        if (   ! is_numeric($year)
            || strlen($year) != 4)
        {
            throw new midcom_error_notfound("The year '{$year}' is not a valid year identifier.");
        }

        $now = new Date();
        if ($year > $now->getYear())
        {
            throw new midcom_error_notfound("The year '{$year}' is in the future, no archive available.");
        }

        $endyear = $year + 1;
        $this->_start = new Date("{$year}-01-01 00:00:00");
        $this->_end = new Date("{$endyear}-01-01 00:00:00");
    }

    /**
     * Computes the start/end dates to only query a given month. It will do validation
     * before processing, throwing 404 in case of incorrectly formatted dates.
     *
     * This is used by the archive-month handler, which expects the year to be in $args[0]
     * and the month to be in $args[1].
     *
     * @param int $year The year to query.
     * @param int $month The month to query.
     */
    private function _set_startend_from_month($year, $month)
    {
        if (   ! is_numeric($year)
            || strlen($year) != 4)
        {
            throw new midcom_error_notfound("The year '{$year}' is not a valid year identifier.");
        }

        if (   ! is_numeric($month)
            || $month < 1
            || $month > 12)
        {
            throw new midcom_error_notfound("The year {$month} is not a valid year identifier.");
        }

        $now = new Date();
        if (strlen($month) == 1)
        {
            $month = "0{$month}";
        }
        $this->_start = new Date("{$year}-{$month}-01 00:00:00");
        if ($this->_start->after($now))
        {
            throw new midcom_error_notfound("The month '{$year}-{$month}' is in the future, no archive available.");
        }

        if ($month == 12)
        {
            $endyear = $year + 1;
            $endmonth = 1;
        }
        else
        {
            $endyear = $year;
            $endmonth = $month + 1;
        }
        if (strlen($endmonth) == 1)
        {
            $endmonth = "0{$endmonth}";
        }
        $this->_end = new Date("{$endyear}-{$endmonth}-01 00:00:00");
    }

    /**
     * Displays the archive.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    public function _show_list($handler_id, &$data)
    {
        // FIXME: For some reason the config topic is lost between _handle and _show phases
        $this->_config->store_from_object($this->_topic, $this->_component);

        midcom_show_style('archive-list-start');
        if ($this->_articles)
        {
            $data['index_fulltext'] = $this->_config->get('index_fulltext');
            if ($this->_config->get('comments_enable'))
            {
                $_MIDCOM->componentloader->load_graceful('net.nehmer.comments');
                $data['comments_enable'] = true;
            }

            $total_count = count($this->_articles);
            $prefix = $_MIDCOM->get_context_data(MIDCOM_CONTEXT_ANCHORPREFIX);

            foreach ($this->_articles as $article_counter => $article)
            {
                if (! $this->_datamanager->autoset_storage($article))
                {
                    debug_add("The datamanager for article {$article->id} could not be initialized, skipping it.");
                    debug_print_r('Object was:', $article);
                    continue;
                }

                $data['article'] =& $article;
                $data['article_counter'] = $article_counter;
                $data['article_count'] = $total_count;
                $arg = $article->name ? $article->name : $article->guid;

                if ($this->_config->get('view_in_url'))
                {
                    $data['local_view_url'] = "{$prefix}view/{$arg}/";
                }
                else
                {
                    $data['local_view_url'] = "{$prefix}{$arg}/";
                }

                if (   $this->_config->get('link_to_external_url')
                    && !empty($article->url))
                {
                    $data['view_url'] = $article->url;
                }
                else
                {
                    $data['view_url'] = $data['local_view_url'];
                }

                if ($article->topic === $this->_content_topic->id)
                {
                    $data['linked'] = false;
                }
                else
                {
                    $data['linked'] = true;

                    $nap = new midcom_helper_nav();
                    $data['node'] = $nap->get_node($article->topic);
                }

                midcom_show_style('archive-list-item');
            }
        }
        else
        {
            midcom_show_style('archive-list-empty');
        }

        midcom_show_style('archive-list-end');
        return true;
    }
}
?>