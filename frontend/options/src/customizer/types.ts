import type { SettingsField } from '../lib/fields';

export type PreviewDevice = 'desktop' | 'tablet' | 'mobile';

export interface CustomizerHtmlBlock {
    content?: string;
}

export interface CustomizerSection {
    id: string;
    title: string;
    description?: string;
    fields: SettingsField[];
    html?: CustomizerHtmlBlock[];
}

export interface CustomizerTab {
    id: string;
    title: string;
    sections: CustomizerSection[];
}

export interface CustomizerPayload {
    apiUrl?: string;
    nonce?: string;
    themeName?: string;
    themeVersion?: string;
    ttdfVersion?: string;
    siteUrl?: string;
    previewUrls?: Record<string, string>;
    blockFields?: Record<string, { kind?: string; slot?: string }>;
    backUrl?: string;
    optionsUrl?: string;
}

export interface CustomizerConfig {
    tabs?: Record<string, { title?: string; sections?: unknown[] }>;
    components?: string[];
    templateSlots?: Record<string, string> | string[];
    categories?: Record<string, string> | string[];
}
