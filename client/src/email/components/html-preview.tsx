import type { FC } from 'react';

import type { HtmlPreviewProps } from '../props/html-preview-props';

/** Iframe con `sandbox` vacío: el HTML de la plantilla no ejecuta scripts ni toca la sesión de la app. */
export const HtmlPreview: FC<HtmlPreviewProps> = ({ html, title }) => (
    <iframe
        title={title}
        srcDoc={html}
        sandbox=""
        className="h-72 w-full rounded-lg border border-gray-200 bg-white md:h-80 dark:border-gray-800"
    />
);
