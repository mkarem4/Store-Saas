/*
 * Panel behaviour shared by the admin, store and staff panels.
 *
 * 1. Unsaved changes: Filament asks with the browser's own confirm() and
 *    "Leave site?" prompts. Every leave we can intercept (panel links, SPA
 *    navigation, plain links such as the language switch, the sign-out form)
 *    now uses one designed dialog instead. Reloading or closing the tab still
 *    gets the browser prompt: browsers allow nothing else there.
 * 2. An open form dialog only counts as unsaved once something in it changed.
 * 3. Dialogs: the body scrolls inside the window, and the window is marked
 *    while it overflows so the header and footer can show a divider.
 *
 * Loaded as a module, so it runs before Alpine starts and the overrides below
 * are in place when Filament's x-init calls them.
 */

const strings = window.ylLeaveStrings ?? {
    title: 'Leave without saving?',
    body: 'Your changes on this page have not been saved. If you leave now, they will be lost.',
    stay: 'Keep editing',
    leave: 'Leave without saving',
}

const guards = new Set()
let bypass = false
let modalTouched = false

const isDirty = () => !bypass && [...guards].some((guard) => guard())

const hashOf = ($wire) => window.jsMd5(JSON.stringify($wire.data).replace(/\\/g, ''))

const isRedirecting = ($wire) => Boolean($wire?.__instance?.effects?.redirect)

/* ---------------------------------------------------------------- dialog */

let dialog = null
let pending = null

const buildDialog = () => {
    const element = document.createElement('dialog')
    element.className = 'yl-leave-dialog'
    element.setAttribute('aria-labelledby', 'yl-leave-title')
    element.setAttribute('aria-describedby', 'yl-leave-body')
    element.innerHTML = `
        <form method="dialog" class="yl-leave-dialog-panel">
            <span class="yl-leave-dialog-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            </span>
            <h2 id="yl-leave-title" class="yl-leave-dialog-title"></h2>
            <p id="yl-leave-body" class="yl-leave-dialog-body"></p>
            <div class="yl-leave-dialog-actions">
                <button type="submit" value="stay" class="yl-leave-dialog-btn yl-leave-dialog-btn-primary" autofocus></button>
                <button type="submit" value="leave" class="yl-leave-dialog-btn yl-leave-dialog-btn-danger"></button>
            </div>
        </form>`

    element.querySelector('#yl-leave-title').textContent = strings.title
    element.querySelector('#yl-leave-body').textContent = strings.body
    element.querySelector('[value="stay"]').textContent = strings.stay
    element.querySelector('[value="leave"]').textContent = strings.leave

    // A click on the backdrop (the dialog element itself) keeps the person on the page.
    element.addEventListener('click', (event) => {
        if (event.target === element) {
            element.close('stay')
        }
    })

    element.addEventListener('close', () => {
        const resolve = pending
        pending = null
        resolve?.(element.returnValue === 'leave')
    })

    document.body.appendChild(element)

    return element
}

const confirmLeave = () => {
    // SPA navigation replaces <body>, so the dialog is rebuilt when it went with it.
    if (! dialog || ! dialog.isConnected) {
        dialog = buildDialog()
    }

    if (pending) {
        return Promise.resolve(false)
    }

    return new Promise((resolve) => {
        pending = resolve
        dialog.returnValue = ''
        dialog.showModal()
    })
}

window.ylConfirmLeave = confirmLeave

/* ------------------------------------------------ Filament alert hooks */

window.setUpFilamentUnsavedDataChangesAlert = ({ $wire }) => {
    const guard = () => ! isRedirecting($wire) && hashOf($wire) !== $wire.savedDataHash

    guards.add(guard)
    $wire.__instance.addCleanup(() => guards.delete(guard))
}

window.setUpFilamentSpaModeUnsavedDataChangesAlert = ({ resolveLivewireComponentUsing, $wire }) => {
    const guard = () =>
        typeof resolveLivewireComponentUsing() !== 'undefined' &&
        ! isRedirecting($wire) &&
        hashOf($wire) !== $wire.savedDataHash

    guards.add(guard)
    $wire.__instance.addCleanup(() => guards.delete(guard))
}

window.setUpFilamentUnsavedActionChangesAlert = ({ resolveLivewireComponentUsing, $wire }) => {
    const guard = () => {
        if (! document.querySelector('.fi-modal.fi-modal-open')) {
            modalTouched = false

            return false
        }

        return (
            modalTouched &&
            typeof resolveLivewireComponentUsing() !== 'undefined' &&
            ! isRedirecting($wire) &&
            ($wire.mountedActions ?? []).some((action) => action.hasUnsavedChangesAlert ?? true)
        )
    }

    guards.add(guard)
    $wire.__instance.addCleanup(() => guards.delete(guard))
}

const markModalTouched = (event) => {
    if (event.target instanceof Element && event.target.closest('.fi-modal.fi-modal-open')) {
        modalTouched = true
    }
}

document.addEventListener('input', markModalTouched, true)
document.addEventListener('change', markModalTouched, true)

/* ------------------------------------------------------- interception */

// SPA navigation (sidebar, breadcrumbs, Cancel): Livewire asks first.
document.addEventListener('livewire:navigate', (event) => {
    if (! isDirty()) {
        return
    }

    event.preventDefault()

    const url = event.detail?.url?.toString?.() ?? event.detail?.url

    confirmLeave().then((leave) => {
        if (! leave || ! url) {
            return
        }

        bypass = true
        window.Livewire.navigate(url)
    })
})

document.addEventListener('livewire:navigated', () => {
    bypass = false
    modalTouched = false
})

const isSpaLink = (link) =>
    [...link.attributes].some((attribute) => attribute.name.startsWith('wire:navigate')) ||
    (link.getAttribute('x-on:click') ?? '').includes('Alpine.navigate')

// Plain links that leave the page with a full load (language switch, impersonation, receipts).
document.addEventListener(
    'click',
    (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return
        }

        const link = event.target instanceof Element ? event.target.closest('a[href]') : null

        if (! link || isSpaLink(link) || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
            return
        }

        const url = new URL(link.href, window.location.href)

        if (! ['http:', 'https:'].includes(url.protocol)) {
            return
        }

        if (url.hash && url.pathname === window.location.pathname && url.search === window.location.search) {
            return
        }

        if (! isDirty()) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()

        confirmLeave().then((leave) => {
            if (leave) {
                bypass = true
                window.location.assign(url.href)
            }
        })
    },
    true,
)

// Plain forms that leave the page (sign out).
document.addEventListener(
    'submit',
    (event) => {
        const form = event.target

        // Livewire forms save in place; only native submits leave the page.
        if (
            ! (form instanceof HTMLFormElement) ||
            form.method === 'dialog' ||
            [...form.attributes].some((attribute) => attribute.name.startsWith('wire:submit'))
        ) {
            return
        }

        if (! isDirty()) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()

        confirmLeave().then((leave) => {
            if (leave) {
                bypass = true
                form.submit()
            }
        })
    },
    true,
)

// Reload shortcuts (F5, Ctrl/⌘+R, with or without Shift) reach the page first, so they
// get the designed dialog. `code` keeps this working on Arabic keyboard layouts.
document.addEventListener(
    'keydown',
    (event) => {
        const isReload =
            event.code === 'F5' || ((event.ctrlKey || event.metaKey) && ! event.altKey && event.code === 'KeyR')

        if (! isReload || ! isDirty()) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()

        confirmLeave().then((leave) => {
            if (leave) {
                bypass = true
                window.location.reload()
            }
        })
    },
    true,
)

// The browser's reload button, closing the tab, typing a new address: only the browser's prompt is allowed.
window.addEventListener('beforeunload', (event) => {
    if (! isDirty()) {
        return
    }

    event.preventDefault()
    event.returnValue = true
})

/* ------------------------------------------------------------- dialogs */

// Selects inside a scrolling dialog body must not be clipped by it.
const markFixedPositioning = () => document.body.classList.add('fi-fixed-positioning-context')

const overflowObserver = new ResizeObserver((entries) => {
    for (const { target } of entries) {
        target.closest('.fi-modal-window')?.toggleAttribute('data-overflowing', target.scrollHeight > target.clientHeight + 1)
    }
})

const watched = new WeakSet()

const watchModalContent = (root) => {
    root.querySelectorAll?.('.fi-modal-window > .fi-modal-content').forEach((content) => {
        if (watched.has(content)) {
            return
        }

        watched.add(content)
        overflowObserver.observe(content)

        // Children change height without the content box resizing when it is capped.
        new MutationObserver(() => {
            content.closest('.fi-modal-window')?.toggleAttribute('data-overflowing', content.scrollHeight > content.clientHeight + 1)
        }).observe(content, { childList: true, subtree: true })
    })
}

const start = () => {
    markFixedPositioning()
    watchModalContent(document)

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => node.nodeType === 1 && watchModalContent(node))
        }
    }).observe(document.body, { childList: true, subtree: true })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start)
} else {
    start()
}

document.addEventListener('livewire:navigated', start)
