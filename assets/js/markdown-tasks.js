// Shared by the main editor and the standalone edit view.
window.KBTasks = {
    removeCompleted(text) {
        let fence = null;
        let count = 0;
        const lines = text.match(/[^\r\n]*(?:\r\n|\n|\r|$)/g) || [];
        const content = lines.filter(line => {
            const plain = line.replace(/[\r\n]+$/, '');
            const marker = plain.match(/^ {0,3}(`{3,}|~{3,})(.*)$/);
            if (fence) {
                if (marker && marker[1][0] === fence.char
                    && marker[1].length >= fence.length && !marker[2].trim()) fence = null;
                return true;
            }
            if (marker && (marker[1][0] !== '`' || !marker[2].includes('`'))) {
                fence = { char: marker[1][0], length: marker[1].length };
                return true;
            }
            if (/^[ \t]*-[ \t]+\[[xX]\](?:[ \t]+|$)/.test(plain)) {
                count++;
                return false;
            }
            return true;
        }).join('');
        return { content, count };
    }
};
