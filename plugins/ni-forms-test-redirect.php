<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/13/17
 * Time: 4:44 PM
 */

/**
 * Class NIFormTestRedirect
 *
 * Processor to test out the Redirect processor response.
 *
 * Can be used as a base for a custom form processor.
 */
class NIFormsTestRedirect implements NIForm_ProcessorAbstract {
    public function process(\NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger)
    {
        return true;
    }

    public function success(NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger)
    {
        $redirect = new \NIForms\ProcessorResponse\Redirect();

        $redirect->url = get_home_url();

        return $redirect;
    }
}

NIForms::register_form_processor('test-redirect', new NIFormsTestRedirect());

