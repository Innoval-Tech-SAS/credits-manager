export function readFilenameFromDisposition(disposition: string | null): string | null {
    if (!disposition) {
        return null;
    }

    const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i);

    if (encoded) {
        return decodeURIComponent(encoded[1]);
    }

    const plain = disposition.match(/filename="?([^";]+)"?/i);

    return plain ? plain[1] : null;
}
