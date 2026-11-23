// Public site behaviour. The theme itself is applied by the inline head script
// (before paint); this file only wires the controls.

const root = document.documentElement;

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function storedTheme() {
    try {
        return localStorage.getItem('theme') ?? 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(theme) {
    const dark = theme === 'dark' || (theme === 'system' && systemPrefersDark());
    root.classList.toggle('dark', dark);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(dark));
    });
}

function initThemeToggle() {
    applyTheme(storedTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = root.classList.contains('dark') ? 'light' : 'dark';

            try {
                // Same key as the Filament panels, so the dashboard opens in the same theme.
                localStorage.setItem('theme', next);
            } catch {
                // Storage blocked: the switch still works for this page view.
            }

            applyTheme(next);
        });
    });

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedTheme() === 'system') {
            applyTheme('system');
        }
    });
}

function initHeader() {
    const header = document.querySelector('[data-site-header]');

    if (! header) {
        return;
    }

    let ticking = false;

    const update = () => {
        header.toggleAttribute('data-scrolled', window.scrollY > 8);
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (! ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    update();
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (! input) {
            return;
        }

        button.hidden = false;

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
        });
    });
}

initThemeToggle();
initHeader();
initPasswordToggles();
