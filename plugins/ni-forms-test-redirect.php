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
    public function process(array $form_values, \NIForms\Form $form)
    {
        return true;
    }

    public function success(array $form_values, \NIForms\Form $form)
    {
        $redirect = new \NIForms\ProcessorResponse\Redirect();

        $redirect->url = get_home_url();

        return $redirect;
    }
}

NIForms::register_form_processor('test-redirect', new NIFormsTestRedirect());

