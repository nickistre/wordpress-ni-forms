<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 10/1/18
 * Time: 10:56 AM
 */

namespace NIForms;

require_once __DIR__.'/../../NIForms/Form.php';


class FormTest extends \WP_UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->class_instance = new Form(
            array(
                'processor' => 'null',
                'success-message' => 'Thank you for your submission!',
            ),
            'Enter something: <input type="text" name="text" required>
<input type="submit" name="submit" value="Submit">',
            'ni-form'
        );
    }

    public function testAttributes()
    {
        $this->assertEquals($this->class_instance->getAttribute('processor'), 'null');
        $this->assertEquals($this->class_instance->getAttribute('success-message'), 'Thank you for your submission!');
    }
}