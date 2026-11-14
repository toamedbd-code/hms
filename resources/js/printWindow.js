export const openPrintableHtml = (
    html,
    features = '_blank',
    { closeAfterPrint = true } = {},
) => {
    const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
    const printWindow = window.open(url, '_blank', features);

    if (!printWindow) {
        URL.revokeObjectURL(url);
        return null;
    }

    printWindow.opener = null;
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    printWindow.addEventListener('load', () => {
        printWindow.focus();
        printWindow.print();
    }, { once: true });

    if (closeAfterPrint) {
        printWindow.addEventListener('afterprint', () => printWindow.close(), { once: true });
    }

    return printWindow;
};
