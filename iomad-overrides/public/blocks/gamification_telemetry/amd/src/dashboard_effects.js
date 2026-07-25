// This file is part of IOMAD - http://www.iomad.org/

/**
 * Reduced-motion-aware progress feedback.
 *
 * @module block_gamification_telemetry/dashboard_effects
 */
define([], function() {
    const init = (rootid, points) => {
        const root = document.getElementById(rootid);
        if (!root) {
            return;
        }
        const key = 'iomad-gamification-points:' + rootid;
        const previous = Number(sessionStorage.getItem(key) || points);
        sessionStorage.setItem(key, String(points));
        if (points <= previous || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        const layer = root.querySelector('.gamification-telemetry__effect');
        if (!layer) {
            return;
        }
        for (let index = 0; index < 12; index++) {
            const spark = document.createElement('span');
            spark.className = 'gamification-telemetry__spark';
            spark.style.setProperty('--spark-x', (10 + index * 7) + '%');
            spark.style.setProperty('--spark-r', (index * 29) + 'deg');
            spark.style.setProperty('--spark-dx', ((index % 3) - 1) * 24 + 'px');
            spark.style.setProperty('--spark-dy', (-20 - (index % 4) * 8) + 'px');
            layer.append(spark);
            spark.addEventListener('animationend', () => spark.remove(), {once: true});
        }
    };

    return {init: init};
});
