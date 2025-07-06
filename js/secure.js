// --- UI/UX Security Measures Harmonized with Server Session ---
// Disable right-click (optional; can frustrate users)
document.addEventListener("contextmenu", event => event.preventDefault());

// Block F12, Ctrl+Shift+I, Ctrl+U (View Source/DevTools) -- Optional, not bulletproof
document.addEventListener("keydown", event => {
    if (
        event.key === "F12" ||
        (event.ctrlKey && event.shiftKey && event.key.toUpperCase() === "I") ||
        (event.ctrlKey && event.key.toUpperCase() === "U")
    ) {
        event.preventDefault();
    }
});

// Prevent drag-and-drop (optional; prevents accidental file upload/drop)
document.addEventListener("dragstart", event => event.preventDefault());

// Prevent Clickjacking (redundant with server header, but harmless)
if (window.top !== window.self) {
    document.body.innerHTML = "<h1>Unauthorized Frame Access!</h1>";
}

// --- Inactivity Auto-Logout Harmonized with PHP ---
// Server-side session timeout is 15 minutes (900s); set client-side to match.
// This will help users be logged out on UI if idle, matching backend logic.
let inactivityTimeout;
function resetInactivityTimer() {
    clearTimeout(inactivityTimeout);
    inactivityTimeout = setTimeout(() => {
        window.location.href = "/pulse/logout";
    }, 900000); // 15 minutes (900,000 ms)
}
["mousemove", "keydown", "mousedown", "touchstart"].forEach(evt =>
    document.addEventListener(evt, resetInactivityTimer)
);
resetInactivityTimer();

// --- DevTools Detection ---
// If DevTools is detected, force logout by redirecting to logout page.
(function() {
    let devtoolsCheck = setInterval(() => {
        let startTime = performance.now();
        debugger;
        if (performance.now() - startTime > 100) {
            window.location.href = "/pulse/logout";
            clearInterval(devtoolsCheck);
        }
    }, 1000);
})();

// --- Basic SQL Injection Prevention (Client-side, best effort only) ---
function preventSQLInjection(input) {
    const forbiddenPatterns = [/--/, /;/, /'/, /"/, /\b(SELECT|INSERT|DELETE|UPDATE|DROP|ALTER)\b/i];
    return forbiddenPatterns.some(pattern => pattern.test(input));
}
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("input, textarea").forEach(input => {
        input.addEventListener("input", event => {
            if (preventSQLInjection(event.target.value)) {
                event.target.value = ""; // Clear the input
            }
        });
    });
});