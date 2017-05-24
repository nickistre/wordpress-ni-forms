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
    public function process(array $form_values, \NIForms\Form $form)
    {
        return true;
    }

    public function success(array $form_values, \NIForms\Form $form)
    {
        $html = new \NIForms\ProcessorResponse\HTML();

        $html->new_html = "<h1>It worked!</h1>";

        return $html;
    }
}

NIForms::register_form_processor('test-html', new NIFormsTestHTML());

