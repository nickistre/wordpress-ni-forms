/**
 * Created by nick on 5/24/17.
 *
 * All honeypot handling code in javascript should be in this file.
 */

var NIForm = NIForm || {};

/**
 * This class handles setting up and
 * @param formId
 * @param tokenUrl
 * @param fieldName
 * @constructor
 */
NIForm.Honeypot = function (formId, tokenUrl, fieldName) {
    // this.formId = formId;
    // this.tokenUrl = tokenUrl;
    // this.fieldName = fieldName;

    jQuery.post(
        tokenUrl,
        {formId: formId},
        function (data, textStatus, jqXHR) {
            var token = data.token;

            // Add hidden field to form.
            jQuery('#' + formId).append('<input type="hidden" name="' + fieldName + '" value="' + token + '">');
        },
        'json'
    );
};

