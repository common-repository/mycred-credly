/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


jQuery(document).ready(function ($) {

    // Listen for Credly Badge Builder events
    window.addEventListener('message', function (e) {
        // Only continue if data is from credly.com
        if ("https://credly.com" === e.origin && "object" === typeof (data = e.data)) {
            var win = window.dialogArguments || opener || parent || top;
            // Remove the badge builder thickbox
            tb_remove();

            // Send the badge data along for uploading
            $.ajax({
                url: ajaxurl,
                data: {
                    'action': 'credly-save-badge',
                    'post_id': $('#post_ID').val(),
                    'image': e.data.image,
                    'icon_meta': e.data.iconMetadata,
                    'badge_meta': e.data.packagedData,
                    'all_data': e.data
                },
                dataType: 'json',
                success: function (response) {
                    
                    jQuery("#mycred-badge-default").find(".default-image-wrapper.image-wrapper img").remove();
                    jQuery("#mycred-badge-default").find(".default-image-wrapper.image-wrapper").removeClass("empty dashicons");
                    jQuery("#mycred-badge-default").find(".default-image-wrapper.image-wrapper").append("<img src='" + e.data.image + "'>");
                    jQuery("input[name='mycred_badge[main_image]']").val(response.data.attachment_id);

                }
            });
        }
    });

    // Resize ThickBox when a badge builder link is clicked
    $('body').on('click', '.badge-builder-link', function (e) {
        e.preventDefault();
        badge_builder_setup_thickbox($(this));
    });

    // Resize badge builder thickbox on window resize
    $(window).resize(function () {
        badge_builder_resize_tb($('.badge-builder-link'));
    });

    // Add a custom class to our badge builder thickbox, then resize
    function badge_builder_setup_thickbox(link) {
        setTimeout(function () {
            $('#TB_window').addClass('badge-builder-thickbox');
            badge_builder_resize_tb(link);
        }, 0);
    }

    // Force badge builder thickboxes to our specified width/height
    function badge_builder_resize_tb(link) {
        setTimeout(function () {

            var width = link.attr('data-width');
            var height = link.attr('data-height');

            $('.badge-builder-thickbox').css({'marginLeft': -(width / 2)});
            $('.badge-builder-thickbox, .badge-builder-thickbox #TB_iframeContent').width(width).height(height);
            $('.badge-builder-thickbox, .badge-builder-thickbox #TB_ajaxContent').width(width).height(height).css({'padding': '0px'});

        }, 0);
    }

    $('#credly_category_search_submit').click(function (event) {
        event.preventDefault();
        var search_terms = $('#credly_category_search').val();
        $.ajax({
            type: 'post',
            // dataType: json,
            url: ajaxurl,
            data: {
                'action': 'search_credly_categories',
                'search_terms': search_terms
            },
            success: function (response) {
                $(' fieldset').append(response);
                $('#credly_search_results').show();
            }
        })

    });

    $('#mycred_credly_authorize').click( function( e ) {

        $(this).attr('disabled', 'disabled');

        var mycred_credly_key = $('#mycred_credly_key').val();
        var mycred_credly_secret = $('#mycred_credly_secret').val();
        var mycred_credly_email = $('#mycred_credly_email').val();
        var mycred_credly_pass = $('#mycred_credly_password').val();

        if ( mycred_credly_key == '' ) {
            mycred_credly_error_notice( 'API Key is required.' );
            $('#mycred_credly_key').focus();
        }
        else if ( mycred_credly_secret == '' ) {
            mycred_credly_error_notice( 'API Secret is required.' );
            $('#mycred_credly_secret').focus();
        }
        else if ( mycred_credly_email == '' || ! /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test( mycred_credly_email ) ) {
            mycred_credly_error_notice( 'Invalid email address.' );
            $('#mycred_credly_email').focus();
        }
        else if ( mycred_credly_pass == '' ) {
            mycred_credly_error_notice( 'Invalid password.' );
            $('#mycred_credly_password').focus();
        }
        else {

            $.ajax({
                url: ajaxurl,
                type: 'post',
                data: {
                    'action': 'mycred_credly_authorize',
                    'mycred_credly_key'   : mycred_credly_key,
                    'mycred_credly_secret': mycred_credly_secret,
                    'mycred_credly_auth' : btoa( mycred_credly_email +':'+ mycred_credly_pass ),
                },
                success: function ( response ) {
                    response = JSON.parse( response );
                    mycred_credly_success_notice( response );
                }
            });

        }

    } );

    function mycred_credly_success_notice( data ) {
        if ( data['status'] == 'success' ) {
            location.reload();
        }
        else {
            mycred_credly_error_notice( data['message'] );
        }
    }

    function mycred_credly_error_notice( msg ) {
        $('#mycred_credly_setting_notice').html(`<div class="error">${msg}</div>`);
        $('#mycred_credly_authorize').removeAttr('disabled');
    }

    function split( val ) {
        return val.split( /,\s*/ );
    }
    function extractLast( term ) {
        return split( term ).pop();
    }
 
    $( "#credly_category_search" ).autocomplete({
        source: function( request, response ) {
            $.ajax({
                dataType: "json",
                url: ajaxurl,
                data: {
                    'action': 'search_credly_categories',
                    'search_terms': extractLast( request.term )
                },
                success: function( data ) {
                    response($.map(data, function (item) {
                        return {
                            label: item.name,
                            value: item.id
                        };
                    }));
                }
            });
            return response;
        },
        select: function( event, ui ) {
            var parent = $(this).closest('tr').next();
            parent.find('th').show();
            parent.find('fieldset').append(`
                <label for="mycred_credly_categories[${ui.item.value}]">
                    <input type="checkbox" name="mycred_credly_categories[${ui.item.value}]" id="mycred_credly_categories[${ui.item.value}]" value="${ui.item.label}" />${ui.item.label}</label>
                </label>
                <br />
            `);
            $(this).val('');
            return false;
        }
    });


    $('#mycred_credly_connect_badge').click(function(){

        var ele = $(this);
        ele.attr('disabled', 'disabled');
        ele.find('img').show();

        $.ajax({
            url: ajaxurl,
            type: 'post',
            data: { 
                'action': 'get-mycred-credly-badges-list',
                'nonce': $('#mycred-credly-connect-badge-nonce').val()
            },
            success: function ( response ) {
                if ( response.status == 'success' ) {
                    $('#mycred-credly-badge-modal-wraper').find('p').remove();
                    $('#mycred-credly-badge-list').children().remove();
                    if(response.data !== null) {
                        response.data.forEach( function(element, index) {
                            $('#mycred-credly-badge-list').append(`
                                <option 
                                    data-img="${element.image_url}" 
                                    data-title="${element.title}" 
                                    data-desc="${element.short_description}" 
                                    value="${element.id}">
                                    ${element.title}
                                </option>
                            `);
                        });
                        tb_show( 'Existing Credly Badges', '#TB_inline?width=400&height=150&inlineId=mycred-credly-badge-modal' );
                    } else {
                        alert("You have 0 Badges in Credly. Please create first!");    
                    }
                }
                ele.removeAttr('disabled');
                ele.find('img').hide();
            },
            error: function (jqXHR, textStatus, errorThrown) { 
                ele.removeAttr('disabled');
                ele.find('img').hide();

            }
        });

    });


    $('#get-mycred-credly_badge').click(function(){

        var ele = $(this);
        ele.attr('disabled', 'disabled');
        ele.find('img').show();

        $.ajax({
            url: ajaxurl,
            type: 'post',
            data: { 
                'action': 'get-mycred-connect-credly-badge',
                'badge_id': $('#mycred-credly-badge-list').val(),
                'badge_title': $('#mycred-credly-badge-list option:selected').data('title'),
                'badge_img': $('#mycred-credly-badge-list option:selected').data('img'),
                'badge_desc': $('#mycred-credly-badge-list option:selected').data('desc'),
                'nonce': $('#mycred-credly-badge-list-nonce').val()
            },
            success: function ( response ) {
                if ( response.status == 'success' ) {
                    location.reload();
                }
                else {
                    $('#mycred-credly-badge-modal-wraper').append(`<p>${response.message}</p>`);
                    ele.removeAttr('disabled');
                    ele.find('img').hide();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) { 
                ele.removeAttr('disabled');
                ele.find('img').hide();
            }
        });

    });


});
