(function($){
    if (typeof POG_ADMIN === 'undefined') return;

    const optionKey = POG_ADMIN.optionKey;
    const $list = $('#pog-images-list');
    let index = $list.find('.pog-item').length;

    // Tabs
    $(document).on('click', '.pog-tabs .nav-tab', function(e){
        e.preventDefault();
        const tab = $(this).data('tab');
        $('.pog-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.pog-tab-panel').removeClass('is-active');
        $('#'+tab).addClass('is-active');
    });

    function productSelectHTML(selectedId) {
        if (!POG_ADMIN.productActive) return '';
        let html = '<label>' + POG_ADMIN.i18n.product + '<br/>' +
                   '<select class="product-select" name="' + optionKey + '[images]['+index+'][product_id]">';
        html += '<option value="0">— None —</option>';
        (POG_ADMIN.products || []).forEach(function(p){
            const sel = (parseInt(selectedId,10) === parseInt(p.id,10)) ? ' selected' : '';
            const title = (p.title || '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;');
            html += '<option value="'+p.id+'"'+sel+'>'+ title +'</option>';
        });
        html += '</select></label>';
        return html;
    }

    function colorInputsHTML() {
        return '<div class="pog-inline">\
            <label>'+POG_ADMIN.i18n.fgLabel+'<br/><input type="color" name="'+optionKey+'[images]['+index+'][fg]" value="#000000"/></label>\
            <label>'+POG_ADMIN.i18n.bgLabel+'<br/><input type="color" name="'+optionKey+'[images]['+index+'][bg]" value="#ffffff"/></label>\
            <label style="margin-top:6px;"><input type="checkbox" name="'+optionKey+'[images]['+index+'][recolor]" value="1"/> '+POG_ADMIN.i18n.recolor+'</label>\
        </div>';
    }

    function labelInputHTML() {
        return '<div class="pog-inline" style="margin-top:6px; width:100%">\
            <label style="flex:1 1 320px;">'+POG_ADMIN.i18n.label+'<br/><input type="text" name="'+optionKey+'[images]['+index+'][label]" placeholder="e.g., Product name or tagline" style="width:100%"/></label>\
        </div>';
    }

    function addImage(att) {
        if (att.mime !== 'image/png') return;
        if ($list.find('.pog-item[data-id="'+att.id+'"]').length) return;

        const alt = att.alt ? att.alt : '';
        const thumb = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;

        const html =
        '<div class="pog-item" data-id="'+att.id+'">' +
            '<img class="thumb" src="'+thumb+'" alt="'+alt.replace(/"/g,'&quot;')+'" />' +
            '<input type="hidden" name="'+optionKey+'[images]['+index+'][id]" value="'+att.id+'"/>' +

            '<div class="pog-inline">' +
                '<label>'+ POG_ADMIN.i18n.linkURL + '<br/>' +
                    '<input class="url-input" type="url" name="'+optionKey+'[images]['+index+'][url]" placeholder="https://example.com/product" />' +
                '</label>' +
                productSelectHTML(0) +
            '</div>' +

            colorInputsHTML() +
            labelInputHTML() +

            '<div class="pog-controls">' +
                '<span class="pog-small">'+ POG_ADMIN.i18n.idLabel + ': '+att.id+'</span>' +
                '<button type="button" class="button button-secondary pog-remove">'+ POG_ADMIN.i18n.remove +'</button>' +
            '</div>' +
        '</div>';

        $list.append(html);
        index++;
    }

    $('#pog-select-images').on('click', function(e){
        e.preventDefault();
        let frame = wp.media({
            title: POG_ADMIN.i18n.selectImages,
            button: { text: POG_ADMIN.i18n.useImages },
            multiple: true,
            library: { type: 'image/png' }
        });

        frame.on('select', function(){
            const selection = frame.state().get('selection');
            selection.each(function(attachment){ addImage(attachment.toJSON()); });
        });

        frame.open();
    });

    $(document).on('click', '.pog-remove', function(){ $(this).closest('.pog-item').remove(); });

})(jQuery);
