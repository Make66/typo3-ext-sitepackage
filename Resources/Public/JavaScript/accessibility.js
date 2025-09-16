document.addEventListener('DOMContentLoaded', function () {
    let contrastEnabled = sessionStorage.getItem('contrastEnabled') === 'true';
    let schriftEnabled = sessionStorage.getItem('schriftEnabled') === 'true';

// Sprache erkennen anhand des URL-Pfads
    const detectLanguage = () => {
        const path = window.location.pathname;
        if (path.includes('/en/')) return 'en';
        if (path.includes('/tr/')) return 'tr';
        if (path.includes('/ar/')) return 'ar';
        return 'de'; // Standard
    };

    const language = detectLanguage();

// Textvarianten
    const buttonTexts = {
        de: {
            contrast: ['Kontrast zurücksetzen', 'Kontrast erhöhen'],
            schrift: ['Schrift verkleinern', 'Schrift vergrößern']
        },
        en: {
            contrast: ['Reset contrast', 'Increase contrast'],
            schrift: ['Reduce font size', 'Increase font size']
        },
        tr: {
            contrast: ['Kontrastı sıfırla', 'Kontrastı artırın'],
            schrift: ['Yazı tipi boyutunu küçült', 'Yazı tipi boyutunu büyüt']
        },
        ar: {
            contrast: ['إعادة ضبط التباين', 'زيادة التباين'],
            schrift: ['تصغير حجم الخط', 'زيادة حجم الخط']
        }
    };

    function updateButtonLabels() {
        const texts = buttonTexts[language];

        document.querySelectorAll('.button-contrast').forEach(function (btn) {
            btn.textContent = contrastEnabled ? texts.contrast[0] : texts.contrast[1];
        });

        document.querySelectorAll('.button-fontsize').forEach(function (btn) {
            btn.textContent = schriftEnabled ? texts.schrift[0] : texts.schrift[1];
        });
    }

    function enableContrast() {
        if (!document.getElementById('contrastStylesheet')) {
            var linkElement = document.createElement('link');
            linkElement.rel = 'stylesheet';
            linkElement.href = sitepackage_assetPath + 'Css/contrast.css';
            linkElement.id = 'contrastStylesheet';
            document.head.appendChild(linkElement);
        }
        contrastEnabled = true;
        sessionStorage.setItem('contrastEnabled', 'true');
        updateButtonLabels();
    }

    function disableContrast() {
        var linkElement = document.getElementById('contrastStylesheet');
        if (linkElement) {
            document.head.removeChild(linkElement);
        }
        contrastEnabled = false;
        sessionStorage.setItem('contrastEnabled', 'false');
        updateButtonLabels();
    }

    function enableSchrift() {
        if (!document.getElementById('schriftStylesheet')) {
            var linkElement = document.createElement('link');
            linkElement.rel = 'stylesheet';
            linkElement.href = sitepackage_assetPath + 'Css/fontsize.css';
            linkElement.id = 'schriftStylesheet';
            document.head.appendChild(linkElement);
        }
        schriftEnabled = true;
        sessionStorage.setItem('schriftEnabled', 'true');
        updateButtonLabels();
    }

    function disableSchrift() {
        var linkElement = document.getElementById('schriftStylesheet');
        if (linkElement) {
            document.head.removeChild(linkElement);
        }
        schriftEnabled = false;
        sessionStorage.setItem('schriftEnabled', 'false');
        updateButtonLabels();
    }

    // Event-Listener für Kontrast
    document.querySelectorAll('.button-contrast').forEach(function (element) {
        element.addEventListener('click', function (event) {
            event.preventDefault();
            if (!contrastEnabled) {
                enableContrast();
            } else {
                disableContrast();
            }
        });
    });

    // Event-Listener für Schrift
    document.querySelectorAll('.button-fontsize').forEach(function (element) {
        element.addEventListener('click', function (event) {
            event.preventDefault();
            if (!schriftEnabled) {
                enableSchrift();
            } else {
                disableSchrift();
            }
        });
    });

    // Auf Seite laden: CSS aktivieren und Button-Texte setzen
    if (contrastEnabled) {
        var contrastLink = document.createElement('link');
        contrastLink.rel = 'stylesheet';
        contrastLink.href = sitepackage_assetPath + 'Css/contrast.css';
        contrastLink.id = 'contrastStylesheet';
        document.head.appendChild(contrastLink);
    }

    if (schriftEnabled) {
        var schriftLink = document.createElement('link');
        schriftLink.rel = 'stylesheet';
        schriftLink.href = sitepackage_assetPath + 'Css/fontsize.css';
        schriftLink.id = 'schriftStylesheet';
        document.head.appendChild(schriftLink);
    }

    updateButtonLabels();

    // === NEU: Menü automatisch schließen bei Klick oder Scroll ===
    const menuToggleMobil = document.getElementById('menu-toggle-mobil');
    const menuToggleDesktop = document.getElementById('menu-toggle-desktop');

    function closeMenus() {
        if (menuToggleMobil && menuToggleMobil.checked) {
            menuToggleMobil.checked = false;
        }
        if (menuToggleDesktop && menuToggleDesktop.checked) {
            menuToggleDesktop.checked = false;
        }
    }

    document.querySelectorAll('.accessibility_menu a').forEach(link => {
        link.addEventListener('click', closeMenus);
    });

    window.addEventListener('scroll', closeMenus);

    // === NEU: Tastatursteuerung (Enter-Taste) für Menüs ===
    document.querySelectorAll('.servicebutton').forEach(button => {
        button.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter' || event.key === ' ') && document.activeElement === button) {
                event.preventDefault();
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
            }
        });
    });
});
