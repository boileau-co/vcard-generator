/* vCard Generator admin JS */
/* global vcardGeneratorAdmin, jQuery */
(function ($) {
    'use strict';

    // --- Copy URL button ---
    function copyUrl(url, $btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                showCopied($btn);
            }).catch(function () {
                fallbackCopy(url, $btn);
            });
        } else {
            fallbackCopy(url, $btn);
        }
    }

    function fallbackCopy(url, $btn) {
        var $temp = $('<textarea>').val(url).css({ position: 'fixed', opacity: 0 }).appendTo('body');
        $temp[0].select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        $temp.remove();
        if (ok) { showCopied($btn); }
        else { alert(vcardGeneratorAdmin.copyFail); }
    }

    function showCopied($btn) {
        var $icon = $btn.find('.dashicons');
        $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
        setTimeout(function () {
            $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
        }, 1500);
    }

    $(document).on('click', '.vcard-generator-copy-url', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        copyUrl(url, $(this));
    });

    // --- QR modal ---
    var $modal = null;

    function buildModal() {
        if ($modal) { return; }
        $modal = $([
            '<div id="vcard-generator-qr-modal" role="dialog" aria-modal="true" aria-labelledby="vcard-generator-qr-modal-title">',
            '  <div id="vcard-generator-qr-modal-inner">',
            '    <button id="vcard-generator-qr-modal-close" type="button" aria-label="Close">&times;</button>',
            '    <p id="vcard-generator-qr-modal-title"></p>',
            '    <img id="vcard-generator-qr-modal-img" src="" alt="QR Code">',
            '    <div id="vcard-generator-qr-modal-actions"></div>',
            '  </div>',
            '</div>',
        ].join('')).appendTo('body');

        $modal.on('click', function (e) {
            if ($(e.target).is('#vcard-generator-qr-modal')) { closeModal(); }
        });
        $('#vcard-generator-qr-modal-close').on('click', closeModal);
        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') { closeModal(); }
        });
    }

    function openModal(postId, url, title) {
        buildModal();
        $('#vcard-generator-qr-modal-title').text(title || 'QR Code');

        var nonce = '';
        // Nonces are embedded via data attributes added by PHP in the list table.
        var $btn = $('[data-post-id="' + postId + '"]');
        nonce = $btn.data('nonce') || '';

        var svgUrl = vcardGeneratorAdmin.ajaxUrl + '?action=vcard_generator_qr_svg&post_id=' + postId + '&_wpnonce=' + nonce;
        var pngUrl = vcardGeneratorAdmin.ajaxUrl + '?action=vcard_generator_qr_png&post_id=' + postId + '&_wpnonce=' + nonce;
        var slug   = $btn.data('slug') || 'qr';

        $('#vcard-generator-qr-modal-img').attr('src', svgUrl);
        $('#vcard-generator-qr-modal-actions').html(
            '<a href="' + svgUrl + '" class="button button-primary" download="qr-' + slug + '.svg">Download SVG</a>' +
            '<a href="' + pngUrl + '" class="button" download="qr-' + slug + '.png">Download PNG</a>' +
            '<button type="button" class="button vcard-generator-copy-url" data-url="' + url + '">Copy URL</button>'
        );
        $modal.addClass('is-open');
        $('#vcard-generator-qr-modal-close').trigger('focus');
    }

    function closeModal() {
        if ($modal) { $modal.removeClass('is-open'); }
    }

    $(document).on('click', '.vcard-generator-qr-preview', function (e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var url    = $(this).data('url');
        var title  = $(this).closest('tr').find('.vcg_name a').text() || 'QR Code';
        openModal(postId, url, title);
    });

})(jQuery);
