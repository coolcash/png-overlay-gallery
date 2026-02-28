(function($){
    if (typeof POG_ADMIN === 'undefined') return;

    const optionKey = POG_ADMIN.optionKey;
    const $list = $('#pog-images-list');
    let index = $list.find('.pog-item').length;

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

    function addImage(att) {
        if (att.mime !== 'image/png') return;
        if ($list.find('.pog-item[data-id="'+att.id+'"]').length) return;

        const alt = att.alt ? att.alt : '';
        const thumb = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;

        const html =
        '<div class="pog-item" data-id="'+att.id+'">' +
            '<img class="thumb" src="'+thumb+'" alt="'+alt.replace(/"/g,'&quot;')+'" />' +
            '<input type="hidden" name="'+optionKey+'[images]['+index+'][id]" value="'+att.id+'"/>' +

            '<label>'+ POG_ADMIN.i18n.linkURL + '<br/>' +
                '<input class="url-input" type="url" name="'+optionKey+'[images]['+index+'][url]" placeholder="https://example.com/product" />' +
            '</label>' +

            productSelectHTML(0) +

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
            selection.each(function(attachment){
                addImage(attachment.toJSON());
            });
        });

        frame.open();
    });

    $(document).on('click', '.pog-remove', function(){
        $(this).closest('.pog-item').remove();
    });

})(jQuery);
