// Pagewright Default Theme - JavaScript
// Minimal interactivity for theme features

(function() {
    'use strict';
    
    // Dark mode toggle (if enabled in theme settings)
    function initDarkMode() {
        const darkModeToggle = document.querySelector('[data-theme-toggle]');
        if (!darkModeToggle) return;
        
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);
        
        darkModeToggle.addEventListener('click', function() {
            const theme = document.documentElement.getAttribute('data-theme');
            const newTheme = theme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
    
    // Smooth scroll for anchor links
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
    
    // Accordion component (if present)
    function initAccordions() {
        document.querySelectorAll('.component-accordion').forEach(accordion => {
            const headers = accordion.querySelectorAll('.accordion-header');
            
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const isOpen = content.style.display === 'block';
                    
                    // Close all others in this accordion
                    accordion.querySelectorAll('.accordion-content').forEach(c => {
                        c.style.display = 'none';
                    });
                    
                    // Toggle current
                    if (!isOpen) {
                        content.style.display = 'block';
                    }
                });
            });
        });
    }
    
    // Tabs component (if present)
    function initTabs() {
        document.querySelectorAll('.component-tabs').forEach(tabGroup => {
            const buttons = tabGroup.querySelectorAll('.tab-button');
            const panels = tabGroup.querySelectorAll('.tab-panel');
            
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-tab');
                    
                    // Remove active from all
                    buttons.forEach(b => b.classList.remove('active'));
                    panels.forEach(p => p.classList.remove('active'));
                    
                    // Add active to current
                    this.classList.add('active');
                    document.getElementById(targetId)?.classList.add('active');
                });
            });
        });
    }
    
    // Mobile menu toggle
    function initMobileMenu() {
        const menuToggle = document.querySelector('[data-menu-toggle]');
        const menu = document.querySelector('nav ul');
        
        if (!menuToggle || !menu) return;
        
        menuToggle.addEventListener('click', function() {
            menu.classList.toggle('show');
        });
    }
    
    // Initialize all features when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        initDarkMode();
        initSmoothScroll();
        initAccordions();
        initTabs();
        initMobileMenu();
    }
})();
