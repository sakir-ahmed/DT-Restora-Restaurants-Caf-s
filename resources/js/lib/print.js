/**
 * Print a same-origin document without leaving the current page.
 * A hidden iframe loads the URL, then the browser print dialog
 * targets that frame. The receipt page stays off-screen.
 */
export function printUrl(url) {
    document.getElementById('receipt-print-frame')?.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'receipt-print-frame';
    iframe.title = 'Receipt';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.tabIndex = -1;
    iframe.style.cssText = 'position:fixed;left:0;top:0;width:0;height:0;border:0;';

    iframe.addEventListener('load', () => {
        const frameWindow = iframe.contentWindow;

        if (! frameWindow) {
            iframe.remove();

            return;
        }

        frameWindow.addEventListener('afterprint', () => {
            iframe.remove();
        }, { once: true });

        frameWindow.focus();
        frameWindow.print();
    }, { once: true });

    iframe.src = url;
    document.body.appendChild(iframe);
}

/**
 * Print a receipt that was built on the device. Used when the
 * server copy does not exist yet because the sale is still queued.
 */
export function printHtml(html) {
    document.getElementById('receipt-print-frame')?.remove();

    const iframe = document.createElement('iframe');
    iframe.id = 'receipt-print-frame';
    iframe.title = 'Receipt';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.tabIndex = -1;
    iframe.style.cssText = 'position:fixed;left:0;top:0;width:0;height:0;border:0;';

    iframe.addEventListener('load', () => {
        const frameWindow = iframe.contentWindow;

        if (! frameWindow) {
            iframe.remove();

            return;
        }

        frameWindow.addEventListener('afterprint', () => {
            iframe.remove();
        }, { once: true });

        frameWindow.focus();
        frameWindow.print();
    }, { once: true });

    iframe.srcdoc = html;
    document.body.appendChild(iframe);
}
