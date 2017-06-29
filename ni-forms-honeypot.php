<?php

/**
 * Plugin Name: NI Forms - Honeypot
 * Description: Addon for NI Forms system that adds a simple anti-bot honeypot system to forms.
 * Version: 0.2.1
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

    const ID_FIELD_NAME = '_ni-form-honeypot-id';

    const OPTIONS_DB_VERSION = 'ni_forms_db_version';

    const DB_VERSION = '1.1';

    const DB_TABLE_NAME = 'ni_form_honeypot_data';

    const SCHEDULE_HOOK_NAME = 'niform_honeypot_cleartable';
    /**
     * Used to store the current plugin version (and not need to analyze the page constantly
     *
     * @var string
     */
    static private $plugin_version = null;
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
     * Crons and setupsneed to be handled outside of the registration code.
     */
    static public function setupCron()
    {
        add_action('admin_init', array('NIFormsHoneypot', 'dbInstall'));

        add_action(self::SCHEDULE_HOOK_NAME, array('NIFormsHoneypot', 'clearOldHoneypotIds'));
        register_activation_hook(__FILE__, array('NIFormsHoneypot', 'activateCron'));
        register_deactivation_hook(__FILE__, array('NIFormsHoneypot', 'deactivateCron'));
    }

    static public function deactivateCron()
    {
        wp_clear_scheduled_hook(self::SCHEDULE_HOOK_NAME);
    }

    static public function clearOldHoneypotIds()
    {
        global $wpdb;

        self::dbInstall();

        // Timestamp to check for
        $maxAge = self::getHoneyPotDataMaxAge();

        $table_name = self::getTableName(self::DB_TABLE_NAME);

        $sql = $wpdb->prepare("DELETE FROM `${table_name}` WHERE TIMESTAMPDIFF(SECOND, ts, CURRENT_TIMESTAMP) >= %d",
            $maxAge);

        $result = $wpdb->query($sql);
    }

    static public function dbInstall()
    {
        $installed_ver = get_option(self::OPTIONS_DB_VERSION);

        if (self::DB_VERSION != $installed_ver) {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            $table_name = self::getTableName(self::DB_TABLE_NAME);
            $sql = <<<EOT
CREATE TABLE ${table_name} (
  id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  form_id varchar(255),
  honeypot_id varchar(255),
  honeypot_token varchar(255)
) ${charset_collate}
EOT;

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $dbDeltaResult = dbDelta($sql);

            update_option(self::OPTIONS_DB_VERSION, self::DB_VERSION);
        }

        // Make sure cron is setup.
        self::activateCron();
    }

    static protected function getTableName($partialName)
    {
        global $wpdb;
        return $wpdb->prefix . $partialName;
    }

    static public function activateCron()
    {
        if (!wp_next_scheduled(self::SCHEDULE_HOOK_NAME)) {
            wp_schedule_event(time(), 'hourly', self::SCHEDULE_HOOK_NAME);
        }
    }

    /**
     * This returns the max age the honeypot data can be.
     *
     * @return int
     *
     * @todo Currently set to 1 day, should be configurable through admin panel.
     */
    static public function getHoneyPotDataMaxAge()
    {
        // Return a max age of 1 day for now.
        return 24 * 60 * 60;
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
        $this->dbInstall();

        if (!$this->hasPostKey('formId')) {
            trigger_error('"formId" parameter is required', E_USER_ERROR);
        }

        if (!$this->hasPostKey('honeypotId')) {
            trigger_error('"honeypotId" parameter is required', E_USER_ERROR);
        }

        $form_id = $this->getPostValue('formId');
        $honeypot_id = $this->getPostValue('honeypotId');

        // Generate a new token
        $token = md5(uniqid(mt_rand(), true));


        // Store honeypot id and token into database table.
        global $wpdb;

        $table_name = $this->getTableName(self::DB_TABLE_NAME);
        $insert_array = array(
            'form_id' => $form_id,
            'honeypot_id' => $honeypot_id,
            'honeypot_token' => $token
        );

        $wpdb->insert($table_name, $insert_array);

        $return = array(
            'token' => $token
        );

        echo wp_json_encode($return);
        wp_die();
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

    public function getPostValue($key)
    {
        if ($this->hasPostKey($key)) {
            $post = $this->getPost();
            return $post[$key];
        } else {
            return null;
        }
    }

    public function enqueue_scripts()
    {
        // Setup scripts
        wp_register_script('ni-forms-honeypot', plugins_url('js/form-honeypot.js', __FILE__), array('jquery'),
            self::getVersion());
    }

    /**
     * Gets the version of this plugin
     *
     * @return mixed
     */
    static public function getVersion()
    {
        if (empty(self::$plugin_version)) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
            $plugin_data = get_plugin_data(__FILE__);
            self::$plugin_version = $plugin_data['Version'];
        }
        return self::$plugin_version;
    }

    public function preformHandler(\NIForms\Form $form, \NIForms\Logger &$logger)
    {
        if (!$form->getAttribute('disable-honeypot', false)) {

            wp_enqueue_script('ni-forms-honeypot');

            $form->addScript("
<script>
    jQuery(document).ready(function() {
        var formId = " . wp_json_encode($form->getAttribute('id')) . ";
        var tokenUrl = " . wp_json_encode($this->actionUrl()) . ";
        var fieldName = " . wp_json_encode(self::FIELD_NAME) . ";
        var honeypotIdFieldName = " . wp_json_encode(self::ID_FIELD_NAME) . ";
        
        var honeypot = new NIForm.Honeypot(formId, tokenUrl, fieldName, honeypotIdFieldName);
    });
</script>
        ");
        } else {
            // Need to inform the system that there is no honeypot token to check for.
            $form->setSavedData('disable-honeypot', true);
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

    public function preprocessHandler(NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger)
    {
        global $wpdb;
        $table_name = $this->getTableName(self::DB_TABLE_NAME);

        $disable_honeypot = $form->getSavedData('disable-honeypot', false);
        if (!$disable_honeypot) {
            $form_id = $form->getAttribute('id');
            $form_token = $form_submit->Post()->getValue(self::FIELD_NAME);
            $honeypot_id = $form_submit->Post()->getValue(self::ID_FIELD_NAME, null);


            // Look for honeypot id in table
            $sql = $wpdb->prepare("SELECT id, honeypot_token FROM `${table_name}` WHERE form_id = %s AND honeypot_id = %s",
                $form_id, $honeypot_id);
            $honeypot_row = $wpdb->get_row($sql, OBJECT);
            if (empty($honeypot_row)) {
                $session_token = null;
            } else {
                $honeypot_row_id = $honeypot_row->id;
                $session_token = $honeypot_row->honeypot_token;
            }

            if (is_null($session_token)) {
                // Something happened where this token was never created but the honeypot system wasn't disabled for the form.
                $logger->log(\NIForms\Psr\Log\LogLevel::ERROR,
                    'Failed honeypot check; token was never generated.  Initialized "silent failure" process');
                return NIForms::PREPROCESS_RETURN_SILENT_FAILURE;
            } else if ($form_token == $session_token) {
                $wpdb->delete($table_name, array('id' => $honeypot_row_id));
                return true;
            } else {
                $logger->log(\NIForms\Psr\Log\LogLevel::ERROR,
                    'Failed honeypot check.  Initialized "silent failure" process');
                return NIForms::PREPROCESS_RETURN_SILENT_FAILURE;
            }
        } else {
            // Honeypot system is disabled on this form; just return true.
            return true;
        }
    }
}

NIFormsHoneypot::setupCron();
add_action('plugins_loaded', array('NIFormsHoneypot', 'register'));
