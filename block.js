( function( blocks, element, components, editor, i18n, ServerSideRender ) {
    const el = element.createElement;
    const InspectorControls = editor.InspectorControls;
    const MediaUpload = editor.MediaUpload;
    const MediaUploadCheck = editor.MediaUploadCheck;
    const PanelBody = components.PanelBody;
    const RangeControl = components.RangeControl;
    const TextControl = components.TextControl;
    const SelectControl = components.SelectControl;
    const ColorPalette = components.ColorPalette;
    const Button = components.Button;

    const COLORS = [
        { color: '#000000', name: 'Black' },
        { color: '#ffffff', name: 'White' },
        { color: '#f5f5f5', name: 'Gray 100' },
        { color: '#e5e7eb', name: 'Gray 200' },
        { color: '#1f2937', name: 'Gray 800' },
        { color: '#ef4444', name: 'Red' },
        { color: '#10b981', name: 'Green' },
        { color: '#3b82f6', name: 'Blue' },
    ];

    blocks.registerBlockType('pog/png-overlay-gallery', {
        title: i18n.__('PNG Overlay Gallery', 'png-overlay-gallery'),
        icon: 'format-gallery',
        category: 'widgets',
        supports: { align: true },
        attributes: {
            ids:     { type: 'array', default: [], items: { type: 'number' } },
            columns: { type: 'number', default: 4 },
            gap:     { type: 'number', default: 10 },
            overlay: { type: 'string', default: '#000000' },
            bg:      { type: 'string', default: '#ffffff' },
            size:    { type: 'string', default: 'large' },
            target:  { type: 'string', default: '_self' },
            rel:     { type: 'string', default: 'noopener' },
            hover:   { type: 'string', default: 'zoom' },
        },
        edit: function(props){
            const attrs = props.attributes;
            const setAttr = (k, v) => props.setAttributes({ [k]: v });

            const mediaPreview = el('div',
                { style: { display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(80px,1fr))', gap:'8px', marginTop:'8px' } },
                (attrs.ids || []).map(function(id){
                    return el('div', { key:id, style:{ border:'1px solid #e5e7eb', borderRadius:'6px', padding:'4px', textAlign:'center' } },
                        el('img', { src: (window.wp && wp.data) ? (wp.data.select('core').getMedia(id)?.media_details?.sizes?.thumbnail?.source_url || wp.data.select('core').getMedia(id)?.source_url || '') : '', alt:'', style:{ width:'100%', height:'60px', objectFit:'contain' } })
                    );
                })
            );

            const controls = el(InspectorControls, {},
                el(PanelBody, { title: i18n.__('Layout', 'png-overlay-gallery'), initialOpen: true },
                    el(RangeControl, {
                        label: i18n.__('Columns', 'png-overlay-gallery'),
                        min:1, max:12, value: attrs.columns,
                        onChange: (v)=> setAttr('columns', v)
                    }),
                    el(RangeControl, {
                        label: i18n.__('Gap (px)', 'png-overlay-gallery'),
                        min:0, max:64, value: attrs.gap,
                        onChange: (v)=> setAttr('gap', v)
                    }),
                    el(SelectControl, {
                        label: i18n.__('Image Size', 'png-overlay-gallery'),
                        value: attrs.size,
                        options: [
                            { label:'Thumbnail', value:'thumbnail' },
                            { label:'Medium',    value:'medium' },
                            { label:'Large',     value:'large' },
                            { label:'Full',      value:'full' },
                        ],
                        onChange: (v)=> setAttr('size', v)
                    }),
                    el(SelectControl, {
                        label: i18n.__('Hover Effect', 'png-overlay-gallery'),
                        value: attrs.hover,
                        options: [
                            { label: i18n.__('Zoom', 'png-overlay-gallery'), value:'zoom' },
                            { label: i18n.__('Shadow', 'png-overlay-gallery'), value:'shadow' },
                            { label: i18n.__('None', 'png-overlay-gallery'), value:'none' },
                        ],
                        onChange: (v)=> setAttr('hover', v)
                    }),
                ),
                el(PanelBody, { title: i18n.__('Colors', 'png-overlay-gallery'), initialOpen: false },
                    el('div', {},
                        el('label', {}, i18n.__('Overlay Color', 'png-overlay-gallery')),
                        el(ColorPalette, {
                            colors: COLORS,
                            value: attrs.overlay,
                            onChange: (v)=> setAttr('overlay', v || '#000000')
                        })
                    ),
                    el('div', { style:{ marginTop:'10px' } },
                        el('label', {}, i18n.__('Tile Background', 'png-overlay-gallery')),
                        el(ColorPalette, {
                            colors: COLORS,
                            value: attrs.bg,
                            onChange: (v)=> setAttr('bg', v || '#ffffff')
                        })
                    )
                ),
                el(PanelBody, { title: i18n.__('Links', 'png-overlay-gallery'), initialOpen: false },
                    el(SelectControl, {
                        label: i18n.__('Link Target', 'png-overlay-gallery'),
                        value: attrs.target,
                        options: [
                            { label:'Same tab', value:'_self' },
                            { label:'New tab',  value:'_blank' },
                        ],
                        onChange: (v)=> setAttr('target', v)
                    }),
                    el(TextControl, {
                        label: i18n.__('rel attribute', 'png-overlay-gallery'),
                        value: attrs.rel,
                        onChange: (v)=> setAttr('rel', v)
                    }),
                )
            );

            const mediaPicker = el('div', {},
                el(MediaUploadCheck, {},
                    el(MediaUpload, {
                        onSelect: function(medias){
                            const pngs = (medias || []).filter(function(m){ return m.mime && m.mime.indexOf('image/png') === 0; });
                            setAttr('ids', pngs.map(function(m){ return m.id; }));
                        },
                        allowedTypes: ['image/png'],
                        multiple: true,
                        gallery: true,
                        value: attrs.ids,
                        render: function(obj){
                            return el(Button, { onClick: obj.open, variant: 'primary' }, i18n.__('Select PNG Images', 'png-overlay-gallery'));
                        }
                    })
                ),
                mediaPreview
            );

            const preview = el(ServerSideRender, {
                block: 'pog/png-overlay-gallery',
                attributes: attrs
            });

            return el('div', {},
                controls,
                mediaPicker,
                el('div', { style:{ marginTop:'12px' } }, preview)
            );
        },
        save: function(){ return null; }
    });
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.editor, window.wp.i18n, window.wp.serverSideRender );
