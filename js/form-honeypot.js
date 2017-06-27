/**
 * Created by nick on 5/24/17.
 *
 * All honeypot handling code in javascript should be in this file.
 */

var NIForm = NIForm || {};

/**
 * Set to true to debug the cookie alternate way of managing the honeypot
 */
NIForm.debugCookieAlt = false;

/**
 * Returns if cookies are enabled in the current browser.
 *
 * This acts as a wrapper to navigator.cookieEnabled if the browser
 * supports that parameter; Added a check for older browsers that
 * do not have this parameter
 *
 * Copied code from here: https://stackoverflow.com/a/6663901/1946899
 *
 * @return boolean
 */
NIForm.cookieEnabled = function () {
    // Check for debug mode.
    if (NIForm.debugCookieAlt) {
        return false;
    }

    if (navigator.cookieEnabled) return true;

// set and read cookie
    document.cookie = "cookietest=1";
    var ret = document.cookie.indexOf("cookietest=") != -1;

// delete cookie
    document.cookie = "cookietest=1; expires=Thu, 01-Jan-1970 00:00:01 GMT";

    return ret;
};

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

    // Base submit data to send to honeypot token generator
    var submitData = {
        formId: formId
    };

    var honeypotId = null;
    // Need to add additional parameters if cookies are not supported
    if (!NIForm.cookieEnabled()) {
        honeypotId = NIForm.generateGuid();
        submitData.honeypotId = honeypotId;
    }

    jQuery.post(
        tokenUrl,
        submitData,
        function (data, textStatus, jqXHR) {
            var token = data.token;

            var $form = jQuery('#' + formId);
            // Add hidden fields to form.
            $form.append('<input type="hidden" name="' + fieldName + '" value="' + token + '">');

            if (honeypotId) {
                $form.append('<input type="hidden" name="' + honeypotIdFieldName + '" value="' + honeypotId + '">');
            }
        },
        'json'
    );
};

