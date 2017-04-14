<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/13/17
 * Time: 4:55 PM
 */

/**
 * Class NIForm_ProcessorAbstract
 *
 * Initial processor that does nothing with the results, for now.
 *
 * This class can be used as a basic template to send the form data to an
 * email, database table, or to send to an API.
 */
class NIForm_Processor_Null implements NIForm_ProcessorAbstract {
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
NIForms::register_form_processor('null', new NIForm_Processor_Null());