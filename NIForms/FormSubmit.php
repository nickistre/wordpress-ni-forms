<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/25/17
 * Time: 3:12 PM
 */

namespace NIForms;

require_once __DIR__ . '/FormSubmit/Post.php';
require_once __DIR__ . '/FormSubmit/Files.php';

use NIForms\FormSubmit\Files;
use NIForms\FormSubmit\Post;

/**
 * Class FormSubmit
 * @package NIForms
 *
 * Manages the POST, FILES, and any other data that may be submitted from the form
 */
class FormSubmit
{
    /**
     * @var Post
     */
    private $post;
    private $files;

    /**
     * FormSubmit constructor.
     */
    public function __construct()
    {
        $this->post = new Post();
        $this->files = new Files();
    }

    /**
     * @return Post
     */
    public function Post()
    {
        return $this->post;
    }

    public function Files()
    {
        return $this->files;
    }
}