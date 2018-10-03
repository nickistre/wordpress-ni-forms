<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 10/1/18
 * Time: 10:56 AM
 */

namespace NIForms;

require_once __DIR__.'/../../NIForms/Form.php';

/**
 * Class FormTest
 * @package NIForms
 *
 * @property Form $class_instance
 */
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
        $this->assertEquals('null', $this->class_instance->getAttribute('processor'));
        $this->assertEquals('Thank you for your submission!', $this->class_instance->getAttribute('success-message'));

        $this->assertTrue($this->class_instance->hasAttribute('processor'));
        $this->assertFalse($this->class_instance->hasAttribute('not-existing'));

        $this->assertNull($this->class_instance->getAttribute('not-existing', null));

        $this->class_instance->setAttribute('test', 'value');
        $this->assertEquals('value', $this->class_instance->getAttribute('test'));
        $this->class_instance->unsetAttribute('test');
        $this->assertFalse($this->class_instance->hasAttribute('test'));
    }

    public function testContent()
    {
        $this->assertEquals('Enter something: <input type="text" name="text" required>
<input type="submit" name="submit" value="Submit">', $this->class_instance->getContent());
    }

    public function testTag()
    {
        $this->assertEquals('ni-form', $this->class_instance->getTag());
    }

    public function testFormHash()
    {
        $this->assertEquals('7f8b2bd7db4dea4b000c0b4508488748', $this->class_instance->getFormHash());
    }

    public function testFormId()
    {
        $this->assertEquals('nif7f8b2bd7db4dea4b000c0b4508488748', $this->class_instance->getAttribute('id'));
    }

    public function testName()
    {
        $this->assertEquals('ni-form: nif7f8b2bd7db4dea4b000c0b4508488748', $this->class_instance->getName());
    }

    public function testToString()
    {
        $formString = $this->class_instance->toString();

        $formExpected = '<form processor="null" success-message="Thank you for your submission!" id="nif7f8b2bd7db4dea4b000c0b4508488748" method="post" enctype="multipart/form-data">
Enter something: <input type="text" name="text" required>
<input type="submit" name="submit" value="Submit">

</form>
';

        $this->assertEquals($formExpected, $formString);
    }
}