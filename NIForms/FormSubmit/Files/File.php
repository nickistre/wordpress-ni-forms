<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/25/17
 * Time: 4:38 PM
 */

namespace NIForms\FormSubmit\Files;


/**
 * Class File
 * @package NIForms\FormSubmit\Files
 *
 * Represents a single record from the $_FILES
 *
 * Note that this assumes the structure of the $_FILES array is as follows:
 * http://php.net/manual/en/features.file-upload.post-method.php
 */
class File
{
    static protected $valid_keys = array(
        'name',
        'type',
        'size',
        'tmp_name',
        'error'
    );

    /**
     * The original name of the file on the client machine.
     *
     * @var string
     */
    public $name;

    /**
     * The mime type of the file, if the browser provided this information. An
     * example would be "image/gif". This mime type is however not checked on the
     * PHP side and therefore don't take its value for granted.
     *
     * @var string
     */
    public $type;

    /**
     * The size, in bytes, of the uploaded file.
     *
     * @var integer
     */
    public $size;

    /**
     * The temporary filename of the file in which the uploaded file was stored on
     * the server.
     *
     * @var string
     */
    public $tmp_name;

    /**
     * The error code associated with this file upload.
     *
     * See: http://php.net/manual/en/features.file-upload.errors.php
     *
     * @var integer
     */
    public $error;

    /**
     * The current location of the file.
     *
     * @var string
     */
    private $current_filepath;

    /**
     * File constructor.
     * @param array|null $data
     * @param null $index
     * @throws \Exception
     */
    public function __construct(array $data = null, $index = null)
    {
        if (is_array($data)) {
            $this->load($data, $index);
        }
    }

    /**
     * @param array $data
     * @param int|null $index
     * @return $this
     * @throws \Exception
     */
    public function load(array $data, $index = null)
    {
        // Check structure of array

        // This will either be -1 for a single file, or the count of all arrays
        // if there's more than one (multi-file upload).
        $file_count = null;

        foreach (self::$valid_keys as $valid_key) {
            // Check if exist first
            if (array_key_exists($valid_key, $data)) {
                // Check for type.
                if (is_null($file_count)) {
                    if (!is_array($data[$valid_key])) {
                        $file_count = -1;
                    } else {
                        $file_count = count($data[$valid_key]);

                        if (!is_int($index) && $index < 0 && $index >= $file_count) {
                            throw new \Exception(sprintf('$index parameter must be provided as an integer from 0 to %1$d',
                                $file_count - 1));
                        }
                    }
                } else {
                    if ($file_count = -1) {
                        if (is_array($data[$valid_key])) {
                            throw new \Exception(sprintf('Was originally detected as a single-file upload, but key %1$s is an array!',
                                $valid_key));
                        }
                    } else {
                        if (count($data[$valid_key]) != $file_count) {
                            throw new \Exception(sprintf('Was expecting %1$d files, but key %2$s only has %3$d',
                                $file_count, $valid_key, count($data[$valid_key])));
                        }
                    }
                }
            } else {
                throw new \Exception(sprintf('$data array parameter is missing required key: %1$s', $valid_key));
            }
        }

        // Completed validation.

        if ($file_count == -1) {
            $this->name = $data['name'];
            $this->type = $data['type'];
            $this->size = $data['size'];
            $this->tmp_name = $data['tmp_name'];
            $this->error = $data['error'];
        } else {
            $this->name = $data['name'][$index];
            $this->type = $data['type'][$index];
            $this->size = $data['size'][$index];
            $this->tmp_name = $data['tmp_name'][$index];
            $this->error = $data['error'][$index];
        }

        $this->current_filepath = is_uploaded_file($this->tmp_name) ? $this->tmp_name : null;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentFilepath()
    {
        return $this->current_filepath;
    }

    /**
     * Attempts to move the file to the destination
     *
     * If successful, the internal current_filepath is updated to the new location, but there is no guarantee this
     * function will work again.
     *
     * @param $destination
     * @return bool
     */
    public function moveTo($destination)
    {
        if ($this->exists()) {
            $result = move_uploaded_file($this->current_filepath, $destination);

            if ($result) {
                $this->current_filepath = $destination;
            }

            return $result;
        }
        return false;
    }

    /**
     * Checks if the file currently exists and can be moved from its current location.
     * @return bool
     */
    public function exists()
    {
        if (is_string($this->current_filepath)) {
            return is_file($this->current_filepath);
        }
        return false;
    }

    /**
     * @return null|string
     */
    public function getMimeType()
    {
        if ($this->exists()) {
            return mime_content_type($this->current_filepath);
        } else {
            return null;
        }
    }
}