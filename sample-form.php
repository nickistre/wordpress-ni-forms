<?php
/**
 * Plugin Name: Sample Form
 * Description: A sample plugin for setting up a form that submits it values
 * via an AJAX call.
 * Version: 0.1
 * Author: Nicholas Istre
 *
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/25/16
 * Time: 11:00 AM
 */

/**
 * Class SampleForm
 *
 * Main class to manage forms
 */
class SampleForm {
    /**
     * @var array
     */
    static private $form_processors = array();

    /**
     *
     *
     * @param $code
     * @param $className
     */
    static public function register_form_processor($code, SampleForm_ProcessorInterface $processor) {
        self::$form_processors[$code] = $processor;
    }

    /**
     * @param $code
     * @return SampleForm_ProcessorInterface
     *
     * @todo Check if code actuall exists in the array and handle the error
     */
    static protected function get_form_processor($code) {
        return self::$form_processors[$code];
    }

    /**
     * SampleForm constructor.
     *
     * Sets up shortcode and any other configuration needed by the plugin
     */
    public function __construct()
    {
        add_shortcode('sample-form', array($this, 'shortcode'));
        add_action('wp_ajax_sampleform_process', array($this, 'process_form'));
        add_action('wp_ajax_nopriv_sampleform_process', array($this, 'process_form'));

        // Setup scripts
        wp_register_script('jquery-ajaxform', plugins_url('js/vendor/jquery.form.min.js', __FILE__), array('jquery'));
        wp_register_script('jquery-blockui', plugins_url('js/vendor/jquery.blockUI.js', __FILE__), array('jquery'));
        wp_register_script('sample-form', plugins_url('js/form.js', __FILE__), array('jquery', 'jquery-ui-dialog', 'jquery-ajaxform', 'jquery-blockui'));

        // Setup styles
        wp_register_style('sample-form', plugins_url('css/form.css', __FILE__), array('wp-jquery-ui-dialog'));
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
     * @todo This is a stub that simply returns the form (with provided atts)
     *   back
     */
    public function shortcode($atts, $content, $tag)
    {
        // Process form in case submitted via standard submit.
        $this->process_form();

        // Check through attributes

        // 'id' attribute is required for later code, if not exist, generate one
        // For best results, this ID should be unique to the whole site
        if (empty($atts['id'])) {
            // Can't use random ID in case of page caching
            global $post;
            // TODO: Make sure post exists and is an object
            $atts['id'] = 'sf-'.md5(var_export($atts, true).$content.$tag.$post->ID);
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
        if (!empty($atts['success-message'])) {
            $success_message = $atts['success-message'];
            unset($atts['success-message']);
        }
        else {
            $success_message = null;
        }

        if (!empty($atts['error-message'])) {
            $error_message = $atts['error-message'];
            unset($atts['error-message']);
        }
        else {
            $error_message = null;
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
            wp_enqueue_script('sample-form');
            wp_enqueue_style('sample-form');

            $output .= "
<script>
    jQuery(document).ready(function() {
        var formId = ".wp_json_encode($atts['id']).";
        var actionUrl = ".wp_json_encode($this->actionUrl()).";
        var formData = ".wp_json_encode(array('_submit-style' => 'ajax')).";
        
        var form = new SampleForm.Form(formId);
        
        form.setupAjaxForm(actionUrl, formData);
    });
</script>";

        }

        return $output;
    }

    /**
     * Processes the given form data.
     */
    public function process_form() {
        // TODO: Setup ways to register and create new ways to process form

        if ($_POST) {
            $form_processor = self::get_form_processor($_POST['_form-processor']);

            $process_result = $form_processor->process($_POST);

            $process_message = $process_result ? $_POST['_success-message'] : $_POST['_error-message'];

            if ($_POST['_submit-style'] = 'ajax') {
                echo wp_json_encode(array(
                    'process_result' => $process_result,
                    'process_message' => $process_message,
                    'submitted_form_values' => $_POST // This is for debugging
                ));
                wp_die();
            }
            else {
                // TODO: Handle when not 'ajax' submit
            }
        }
    }

    protected function actionUrl() {
        $actionUrl = admin_url('admin-ajax.php') . '?' . http_build_query(array('action' => 'sampleform_process'));
        return $actionUrl;
    }
}

/**
 * Class SampleForm_ProcessorAbstract
 *
 * Interface for form processors used by the main SampleForm class to handle
 * form results.
 */
interface SampleForm_ProcessorInterface {
    /**
     * Processes the values from the form.
     *
     * Return value return whether the form processing was successful or not.
     *
     * @param $form_values
     * @param $attr
     * @param $content
     * @param $tag
     * @return boolean
     */
    public function process(array $form_values);
}

/**
 * Class SampleForm_Processor_Null
 *
 * Initial processor that does nothing with the results, for now.
 */
class SampleForm_Processor_Null implements SampleForm_ProcessorInterface {
    /**
     * This does nothing and simply returns true.
     *
     * @param array $form_values
     * @return bool
     */
    public function process(array $form_values)
    {
        // Just return true!
        return true;
    }
}

// Register null form processor
SampleForm::register_form_processor('null', new SampleForm_Processor_Null());

// Setup sampleForm instance
$sampleForm = new SampleForm();