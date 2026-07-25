// This file is part of Moodle - http://moodle.org/

/**
 * Accessible playlist controls for format_iomadvideo.
 *
 * @module     format_iomadvideo/player
 * @copyright  2026 IOMAD Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    root: '[data-region="iomadvideo"]',
    player: '[data-region="player"]',
    select: '[data-action="select-video"]',
};

/**
 * Stop local media in a hidden player.
 *
 * @param {HTMLElement} player Player container.
 */
const stopMedia = player => {
    player.querySelectorAll('video, audio').forEach(media => media.pause());
};

/**
 * Select a playlist item.
 *
 * @param {HTMLElement} root Playlist root.
 * @param {HTMLButtonElement} button Selected button.
 * @param {Boolean} requestPlayback Attempt playback for direct media.
 */
const select = (root, button, requestPlayback = false) => {
    const targetId = button.dataset.player;
    root.querySelectorAll(SELECTORS.player).forEach(player => {
        const active = player.id === targetId;
        if (!active) {
            stopMedia(player);
        }
        player.hidden = !active;
        player.classList.toggle('is-active', active);
    });
    root.querySelectorAll(SELECTORS.select).forEach(candidate => {
        const active = candidate === button;
        candidate.classList.toggle('is-active', active);
        candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    const player = root.querySelector(`#${CSS.escape(targetId)}`);
    if (player && requestPlayback) {
        const video = player.querySelector('video');
        if (video) {
            video.play().catch(() => {
                // Browser autoplay policy may require an explicit user gesture.
            });
        }
    }
};

/**
 * Bind one playlist.
 *
 * @param {HTMLElement} root Playlist root.
 */
const bind = root => {
    const buttons = Array.from(root.querySelectorAll(SELECTORS.select));
    buttons.forEach((button, index) => {
        button.addEventListener('click', () => select(root, button, true));
        button.addEventListener('keydown', event => {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            let target = index;
            if (event.key === 'ArrowDown') {
                target = (index + 1) % buttons.length;
            } else if (event.key === 'ArrowUp') {
                target = (index - 1 + buttons.length) % buttons.length;
            } else if (event.key === 'Home') {
                target = 0;
            } else {
                target = buttons.length - 1;
            }
            buttons[target].focus();
        });
    });

    if (root.dataset.autoadvance === '1') {
        root.querySelectorAll('video').forEach(video => {
            video.addEventListener('ended', () => {
                const player = video.closest(SELECTORS.player);
                const current = buttons.findIndex(button => button.dataset.player === player.id);
                if (current >= 0 && current + 1 < buttons.length) {
                    select(root, buttons[current + 1], true);
                    buttons[current + 1].focus();
                }
            });
        });
    }
};

/**
 * Initialise every video-first course region.
 */
export const init = () => document.querySelectorAll(SELECTORS.root).forEach(bind);
