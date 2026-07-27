<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iomad_learning\local;

/**
 * Immutable theme SVG icon library.
 *
 * @package    theme_iomad_learning
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class svg_icon_library {
    /**
     * Reviewed symbols available in the bundled sprite.
     */
    private const ICONS = [
        'activity', 'add', 'alert', 'archive', 'arrowDown', 'arrowLeft', 'arrowRight', 'arrowUp',
        'award', 'ban', 'bell', 'book', 'brain', 'briefcase', 'building', 'bullhorn', 'calendar',
        'cart', 'certificate', 'chart', 'check', 'checkCircle', 'chevronDown', 'chevronLeft',
        'chevronRight', 'chevronUp', 'circle', 'clipboard', 'clock', 'close', 'cloud', 'code',
        'coffee', 'compress', 'copy', 'course', 'creditCard', 'cut', 'dashboard', 'database',
        'download', 'edit', 'expand', 'externalLink', 'eye', 'eyeOff', 'failure', 'file', 'fileExport',
        'fileImport', 'filter', 'flag', 'folder', 'folderPlus', 'form', 'globe', 'group', 'help', 'home',
        'image', 'info', 'institution', 'key', 'language', 'leaf', 'lightbulb', 'link', 'list', 'lock',
        'logout', 'mail', 'mapPin', 'menu', 'message', 'monitor', 'more', 'package', 'palette',
        'paperclip', 'pause', 'play', 'print', 'puzzle', 'refresh', 'report', 'restore', 'save',
        'search', 'settings', 'shapes', 'shield', 'smile', 'sort', 'spinner', 'star', 'store', 'tag',
        'trash', 'trophy', 'upload', 'user', 'userAdd', 'video', 'volume', 'wand', 'workflow',
        'facebook', 'instagram', 'linkedin', 'whatsapp', 'xSocial', 'youtube',
    ];

    /**
     * Return all reviewed icon names.
     *
     * @return string[]
     */
    public static function names(): array {
        return self::ICONS;
    }

    /**
     * Check whether a symbol is available.
     *
     * @param string $name Symbol name.
     * @return bool
     */
    public static function has(string $name): bool {
        return in_array($name, self::ICONS, true);
    }

    /**
     * Return the cache-addressed sprite URL.
     *
     * @return \moodle_url
     */
    public static function sprite_url(): \moodle_url {
        return new \moodle_url('/theme/iomad_learning/pix/icons/lms-icons.svg', [
            'v' => theme_get_revision(),
        ]);
    }
}
