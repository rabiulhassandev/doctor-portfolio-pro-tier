/**
 * Front-end behaviour for the public site.
 *
 * ---------------------------------------------------------------------------
 * WHY ALPINE IS IMPORTED FROM LIVEWIRE AND NOT FROM 'alpinejs'
 * ---------------------------------------------------------------------------
 *
 * The booking wizard and the video library are Livewire components, and
 * Livewire 3 ships its own copy of Alpine inside its bundle. If this file also
 * did `import Alpine from 'alpinejs'` there would be two Alpine instances on
 * the page, the second would throw "Alpine has already been initialised", and
 * every interactive thing on the site — the mobile menu, the lightbox, the
 * accordion — would silently stop working.
 *
 * So we take Alpine *from* Livewire, register our plugins against it, and start
 * them together. This is Livewire's documented "manual bundling" setup. It also
 * requires `@livewireScriptConfig` in the layout instead of `@livewireScripts`;
 * both halves are needed, and omitting the Blade directive fails the same way.
 *
 * If you ever remove Livewire from the public site, this becomes a plain
 * `import Alpine from 'alpinejs'` again.
 *
 * Everything else interactive is declared inline in the Blade views with
 * x-data. This file only wires up the libraries and adds the one piece of
 * shared behaviour that would be repetitive to write per element.
 */

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';

Alpine.plugin(intersect);

/*
 * `focus` provides x-trap, which keeps the keyboard inside the mobile menu, the
 * gallery lightbox and the video player while they are open. Without it, tabbing
 * from an open dialogue walks silently into the page behind — which for someone
 * navigating by keyboard alone means the dialogue effectively cannot be closed.
 */
Alpine.plugin(focus);

window.Alpine = Alpine;

Livewire.start();

/**
 * Scroll-reveal.
 *
 * Add `data-reveal` to any element and it fades and slides into place the first
 * time it scrolls into view. Add a delay in milliseconds to stagger a group:
 *
 *     <div data-reveal></div>
 *     <div data-reveal="150"></div>
 *
 * The matching CSS lives in resources/css/app.css.
 *
 * This is deliberately plain DOM code rather than an Alpine directive. Alpine
 * only walks trees rooted at an `x-data` element, and almost every element we
 * want to reveal sits outside a component — as a directive it would leave most
 * of the site permanently at `opacity: 0`.
 */
const SELECTOR = '[data-reveal]';

const reveal = (el, animate = true) => {
    // Something already off-screen has nothing to animate into, so skip the
    // delay: staggering content the visitor has scrolled past only means they
    // find it still fading in when they scroll back up.
    el.style.transitionDelay = animate ? `${parseInt(el.dataset.reveal, 10) || 0}ms` : '0ms';
    el.classList.add('is-revealed');
};

// True once the element has gone past the top of the window — it can no longer
// scroll into view from below, so it must be shown now or it never will be.
const isAbove = (el) => el.getBoundingClientRect().bottom <= 0;

// IntersectionObserver is in every browser we support, but if it is ever
// missing, show the content rather than leaving it invisible.
const observer =
    typeof IntersectionObserver === 'undefined'
        ? null
        : new IntersectionObserver(
              (entries) => {
                  entries.forEach((entry) => {
                      // `threshold: 0` matters: with a fractional threshold a
                      // section taller than the window has to be scrolled a
                      // long way before enough of it counts as visible, and a
                      // section more than ten windows tall never reveals at all.
                      if (entry.isIntersecting) {
                          reveal(entry.target);
                      } else if (entry.boundingClientRect.bottom <= 0) {
                          reveal(entry.target, false);
                      } else {
                          return;
                      }

                      observer.unobserve(entry.target);
                  });
              },
              // The margin is positive on the bottom, which grows the root box
              // *past* the fold: an element starts revealing shortly before it
              // scrolls into view. With a negative margin — waiting until the
              // element is already 10% up the screen — anyone scrolling quickly
              // arrives at a section that is still fading in, which reads as a
              // page that has not loaded rather than as an animation.
              { rootMargin: '0px 0px 15% 0px', threshold: 0 }
          );

const watch = (el) => {
    if (! observer) return reveal(el, false);

    // Loading straight into the middle of a page — a '#book' link, a reload
    // where the browser restores the scroll position, a back navigation —
    // leaves everything above the landing point outside the observer's reach
    // forever. Show those immediately instead.
    if (isAbove(el)) return reveal(el, false);

    observer.observe(el);
};

document.querySelectorAll(SELECTOR).forEach(watch);

// Alpine and Livewire both render parts of the page after we have run — the
// gallery grid, the video filter and every step of the booking wizard swap
// their contents. Pick up anything added later.
new MutationObserver((mutations) => {
    mutations.forEach(({ addedNodes }) => {
        addedNodes.forEach((node) => {
            if (node.nodeType !== Node.ELEMENT_NODE) return;

            if (node.matches(SELECTOR)) watch(node);
            node.querySelectorAll(SELECTOR).forEach(watch);
        });
    });
}).observe(document.body, { childList: true, subtree: true });
