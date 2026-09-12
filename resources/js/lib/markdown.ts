import DOMPurify from 'dompurify';
import MarkdownIt from 'markdown-it';

const markdown = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
});

if (typeof window !== 'undefined') {
    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
        if (node.tagName === 'A' && node.getAttribute('href')) {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });
}

export function renderMarkdown(content: string): string {
    const html = markdown.render(content);

    if (
        typeof window === 'undefined' ||
        typeof DOMPurify.sanitize !== 'function'
    ) {
        return html;
    }

    return DOMPurify.sanitize(html);
}
