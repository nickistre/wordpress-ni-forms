<?php
/**
 * Plugin Name: NI Forms
 * Description: A base forms plugin for building AJAX-style submitting forms.
 * Version: 0.3.0
 * Author: Nicholas Istre
 * GitHub Plugin URI: https://github.com/nickistre/wordpress-ni-forms
 *
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/25/16
 * Time: 11:00 AM
 *
 * Use https://github.com/afragen/github-updater for updating of this plugin from github repo directly.
 */


require_once __DIR__ . '/NIForms/ProcessorResponse/HTML.php';
require_once __DIR__ . '/NIForms/ProcessorResponse/Redirect.php';
require_once __DIR__ . '/NIForms/Form.php';

/**
 * Class NIForm_ProcessorAbstract
 *
 * Interface for form processors used by the main NIForm class to handle
 * form results.
 */
interface NIForm_ProcessorAbstract
{
    /**
     * Processes the values from the form.
     *
     * Return value return whether the form processing was successful or not.
     *
     * @param $form_values array
     * @param $form \NIForms\Form
     * @return boolean
     */
    public function process(array $form_values, \NIForms\Form $form);

    /**
     * What to actually do in the case of success
     *
     * Needed separately from the process method
     *
     * @param array $form_values
     * @param \NIForms\Form $form
     * @return boolean|string|\NIForms\ProcessorResponse\HTML|\NIForms\ProcessorResponse\Redirect
     */
    public function success(array $form_values, \NIForms\Form $form);
}

/**
 * Class NIForm
 *
 * Main class to manage forms
 *
 * @todo Add validation code (default to HTML5 validation checking)
 */
class NIForms
{
    /**
     * Constant for event to run before the form itself is generated.  Can be used to add hidden fields and
     * Javascript handling to the form.
     */
    const HANDLER_PREFORM = 'preform';

    /**
     * Constant for event to run before the process functionality is started.  Can be used to add functionality
     * that must run before the processing system is initiated.
     */
    const HANDLER_PREPROCESS = 'preprocess';

    /**
     * Constant for event to run after the processing functionality is completed.  Can be used for logging
     * functionality after processing was completed.
     */
    const HANDLER_POSTPROCESS = 'postprocess';

    /**
     * @var array
     */
    static private $form_processors = array();
    /**
     * @var array
     *
     * Handlers for where custom code can be injected.
     */
    static private $event_handlers = array(
        self::HANDLER_PREFORM => array(),
        self::HANDLER_PREPROCESS => array(),
        self::HANDLER_POSTPROCESS => array(),
    );

    /**
     * Default success message if none was set in the tag.
     * @var string
     */
    static private $default_success_message = null;

    /**
     * Default failure message if none was set in the tag.
     * @var string
     */
    static private $default_failure_message = 'Form submit failed.';

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
     * NIForm constructor.
     *
     * Sets up shortcode, ajax management, and any other configuration needed
     * by the plugin
     */
    public function __construct()
    {
        add_shortcode('ni-form', array($this, 'shortcode'));
        add_action('wp_ajax_niform_process', array($this, 'process_form'));
        add_action('wp_ajax_nopriv_niform_process', array($this, 'process_form'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Registers a processor instance with a given code.
     *
     * This allows for custom form processors to be created and used by
     * forms.  The code is referenced via the "form-processor" attribute.
     *
     * @param string $code
     * @param NIForm_ProcessorAbstract $processor
     */
    static public function register_form_processor($code, NIForm_ProcessorAbstract $processor)
    {
        self::$form_processors[$code] = $processor;
    }

    static public function addHandler($event, $function)
    {
        if (!array_key_exists($event, self::$event_handlers)) {
            trigger_error(sprintf('Invalid event "%1$s".  Valid event are: %2$s',
                implode(', ', $event, array_keys(self::$event_handlers))), E_USER_ERROR);
        }

        if (!is_callable($function)) {
            trigger_error('Value passed for $function parameter is not callable.', E_USER_ERROR);
        }

        self::$event_handlers[] = $function;
    }

    static protected function runHandlers($event, $param_arr)
    {
        if (!array_key_exists($event, self::$event_handlers)) {
            trigger_error(sprintf('Invalid event "%1$s".  Valid event are: %2$s',
                implode(', ', $event, array_keys(self::$event_handlers))), E_USER_ERROR);
        }

        $handlers = self::$event_handlers[$event];
        assert(is_array($handlers));
        foreach ($handlers as $handler) {
            assert(is_callable($handler));
            $return = call_user_func_array($handler, $param_arr);

            // If any handler returns false, stop running?
            if ($return === false) {
                return false;
            }
        }

        // If we got here, return true.
        return true;
    }

    public function enqueue_scripts()
    {
        // Setup scripts
        wp_register_script('jquery-ajaxform', plugins_url('js/vendor/jquery.form.min.js', __FILE__), array('jquery'));
        wp_register_script('jquery-blockui', plugins_url('js/vendor/jquery.blockUI.js', __FILE__), array('jquery'));
        wp_register_script('ni-forms', plugins_url('js/form.js', __FILE__),
            array('jquery', 'jquery-ui-dialog', 'jquery-ajaxform', 'jquery-blockui'));

        // Setup styles
        wp_register_style('ni-forms', plugins_url('css/form.css', __FILE__), array('wp-jquery-ui-dialog'));
    }

    /**
     * Loads plugin data from the plugins/ folder in the system.
     */
    public function load_plugins()
    {
        // This is probably dangerous, but hey, need to load all plugins from the plugins/ directory...
        // TODO; Setup a adminitration screen to allow for activating/deactivating these plugins.
        foreach (glob(__DIR__ . '/plugins/*.php') as $plugin_file) {
            include_once $plugin_file;
        }
    }

    /**
     * Processes and sets up the form from the shortcode input
     *
     * Any $atts values not used in this method will be passed through to the
     * form as a tag.
     *
     * @param array|null $atts
     * @param string $content
     * @param string $tag
     *
     * @return string
     *
     */
    public function shortcode($atts, $content, $tag)
    {
        // Check through attributes

        $form = new \NIForms\Form($atts, $content, $tag);

        // Require a form processor.  If missing, use "null"
        $form_processor = 'null';
        if ($form->hasAttribute('processor')) {
            $form_processor = $form->getAttribute('processor');
        } else {
            // Check old attribute name
            if ($form->hasAttribute('form-processor')) {
                $form_processor = $form->getAttribute('form-processor');
                trigger_error('"form-processor" attribute deprecated. Use "processor" instead', E_USER_DEPRECATED);
            } else {
                trigger_error(sprintf('"processor" tag missing!  Using "%1$s" processor as default.', $form_processor),
                    E_USER_WARNING);
            }
        }
        // Remove the processor attributes so they don't show up in final form output.
        $form->unsetAttribute('processor');
        $form->unsetAttribute('form-processor');


        // Check that given processor code is registered in class
        if (!self::has_form_processor($form_processor)) {
            trigger_error(sprintf('Unregistered form code "%1$s"; available form processor codes: %2$s',
                $form_processor, implode(', ', self::get_all_processor_codes())), E_USER_ERROR);
        }

        // Get any status messages from the plugin
        // TODO: Store messages in session instead of within form?
        if (!empty($form->getAttribute('success-message'))) {
            $success_message = $form->getAttribute('success-message');
            $form->unsetAttribute('success-message');
        } else {
            $success_message = self::getDefaultSuccessMessage();
        }

        if (!empty($form->getAttribute('error-message'))) {
            $error_message = $form->getAttribute(['error-message']);
        } else {
            $error_message = self::getDefaultFailureMessage();
        }


        // Check for disable-ajax attribute
        $disable_ajax = $form->getAttribute('disable-ajax', false);
        // Remove disable-ajax from attributes so it's not added to form tag later
        $form->unsetAttribute('disable-ajax');

        // Setup additional hidden fields and saved data

        // TODO: The following is probably better stored on the server, but for now, this will work.
        $form->setSavedData('processor', $form_processor);
        if (!empty($success_message)) {
            $form->setSavedData('success-message', $success_message);
        }
        if (!empty($error_message)) {
            $form->setSavedData('error-message', $error_message);
        }

        $form->setHiddenField('_form-hash', $form->getFormHash());


        $output = "";
        $form_output = $form->toString();
        // Add javascript code for ajax and/or honeypot
        if (!$disable_ajax) {
            wp_enqueue_script('ni-forms');
            wp_enqueue_style('ni-forms');

            $form_output .= "
<script>
    jQuery(document).ready(function() {
        var formId = " . wp_json_encode($form->getAttribute('id')) . ";
        var actionUrl = " . wp_json_encode($this->actionUrl()) . ";
        var formData = " . wp_json_encode(array('_submit-style' => 'ajax')) . ";
        
        var form = new NIForm.Form(formId);
        
        form.setupAjaxForm(actionUrl, formData);
    });
</script>";

        }

        // Save current form to disk
        $form->save($this->getCacheDir());

        // Process form in case submitted via standard submit.
        if ($this->check_for_processing($form)) {
            $process_result = $this->process_form();

            if ($process_result['process_result']) {
                // Success path.
                if (!is_null($process_result['redirect_url'])) {
                    $redirect_url = $process_result['redirect_url'];
                    // Need to redirect to the given url
                    if (!headers_sent()) {
                        // Headers not sent yet!  We can use the redirect headers immediately.
                        header('Location: ' . $redirect_url);
                        $output = "<p>Redirecting to <a href='${redirect_url}'>" . htmlentities($redirect_url) . "</a></p>";
                        echo $output;
                        wp_die();
                    } else {
                        // Headers already sent!  We can attempt to redirect via Javascript at this point.
                        $output .= "<p>Redirecting to <a href='${redirect_url}'>" . htmlentities($redirect_url) . "</a></p>";
                        $output .= <<<EOT
<script>
jQuery(document).ready(function() {
    window.location.replace('${redirect_url}');
});
</script>
EOT;
                        return $output;
                    }
                } elseif (!is_null($process_result['replace_html'])) {
                    // Replace form with supplied html

                    $output .= $process_result['replace_html'];
                } elseif (!is_null($process_result['process_message'])) {
                    // Add message before form
                    // TODO: Add some way to template this.
                    $process_message = $process_result['process_message'];

                    $output .= "<p>${process_message}</p>";
                } else {
                    // Nothing to do, just show new form.
                    $output .= $form_output;
                }
            } else {
                // Fail mode.  Show an error message with the form.
                // TODO: Add some way to template this.
                $output .= "<p class='failure'>Error occurred on form submit!</p>";
                $output .= $form_output;
            }
        } else {
            $output .= $form_output;
        }

        return $output;
    }

    static protected function has_form_processor($code)
    {
        if (array_key_exists($code, self::$form_processors)) {
            return true;
        } else {
            return false;
        }
    }

    static protected function get_all_processor_codes()
    {
        return array_keys(self::$form_processors);
    }

    /**
     * @return string
     */
    public static function getDefaultSuccessMessage()
    {
        return self::$default_success_message;
    }

    /**
     * @param string $default_success_message
     */
    public static function setDefaultSuccessMessage($default_success_message)
    {
        self::$default_success_message = $default_success_message;
    }

    /**
     * @return string
     */
    public static function getDefaultFailureMessage()
    {
        return self::$default_failure_message;
    }

    /**
     * @param string $default_failure_message
     */
    public static function setDefaultFailureMessage($default_failure_message)
    {
        self::$default_failure_message = $default_failure_message;
    }

    /**
     * Retrieves the url to post to for the ajax call.
     *
     * @return string
     */
    protected function actionUrl()
    {
        $actionUrl = admin_url('admin-ajax.php') . '?' . http_build_query(array('action' => 'niform_process'));
        return $actionUrl;
    }

    protected function getCacheDir()
    {
        // TODO: Create a config constant to use to configure this, or admin panel to do so.
        $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache/ni-forms';

        // If needed, setup cache directory.
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);

            // Create .htaccess file to prevent web access to cache files
            file_put_contents($cache_dir . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
        }

        return $cache_dir;
    }

    /**
     * Check if there's a post for the current form, for the given $form_id.
     *
     * @param $form \NIForms\Form
     * @return boolean
     */
    protected function check_for_processing(\NIForms\Form $form)
    {
        $form_hash = $form->getFormHash();
        $post_form_hash = $this->getPostValue('_form-hash');
        if ($post_form_hash == $form_hash) {
            return true;
        } else {
            return false;
        }
    }

    public function getPostValue($key)
    {
        if ($this->hasPostKey($key)) {
            $post = $this->getPost();
            return $post[$key];
        } else {
            return null;
        }
    }

    public function hasPostKey($key)
    {
        $post = $this->getPost();
        return is_array($post) && array_key_exists($key, $post);
    }

    /**
     * Retrieves the "current" post array.  Form pre-process handlers may change or set values using other methods.
     * @return array|null
     *
     */
    public function getPost()
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
     * Processes the given form data.
     *
     * This form returns some info back in an array if it attempted to process
     * the form.
     *
     * @return array|null
     *
     * @todo Validate the form based on the tags used in form elements (I.E. "required" or "type='email'")
     */
    public function process_form()
    {
        // TODO: Setup ways to register and create new ways to process form

        if ($this->getPost()) {
            // Get form from _form-hash post value
            $form_hash = $this->getPostValue('_form-hash');
            $form = \NIForms\Form::load($this->getCacheDir() . DIRECTORY_SEPARATOR . $form_hash);

            $form_processor = self::get_form_processor($form->getSavedData('processor'));

            $process_result = $form_processor->process($this->getPost(), $form);

            if ($process_result) {
                $process_result = $form_processor->success($this->getPost(), $form);
            }

            // Initialize variables with defaults
            $process_message = null;
            $replace_html = null;
            $redirect_url = null;

            if (is_string($process_result)) {
                $process_message = $process_result;
            } elseif ($process_result instanceof \NIForms\ProcessorResponse\HTML) {
                $replace_html = $process_result->new_html;
                $process_message = $process_result->popup_message;
            } elseif ($process_result instanceof \NIForms\ProcessorResponse\Redirect) {
                $redirect_url = $process_result->url;
            } elseif ($process_result) {
                $process_message = $form->getSavedData("success-message", null);
            } else {
                $process_message = $form->getSavedData('error-message', null);
            }

            $return = array(
                'process_result' => $process_result,
                'process_message' => $process_message,
                'replace_html' => $replace_html,
                'redirect_url' => $redirect_url,
                'submitted_form_values' => $this->getPost() // This is for debugging
            );

            if ($this->check_if_ajax()) {
                echo wp_json_encode($return);
                wp_die();
            } else {
                return $return;
            }
        }

        return null;
    }

    /**
     * @param $code
     * @return NIForm_ProcessorAbstract
     *
     * @todo Check if code actually exists in the array and handle the error
     */
    static protected function get_form_processor($code)
    {
        return self::$form_processors[$code];
    }

    /**
     * Returns whether the current connection was an ajax request
     */
    protected function check_if_ajax()
    {
        return wp_doing_ajax();
    }

    /**
     * Will reset the current_post to $_POST
     */
    public function resetPost()
    {
        $this->post_cached = false;
    }
}

// Setup sampleForm instance to initialize plugin.
$sampleForm = new NIForms();
$sampleForm->load_plugins();

