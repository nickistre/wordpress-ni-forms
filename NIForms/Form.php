<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/15/17
 * Time: 11:12 AM
 */

namespace NIForms;


/**
 * Class Form
 * @package NIForms
 *
 * Handles a form in the NIForms system.
 */
class Form
{
    /**
     * @var array
     *
     * The attributes passed in the shortcode
     */
    private $atts = array();
    /**
     * @var array
     *
     * Hidden fields to generate on the final form output.
     */
    private $hidden_fields = array();
    /**
     * @var string
     *
     * The content inside the shortcode; normally is the html contents of the form.
     */
    private $content = null;
    /**
     * @var string
     *
     * The tag used as the shortcode.
     */
    private $tag = null;
    /**
     * @var \WP_Post|null
     *
     * The post object to use with the current form.
     */
    private $post = null;

    /**
     * Form constructor.
     * @param $atts
     * @param $content
     * @param $tag
     * @param \WP_Post|null $post
     */
    public function __construct($atts, $content, $tag, \WP_Post $post = null)
    {
        // Make sure the $atts is passed as an array.
        if (!is_array($atts)) {
            $atts = array();
        }
        $this->atts = $atts;
        $this->content = $content;
        $this->tag = $tag;
        $this->post = $post;

        $this->generateFormId()
            ->checkMethod();
    }

    /**
     * @return $this
     */
    protected function checkMethod()
    {
        if (!$this->hasAttribute('method')) {
            $this->setAttribute('method', 'post');
        }

        return $this;
    }

    /**
     * @param $key string
     * @return bool
     */
    public function hasAttribute($key)
    {
        return array_key_exists($key, $this->atts);
    }

    /**
     * @param $key string
     * @param $value string
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $this->atts[$key] = $value;
        return $this;
    }

    /**
     * Generates the form Id if one isn't setup in $atts parameter
     *
     * @return $this
     */
    protected function generateFormId()
    {

        if (empty($this->getAttribute('id'))) {
            $post_id = $this->post instanceof \WP_Post ? $post_id->ID : "";
            $id = 'nif' . md5(var_export($this->atts, true) . $this->content . $this->tag . $post_id);

            $this->setAttribute('id', $id);
        }
        return $this;
    }

    /**
     * @param $key string
     * @param $default_value mixed The value to use if the key does
     * not exist in the attributes
     * @return mixed
     */
    public function getAttribute($key, $default_value = null)
    {
        if ($this->hasAttribute($key)) {
            return $this->atts[$key];
        } else {
            return $default_value;
        }
    }

    /**
     * @param $key
     * @return $this
     */
    public function unsetAttribute($key)
    {
        unset($this->atts[$key]);
        return $this;
    }

    /**
     * @param $name
     * @param null $default_value
     * @return mixed|null
     */
    public function getHiddenField($name, $default_value = null)
    {
        if ($this->hasHiddenField($name)) {
            return $this->hidden_fields[$name];
        } else {
            return $default_value;
        }
    }

    /**
     * @param $name string
     * @return bool
     */
    public function hasHiddenField($name)
    {
        if (array_key_exists($name, $this->hidden_fields)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function setHiddenField($name, $value)
    {
        $this->hidden_fields[$name] = $value;
        return $this;
    }

    /**
     * @param $name
     * @return $this
     */
    public function unsetHiddenField($name)
    {
        if ($this->hasHiddenField($name)) {
            unset ($this->hidden_fields[$name]);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        // Create string for form attributes
        $form_atts_string = '';
        foreach ($this->getAttributes() as $key => $value) {
            $form_atts_string .= sprintf(' %1$s="%2$s"', htmlentities2($key), htmlentities2($value));
        }

        // generate html for hidden fields.
        $hidden_fields_content = '';
        foreach ($this->getHiddenFields() as $name => $value) {
            $hidden_fields_content .= sprintf('<input type="hidden" name="%1$s" value="%2$s">' . PHP_EOL,
                htmlentities2($name), htmlentities2($value));
        }

        // generate final form output.
        $form_output = sprintf('<form%1$s>%2$s%3$s</form>', $form_atts_string, $this->content, $hidden_fields_content);

        return $form_output;
    }

    /**
     * Returns all attributes in an array.
     * @return array
     */
    public function getAttributes()
    {
        return $this->atts;
    }

    /**
     * @return array
     */
    public function getHiddenFields()
    {
        return $this->hidden_fields;
    }
}