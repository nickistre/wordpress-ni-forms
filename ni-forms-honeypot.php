<?php

/**
 * Plugin Name: NI Forms - Honeypot
 * Description: Addon for NI Forms system that adds a simple anti-bot honeypot system to forms.
 * Version: 0.0.1
 * Author: Nicholas Istre
 * GitHub Plugin URI: https://github.com/nickistre/wordpress-ni-forms
 *
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/24/17
 * Time: 2:53 PM
 */
class NIFormsHoneypot
{
    const AJAX_ACTION = 'niform_honeypot_token';

    const FIELD_NAME = '_ni-form-honeypot-token';

    const SESSION_VAR = 'ni-forms-honeypot';


    /**
     * @var null|array
     *
     * Holds the current post status.  This can be changed by handlers so that it changes the process_form input.
     */
    private $current_post = null;

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'ajaxGenerateToken'));
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'ajaxGenerateToken'));


        add_action('init', array($this, 'startSession'));
        add_action('wp_logout', array($this, 'endSession'));
        add_action('wp-login', array($this, 'endSession'));

    }

    static public function register()
    {
        $honeypot = new self();

        NIForms::addHandler(NIForms::HANDLER_PREFORM, array($honeypot, 'preformHandler'));
        NIForms::addHandler(NIForms::HANDLER_PREPROCESS, array($honeypot, 'preprocessHandler'));
    }

    /**
     * Will reset the current_post to $_POST
     */
    public function resetPost()
    {
        $this->post_cached = false;
    }

    public function startSession()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function endSession()
    {
        session_destroy();
    }

    public function ajaxGenerateToken()
    {
        $form_id = $this->getPostValue('formId');

        // Generate a new token
        $token = md5(uniqid(mt_rand(), true));

        // Save token in Session in relation to formId given.
        if (!is_array($_SESSION[self::SESSION_VAR])) {
            $_SESSION[self::SESSION_VAR] = array();
        }

        $_SESSION[self::SESSION_VAR][$form_id] = $token;

        $return = array(
            'token' => $token
        );

        echo wp_json_encode($return);
        wp_die();

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

    public function enqueue_scripts()
    {
        // Setup scripts
        wp_register_script('ni-forms-honeypot', plugins_url('js/form-honeypot.js', __FILE__), array('jquery'));
    }

    public function preformHandler(\NIForms\Form $form)
    {
        if (!$form->getAttribute('disable-honeypot', false)) {

            wp_enqueue_script('ni-forms-honeypot');

            $form->addScript("
<script>
    jQuery(document).ready(function() {
        var formId = " . wp_json_encode($form->getAttribute('id')) . ";
        var tokenUrl = " . wp_json_encode($this->actionUrl()) . ";
        var fieldName = " . wp_json_encode(self::FIELD_NAME) . ";
        
        var honeypot = new NIForm.Honeypot(formId, tokenUrl, fieldName);
    });
</script>
        ");
        }

        // Remove attribute so it doesn't end up in final form
        $form->unsetAttribute('disable-honeypot');

        return $form;
    }

    protected function actionUrl()
    {
        $actionUrl = admin_url('admin-ajax.php') . '?' . http_build_query(array('action' => self::AJAX_ACTION));
        return $actionUrl;
    }

    public function preprocessHandler(NIForms\FormSubmit $form_submit, \NIForms\Form $form)
    {
        $form_id = $form->getAttribute('id');
        $form_token = $form_submit->Post()->getValue(self::FIELD_NAME);

        // Check for existing token in session.
        $session_token = $_SESSION[self::SESSION_VAR][$form_id];

        if ($form_token == $session_token) {
            // Remove token from session
            unset($_SESSION[self::SESSION_VAR][$form_id]);
            return true;
        } else {
            return NIForms::PREPROCESS_RETURN_SILENT_SUCCESS;
        }
    }
}

add_action('plugins_loaded', array('NIFormsHoneypot', 'register'));
