<?php
/**
 * Plugin Name: NI Forms
 * Description: A base forms plugin for building AJAX-style submitting forms.
 * Version: 0.4.1
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
require_once __DIR__ . '/NIForms/FormSubmit.php';
require_once __DIR__ . '/NIForms/Logger.php';

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
     * @param \NIForms\FormSubmit $form_submit
     * @param \NIForms\Form $form
     * @param \NIForms\Logger &$logger
     * @return bool
     */
    public function process(\NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger);

    /**
     * What to actually do in the case of success
     *
     * Needed separately from the process method
     *
     * @param \NIForms\FormSubmit $form_submit
     * @param \NIForms\Form $form
     * @param \NIForms\Logger &$logger
     * @return boolean|string|\NIForms\ProcessorResponse\HTML|\NIForms\ProcessorResponse\Redirect
     */
    public function success(\NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger);
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
     *
     * Function structure:
     * function(\NIForms\Form $form, \NIForms\Logger &$logger) -> \NIForms\Form
     *
     * Function must return an instance of \NIForms\Form; plugin with warning out if this doesn't happen.
     */
    const HANDLER_PREFORM = 'preform';

    /**
     * Constant for event to run before the process functionality is started.  Can be used to add functionality
     * that must run before the processing system is initiated.
     *
     * Function structure:
     * function(array $form_values, \NIForms\Form $form, \NIForms\Logger &$logger) -> bool/int
     *
     * Return true to continue pre process.  Return false to immediately stop preprocess.
     * For integers, look at the "PREPROCESS_RETURN_*" constants for the possible values.
     */
    const HANDLER_PREPROCESS = 'preprocess';

    /**
     * If this is returned by the Preprocess, halt further execution and pretend the process succeeded.
     */
    const PREPROCESS_RETURN_SILENT_FAILURE = 1;

    /**
     * Constant for event to run after the processing functionality is completed.  Can be used for logging
     * functionality after processing was completed.
     *
     * Function structure:
     * function(array $form_values, \NIForms\Form $form, mixed $preprocess_result, mixed $process_result, \NIForms\Logger &$logger) -> void
     */
    const HANDLER_POSTPROCESS = 'postprocess';

    /**
     * The action endpoint to use for AJAX form submits.
     */
    const AJAX_ACTION = 'niform_process';

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
     * NIForm constructor.
     *
     * Sets up shortcode, ajax management, and any other configuration needed
     * by the plugin
     */
    public function __construct()
    {
        add_shortcode('ni-form', array($this, 'shortcode'));
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'process_form'));
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'process_form'));

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

    /**
     * @param string $event
     * @param callable $function
     * @param string|null $name
     */
    static public function addHandler($event, $function, $name = null)
    {
        if (!array_key_exists($event, self::$event_handlers)) {
            trigger_error(sprintf('Invalid event "%1$s".  Valid event are: %2$s',
                implode(', ', array_keys(self::$event_handlers))), E_USER_ERROR);
        }

        if (is_null($name)) {
            // Generate a name from the function, if possible.
            if (is_string($function)) {
                $name = $function;
            } else {
                if (is_array($function) && is_object($function[0]) && is_string($function[1])) {
                    $class_name = get_class($function[0]);
                    $name = sprintf('%1$s::%2$s', $class_name, $function[1]);
                } else {
                    // Can't create a name from the function here
                    trigger_error('Cannot generate name from $function parameter.  $name parameter is required.',
                        E_USER_ERROR);
                }
            }
        }

        if (!is_string($name)) {
            trigger_error('Value passed for $name parameter must be a string.', E_USER_ERROR);
        }

        if (!is_callable($function)) {
            trigger_error('Value passed for $function parameter is not callable.', E_USER_ERROR);
        }

        self::$event_handlers[$event][$name] = $function;
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
        $shortcode_logger = new NIForms\Logger();

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
        if (!empty($form->getAttribute('success-message'))) {
            $success_message = $form->getAttribute('success-message');
            $form->unsetAttribute('success-message');
        } else {
            $success_message = self::getDefaultSuccessMessage();
        }

        if (!empty($form->getAttribute('error-message'))) {
            $error_message = $form->getAttribute('error-message');
        } else {
            $error_message = self::getDefaultFailureMessage();
        }


        // Check for disable-ajax attribute
        $disable_ajax = $form->getAttribute('disable-ajax', false);
        // Remove disable-ajax from attributes so it's not added to form tag later
        $form->unsetAttribute('disable-ajax');

        // Setup additional hidden fields and saved data
        $form->setSavedData('processor', $form_processor);
        if (!empty($success_message)) {
            $form->setSavedData('success-message', $success_message);
        }
        if (!empty($error_message)) {
            $form->setSavedData('error-message', $error_message);
        }

        $form->setHiddenField('_form-hash', $form->getFormHash());


        $output = "";

        $form = self::runPreformHandlers($form, $shortcode_logger);
        // Add javascript code for ajax and/or honeypot
        if (!$disable_ajax) {
            wp_enqueue_script('ni-forms');
            wp_enqueue_style('ni-forms');

            $encoded_form_id = wp_json_encode($form->getAttribute('id'));
            $encoded_action_url = wp_json_encode($this->actionUrl());
            $encoded_form_data = wp_json_encode(array('_submit-style' => 'ajax'));

            $form->addScript(<<<EOT

<script>
    jQuery(document).ready(function() {
        var formId = ${encoded_form_id};
        var actionUrl = ${encoded_action_url};
        var formData = ${encoded_form_data};
        
        var form = new NIForm.Form(formId);
        
        form.setupAjaxForm(actionUrl, formData);
    });
</script>
EOT
            );

        }

        $form_output = $form->toString();

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
     * @param \NIForms\Form $form
     * @return \NIForms\Form
     */
    static protected function runPreformHandlers(\NIForms\Form $form, \NIForms\Logger &$logger)
    {
        $logger->pushStage(self::HANDLER_PREFORM);

        $handlers = self::$event_handlers[self::HANDLER_PREFORM];
        assert(is_array($handlers));
        foreach ($handlers as $name => $handler) {
            assert(is_string($name));
            assert(is_callable($handler));

            $logger->pushHandler($name);
            $return = call_user_func($handler, $form, $logger);

            if ($return instanceof $form) {
                $form = $return;
            } else {
                trigger_error(sprintf('Preform Handler is not returning an instance of \NIForms\Form: %1$s',
                    var_export($handler, true)), E_USER_WARNING);
            }

            $logger->popHandler();
        }

        $logger->popStage();

        assert($form instanceof \NIForms\Form);
        return $form;
    }

    /**
     * Retrieves the url to post to for the ajax call.
     *
     * @return string
     */
    protected function actionUrl()
    {
        $actionUrl = admin_url('admin-ajax.php') . '?' . http_build_query(array('action' => self::AJAX_ACTION));
        return $actionUrl;
    }

    protected function getCacheDir()
    {
        // TODO: Create a config constant to use to configure this, or admin panel to do so.
        $cache_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache/ni-forms';

        // If needed, setup cache directory.
        if (!is_dir($cache_dir)) {
            $mkdir_result = wp_mkdir_p($cache_dir);

            // Create .htaccess file to prevent web access to cache files
            $file_put_result = file_put_contents($cache_dir . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
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
        $formSubmit = new NIForms\FormSubmit();

        $post_form_hash = $formSubmit->Post()->getValue('_form-hash');
        if ($post_form_hash == $form_hash) {
            return true;
        } else {
            return false;
        }
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

        $process_logger = new \NIForms\Logger();

        $form_submit = new \NIForms\FormSubmit();

        if ($form_submit->Post()->exists()) {
            // Get form from _form-hash post value
            $form_hash = $form_submit->Post()->getValue('_form-hash');
            $form = \NIForms\Form::load($this->getCacheDir() . DIRECTORY_SEPARATOR . $form_hash);

            $preprocess_result = self::runPreprocessHandlers($form_submit, $form, $process_logger);

            $form_processor = self::get_form_processor($form->getSavedData('processor'));

            $process_result = null;
            if ($preprocess_result === true) {
                $process_result = $form_processor->process($form_submit, $form, $process_logger);

                if ($process_result) {
                    $process_result = $form_processor->success($form_submit, $form, $process_logger);
                }
            } else {
                if ($preprocess_result === false) {
                    $process_result = false;
                } else {
                    switch ($preprocess_result) {
                        case self::PREPROCESS_RETURN_SILENT_FAILURE:
                            // We're going to skip the actual form processing and pretend it succeeded.
                            $process_result = $form_processor->success($form_submit, $form, $process_logger);
                            break;

                        default:
                            // Something went wrong.  This should be caught in the run handler!
                            throw new trigger_error('Preprocessor handler error!', E_USER_ERROR);
                    }
                }
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
            );

            self::runPostprocessHandlers($form_submit, $form, $preprocess_result, $process_result, $process_logger);

            if (wp_doing_ajax()) {
                echo wp_json_encode($return);
                wp_die();
            } else {
                return $return;
            }
        }

        return null;
    }

    /**
     * @param \NIForms\FormSubmit $form_submit
     * @param \NIForms\Form $form
     * @param \NIForms\Logger &$logger
     * @return bool|mixed
     */
    static protected function runPreprocessHandlers(
        \NIForms\FormSubmit $form_submit,
        \NIForms\Form $form,
        \NIForms\Logger &$logger
    )
    {
        $logger->pushStage(self::HANDLER_PREPROCESS);

        /**
         * Indicates whether to run processor after preProcess section.
         */
        $run_process = true;

        $handlers = self::$event_handlers[self::HANDLER_PREPROCESS];
        assert(is_array($handlers));
        foreach ($handlers as $name => $handler) {
            assert(is_string($name));
            assert(is_callable($handler));

            $logger->pushHandler($name);
            $return = call_user_func($handler, $form_submit, $form, $logger);

            if ($return !== true) {
                if ($return === false) {
                    // Silent fail should override hard fail.
                    if ($run_process === true) {
                        $run_process = false;
                    }
                } else {
                    switch ($return) {
                        case self::PREPROCESS_RETURN_SILENT_FAILURE:
                            // We want to set that we don't run the processor, but continue with other preprocessors
                            $run_process = $return;
                            break;

                        default:
                            // Something went wrong.  Throw an exception with hopefully enough info to debug issue
                            throw new Exception(sprintf('Preprocess return errror!  Handler "%1$s" returned value: %2$s',
                                $name, $return));
                    }
                }
            }

            $logger->popHandler();
        }

        if ($run_process !== true) {
            $logger->log(\NIForms\Psr\Log\LogLevel::INFO,
                sprintf('Some pre process setup returned as failed, not running processor on form.  Returning: %1$s',
                    $return));
        }

        $logger->popStage();

        return $run_process;
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
     * @param \NIForms\FormSubmit $form_submit
     * @param \NIForms\Form $form
     * @param mixed $preprocess_result
     * @param mixed $process_message
     * @param \NIForms\Logger &$logger
     */
    static protected function runPostprocessHandlers(
        \NIForms\FormSubmit $form_submit,
        \NIForms\Form $form,
        $preprocess_result,
        $process_message,
        \NIForms\Logger &$logger
    ) {
        $logger->pushStage(self::HANDLER_POSTPROCESS);

        $handlers = self::$event_handlers[self::HANDLER_POSTPROCESS];
        assert(is_array($handlers));
        foreach ($handlers as $name => $handler) {
            assert(is_string($name));
            assert(is_callable($handler));

            $logger->pushHandler($name);
            $return = call_user_func($handler, $form_submit, $form, $preprocess_result, $process_message, $logger);
            // We're not doing anything with the return value, actually...

            $logger->popHandler();
        }

        $logger->popStage();
    }
}

// Setup sampleForm instance to initialize plugin.
$sampleForm = new NIForms();
$sampleForm->load_plugins();

