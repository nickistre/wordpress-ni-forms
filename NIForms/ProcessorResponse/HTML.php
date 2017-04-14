<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/13/17
 * Time: 4:31 PM
 */

namespace NIForms\ProcessorResponse;

/**
 * Class HTMLReplace
 * @package NIForms
 *
 * Return class type to indicate that form html should be replaced with new HTML data.
 *
 * Optional popup message available.
 */
class HTML
{
    /**
     * Required field with the HTML to replace the current form with.
     *
     * This could replace the existing form with a message, a download link, etc.
     *
     * @var string
     */
    public $new_html;

    /**
     * Optional parameter to tell the form to popup with the given message (as HTML) as the contents.
     *
     * @var null|string
     */
    public $popup_message = null;
}