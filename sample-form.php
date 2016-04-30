<?php
/**
 * Plugin Name: Sample Form
 * Description: A sample plugin showing a customizeable form, complete with
 *   adding AJAX form submit and a javascript-based anti-bot honeypot field.
 * Version: 0.1
 * Author: Nicholas Istre
 *
 * Created by IntelliJ IDEA.
 * User: nick
 * Date: 4/25/16
 * Time: 11:00 AM
 */

class SampleForm {
    /**
     * SampleForm constructor.
     *
     * Sets up shortcode and any other configuration needed by the plugin
     */
    public function __construct()
    {
        add_shortcode('sample-form', array($this, 'shortcode'));
    }

    /**
     * Processes and sets up the form from the shortcode input
     *
     * @param array $atts
     * @param string $content
     * @param string $tag
     *
     * @return string
     *
     * @todo This is a stub that simply returns the form (with provided atts)
     *   back
     */
    public function shortcode($atts, $content, $tag)
    {
        // Create attributes for form
        $atts_string = '';
        foreach ($atts as $name => $value) {
            $atts_string = sprintf(' %1$s="%2$s"', htmlentities2($name), htmlentities2($value));
        }

        $output = sprintf('<form%1$s>%2$s</form>', $atts_string, $content);
        return $output;
    }
}

$sampleForm = new SampleForm();