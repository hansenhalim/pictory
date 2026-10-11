import QRCode from 'qrcode';

/**
 * Draw each card's QR code with the error correction level and quiet zone the kiosk prints with.
 */
await Promise.all(
    [...document.querySelectorAll('[data-qr-url]')].map(async (element) => {
        element.innerHTML = await QRCode.toString(element.dataset.qrUrl, {
            type: 'svg',
            errorCorrectionLevel: 'Q',
            margin: 4,
        });
    }),
);
