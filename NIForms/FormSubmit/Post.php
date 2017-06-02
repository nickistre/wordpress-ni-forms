<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/25/17
 * Time: 3:30 PM
 */

namespace NIForms\FormSubmit;


class Post
{
    /**
     * @var bool
     *
     * Used to indicate if the post has been cached by the current instance
     */
    private $post_cached = false;

    /**
     * @var null|array
     *
     * Holds the current post status.  This can be changed by handlers so that it changes the process_form input.
     */
    private $current_post = null;

    /**
     * @return bool
     */
    public function exists()
    {
        $post = $this->get();
        return is_array($post);
    }

    /**
     * Retrieves the "current" post array.  Form pre-process handlers may change or set values using other methods
     *
     * @return array|null
     */
    public function get()
    {
        if (!$this->post_cached) {
            if (!empty($_POST)) {
                // Run through and "deslash" post because Wordpress adds them automatically.
                $this->current_post = array_map('stripslashes_deep', $_POST);
                $this->post_cached = true;
            } else {
                $this->current_post = null;
            }
        }

        return $this->current_post;
    }

    /**
     * Returns the value with the given key from the post
     *
     * Can supply a default value incase the key doesn't exist
     * in the post.
     *
     * @param $key
     * @param null $default_value
     * @return mixed|null
     */
    public function getValue($key, $default_value = null)
    {
        if ($this->hasKey($key)) {
            $post = $this->get();
            return $post[$key];
        } else {
            return $default_value;
        }
    }

    /**
     * Checks if the given key exists in the post
     *
     * @param $key
     * @return bool
     */
    public function hasKey($key)
    {
        $post = $this->get();

        return is_array($post) && array_key_exists($key, $post);
    }

    /**
     * Set a value to a key.
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setValue($key, $value)
    {
        $post = $this->get();
        if (!is_array($post)) {
            $post = array();
        }

        $post[$key] = $value;

        $this->set($post);

        return $this;
    }

    /**
     * Force the current post to look like $post_values
     *
     * @param array $post_values
     * @return $this
     */
    public function set(array $post_values)
    {
        $this->current_post = $post_values;
        $this->post_cached = true;
        return $this;
    }

    /**
     * @param $key
     * @return $this
     */
    public function unsetValue($key)
    {
        if ($this->hasKey($key)) {
            $post = $this->get();
            unset($post[$key]);
            $this->set($post);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->post_cached = false;
        return $this;
    }
}