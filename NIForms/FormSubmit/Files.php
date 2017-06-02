<?php
/**
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 5/25/17
 * Time: 4:14 PM
 */

namespace NIForms\FormSubmit;

require_once __DIR__ . '/Files/File.php';


use NIForms\FormSubmit\Files\File;

class Files
{
    /**
     * @var bool
     *
     * Used to indicate if the files data has been cached by the current instance
     */
    private $files_cached = false;

    /**
     * @var null|array
     *
     * Holds the current files status.  This can be changed by handlers so that it changes the process_form input.
     */
    private $current_files = null;

    /**
     * @return bool
     */
    public function exists()
    {
        $files = $this->get();
        return is_array($files);
    }

    /**
     * Returns the entire "current" files array.
     *
     * @return array|null
     */
    public function get()
    {
        if (!$this->files_cached) {
            if (!empty($_FILES)) {
                $this->current_files = $_FILES;
                $this->files_cached = true;
            } else {
                $this->current_files = null;
            }
        }

        return $this->current_files;
    }

    /**
     * Sets the entire "current" files array.
     *
     * @param array $files_values
     * @return $this
     *
     * @todo This doesn't validate the structure currently
     */
    public function set(array $files_values)
    {
        $this->current_files = $files_values;
        $this->files_cached = true;
        return $this;
    }

    /**
     * Returns the number of files for a key.  Useful for multiple-file upload fields
     *
     * @param string $key
     * @return int
     */
    public function getCount($key)
    {
        if ($this->hasKey($key)) {
            $files = $this->get();
            $file = $files[$key];

            // Will test with just the "name" element
            if (!is_array($file['name'])) {
                return 1;
            } else {
                // It's an array, return the count.
                return count($file['name']);
            }
        } else {
            return 0;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasKey($key)
    {
        $files = $this->get();

        return is_array($files) && array_key_exists($key, $files);
    }

    /**
     * Returns a File object corresponding to the key given.
     *
     * @param string $key
     * @param int|null $index
     * @return File|null
     * @throws \Exception
     */
    public function getFile($key, $index = null)
    {
        if ($this->hasKey($key)) {
            $files = $this->get();
            $file = new File($files[$key], $index);

            return $file;
        }

        return null;
    }

    // TODO: Implement "set" functions for handlers to manipulate the $files data before going through the processor.

    /**
     * @return $this
     */
    public function reset()
    {
        $this->files_cached = false;
        return $this;
    }
}