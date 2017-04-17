<?php
/**
 * Plugin Name: NI Forms
 * Description: A base forms plugin for building AJAX-style submitting forms.
 * Version: 0.2
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


require_once __DIR__.'/NIForms/ProcessorResponse/HTML.php';
require_once __DIR__.'/NIForms/ProcessorResponse/Redirect.php';


/**
 * Class NIForm
 *
 * Main class to manage forms
 */
class NIForms {
    /**
     * @var array
     */
    static private $form_processors = array();

    /**
     * Registers a processor instance with a given code.
     *
     * This allows for custom form processors to be created and used by
     * forms.  The code is referenced via the "form-processor" attribute.
     *
     * @param string $code
     * @param NIForm_ProcessorAbstract $processor
     */
    static public function register_form_processor($code, NIForm_ProcessorAbstract $processor) {
        self::$form_processors[$code] = $processor;
    }





    /**
     * @param $code
     * @return NIForm_ProcessorAbstract
     *
     * @todo Check if code actually exists in the array and handle the error
     */
    static protected function get_form_processor($code) {
        return self::$form_processors[$code];
    }


    /**
     * Default success message if none was set in the tag.
     * @var string
     */
    static private $default_success_message = null;

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
     * Default failure message if none was set in the tag.
     * @var string
     */
    static private $default_failure_message = 'Form submit failed.';

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

        // Setup scripts
        wp_register_script('jquery-ajaxform', plugins_url('js/vendor/jquery.form.min.js', __FILE__), array('jquery'));
        wp_register_script('jquery-blockui', plugins_url('js/vendor/jquery.blockUI.js', __FILE__), array('jquery'));
        wp_register_script('ni-forms', plugins_url('js/form.js', __FILE__), array('jquery', 'jquery-ui-dialog', 'jquery-ajaxform', 'jquery-blockui'));

        // Setup styles
        wp_register_style('ni-forms', plugins_url('css/form.css', __FILE__), array('wp-jquery-ui-dialog'));
    }

    /**
     * Loads plugin data from the plugins/ folder in the system.
     */
    public function load_plugins() {
        // This is probably dangerous, but hey, need to load all plugins from the plugins/ directory...
        foreach (glob(__DIR__.'/plugins/*.php') as $plugin_file) {
            include_once $plugin_file;
        }
    }

    /**
     * Processes and sets up the form from the shortcode input
     *
     * Any $atts values not used in this method will be passed through to the
     * form as a tag.
     *
     * @param array $atts
     * @param string $content
     * @param string $tag
     *
     * @return string
     *
     */
    public function shortcode(array $atts, $content, $tag)
    {
        // Process form in case submitted via standard submit.
        // TODO: Do something with the process_result, like showing error or success message
        $process_result = $this->process_form();

        // Check through attributes

        // 'id' attribute is required for later code, if not exist, generate one
        // For best results, this ID should be unique to the whole site
        if (empty($atts['id'])) {
            // Can't use random ID in case of page caching
            global $post;
            // TODO: Make sure post exists and is an object
            $atts['id'] = 'nif'.md5(var_export($atts, true).$content.$tag.$post->ID);
        }

        // 'method' should default to POST
        if (empty($atts['method'])) {
            $atts['method'] = 'post';
        }

        // Require a form processor.  If missing, use "null"
        if (empty($atts['form-processor'])) {
            trigger_error('"form-processor" tag missing!  Using "null" processor as default.', E_USER_WARNING);
            $atts['form-processor'] = 'null';
        }

        // TODO: check that given processor code is registered in class

        // Get any status messages from the plugin
        // TODO: Store messages in session instead of within form?
        if (!empty($atts['success-message'])) {
            $success_message = $atts['success-message'];
            unset($atts['success-message']);
        }
        else {
            $success_message = self::getDefaultSuccessMessage();
        }

        if (!empty($atts['error-message'])) {
            $error_message = $atts['error-message'];
            unset($atts['error-message']);
        }
        else {
            $error_message = self::getDefaultFailureMessage();
        }


        // Check for disable-ajax attribute
        $disable_ajax = isset($atts['disable-ajax']) && $atts['disable-ajax'];
        // Remove disable-ajax from attributes so it's not added to form tag later
        if (isset($atts['disable-ajax'])) {
            unset($atts['disable-ajax']);
        }

        // Create attributes for form
        $atts_string = '';
        foreach ($atts as $name => $value) {
            $atts_string .= sprintf(' %1$s="%2$s"', htmlentities2($name), htmlentities2($value));
        }

        // Setup additional hidden fields
        $hidden_fields = array();
        $hidden_fields['_form-id'] = $atts['id'];

        // TODO: The following is probably better stored on the server, but for now, this will work.
        $hidden_fields['_form-processor'] = $atts['form-processor'];
        if (!empty($success_message)) {
            $hidden_fields['_success-message'] = $success_message;
        }
        if (!empty($error_message)) {
            $hidden_fields['_error-message'] = $error_message;
        }
        unset ($atts['form-processor']);


        // Generate html for hidden fields.
        $hidden_fields_content = '';
        foreach ($hidden_fields as $name => $value) {
            $hidden_fields_content .= sprintf('<input type="hidden" name="%1$s" value="%2$s">'.PHP_EOL, htmlentities2($name), htmlentities2($value));
        }


        $output = sprintf('<form%1$s>%2$s%3$s</form>', $atts_string, $content, $hidden_fields_content);

        // Add javascript code for ajax and/or honeypot
        if (!$disable_ajax) {
            wp_enqueue_script('ni-forms');
            wp_enqueue_style('ni-forms');

            $output .= "
<script>
    jQuery(document).ready(function() {
        var formId = ".wp_json_encode($atts['id']).";
        var actionUrl = ".wp_json_encode($this->actionUrl()).";
        var formData = ".wp_json_encode(array('_submit-style' => 'ajax')). ";
        
        var form = new NIForm.Form(formId);
        
        form.setupAjaxForm(actionUrl, formData);
    });
</script>";

        }

        return $output;
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
    public function process_form() {
        // TODO: Setup ways to register and create new ways to process form

        if ($_POST) {
            $form_processor = self::get_form_processor($_POST['_form-processor']);

            $process_result = $form_processor->process($_POST);

            // Initialize variables with defaults
            $process_message = null;
            $replace_html = null;
            $redirect_url = null;

            if (is_string($process_result)) {
                $process_message = $process_result;
            }
            elseif ($process_result instanceof \NIForms\ProcessorResponse\HTML) {
                $replace_html = $process_result->new_html;
                $process_message = $process_result->popup_message;
            }
            elseif ($process_result instanceof \NIForms\ProcessorResponse\Redirect) {
                $redirect_url = $process_result->url;
            }
            elseif ($process_result) {
                $process_message = isset($_POST['_success-message']) ? $_POST["_success-message"] : null;
            }
            else {
                $process_message = isset($_POST['_error-message']) ? $_POST['_error-messag'] : null;
            }

            $return = array(
                'process_result' => $process_result,
                'process_message' => $process_message,
                'replace_html' => $replace_html,
                'redirect_url' => $redirect_url,
                'submitted_form_values' => $_POST // This is for debugging
            );

            if ($_POST['_submit-style'] = 'ajax') {
                echo wp_json_encode($return);
                wp_die();
            }
            else {
                return $return;
            }
        }

        return null;
    }

    /**
     * Retrieves the url to post to for the ajax call.
     *
     * @return string
     */
    protected function actionUrl() {
        $actionUrl = admin_url('admin-ajax.php') . '?' . http_build_query(array('action' => 'niform_process'));
        return $actionUrl;
    }
}

/**
 * Class NIForm_ProcessorAbstract
 *
 * Interface for form processors used by the main NIForm class to handle
 * form results.
 */
interface NIForm_ProcessorAbstract {
    /**
     * Processes the values from the form.
     *
     * Return value return whether the form processing was successful or not.
     *
     * @param $form_values
     * @param $attr
     * @param $content
     * @param $tag
     * @return boolean|string|\NIForms\ProcessorResponse\HTML
     */
    public function process(array $form_values);
}

// Setup sampleForm instance to initialize plugin.
$sampleForm = new NIForms();
$sampleForm->load_plugins();

