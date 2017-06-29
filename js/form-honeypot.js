/**
 * Created by nick on 5/24/17.
 *
 * All honeypot handling code in javascript should be in this file.
 */

var NIForm = NIForm || {};

/**
 * Generates a GUID value.
 *
 * Copied function contents from here: https://stackoverflow.com/a/105074/1946899
 *
 * @returns {string}
 */
NIForm.generateGuid = function () {
    function s4() {
        return Math.floor((1 + Math.random()) * 0x10000)
            .toString(16)
            .substring(1);
    }

    return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
        s4() + '-' + s4() + s4() + s4();
};

/**
 * This class handles setting up and
 * @param formId
 * @param tokenUrl
 * @param fieldName
 * @param honeypotIdFieldName
 * @constructor
 */
NIForm.Honeypot = function (formId, tokenUrl, fieldName, honeypotIdFieldName) {
    honeypotId = NIForm.generateGuid();

    // Base submit data to send to honeypot token generator
    var submitData = {
        formId: formId,
        honeypotId: honeypotId
    };

    jQuery.post(
        tokenUrl,
        submitData,
        function (data, textStatus, jqXHR) {
            var token = data.token;

            var $form = jQuery('#' + formId);
            // Add hidden fields to form.
            $form.append('<input type="hidden" name="' + fieldName + '" value="' + token + '">');

            $form.append('<input type="hidden" name="' + honeypotIdFieldName + '" value="' + honeypotId + '">');

        },
        'json'
    );
};

