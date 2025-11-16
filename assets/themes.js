/**
 * Système de thèmes de couleurs par page
 * Chaque page a une couleur principale pour créer une identité visuelle distincte
 */

export const pageThemes = {
    'dashboard': {
        primary: 'indigo',
        secondary: 'indigo-600',
        light: 'indigo-50',
        dark: 'indigo-900/20'
    },
    'families': {
        primary: 'purple',
        secondary: 'purple-600',
        light: 'purple-50',
        dark: 'purple-900/20'
    },
    'objectives': {
        primary: 'blue',
        secondary: 'blue-600',
        light: 'blue-50',
        dark: 'blue-900/20'
    },
    'tasks': {
        primary: 'cyan',
        secondary: 'cyan-600',
        light: 'cyan-50',
        dark: 'cyan-900/20'
    },
    'planning': {
        primary: 'emerald',
        secondary: 'emerald-600',
        light: 'emerald-50',
        dark: 'emerald-900/20'
    },
    'proofs': {
        primary: 'amber',
        secondary: 'amber-600',
        light: 'amber-50',
        dark: 'amber-900/20'
    },
    'requests': {
        primary: 'rose',
        secondary: 'rose-600',
        light: 'rose-50',
        dark: 'rose-900/20'
    },
    'requests-detail': {
        primary: 'rose',
        secondary: 'rose-600',
        light: 'rose-50',
        dark: 'rose-900/20'
    },
    'messages': {
        primary: 'pink',
        secondary: 'pink-600',
        light: 'pink-50',
        dark: 'pink-900/20'
    },
    'activities': {
        primary: 'teal',
        secondary: 'teal-600',
        light: 'teal-50',
        dark: 'teal-900/20'
    },
    'paths': {
        primary: 'violet',
        secondary: 'violet-600',
        light: 'violet-50',
        dark: 'violet-900/20'
    },
    'specialists': {
        primary: 'sky',
        secondary: 'sky-600',
        light: 'sky-50',
        dark: 'sky-900/20'
    },
    'notifications': {
        primary: 'yellow',
        secondary: 'yellow-600',
        light: 'yellow-50',
        dark: 'yellow-900/20'
    },
    'integrations': {
        primary: 'slate',
        secondary: 'slate-600',
        light: 'slate-50',
        dark: 'slate-900/20'
    },
    'profile': {
        primary: 'gray',
        secondary: 'gray-600',
        light: 'gray-50',
        dark: 'gray-900/20'
    },
    'availabilities': {
        primary: 'lime',
        secondary: 'lime-600',
        light: 'lime-50',
        dark: 'lime-900/20'
    },
    'users': {
        primary: 'gray',
        secondary: 'gray-600',
        light: 'gray-50',
        dark: 'gray-900/20'
    }
};

/**
 * Récupère le thème d'une page
 * @param {string} pageName - Nom de la page
 * @returns {Object} Thème de la page ou thème par défaut
 */
export function getPageTheme(pageName) {
    return pageThemes[pageName] || {
        primary: 'brand',
        secondary: 'brand-600',
        light: 'brand-50',
        dark: 'brand-900/20'
    };
}

/**
 * Génère les classes CSS pour un bouton principal avec le thème
 * @param {string} pageName - Nom de la page
 * @param {boolean} useBrand - Utiliser brand-500 au lieu du thème (pour actions critiques)
 * @returns {string} Classes CSS
 */
export function getButtonPrimaryClasses(pageName, useBrand = false) {
    const theme = getPageTheme(pageName);
    if (useBrand) {
        return `bg-brand-500 hover:bg-brand-600 text-white border-brand-500`;
    }
    return `bg-${theme.primary}-500 hover:bg-${theme.secondary} text-white border-${theme.primary}-500`;
}

/**
 * Génère les classes CSS pour un bouton secondaire (outline) avec le thème
 * @param {string} pageName - Nom de la page
 * @returns {string} Classes CSS
 */
export function getButtonSecondaryClasses(pageName) {
    const theme = getPageTheme(pageName);
    return `border-${theme.primary}-500 bg-white text-${theme.primary}-500 hover:bg-${theme.light} dark:bg-gray-800 dark:border-${theme.primary}-400 dark:text-${theme.primary}-400 dark:hover:bg-${theme.dark}`;
}

/**
 * Génère les classes CSS pour un badge avec le thème
 * @param {string} pageName - Nom de la page
 * @returns {string} Classes CSS
 */
export function getBadgeClasses(pageName) {
    const theme = getPageTheme(pageName);
    return `bg-${theme.light} text-${theme.primary}-700 border border-${theme.primary}-300 dark:bg-${theme.dark} dark:text-${theme.primary}-400 dark:border-${theme.primary}-700`;
}

/**
 * Génère les classes CSS pour une bordure colorée avec le thème
 * @param {string} pageName - Nom de la page
 * @returns {string} Classes CSS
 */
export function getBorderClasses(pageName) {
    const theme = getPageTheme(pageName);
    return `border-${theme.primary}-500`;
}

/**
 * Génère les classes CSS pour un fond subtil avec le thème
 * @param {string} pageName - Nom de la page
 * @returns {string} Classes CSS
 */
export function getBackgroundClasses(pageName) {
    const theme = getPageTheme(pageName);
    return `bg-${theme.light}/30 dark:bg-${theme.dark}`;
}

