/* Freezed default theme — minimal progressive enhancement. */
(function () {
    "use strict";

    var root = document.documentElement;

    /* ----- Theme toggle (light / dark), persisted in localStorage --------- */
    var STORAGE_KEY = "freezed-theme";

    function applyTheme(theme) {
        if (theme === "light" || theme === "dark") {
            root.setAttribute("data-theme", theme);
        } else {
            root.removeAttribute("data-theme");
        }
    }

    try {
        var saved = window.localStorage.getItem(STORAGE_KEY);
        if (saved) {
            applyTheme(saved);
        }
    } catch (e) {
        /* localStorage unavailable — fall back to system preference */
    }

    function currentTheme() {
        var attr = root.getAttribute("data-theme");
        if (attr) {
            return attr;
        }
        return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
    }

    var toggle = document.querySelector(".theme-toggle");
    if (toggle) {
        toggle.addEventListener("click", function () {
            var next = currentTheme() === "dark" ? "light" : "dark";
            applyTheme(next);
            try {
                window.localStorage.setItem(STORAGE_KEY, next);
            } catch (e) {
                /* ignore */
            }
        });
    }

    /* ----- Mobile navigation toggle -------------------------------------- */
    var navToggle = document.querySelector(".nav-toggle");
    var navLinks = document.querySelector(".nav__links");
    if (navToggle && navLinks) {
        navToggle.addEventListener("click", function () {
            navLinks.classList.toggle("nav__links--open");
        });
    }
})();
