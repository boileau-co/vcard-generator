/* BCO vCard admin JS */
/* global bcovCardAdmin, jQuery */
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
        else { alert(bcovCardAdmin.copyFail); }
    }

    function showCopied($btn) {
        var $icon = $btn.find('.dashicons');
        $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
        setTimeout(function () {
            $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
        }, 1500);
    }

    $(document).on('click', '.bco-copy-url', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        copyUrl(url, $(this));
    });

    // --- QR modal ---
    var $modal = null;

    function buildModal() {
        if ($modal) { return; }
        $modal = $([
            '<div id="bco-qr-modal" role="dialog" aria-modal="true" aria-labelledby="bco-qr-modal-title">',
            '  <div id="bco-qr-modal-inner">',
            '    <button id="bco-qr-modal-close" type="button" aria-label="Close">&times;</button>',
            '    <p id="bco-qr-modal-title"></p>',
            '    <img id="bco-qr-modal-img" src="" alt="QR Code">',
            '    <div id="bco-qr-modal-actions"></div>',
            '  </div>',
            '</div>',
        ].join('')).appendTo('body');

        $modal.on('click', function (e) {
            if ($(e.target).is('#bco-qr-modal')) { closeModal(); }
        });
        $('#bco-qr-modal-close').on('click', closeModal);
        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') { closeModal(); }
        });
    }

    function openModal(postId, url, title) {
        buildModal();
        $('#bco-qr-modal-title').text(title || 'QR Code');

        var nonce = '';
        // Nonces are embedded via data attributes added by PHP in the list table.
        var $btn = $('[data-post-id="' + postId + '"]');
        nonce = $btn.data('nonce') || '';

        var svgUrl  = bcovCardAdmin.ajaxUrl + '?action=bco_vcard_qr_svg&post_id=' + postId + '&_wpnonce=' + nonce;
        var pngUrl  = bcovCardAdmin.ajaxUrl + '?action=bco_vcard_qr_png&post_id=' + postId + '&_wpnonce=' + nonce;
        var slug    = $btn.data('slug') || 'qr';

        $('#bco-qr-modal-img').attr('src', svgUrl);
        $('#bco-qr-modal-actions').html(
            '<a href="' + svgUrl + '" class="button button-primary" download="qr-' + slug + '.svg">Download SVG</a>' +
            '<a href="' + pngUrl + '" class="button" download="qr-' + slug + '.png">Download PNG</a>' +
            '<button type="button" class="button bco-copy-url" data-url="' + url + '">Copy URL</button>'
        );
        $modal.addClass('is-open');
        $('#bco-qr-modal-close').trigger('focus');
    }

    function closeModal() {
        if ($modal) { $modal.removeClass('is-open'); }
    }

    $(document).on('click', '.bco-qr-preview', function (e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        var url    = $(this).data('url');
        var title  = $(this).closest('tr').find('.bco_name a').text() || 'QR Code';
        openModal(postId, url, title);
    });

})(jQuery);
