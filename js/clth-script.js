document.addEventListener('DOMContentLoaded', function () {
    const headings = clthData.headings;
    const iconUrl = clthData.iconUrl;
    const iconSize = clthData.iconSize || 24;
    const showIconOnMobile = clthData.showIconOnMobile;
    const showIconOnDesktop = clthData.showIconOnDesktop;
    const enableTooltip = clthData.enableTooltip;
    const copyText = clthData.copyText || 'Copy Link to Heading';
    const copiedText = clthData.copiedText || 'Copied';
    const iconPosition = clthData.iconPosition || 'after';
    const contentSelector = clthData.contentSelector || '.entry-content, .post-content, .page-content, .dynamic-entry-content';

    // Add or remove class on the body based on mobile and desktop settings
    if (showIconOnMobile) {
        document.body.classList.add('clth-show-icon-mobile');
    } else {
        document.body.classList.remove('clth-show-icon-mobile');
    }
    if (showIconOnDesktop) {
        document.body.classList.add('clth-show-icon-desktop');
    } else {
        document.body.classList.remove('clth-show-icon-desktop');
    }

    // Create an invisible aria-live region to announce "Copied" to screen readers
    let ariaAnnouncer = document.getElementById('clth-aria-announcer');
    if (!ariaAnnouncer) {
        ariaAnnouncer = document.createElement('div');
        ariaAnnouncer.id = 'clth-aria-announcer';
        ariaAnnouncer.setAttribute('aria-live', 'polite');
        ariaAnnouncer.setAttribute('class', 'screen-reader-text');

        // Inline styles just in case the WordPress standard .screen-reader-text class isn't loaded
        ariaAnnouncer.style.border = '0';
        ariaAnnouncer.style.clip = 'rect(1px, 1px, 1px, 1px)';
        ariaAnnouncer.style.clipPath = 'inset(50%)';
        ariaAnnouncer.style.height = '1px';
        ariaAnnouncer.style.margin = '-1px';
        ariaAnnouncer.style.overflow = 'hidden';
        ariaAnnouncer.style.padding = '0';
        ariaAnnouncer.style.position = 'absolute';
        ariaAnnouncer.style.width = '1px';
        ariaAnnouncer.style.wordWrap = 'normal';

        document.body.appendChild(ariaAnnouncer);
    }

    function handleCopyAction(iconWrapper) {
        const heading = iconWrapper.parentElement;
        if (!heading || !heading.id) return;

        const baseUrl = window.location.href.split('#')[0];
        const url = `${baseUrl}#${heading.id}`;

        navigator.clipboard.writeText(url).then(() => {
            // Announce to screen readers that it was copied
            ariaAnnouncer.textContent = `${copiedText}: ${heading.innerText.trim()}`;
            setTimeout(() => {
                ariaAnnouncer.textContent = ''; // Clear it out so it announces again next time
            }, 3000);

            if (enableTooltip) {
                const tooltip = iconWrapper.querySelector('.clth-tooltip');
                if (tooltip) {
                    tooltip.textContent = copiedText;
                    setTimeout(() => {
                        tooltip.textContent = copyText;
                    }, 2000);
                }
            } else {
                alert(`${copiedText}: ${url}`);
            }
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }

    // Event Delegation for clicking the icon
    document.body.addEventListener('click', function (e) {
        const iconWrapper = e.target.closest('.clth-copy-icon-wrapper');
        if (!iconWrapper) return;

        e.preventDefault();
        e.stopPropagation();

        handleCopyAction(iconWrapper);
    });

    // Support keyboard activation for accessibility
    document.body.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            const iconWrapper = e.target.closest('.clth-copy-icon-wrapper');
            if (!iconWrapper) return;

            e.preventDefault();
            e.stopPropagation();

            handleCopyAction(iconWrapper);
        }
    });

    function clthInit() {
        const contentElements = document.querySelectorAll(contentSelector);

        function sanitizeSlug(text) {
            return text
                .toLowerCase()
                .replace(/[^a-z0-9 ]+/g, '')
                .trim()
                .replace(/\s+/g, '-');
        }

        contentElements.forEach(function (content) {
            headings.forEach(function (level) {
                const elements = content.querySelectorAll(level);
                elements.forEach(function (heading) {
                    if (!heading.id) {
                        const id = sanitizeSlug(heading.innerText.trim());
                        heading.id = id;
                    }

                    if (!heading.querySelector('.clth-copy-icon-wrapper')) {
                        const iconWrapper = document.createElement('span');
                        iconWrapper.classList.add('clth-copy-icon-wrapper');
                        iconWrapper.style.cursor = 'pointer';
                        iconWrapper.setAttribute('aria-label', `${copyText}: ${heading.innerText.trim()}`);
                        iconWrapper.setAttribute('role', 'button');
                        iconWrapper.setAttribute('tabindex', '0');
                        // iconWrapper.style.display = 'none'; // Removed to allow CSS hover effect
                        iconWrapper.style.width = `${iconSize}px`;
                        iconWrapper.style.height = `${iconSize}px`;
                        iconWrapper.style.verticalAlign = 'middle';
                        iconWrapper.style.marginLeft = '8px';
                        iconWrapper.style.position = 'relative';

                        const icon = document.createElement('span');
                        icon.classList.add('clth-copy-icon');
                        icon.style.display = 'flex'; // Use flex to center SVG
                        icon.style.alignItems = 'center';
                        icon.style.justifyContent = 'center';
                        icon.style.width = '100%';
                        icon.style.height = '100%';

                        // Apply thickness class
                        if (clthData.iconThickness) {
                            icon.classList.add(`clth-thickness-${clthData.iconThickness}`);
                        }

                        // Apply type class
                        if (clthData.iconType) {
                            icon.classList.add(`clth-icon-${clthData.iconType}`);
                        }

                        if (clthData.iconSvg) {
                            // Use Inline SVG
                            icon.innerHTML = clthData.iconSvg;

                            // If color is set, we need to ensure the SVG uses it
                            if (clthData.iconColor) {
                                icon.style.color = clthData.iconColor;
                            }

                            // Ensure SVG fills container
                            const svg = icon.querySelector('svg');
                            if (svg) {
                                svg.style.width = '100%';
                                svg.style.height = '100%';
                                svg.style.display = 'block';
                            }
                        } else {
                            // Fallback / Custom Image (still uses background/mask logic)
                            if (clthData.iconColor) {
                                icon.style.backgroundColor = clthData.iconColor;
                                icon.style.maskImage = `url('${iconUrl}')`;
                                icon.style.webkitMaskImage = `url('${iconUrl}')`;
                                icon.style.maskSize = 'contain';
                                icon.style.webkitMaskSize = 'contain';
                                icon.style.maskRepeat = 'no-repeat';
                                icon.style.webkitMaskRepeat = 'no-repeat';
                                icon.style.maskPosition = 'center';
                                icon.style.webkitMaskPosition = 'center';
                            } else {
                                icon.style.backgroundImage = `url('${iconUrl}')`;
                                icon.style.backgroundSize = 'contain';
                                icon.style.backgroundRepeat = 'no-repeat';
                                icon.style.backgroundPosition = 'center';
                            }
                        }

                        iconWrapper.appendChild(icon);

                        if (enableTooltip) {
                            const tooltip = document.createElement('span');
                            tooltip.classList.add('clth-tooltip');
                            tooltip.textContent = copyText;
                            iconWrapper.appendChild(tooltip);
                        }

                        if (iconPosition === 'before') {
                            // Insert icon before the heading's first child and add a small right margin
                            heading.insertBefore(iconWrapper, heading.firstChild);
                            iconWrapper.style.marginRight = '8px';
                            iconWrapper.style.marginLeft = '0px';
                            // Force icon to display by default
                            iconWrapper.style.display = 'inline-block';
                        } else {
                            // Default: insert icon after the heading content
                            heading.appendChild(iconWrapper);
                            // For 'after' position, if desktop option is enabled then force display
                            if (showIconOnDesktop) {
                                iconWrapper.style.display = 'inline-block';
                            }
                        }
                    }
                });
            });
        });
    }

    // Initial run
    clthInit();

    // Observe for dynamic content changes
    const observer = new MutationObserver(function (mutations) {
        let shouldUpdate = false;
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes.length > 0) {
                shouldUpdate = true;
            }
        });

        if (shouldUpdate) {
            clthInit();
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});