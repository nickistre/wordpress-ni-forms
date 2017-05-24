/**
 * Created by nick on 4/30/16.
 *
 * All code handing form in javascript should be in this file
 */

var NIForm = NIForm || {};

/**
 * This class handles all the javascript code to handle for a form.
 * @param formId The ID of the form to work with.
 * @constructor
 */
NIForm.Form = function(formId) {
    this.formId = formId;
};

/**
 * Setup form to submit using AjaxForm jQuery plugin
 * @param targetUrl
 * @param formData
 */
NIForm.Form.prototype.setupAjaxForm = function(targetUrl, formData) {
    // console.log('formId: ', this.formId);
    // console.log('targetUrl: ', targetUrl);
    // console.log('formData: ', formData);

    // Setup blockUI defaults
    jQuery.blockUI.defaults.css = {
        padding:	0,
        margin:		0,
        width:		'30%',
        top:		'40%',
        left:		'35%',
        textAlign:	'center',
        color:		'#fff',
        border:		'none',
        backgroundColor:'rgba(0, 0, 0, .5)',
        cursor:		'wait'
    };

    var formSel = '#'+this.formId;
    var formOptions = {
        data: formData,
        dataType: 'json',
        resetForm: true,
        beforeSerialize: function($form, options) {
            $form.block();
        },
        success: function(responseData, statusText, xhr, element) {
            // console.log('Success!');
            // console.log(arguments);

            if (responseData.process_result) {
                if (responseData.redirect_url) {
                    window.location.href = responseData.redirect_url;
                }

                if (responseData.replace_html) {
                    jQuery(formSel).fadeOut(function () {
                        jQuery(formSel).html(responseData.replace_html);
                        jQuery(formSel).fadeIn();
                    });
                }

                if (responseData.process_message) {
                    // TODO: Check if messaging container in form and use that if it exists
                    jQuery('<div>'+responseData.process_message+'</div>').dialog({
                        position: {
                            my: "center",
                            at: "center",
                            of: formSel
                        },
                        show: true,
                        hide: true,
                        modal: false,
                        draggable: false,
                        resizable: false,
                        close: function( event, ui ) {
                            jQuery(formSel).unblock();
                        },
                        buttons: {
                            Ok: function() {
                                jQuery( this ).dialog('close');
                            }
                        }
                    });
                }
                else {
                    // No message to show, just unblock the form.
                    jQuery(formSel).unblock();
                }
            }
        },
        error: function() {
            //console.log('Error!');
            //console.log(arguments);
            window.alert('Error occurred on form submit!');
            jQuery(formSel).unblock();
        },
        type: 'POST',
        url: targetUrl
    };

    jQuery(formSel).ajaxForm(formOptions);
};