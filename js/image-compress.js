/**
 * Shared image compressor for BitBalance upload widgets.
 *
 * Re-encodes a user-picked image into a downscaled sRGB JPEG by drawing it
 * through a 2D canvas. Drawing through the canvas drops iPhone HDR gain maps
 * and embedded color profiles (which look over-saturated and bloat file size),
 * and the resize + quality cap keeps uploads small and fast.
 *
 * Used by:
 *   - ai-coach.php                    (AI Coach chat composer)
 *   - dashboard/dashboard-intake.php  (calorie-estimate chat bubble)
 *
 * Exposes: window.BitBalanceImage.compressImage(file, opts) => Promise<File>
 */
(function (global) {
    'use strict';

    const DEFAULTS = {
        maxEdge: 1600,          // longest side in px after downscale
        quality: 0.85,          // JPEG quality, 0..1
        filename: 'photo.jpg',  // name of the returned File
        mimeType: 'image/jpeg',
    };

    const DEFAULT_MESSAGES = {
        decode: 'Could not read the image.',
        empty:  'The image appears to be empty.',
        encode: 'Could not process the image.',
    };

    /**
     * @param {File|Blob} file - the user-picked file. Non-images are returned unchanged.
     * @param {Object} [opts]
     * @param {number} [opts.maxEdge]   - longest side in px (default 1600)
     * @param {number} [opts.quality]   - JPEG quality 0..1 (default 0.85)
     * @param {string} [opts.filename]  - returned File name (default 'photo.jpg')
     * @param {string} [opts.mimeType]  - output mime type (default 'image/jpeg')
     * @param {{decode?:string, empty?:string, encode?:string}} [opts.messages]
     *        Localized error messages thrown on failure so the caller can surface them.
     * @returns {Promise<File>}
     */
    async function compressImage(file, opts) {
        const cfg = Object.assign({}, DEFAULTS, opts || {});
        const msg = Object.assign({}, DEFAULT_MESSAGES, (opts && opts.messages) || {});

        if (!file || !file.type || !file.type.startsWith('image/')) return file;

        const blobUrl = URL.createObjectURL(file);
        try {
            const img = await new Promise((resolve, reject) => {
                const i = new Image();
                i.onload  = () => resolve(i);
                i.onerror = () => reject(new Error(msg.decode));
                i.src = blobUrl;
            });

            let w = img.naturalWidth, h = img.naturalHeight;
            if (!w || !h) throw new Error(msg.empty);

            if (w > cfg.maxEdge || h > cfg.maxEdge) {
                const r = Math.min(cfg.maxEdge / w, cfg.maxEdge / h);
                w = Math.round(w * r);
                h = Math.round(h * r);
            }

            const canvas = document.createElement('canvas');
            canvas.width  = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(img, 0, 0, w, h);

            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob(
                    b => b ? resolve(b) : reject(new Error(msg.encode)),
                    cfg.mimeType,
                    cfg.quality
                );
            });

            return new File([blob], cfg.filename, { type: cfg.mimeType });
        } finally {
            URL.revokeObjectURL(blobUrl);
        }
    }

    global.BitBalanceImage = { compressImage, DEFAULTS };
})(window);
