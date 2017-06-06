<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/13/17
 * Time: 4:44 PM
 */

/**
 * Class NIFormTestHTML
 *
 * Processor to test out the HTML replacement processor response.
 *
 * Can be used as a base for a custom form processor.
 */
class NIFormsTestHTML implements NIForm_ProcessorAbstract {
    public function process(\NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger)
    {
        return true;
    }

    public function success(\NIForms\FormSubmit $form_submit, \NIForms\Form $form, \NIForms\Logger &$logger)
    {
        $html = new \NIForms\ProcessorResponse\HTML();

        $html->new_html = "<h1>It worked!</h1>";

        return $html;
    }
}

NIForms::register_form_processor('test-html', new NIFormsTestHTML());

