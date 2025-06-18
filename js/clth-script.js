document.addEventListener('DOMContentLoaded', function () {
    const headings = clthData.headings;
    const iconSvg = clthData.iconSvg;
    const showIconOnMobile = clthData.showIconOnMobile;
    const showIconOnDesktop = clthData.showIconOnDesktop;
    const enableTooltip = clthData.enableTooltip; 
    const copyText = clthData.copyText || 'Copy Link to Heading';
    const copiedText = clthData.copiedText || 'Copied';
    const iconPosition = clthData.iconPosition || 'after';
    const iconSize = clthData.iconSize; // Get icon size string (e.g., "16px", "1.2em", or empty)
    const iconColor = clthData.iconColor; // Get icon color string (e.g., "#ff0000", or empty for currentColor)
    const customSelectors = clthData.customSelectors;

    // Default selectors cover common WordPress content areas.
    let contentSelector = '.entry-content, .post-content, .page-content, .site-content, .main-content, [role="main"]';
    if (customSelectors) {
        contentSelector += ', ' + customSelectors;
    }

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

                if (!heading.querySelector('.clth-copy-icon')) {
                    const icon = document.createElement('span');
                    icon.classList.add('clth-copy-icon');
                    icon.innerHTML = iconSvg;

                    const svgElement = icon.querySelector('svg');
                    if (svgElement) {
                        // Apply custom size if specified
                        if (iconSize) { // Check if iconSize string is not empty
                            svgElement.style.width = iconSize;
                            svgElement.style.height = iconSize;
                        }
                        // Apply custom color if specified, otherwise it inherits via CSS (currentColor)
                        if (iconColor) {
                            svgElement.style.fill = iconColor;
                        }
                    }


                    if (enableTooltip) {
                        const tooltip = document.createElement('span');
                        tooltip.classList.add('clth-tooltip');
                        tooltip.textContent = copyText;
                        icon.appendChild(tooltip);
                    }

                    if (iconPosition === 'before') {
                        // Insert icon before the heading's first child and add a small right margin
                        heading.insertBefore(icon, heading.firstChild);
                        icon.classList.add('clth-icon-position-before'); // Add class for CSS to handle margin
                    } else {
                        // Default: insert icon after the heading content
                        heading.appendChild(icon);
                    }

                    icon.addEventListener('click', function () {
                        const baseUrl = window.location.href.split('#')[0];
                        const url = `${baseUrl}#${heading.id}`;
                        navigator.clipboard.writeText(url).then(() => {
                            if (enableTooltip) {
                                const tooltip = icon.querySelector('.clth-tooltip');
                                tooltip.textContent = copiedText;
                                setTimeout(() => {
                                    tooltip.textContent = copyText;
                                }, 2000);
                            } else {
                                alert(`${copiedText}: ${url}`);
                            }
                        });
                    });
                }
            });
        });
    });
});