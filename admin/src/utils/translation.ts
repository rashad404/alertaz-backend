import { DEFAULT_LANG, type LanguageCode } from '../config/admin';

/**
 * Parse multilingual content that could be a JSON string or object
 * @param content - The content to parse (can be string, JSON string, or object)
 * @param locale - The language code to extract (defaults to admin default language)
 * @returns The translated string for the specified locale
 */
export function parseTranslatedContent(
  content: any,
  locale: LanguageCode | string = DEFAULT_LANG
): string {
  if (!content) return '';

  // If it's a plain string
  if (typeof content === 'string') {
    // Check if it's a JSON string
    if (content.startsWith('{') && content.endsWith('}')) {
      try {
        const parsed = JSON.parse(content);
        if (typeof parsed === 'object' && parsed !== null) {
          // Try to get the requested locale, fallback to English, then Azerbaijani
          return parsed[locale] || parsed.en || parsed.az || parsed.ru || content;
        }
      } catch {
        // Not valid JSON, return as is
        return content;
      }
    }
    return content;
  }

  // If it's already an object
  if (typeof content === 'object' && content !== null) {
    // Try to get the requested locale, fallback to English, then Azerbaijani
    return content[locale] || content.en || content.az || content.ru || '';
  }

  return String(content || '');
}

/**
 * Helper to get a specific field from translatable content
 * Useful for getting names, titles, descriptions etc.
 */
export function getTranslatedField(
  item: any,
  field: string,
  locale: LanguageCode | string = DEFAULT_LANG
): string {
  if (!item || !field) return '';

  const content = item[field];
  return parseTranslatedContent(content, locale);
}

/**
 * Format translatable content for form fields
 * Ensures the content is in the correct object format
 */
export function formatTranslatableField(
  content: any
): { az: string; en: string; ru: string } {
  const defaultValue = { az: '', en: '', ru: '' };

  if (!content) return defaultValue;

  // If it's already in the correct format
  if (typeof content === 'object' && 'az' in content && 'en' in content && 'ru' in content) {
    return {
      az: content.az || '',
      en: content.en || '',
      ru: content.ru || ''
    };
  }

  // If it's a JSON string, parse it
  if (typeof content === 'string' && content.startsWith('{')) {
    try {
      const parsed = JSON.parse(content);
      if (typeof parsed === 'object') {
        return {
          az: parsed.az || '',
          en: parsed.en || '',
          ru: parsed.ru || ''
        };
      }
    } catch {
      // If parsing fails, use the string for all languages
      return { az: content, en: content, ru: content };
    }
  }

  // If it's a plain string, use it for all languages
  if (typeof content === 'string') {
    return { az: content, en: content, ru: content };
  }

  return defaultValue;
}