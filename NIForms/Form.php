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
     * @var array
     *
     * Data that needs to be kept with the form through serialization.
     */
    private $saved_data = array();
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
     * @var array
     * Array of Javascript code to include as part of the form output.
     */
    private $scripts = array();
    /**
     * Used to cache the hash of the form object.
     *
     * @var string|null
     */
    private $cache_hash = null;

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
            ->initAttributes();
    }

    /**
     * Initialize a few attributes to use instead of the standard form settings.
     *
     * @return $this
     */
    protected function initAttributes()
    {
        if (!$this->hasAttribute('method')) {
            $this->setAttribute('method', 'post');
        }

        // Experimenting with setting this as default to allow for file downloads.
        if (!$this->hasAttribute('enctype')) {
            $this->setAttribute('enctype', 'multipart/form-data');
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

            $id = 'nif' . $this->getFormHash();

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
     * Returns the form "hash" to be used to uniquely id this form.
     *
     * @return string
     */
    public function getFormHash()
    {
        if (is_null($this->cache_hash)) {
            $post_id = $this->post instanceof \WP_Post ? $this->post->ID : "";
            $this->cache_hash = md5(var_export($this->atts, true) . $this->content . $this->tag . $post_id);
        }


        return $this->cache_hash;
    }

    /**
     * Attempts to load a Form class instance from given filepath
     *
     * @param $filepath
     * @return Form
     * @throws \Exception
     *
     * @todo This needs to be changed to be stored in the database instead!
     */
    static public function load($filepath)
    {
        $serialized_form = file_get_contents($filepath);

        if ($serialized_form === false) {
            throw new \Exception(sprintf('Error loading serialized form data from "%1$s".', $filepath));
        }

        $form = unserialize($serialized_form);

        if (!($form instanceof Form)) {
            throw new \Exception(sprintf('Serialized instance created from file at "%1$s" is not an instance of \NIForms\Form',
                $filepath));
        }

        assert($form instanceof Form);

        return $form;
    }

    /**
     * @param $key string
     * @param null $default_value
     * @return mixed
     */
    public function getSavedData($key, $default_value = null)
    {
        if ($this->hasSavedData($key)) {
            return $this->saved_data[$key];
        } else {
            return $default_value;
        }
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setSavedData($key, $value)
    {
        $this->saved_data[$key] = $value;
        return $this;
    }

    /**
     * @param $key string
     * @return bool
     */
    public function hasSavedData($key)
    {
        if (array_key_exists($key, $this->saved_data)) {
            return true;
        } else {
            return false;
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

        $script_output = implode("\n\n", $this->scripts);

        // generate final form output.
        $form_output = sprintf(<<<'EOT'
<form%1$s>
%2$s
%3$s
</form>
%4$s
EOT
            , $form_atts_string, $this->content, $hidden_fields_content, $script_output);

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

    /**
     * @param $script
     * Javascript to add to the form output.
     *
     * Must be surrounded by <script> tags!
     */
    public function addScript($script)
    {
        // TODO: Validate script to be proper javascript?
        $this->scripts[] = $script;
    }

    /**
     * Stores a the serialized string of the current instance into a file.
     *
     * Requires the directory parameter.  This directory must be
     * writeable by the web process.
     *
     * Returns the form hash, which will be the name of the file
     * used to store the serialized data in the given directory.
     *
     * @param $directory
     * @return string
     * @throws \Exception
     *
     * @todo Change to store in database instead!
     */
    public function save($directory)
    {
        $filename = $this->getFormHash();
        $filepath = $directory . DIRECTORY_SEPARATOR . $filename;
        $serialized_form = serialize($this);
        $result = file_put_contents($filepath, $serialized_form);

        if ($result === false) {
            throw new \Exception(sprintf('Error storing to file at "%1$s". Please make sure the directory at "%2$1" exists is writable for web process',
                $filepath, $directory));
        }

        return $filename;
    }
}